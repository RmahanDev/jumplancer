<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Failed queue jobs with retry and forget, instead of running artisan on the server.
 */
class FailedJobController extends Controller
{
    public function index(): Response
    {
        $jobs = DB::table('failed_jobs')
            ->orderByDesc('failed_at')
            ->orderByDesc('id')
            ->paginate(config('jumplancer.per_page'))
            ->through(fn (object $job): array => [
                'uuid' => $job->uuid,
                'connection' => $job->connection,
                'queue' => $job->queue,
                'job' => json_decode($job->payload, true)['displayName'] ?? null,
                'exception' => Str::limit(Str::before($job->exception, "\n"), 300),
                'failed_at' => $job->failed_at,
            ]);

        return Inertia::render('SuperAdmin/Jobs', [
            'jobs' => $jobs,
            'routes' => [
                'retry' => route('super.jobs.retry', ':id'),
                'forget' => route('super.jobs.destroy', ':id'),
            ],
        ]);
    }

    /**
     * Push the job back onto its queue.
     */
    public function update(string $uuid): RedirectResponse
    {
        abort_unless(DB::table('failed_jobs')->where('uuid', $uuid)->exists(), 404);

        try {
            Artisan::call('queue:retry', ['id' => [$uuid]]);
        } catch (Throwable $exception) {
            // e.g. a payload encrypted with an old APP_KEY: show why instead of a 500 page.
            throw ValidationException::withMessages([
                'job' => __('The job could not be retried: :message', ['message' => Str::limit($exception->getMessage(), 150)]),
            ]);
        }

        $this->toast(__('The job was pushed back onto the queue.'));

        return back();
    }

    public function destroy(string $uuid): RedirectResponse
    {
        abort_unless(DB::table('failed_jobs')->where('uuid', $uuid)->exists(), 404);

        Artisan::call('queue:forget', ['id' => $uuid]);

        $this->toast(__('The failed job was deleted.'));

        return back();
    }
}
