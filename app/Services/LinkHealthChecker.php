<?php

namespace App\Services;

use App\Exceptions\WebsiteAuditException;
use GuzzleHttp\Exception\TransferException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Checks whether links found on a monitored site still work. Every request is pinned to an
 * address that PublicHostResolver confirmed is public, and redirects are not followed, so a
 * link on the page cannot steer the scanner into a private network.
 */
class LinkHealthChecker
{
    public const MAX_INTERNAL_LINKS = 60;

    public const MAX_EXTERNAL_LINKS = 40;

    private const BATCH_SIZE = 10;

    /** Statuses that sites send to bots; the link may work fine in a real browser. */
    private const UNVERIFIABLE_STATUSES = [401, 403, 405, 406, 429, 451, 999];

    public function __construct(private PublicHostResolver $hostResolver) {}

    /**
     * @param  list<array{url: string, found_on: string, internal: bool}>  $links
     * @return array{
     *     checked: int,
     *     ok: int,
     *     broken: list<array{url: string, found_on: string, internal: bool, status: int|null, reason: string}>,
     *     unverified: list<array{url: string, found_on: string, internal: bool, status: int|null, reason: string}>,
     *     redirects: list<array{url: string, found_on: string, internal: bool, status: int, location: string}>,
     *     truncated: bool
     * }
     */
    public function check(array $links): array
    {
        $internal = array_values(array_filter($links, fn (array $link): bool => $link['internal']));
        $external = array_values(array_filter($links, fn (array $link): bool => ! $link['internal']));
        $selected = array_merge(
            array_slice($internal, 0, self::MAX_INTERNAL_LINKS),
            array_slice($external, 0, self::MAX_EXTERNAL_LINKS),
        );
        $truncated = count($selected) < count($links);

        $broken = [];
        $unverified = [];
        $redirects = [];
        $ok = 0;
        $resolvedHosts = [];
        $requests = [];

        foreach ($selected as $link) {
            $parts = parse_url($link['url']);
            $host = is_array($parts) ? strtolower(rtrim($parts['host'] ?? '', '.')) : '';
            $scheme = strtolower($parts['scheme'] ?? '');

            if ($host === '' || ! in_array($scheme, ['http', 'https'], true) || isset($parts['port']) || isset($parts['user'])) {
                continue;
            }

            if (! array_key_exists($host, $resolvedHosts)) {
                try {
                    $resolvedHosts[$host] = $this->hostResolver->resolve($host);
                } catch (WebsiteAuditException $exception) {
                    $resolvedHosts[$host] = $exception;
                }
            }

            if ($resolvedHosts[$host] instanceof WebsiteAuditException) {
                $message = $resolvedHosts[$host]->getMessage();

                // A private-network target is skipped silently; a domain that no longer exists is a broken link.
                if (! str_contains($message, 'publicly accessible')) {
                    $broken[] = $link + ['status' => null, 'reason' => 'The domain does not exist or has no DNS records.'];
                }

                continue;
            }

            $address = $resolvedHosts[$host];
            $requests[] = $link + [
                'host' => $host,
                'port' => $scheme === 'https' ? 443 : 80,
                'address' => str_contains($address, ':') ? '['.$address.']' : $address,
            ];
        }

        foreach (array_chunk($requests, self::BATCH_SIZE) as $batch) {
            $responses = Http::pool(function (Pool $pool) use ($batch): array {
                $pending = [];

                foreach ($batch as $index => $request) {
                    $pending[] = $this->pinnedRequest($pool->as((string) $index), $request)->head($request['url']);
                }

                return $pending;
            });

            foreach ($batch as $index => $request) {
                $link = ['url' => $request['url'], 'found_on' => $request['found_on'], 'internal' => $request['internal']];
                $response = $responses[(string) $index] ?? null;

                // Some servers reject HEAD; confirm with a one-byte GET before judging the link.
                if ($response instanceof Response && in_array($response->status(), [403, 405, 501], true)) {
                    try {
                        $response = $this->pinnedRequest(Http::withHeaders(['Range' => 'bytes=0-0']), $request)->get($request['url']);
                    } catch (Throwable $exception) {
                        $response = $exception;
                    }
                }

                if (! $response instanceof Response) {
                    $broken[] = $link + ['status' => null, 'reason' => $this->connectionReason($response)];

                    continue;
                }

                $status = $response->status();

                if ($status >= 300 && $status < 400) {
                    $location = (string) $response->header('Location');
                    $redirects[] = $link + ['status' => $status, 'location' => mb_substr($location, 0, 500)];
                    $ok++;
                } elseif ($status < 300) {
                    $ok++;
                } elseif (in_array($status, self::UNVERIFIABLE_STATUSES, true)) {
                    $unverified[] = $link + ['status' => $status, 'reason' => 'The site refused the automated check (HTTP '.$status.'). Open it in a browser to confirm.'];
                } else {
                    $broken[] = $link + ['status' => $status, 'reason' => $this->statusReason($status)];
                }
            }
        }

        return [
            'checked' => $ok + count($broken) + count($unverified),
            'ok' => $ok,
            'broken' => $broken,
            'unverified' => $unverified,
            'redirects' => $redirects,
            'found' => count($links),
            'truncated' => $truncated,
        ];
    }

    /**
     * @param  array{host: string, port: int, address: string}  $request
     */
    private function pinnedRequest(PendingRequest $pending, array $request): PendingRequest
    {
        return $pending
            ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; LeadRadarHealthCheck/1.0)'])
            ->connectTimeout(4)
            ->timeout(8)
            ->withOptions([
                'allow_redirects' => false,
                'curl' => [CURLOPT_RESOLVE => ["{$request['host']}:{$request['port']}:{$request['address']}"]],
            ]);
    }

    private function statusReason(int $status): string
    {
        return match (true) {
            $status === 404 => 'Page not found (HTTP 404).',
            $status === 410 => 'Page was permanently removed (HTTP 410).',
            $status >= 500 => 'The server returned an error (HTTP '.$status.').',
            default => 'The link returned HTTP '.$status.'.',
        };
    }

    private function connectionReason(mixed $failure): string
    {
        $message = $failure instanceof Throwable ? strtolower($failure->getMessage()) : '';

        return match (true) {
            str_contains($message, 'timed out') || str_contains($message, 'timeout') => 'The link did not respond in time.',
            str_contains($message, 'ssl') || str_contains($message, 'certificate') => 'The link has an SSL/HTTPS certificate problem.',
            $failure instanceof ConnectionException || $failure instanceof TransferException => 'Could not connect to the link.',
            default => 'Could not connect to the link.',
        };
    }
}
