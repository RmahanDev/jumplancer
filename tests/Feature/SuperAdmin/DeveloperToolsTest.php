<?php

namespace Tests\Feature\SuperAdmin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

class DeveloperToolsTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    private string $logPath;

    private ?string $originalLog = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
        $this->actingAs($this->superAdmin());

        $this->logPath = storage_path('logs/laravel.log');
        $this->originalLog = File::exists($this->logPath) ? File::get($this->logPath) : null;
    }

    protected function tearDown(): void
    {
        $this->originalLog === null ? File::delete($this->logPath) : File::put($this->logPath, $this->originalLog);

        parent::tearDown();
    }

    public function test_system_dashboard_reports_environment_and_health(): void
    {
        $this->get(route('super.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('SuperAdmin/Dashboard')
                ->where('environment.locale', 'fa')
                ->where('environment.payments_sandbox', true)
                ->where('health', fn ($checks) => collect($checks)->pluck('key')->all() === ['database', 'cache', 'storage', 'storage_link', 'failed_jobs', 'debug_mode', 'root_admin', 'log_size'])
                ->where('health.0.ok', true)
                ->where('staff.super_admins', 1)
                ->has('routes.impersonate'));
    }

    public function test_logs_are_parsed_newest_first_filtered_and_cleared(): void
    {
        File::put($this->logPath, implode("\n", [
            '[2026-09-26 10:00:00] local.INFO: Queue worker started',
            '[2026-09-27 09:15:02] local.ERROR: SQLSTATE[HY000] database is locked {"exception":"..."}',
            '#0 /app/vendor/laravel/framework/src/Database/Connection.php(825)',
            '[2026-09-27 09:20:00] local.WARNING: Slow request on /panel/admin',
            '',
        ]));

        $this->get(route('super.logs.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('entries', 3)
                ->where('entries.0.level', 'warning')
                ->where('entries.1.level', 'error')
                ->where('entries.1.context', '#0 /app/vendor/laravel/framework/src/Database/Connection.php(825)'));

        $this->get(route('super.logs.index', ['level' => 'error']))->assertInertia(fn (Assert $page) => $page->has('entries', 1));
        $this->get(route('super.logs.index', ['search' => 'queue worker']))->assertInertia(fn (Assert $page) => $page->has('entries', 1)->where('entries.0.level', 'info'));
        $this->get(route('super.logs.index', ['level' => 'verbose']))->assertSessionHasErrors('level');

        $this->delete(route('super.logs.destroy'))->assertInertiaFlash('toast.message', 'فایل لاگ پاک شد.');
        $this->assertSame('', File::get($this->logPath));
    }

    public function test_route_list_shows_the_dashboard_routes(): void
    {
        $this->get(route('super.routes.index'))
            ->assertInertia(fn (Assert $page) => $page->where('routes', function ($routes): bool {
                $users = collect($routes)->firstWhere('name', 'admin.users.index');

                return $users['uri'] === '/panel/admin/users'
                    && $users['methods'] === ['GET']
                    && $users['action'] === 'Admin\UserController@index'
                    && in_array('PermissionMiddleware:users.manage', $users['middleware'], true);
            }));
    }

    public function test_failed_jobs_can_be_retried_or_forgotten(): void
    {
        $retry = $this->failedJob();
        $forget = $this->failedJob();

        $this->get(route('super.jobs.index'))->assertInertia(fn (Assert $page) => $page->has('jobs.data', 2)->where('jobs.data.0.job', 'App\\Jobs\\SendInvoice'));

        $this->post(route('super.jobs.retry', $retry))->assertInertiaFlash('toast.message', 'جاب دوباره به صف برگشت.');
        $this->delete(route('super.jobs.destroy', $forget))->assertInertiaFlash('toast.message', 'جاب ناموفق حذف شد.');

        $this->assertSame(0, DB::table('failed_jobs')->count());
        $this->assertSame(1, DB::table('jobs')->count());
        $this->post(route('super.jobs.retry', Str::uuid()))->assertNotFound();
    }

    public function test_a_job_that_cannot_be_retried_explains_why_instead_of_crashing(): void
    {
        $broken = $this->failedJob(command: 'not-a-serialized-or-encrypted-command');

        $this->post(route('super.jobs.retry', $broken))->assertSessionHasErrors('job');

        $this->assertSame(1, DB::table('failed_jobs')->count());
    }

    public function test_caches_are_cleared_from_the_browser(): void
    {
        $this->delete(route('super.cache.clear'))->assertRedirect()->assertInertiaFlash('toast.message', 'همه‌ی کش‌ها پاک شدند.');
    }

    private function failedJob(string $command = 'O:8:"stdClass":0:{}'): string
    {
        $uuid = (string) Str::uuid();

        DB::table('failed_jobs')->insert([
            'uuid' => $uuid,
            'connection' => 'database',
            'queue' => 'default',
            'payload' => json_encode(['uuid' => $uuid, 'displayName' => 'App\\Jobs\\SendInvoice', 'job' => 'Illuminate\\Queue\\CallQueuedHandler@call', 'data' => ['commandName' => 'App\\Jobs\\SendInvoice', 'command' => $command], 'attempts' => 3]),
            'exception' => "RuntimeException: SMTP timeout\n#0 stack",
            'failed_at' => now(),
        ]);

        return $uuid;
    }
}
