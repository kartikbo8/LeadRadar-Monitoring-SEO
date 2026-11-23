<?php

namespace App\Services;

use App\Exceptions\WebsiteAuditException;

class TlsCertificateChecker
{
    public function __construct(private PublicHostResolver $hostResolver) {}

    /**
     * Connect to the host's public address on 443 and read its certificate, validating it
     * against the hostname exactly as a browser would.
     *
     * @return array{valid: bool, expires_at: string|null, days_remaining: int|null, issuer: string|null, error: string|null}
     */
    public function check(string $host): array
    {
        try {
            $address = $this->hostResolver->resolve($host);
        } catch (WebsiteAuditException $exception) {
            return $this->failure($exception->getMessage());
        }

        $context = stream_context_create(['ssl' => [
            'peer_name' => $host,
            'SNI_enabled' => true,
            'verify_peer' => true,
            'verify_peer_name' => true,
            'capture_peer_cert' => true,
        ]]);
        $target = 'ssl://'.(str_contains($address, ':') ? '['.$address.']' : $address).':443';

        $client = @stream_socket_client($target, $errorCode, $errorMessage, 6, STREAM_CLIENT_CONNECT, $context);

        if ($client === false) {
            return $this->failure(
                $errorMessage !== '' && $errorMessage !== null
                    ? 'The HTTPS certificate could not be validated: '.$errorMessage
                    : 'The HTTPS certificate is invalid, expired, or does not match this domain.',
            );
        }

        $certificate = stream_context_get_params($client)['options']['ssl']['peer_certificate'] ?? null;
        fclose($client);
        $parsed = $certificate === null ? false : openssl_x509_parse($certificate);

        if (! is_array($parsed) || ! isset($parsed['validTo_time_t'])) {
            return $this->failure('The HTTPS certificate could not be read.');
        }

        $expiresAt = (int) $parsed['validTo_time_t'];

        return [
            'valid' => true,
            'expires_at' => date(DATE_ATOM, $expiresAt),
            'days_remaining' => (int) floor(($expiresAt - time()) / 86400),
            'issuer' => $parsed['issuer']['O'] ?? $parsed['issuer']['CN'] ?? null,
            'error' => null,
        ];
    }

    /**
     * @return array{valid: false, expires_at: null, days_remaining: null, issuer: null, error: string}
     */
    private function failure(string $error): array
    {
        return ['valid' => false, 'expires_at' => null, 'days_remaining' => null, 'issuer' => null, 'error' => $error];
    }
}
