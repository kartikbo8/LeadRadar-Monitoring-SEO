<?php

use App\Exceptions\WebsiteAuditException;
use App\Services\PlaywrightResponsiveAuditService;
use App\Services\PublicHostResolver;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use RuntimeException;

it('runs the browser audit against a pinned public host and returns viewport findings', function () {
    $this->mock(PublicHostResolver::class)
        ->shouldReceive('resolve')
        ->once()
        ->with('example.test')
        ->andReturn('93.184.216.34');

    Process::preventStrayProcesses();
    Process::fake([
        '*health-audit.mjs*' => Process::result(
            output: json_encode([
                'pages' => [[
                    'url' => 'https://example.test/',
                    'viewport' => 'mobile',
                    'response_status' => 200,
                    'navigation_error' => null,
                    'horizontal_overflow' => true,
                    'page_errors' => [],
                    'failed_requests' => [],
                ]],
                'blocked_external_requests' => 2,
            ], JSON_THROW_ON_ERROR),
        ),
    ]);

    $result = app(PlaywrightResponsiveAuditService::class)
        ->scan('example.test', ['https://example.test/']);

    expect($result['pages'][0]['horizontal_overflow'])->toBeTrue()
        ->and($result['blocked_external_requests'])->toBe(2);

    Process::assertRan(function (PendingProcess $process, ProcessResult $result): bool {
        $command = is_array($process->command) ? implode(' ', $process->command) : $process->command;

        return str_contains($command, 'health-audit.mjs') && $process->timeout === PlaywrightResponsiveAuditService::PROCESS_TIMEOUT_SECONDS;
    });
});

it('rejects private network hosts before invoking Chromium', function () {
    Process::preventStrayProcesses();

    expect(fn () => app(PlaywrightResponsiveAuditService::class)
        ->scan('127.0.0.1', ['http://127.0.0.1/']))
        ->toThrow(WebsiteAuditException::class);
});

it('does not pass cross-domain page URLs to Chromium', function () {
    $this->mock(PublicHostResolver::class)
        ->shouldReceive('resolve')
        ->once()
        ->with('example.test')
        ->andReturn('93.184.216.34');

    Process::preventStrayProcesses();
    Process::fake([
        '*health-audit.mjs*' => Process::result(
            output: json_encode([
                'pages' => [],
                'blocked_external_requests' => 0,
            ], JSON_THROW_ON_ERROR),
        ),
    ]);

    app(PlaywrightResponsiveAuditService::class)->scan('example.test', [
        'http://127.0.0.1/admin',
        'https://attacker.example.test/',
        'https://example.test/',
    ]);

    Process::assertRan(function (PendingProcess $process, ProcessResult $result): bool {
        $input = json_decode($process->input, true);

        return is_array($input) && $input['urls'] === ['https://example.test/'];
    });
});

it('reports when the Playwright process fails', function () {
    $this->mock(PublicHostResolver::class)
        ->shouldReceive('resolve')
        ->once()
        ->with('example.test')
        ->andReturn('93.184.216.34');

    Process::fake([
        '*health-audit.mjs*' => Process::result(exitCode: 1),
    ]);

    expect(fn () => app(PlaywrightResponsiveAuditService::class)
        ->scan('example.test', ['https://example.test/']))
        ->toThrow(RuntimeException::class, 'The browser-based check could not run.');
});
