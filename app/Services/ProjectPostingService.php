<?php

namespace App\Services;

use App\Enums\PostingType;
use App\Enums\ProjectStatus;
use App\Enums\SubscriptionStatus;
use App\Models\EmployerProfile;
use App\Models\EmployerSubscription;
use App\Models\PlatformSetting;
use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Sends a draft project for admin review and decides how the posting is paid for (v4 rules):
 * the 1st project is always free, the 2nd is free only inside the window after the 1st,
 * after that the employer needs an active plan with quota left.
 */
class ProjectPostingService
{
    /**
     * @throws ValidationException
     */
    public function submitForReview(Project $project): void
    {
        DB::transaction(function () use ($project): void {
            $project = Project::with('employer.employerProfile')->lockForUpdate()->findOrFail($project->id);

            if ($project->status !== ProjectStatus::Draft) {
                throw ValidationException::withMessages(['project' => __('Only draft projects can be sent for review.')]);
            }

            // A project returned for edits was already paid for when it was first submitted.
            if ($project->submitted_at === null) {
                $this->chargePosting($project);
            }

            $project->forceFill([
                'status' => ProjectStatus::PendingReview,
                'submitted_at' => $project->submitted_at ?? now(),
                'review_note' => null,
            ])->save();
        });
    }

    /**
     * The next free posting or plan slot the employer can use, without using it.
     *
     * @return array{type: ?PostingType, subscription: ?EmployerSubscription, second_free_until: ?string}
     */
    public function nextPostingFor(Project|int $employerId): array
    {
        $employerId = $employerId instanceof Project ? $employerId->employer_id : $employerId;
        $profile = EmployerProfile::firstWhere('user_id', $employerId);
        $freeLimit = $this->setting('employer_free_projects', 2);
        $used = $profile?->free_projects_used ?? 0;

        if ($used === 0 && $freeLimit >= 1) {
            return ['type' => PostingType::FreeFirst, 'subscription' => null, 'second_free_until' => null];
        }

        if ($used === 1 && $freeLimit >= 2 && $profile?->second_free_until?->isFuture()) {
            return ['type' => PostingType::FreeSecond, 'subscription' => null, 'second_free_until' => $profile->second_free_until->toIso8601String()];
        }

        $subscription = $this->usableSubscription($employerId);

        return [
            'type' => $subscription ? PostingType::Subscription : null,
            'subscription' => $subscription,
            'second_free_until' => $profile?->second_free_until?->toIso8601String(),
        ];
    }

    /**
     * @throws ValidationException
     */
    private function chargePosting(Project $project): void
    {
        $next = $this->nextPostingFor($project);
        $profile = $project->employer->employerProfile ?? $project->employer->employerProfile()->create();

        match ($next['type']) {
            PostingType::FreeFirst => $profile->forceFill([
                'free_projects_used' => 1,
                'first_free_project_at' => now(),
                'second_free_until' => now()->addDays($this->setting('second_free_project_window_days', 30)),
            ])->save(),
            PostingType::FreeSecond => $profile->forceFill(['free_projects_used' => 2])->save(),
            PostingType::Subscription => $next['subscription']->increment('projects_used'),
            null => throw ValidationException::withMessages([
                'project' => __('Your free postings are used up. Buy a plan to publish more projects.'),
            ]),
        };

        $project->forceFill([
            'posting_type' => $next['type'],
            'subscription_id' => $next['subscription']?->id,
        ]);
    }

    private function usableSubscription(int $employerId): ?EmployerSubscription
    {
        return EmployerSubscription::query()
            ->with('plan')
            ->where('employer_id', $employerId)
            ->where('status', SubscriptionStatus::Active)
            ->where(fn (Builder $query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->latest('started_at')
            ->get()
            ->first(fn (EmployerSubscription $subscription): bool => $subscription->plan->project_quota === null
                || $subscription->projects_used < $subscription->plan->project_quota);
    }

    private function setting(string $key, int $default): int
    {
        $value = PlatformSetting::firstWhere('setting_key', $key)?->typed_value;

        return is_int($value) ? $value : $default;
    }
}
