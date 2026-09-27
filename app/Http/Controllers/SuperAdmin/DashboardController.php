<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Contract;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\Ticket;
use App\Models\Transaction;
use App\Models\User;
use Composer\InstalledVersions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Developer overview: runtime configuration, health checks and data volume.
 */
class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('SuperAdmin/Dashboard', [
            'environment' => $this->environment(),
            'health' => $this->healthChecks(),
            'tables' => [
                'users' => User::withTrashed()->count(),
                'projects' => Project::withTrashed()->count(),
                'proposals' => Proposal::count(),
                'contracts' => Contract::count(),
                'transactions' => Transaction::count(),
                'tickets' => Ticket::count(),
            ],
            'staff' => [
                'super_admins' => User::role(RoleName::SuperAdmin)->count(),
                'admins' => User::role(RoleName::Admin)->count(),
            ],
            'recentLogins' => UserResource::collection(
                User::with('roles')->whereNotNull('last_login_at')->latest('last_login_at')->limit(8)->get(),
            ),
            'routes' => [
                'clearCache' => route('super.cache.clear'),
                'logs' => route('super.logs.index'),
                'jobs' => route('super.jobs.index'),
                'impersonate' => route('super.impersonate', ':id'),
            ],
        ]);
    }

    /**
     * @return array<string, string|bool|null>
     */
    private function environment(): array
    {
        return [
            'app_name' => config('app.name'),
            'app_env' => app()->environment(),
            'app_debug' => (bool) config('app.debug'),
            'app_url' => config('app.url'),
            'php' => PHP_VERSION,
            'laravel' => app()->version(),
            'inertia' => InstalledVersions::getPrettyVersion('inertiajs/inertia-laravel'),
            'permission' => InstalledVersions::getPrettyVersion('spatie/laravel-permission'),
            'database' => config('database.default').' / '.DB::connection()->getDriverName(),
            'cache' => config('cache.default'),
            'queue' => config('queue.default'),
            'session' => config('session.driver'),
            'mail' => config('mail.default'),
            'timezone' => config('app.timezone'),
            'locale' => app()->getLocale(),
            'payments_sandbox' => (bool) config('jumplancer.payments.sandbox'),
        ];
    }

    /**
     * @return list<array{key: string, ok: bool, detail: ?string}>
     */
    private function healthChecks(): array
    {
        $databaseStartedAt = microtime(true);

        try {
            DB::select('select 1');
            $database = ['ok' => true, 'detail' => round((microtime(true) - $databaseStartedAt) * 1000, 1).' ms'];
        } catch (Throwable $exception) {
            $database = ['ok' => false, 'detail' => Str::limit($exception->getMessage(), 120)];
        }

        $cacheKey = 'health-check-'.Str::random(8);
        Cache::put($cacheKey, 'ok', 10);
        $cacheWorks = Cache::pull($cacheKey) === 'ok';

        $failedJobs = DB::table('failed_jobs')->count();
        $logPath = storage_path('logs/laravel.log');

        return [
            ['key' => 'database', ...$database],
            ['key' => 'cache', 'ok' => $cacheWorks, 'detail' => config('cache.default')],
            ['key' => 'storage', 'ok' => is_writable(storage_path('app')) && is_writable(storage_path('logs')), 'detail' => null],
            ['key' => 'storage_link', 'ok' => File::exists(public_path('storage')), 'detail' => null],
            ['key' => 'failed_jobs', 'ok' => $failedJobs === 0, 'detail' => (string) $failedJobs],
            ['key' => 'debug_mode', 'ok' => ! (app()->isProduction() && config('app.debug')), 'detail' => null],
            ['key' => 'root_admin', 'ok' => User::where('username', config('jumplancer.super_admin.username'))->exists(), 'detail' => config('jumplancer.super_admin.username')],
            ['key' => 'log_size', 'ok' => ! File::exists($logPath) || File::size($logPath) < 20 * 1024 * 1024, 'detail' => File::exists($logPath) ? number_format(File::size($logPath) / 1024, 1).' KB' : '0 KB'],
        ];
    }
}
