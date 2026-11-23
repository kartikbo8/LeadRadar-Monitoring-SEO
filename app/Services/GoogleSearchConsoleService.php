<?php

namespace App\Services;

use App\Exceptions\SearchConsoleException;
use Google\Client;
use Google\Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleSearchConsoleService
{
    private const API_BASE_URL = 'https://www.googleapis.com/webmasters/v3';

    private const SCOPE = 'https://www.googleapis.com/auth/webmasters.readonly';

    public function isConfigured(): bool
    {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'))
            && filled(config('services.google.redirect_uri'));
    }

    public function authorizationUrl(string $state): string
    {
        $client = $this->makeClient();
        $client->setState($state);

        return $client->createAuthUrl();
    }

    /**
     * @return array<string, mixed>
     */
    public function exchangeAuthorizationCode(string $code): array
    {
        try {
            $token = $this->makeClient()->fetchAccessTokenWithAuthCode($code);
        } catch (Exception $exception) {
            Log::warning('Google Search Console OAuth code exchange failed.', [
                'reason' => $exception->getMessage(),
            ]);

            throw new SearchConsoleException('Google could not complete the connection. Please try again.', previous: $exception);
        }

        if (! is_array($token) || ! is_string($token['access_token'] ?? null)) {
            throw new SearchConsoleException('Google did not return an access token. Please try connecting again.');
        }

        return $token;
    }

    /**
     * @param  array<string, mixed>  $token
     * @return array{token: array<string, mixed>, sites: list<array{site_url: string, permission_level: string}>}
     */
    public function listSites(array $token): array
    {
        [$token, $payload] = $this->request($token, self::API_BASE_URL.'/sites');
        $sites = $payload['siteEntry'] ?? [];

        if (! is_array($sites)) {
            throw new SearchConsoleException('Google returned an invalid Search Console property list.');
        }

        $properties = [];

        foreach ($sites as $site) {
            if (! is_array($site) || ! is_string($site['siteUrl'] ?? null)) {
                continue;
            }

            $properties[] = [
                'site_url' => $site['siteUrl'],
                'permission_level' => is_string($site['permissionLevel'] ?? null)
                    ? $site['permissionLevel']
                    : 'unknown',
            ];
        }

        return ['token' => $token, 'sites' => $properties];
    }

    /**
     * @param  array<string, mixed>  $token
     * @return array{
     *     token: array<string, mixed>,
     *     summary: array{clicks: float, impressions: float, ctr: float, position: float},
     *     rows: list<array{query: string, page: string, clicks: float, impressions: float, ctr: float, position: float}>,
     *     trend: list<array{date: string, clicks: float, impressions: float, ctr: float, position: float}>
     * }
     */
    public function searchAnalytics(
        array $token,
        string $siteUrl,
        string $startDate,
        string $endDate,
        ?string $selectedQuery = null,
        ?string $selectedPage = null,
    ): array {
        if (($selectedQuery === null) !== ($selectedPage === null)) {
            throw new SearchConsoleException('Both a search query and page URL are required for a page ranking report.');
        }

        $baseQuery = [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'type' => 'web',
        ];

        if ($selectedQuery !== null && $selectedPage !== null) {
            $baseQuery['dimensionFilterGroups'] = [[
                'groupType' => 'and',
                'filters' => [
                    ['dimension' => 'query', 'operator' => 'equals', 'expression' => $selectedQuery],
                    ['dimension' => 'page', 'operator' => 'equals', 'expression' => $selectedPage],
                ],
            ]];
        }

        [$token, $summaryPayload] = $this->request(
            $token,
            self::API_BASE_URL.'/sites/'.rawurlencode($siteUrl).'/searchAnalytics/query',
            $baseQuery + ['rowLimit' => 1],
        );

        [$token, $detailPayload] = $this->request(
            $token,
            self::API_BASE_URL.'/sites/'.rawurlencode($siteUrl).'/searchAnalytics/query',
            $baseQuery + ['dimensions' => ['query', 'page'], 'rowLimit' => 250],
        );

        $summaryRow = is_array($summaryPayload['rows'][0] ?? null) ? $summaryPayload['rows'][0] : [];
        $rows = [];
        $trend = [];

        foreach (($detailPayload['rows'] ?? []) as $row) {
            if (! is_array($row) || ! is_array($row['keys'] ?? null) || count($row['keys']) < 2) {
                continue;
            }

            $rowQuery = $row['keys'][0];
            $rowPage = $row['keys'][1];

            if (! is_string($rowQuery) || ! is_string($rowPage)) {
                continue;
            }

            $rows[] = [
                'query' => $rowQuery,
                'page' => $rowPage,
                'clicks' => $this->numericMetric($row, 'clicks'),
                'impressions' => $this->numericMetric($row, 'impressions'),
                'ctr' => $this->numericMetric($row, 'ctr'),
                'position' => $this->numericMetric($row, 'position'),
            ];
        }

        if ($selectedQuery !== null && $selectedPage !== null) {
            [$token, $trendPayload] = $this->request(
                $token,
                self::API_BASE_URL.'/sites/'.rawurlencode($siteUrl).'/searchAnalytics/query',
                $baseQuery + ['dimensions' => ['date'], 'rowLimit' => 500],
            );

            foreach (($trendPayload['rows'] ?? []) as $row) {
                $date = is_array($row) && is_array($row['keys'] ?? null)
                    ? ($row['keys'][0] ?? null)
                    : null;

                if (! is_string($date)) {
                    continue;
                }

                $trend[] = [
                    'date' => $date,
                    'clicks' => $this->numericMetric($row, 'clicks'),
                    'impressions' => $this->numericMetric($row, 'impressions'),
                    'ctr' => $this->numericMetric($row, 'ctr'),
                    'position' => $this->numericMetric($row, 'position'),
                ];
            }
        }

        return [
            'token' => $token,
            'summary' => [
                'clicks' => $this->numericMetric($summaryRow, 'clicks'),
                'impressions' => $this->numericMetric($summaryRow, 'impressions'),
                'ctr' => $this->numericMetric($summaryRow, 'ctr'),
                'position' => $this->numericMetric($summaryRow, 'position'),
            ],
            'rows' => $rows,
            'trend' => $trend,
        ];
    }

    /**
     * @param  array<string, mixed>  $token
     * @param  array<string, mixed>|null  $body
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function request(array $token, string $url, ?array $body = null): array
    {
        $token = $this->refreshTokenIfNeeded($token);
        $response = $this->sendRequest($token, $url, $body);

        if ($response->unauthorized() && is_string($token['refresh_token'] ?? null)) {
            $token = $this->refreshToken($token);
            $response = $this->sendRequest($token, $url, $body);
        }

        if ($response->unauthorized()) {
            throw new SearchConsoleException('Google Search Console access expired. Please reconnect your Google account.');
        }

        if ($response->forbidden()) {
            throw new SearchConsoleException('Google denied access to this Search Console property. Confirm that the connected account has permission and the Search Console API is enabled.');
        }

        if (! $response->successful()) {
            Log::warning('Google Search Console returned an API error.', [
                'status' => $response->status(),
            ]);

            throw new SearchConsoleException('Google Search Console could not load this report. Please try again later.');
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new SearchConsoleException('Google Search Console returned an invalid response.');
        }

        return [$token, $payload];
    }

    /**
     * @param  array<string, mixed>  $token
     */
    private function sendRequest(array $token, string $url, ?array $body): Response
    {
        try {
            $request = Http::acceptJson()
                ->withToken((string) $token['access_token'])
                ->connectTimeout(5)
                ->timeout(20);

            return $body === null
                ? $request->get($url)
                : $request->post($url, $body);
        } catch (ConnectionException $exception) {
            Log::warning('Could not connect to Google Search Console.', [
                'reason' => $exception->getMessage(),
            ]);

            throw new SearchConsoleException('Google Search Console could not be reached. Please try again later.', previous: $exception);
        }
    }

    /**
     * @param  array<string, mixed>  $token
     * @return array<string, mixed>
     */
    private function refreshTokenIfNeeded(array $token): array
    {
        $client = $this->makeClient();
        $client->setAccessToken($token);

        if (! $client->isAccessTokenExpired()) {
            return $token;
        }

        if (! is_string($token['refresh_token'] ?? null)) {
            throw new SearchConsoleException('Google Search Console access expired. Please reconnect your Google account.');
        }

        return $this->refreshToken($token);
    }

    /**
     * @param  array<string, mixed>  $token
     * @return array<string, mixed>
     */
    private function refreshToken(array $token): array
    {
        $refreshToken = $token['refresh_token'] ?? null;

        if (! is_string($refreshToken)) {
            throw new SearchConsoleException('Google Search Console access expired. Please reconnect your Google account.');
        }

        try {
            $refreshedToken = $this->makeClient()->fetchAccessTokenWithRefreshToken($refreshToken);
        } catch (Exception $exception) {
            Log::warning('Google Search Console access token refresh failed.', [
                'reason' => $exception->getMessage(),
            ]);

            throw new SearchConsoleException('Google could not refresh Search Console access. Please reconnect your Google account.', previous: $exception);
        }

        if (! is_array($refreshedToken) || ! is_string($refreshedToken['access_token'] ?? null)) {
            throw new SearchConsoleException('Google could not refresh Search Console access. Please reconnect your Google account.');
        }

        return array_merge($token, $refreshedToken, ['refresh_token' => $refreshToken]);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function numericMetric(array $row, string $metric): float
    {
        $value = $row[$metric] ?? 0;

        return is_numeric($value) ? (float) $value : 0.0;
    }

    private function makeClient(): Client
    {
        if (! $this->isConfigured()) {
            throw new SearchConsoleException('Google Search Console is not configured. Set GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, and GOOGLE_REDIRECT_URI on the server.');
        }

        $client = new Client;
        $client->setApplicationName((string) config('app.name'));
        $client->setClientId((string) config('services.google.client_id'));
        $client->setClientSecret((string) config('services.google.client_secret'));
        $client->setRedirectUri((string) config('services.google.redirect_uri'));
        $client->setAccessType('offline');
        $client->setPrompt('consent');
        $client->addScope(self::SCOPE);

        return $client;
    }
}
