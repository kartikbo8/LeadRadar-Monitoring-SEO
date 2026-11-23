<?php

namespace App\Http\Controllers;

use App\Exceptions\SearchConsoleException;
use App\Services\GoogleSearchConsoleService;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use JsonException;

class SearchConsoleController extends Controller
{
    private const TOKEN_SESSION_KEY = 'google_search_console_token';

    private const STATE_SESSION_KEY = 'google_search_console_oauth_state';

    private const RANKING_TARGET_SESSION_KEY = 'google_search_console_ranking_target';

    public function index(Request $request, GoogleSearchConsoleService $searchConsoleService): View
    {
        $rankingTarget = $this->validatedRankingTarget($request);
        $configured = $searchConsoleService->isConfigured();
        $sites = [];
        $selectedSite = '';
        $selectedQuery = $rankingTarget['query'] ?? '';
        $selectedPage = $rankingTarget['page'] ?? '';
        $range = '28';
        $summary = null;
        $rows = [];
        $trend = [];
        $chartData = $this->rankingChartData($trend);
        $searchError = $request->session()->get('search_console_error');
        $token = null;

        try {
            $token = $this->storedToken($request);
        } catch (SearchConsoleException $exception) {
            $request->session()->forget(self::TOKEN_SESSION_KEY);
            $searchError = $exception->getMessage();
        }

        if ($configured && $token !== null) {
            try {
                $siteList = $searchConsoleService->listSites($token);
                $token = $siteList['token'];
                $sites = $siteList['sites'];
                $this->storeToken($request, $token);

                if ($request->query->has('site')) {
                    $validated = $request->validate([
                        'site' => ['required', 'string', Rule::in(array_column($sites, 'site_url'))],
                        'range' => ['required', Rule::in(['7', '28', '90'])],
                    ]);

                    $selectedSite = $validated['site'];
                    $range = $validated['range'];
                    $endDate = now()->subDays(3);
                    $startDate = $endDate->copy()->subDays((int) $range - 1);
                    $report = $searchConsoleService->searchAnalytics(
                        $token,
                        $selectedSite,
                        $startDate->toDateString(),
                        $endDate->toDateString(),
                        $selectedQuery !== '' ? $selectedQuery : null,
                        $selectedPage !== '' ? $selectedPage : null,
                    );

                    $this->storeToken($request, $report['token']);
                    $summary = $report['summary'];
                    $rows = $report['rows'];
                    $trend = $report['trend'] ?? [];
                    $chartData = $this->rankingChartData($trend);
                }
            } catch (SearchConsoleException $exception) {
                $searchError = $exception->getMessage();
            }
        }

        return view('companies.search-console', compact(
            'configured',
            'sites',
            'selectedSite',
            'selectedQuery',
            'selectedPage',
            'rankingTarget',
            'range',
            'summary',
            'rows',
            'trend',
            'chartData',
            'searchError',
            'token',
        ));
    }

    public function connect(Request $request, GoogleSearchConsoleService $searchConsoleService): RedirectResponse
    {
        $rankingTarget = $this->validatedRankingTarget($request);

        if (! $searchConsoleService->isConfigured()) {
            return redirect()
                ->route('search-console.index', $rankingTarget)
                ->with('search_console_error', 'Google Search Console is not configured. Set GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, and GOOGLE_REDIRECT_URI on the server.');
        }

        if ($rankingTarget === []) {
            $request->session()->forget(self::RANKING_TARGET_SESSION_KEY);
        } else {
            $request->session()->put(self::RANKING_TARGET_SESSION_KEY, $rankingTarget);
        }

        $state = Str::random(64);
        $request->session()->put(self::STATE_SESSION_KEY, $state);

        return redirect()->away($searchConsoleService->authorizationUrl($state));
    }

