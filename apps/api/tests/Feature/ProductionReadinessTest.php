<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_readiness_rejects_sqlite_even_when_other_settings_are_safe(): void
    {
        $this->configureProduction();

        $this->artisan('laos:check-production-readiness')
            ->expectsOutputToContain('MySQL/MariaDB database configured')
            ->assertFailed();
    }

    public function test_production_readiness_fails_closed_for_unsafe_configuration(): void
    {
        $this->configureProduction();
        config([
            'app.debug' => true,
            'app.url' => 'http://laos.example.test',
            'queue.default' => 'sync',
            'session.secure' => false,
            'mail.default' => 'log',
            'filesystems.default' => 'public',
        ]);

        $this->artisan('laos:check-production-readiness')
            ->expectsOutputToContain('Debug mode disabled')
            ->expectsOutputToContain('Application URL uses HTTPS')
            ->expectsOutputToContain('Asynchronous queue configured')
            ->expectsOutputToContain('Secure session cookies enabled')
            ->expectsOutputToContain('Mail transport configured')
            ->expectsOutputToContain('Non-public report storage configured')
            ->assertFailed();
    }

    private function configureProduction(): void
    {
        config([
            'app.env' => 'production',
            'app.debug' => false,
            'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
            'app.url' => 'https://laos.example.test',
            'database.default' => 'sqlite',
            'queue.default' => 'database',
            'cache.default' => 'database',
            'session.driver' => 'database',
            'session.secure' => true,
            'session.http_only' => true,
            'mail.default' => 'smtp',
            'filesystems.default' => 'local',
            'filesystems.disks.local.root' => storage_path('app/private'),
            'report-exports.retention_days' => 7,
        ]);
    }
}
