<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\ConnectionException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PDOException;

class CheckProductionReadiness extends Command
{
    protected $signature = 'laos:check-production-readiness';

    protected $description = 'Check production configuration and database migration readiness without changing data';

    public function handle(): int
    {
        $checks = [
            'Production environment' => config('app.env') === 'production',
            'Debug mode disabled' => config('app.debug') === false,
            'Application key configured' => filled(config('app.key')),
            'Application URL uses HTTPS' => $this->applicationUrlUsesHttps(),
            'MySQL/MariaDB database configured' => in_array(
                config('database.connections.'.config('database.default').'.driver'),
                ['mysql', 'mariadb'],
                true,
            ),
            'Asynchronous queue configured' => config('queue.default') !== 'sync',
            'Persistent cache configured' => config('cache.default') !== 'array',
            'Persistent sessions configured' => config('session.driver') !== 'array',
            'Secure session cookies enabled' => config('session.secure') === true,
            'HTTP-only session cookies enabled' => config('session.http_only') === true,
            'Mail transport configured' => ! in_array(config('mail.default'), ['log', 'array'], true),
            'Non-public report storage configured' => $this->hasPrivateReportStorage(),
            'Positive report retention configured' => (int) config('report-exports.retention_days') > 0,
        ];

        $databaseReady = $this->databaseIsReady();
        $checks['Database reachable and migrations current'] = $databaseReady;

        $failed = [];
        foreach ($checks as $label => $passed) {
            if ($passed) {
                $this->components->info($label);
            } else {
                $this->components->error($label);
                $failed[] = $label;
            }
        }

        if ($failed !== []) {
            $this->newLine();
            $this->error('Production readiness failed. Resolve the checks above; no changes were applied.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Production configuration and database are ready for a separately approved release.');

        return self::SUCCESS;
    }

    private function hasPrivateReportStorage(): bool
    {
        $disk = config('filesystems.default');
        if ($disk === 'public' || ! config("filesystems.disks.{$disk}")) {
            return false;
        }

        if ($disk !== 'local') {
            return true;
        }

        $root = realpath((string) config('filesystems.disks.local.root'));
        $publicPath = realpath(public_path());

        return $root !== false
            && $publicPath !== false
            && ! str_starts_with(
                strtolower($root.DIRECTORY_SEPARATOR),
                strtolower($publicPath.DIRECTORY_SEPARATOR),
            );
    }

    private function applicationUrlUsesHttps(): bool
    {
        $url = parse_url((string) config('app.url'));

        return is_array($url)
            && ($url['scheme'] ?? null) === 'https'
            && filled($url['host'] ?? null);
    }

    private function databaseIsReady(): bool
    {
        try {
            DB::connection()->select('select 1');
            if (! Schema::hasTable('migrations')) {
                return false;
            }

            $ran = app('migration.repository')->getRan();
            $files = app('migrator')->getMigrationFiles(database_path('migrations'));

            return array_diff(array_keys($files), $ran) === [];
        } catch (ConnectionException|PDOException|QueryException) {
            return false;
        }
    }
}