    public function callback(Request $request, GoogleSearchConsoleService $searchConsoleService): RedirectResponse
    {
        $expectedState = $request->session()->pull(self::STATE_SESSION_KEY);
        $state = $request->query('state');

        abort_unless(
            is_string($expectedState) && is_string($state) && hash_equals($expectedState, $state),
            403,
        );

        $rankingTarget = $request->session()->pull(self::RANKING_TARGET_SESSION_KEY, []);
        $dashboardUrl = route('search-console.index', is_array($rankingTarget) ? $rankingTarget : []);

        if ($request->query->has('error')) {
            return redirect()
                ->to($dashboardUrl)
                ->with('search_console_error', 'Google Search Console access was not granted.');
        }

        $code = $request->query('code');

        if (! is_string($code) || $code === '') {
            return redirect()
                ->to($dashboardUrl)
                ->with('search_console_error', 'Google did not return an authorization code. Please connect again.');
        }

        try {
            $this->storeToken($request, $searchConsoleService->exchangeAuthorizationCode($code));
        } catch (SearchConsoleException $exception) {
            return redirect()
                ->to($dashboardUrl)
                ->with('search_console_error', $exception->getMessage());
        }

        return redirect()
            ->to($dashboardUrl)
            ->with('status', 'Google Search Console is connected.');
    }

    public function disconnect(Request $request): RedirectResponse
    {
        $request->session()->forget([
            self::TOKEN_SESSION_KEY,
            self::RANKING_TARGET_SESSION_KEY,
        ]);

        return redirect()
            ->route('search-console.index')
            ->with('status', 'Google Search Console has been disconnected.');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function storedToken(Request $request): ?array
    {
        $encryptedToken = $request->session()->get(self::TOKEN_SESSION_KEY);

        if (! is_string($encryptedToken)) {
            return null;
        }

        try {
            $token = json_decode(Crypt::decryptString($encryptedToken), true, 512, JSON_THROW_ON_ERROR);
        } catch (DecryptException|JsonException $exception) {
            throw new SearchConsoleException('The saved Google connection could not be read. Please reconnect your account.', previous: $exception);
        }

        if (! is_array($token) || ! is_string($token['access_token'] ?? null)) {
            throw new SearchConsoleException('The saved Google connection is invalid. Please reconnect your account.');
        }

        return $token;
    }

    /**
     * @param  array<string, mixed>  $token
     */
    private function storeToken(Request $request, array $token): void
    {
        $request->session()->put(
            self::TOKEN_SESSION_KEY,
            Crypt::encryptString(json_encode($token, JSON_THROW_ON_ERROR)),
        );
    }

    /**
     * @return array{query: string, page: string}|array{}
     */
    private function validatedRankingTarget(Request $request): array
    {
        if (! $request->query->has('query') && ! $request->query->has('page')) {
            return [];
        }

        return $request->validate([
            'query' => ['required', 'string', 'max:500'],
            'page' => ['required', 'url:http,https', 'max:2048'],
        ]);
    }

    /**
     * @param  list<array{date: string, clicks: float, impressions: float, ctr: float, position: float}>  $trend
     * @return array{
     *     points: list<array{x: float, y: float, date: string, date_label: string, position: float, clicks: float, impressions: float}>,
     *     polyline: string,
     *     best_position: float|null,
     *     worst_position: float|null
     * }
     */
    private function rankingChartData(array $trend): array
    {
        if ($trend === []) {
            return [
                'points' => [],
                'polyline' => '',
                'best_position' => null,
                'worst_position' => null,
            ];
        }

        $positions = array_map(
            static fn (array $point): float => (float) $point['position'],
            $trend,
        );
        $bestPosition = min($positions);
        $worstPosition = max($positions);
        $positionRange = $worstPosition - $bestPosition;
        $pointCount = count($trend);
        $labelIndexes = array_unique([0, intdiv($pointCount - 1, 2), $pointCount - 1]);
        $points = [];

        foreach (array_values($trend) as $index => $point) {
            $x = $pointCount === 1
                ? 408
                : 56 + ($index / ($pointCount - 1)) * 704;
            $y = $positionRange === 0
                ? 90
                : 25 + (((float) $point['position'] - $bestPosition) / $positionRange) * 130;

            $points[] = [
                'x' => round($x, 1),
                'y' => round($y, 1),
                'date' => $point['date'],
                'date_label' => in_array($index, $labelIndexes, true)
                    ? substr($point['date'], 5)
                    : '',
                'position' => (float) $point['position'],
                'clicks' => (float) $point['clicks'],
                'impressions' => (float) $point['impressions'],
            ];
        }

        $polyline = implode(' ', array_map(
            static fn (array $point): string => sprintf('%.1f,%.1f', $point['x'], $point['y']),
            $points,
        ));

        return [
            'points' => $points,
            'polyline' => $polyline,
            'best_position' => $bestPosition,
            'worst_position' => $worstPosition,
        ];
    }
}
