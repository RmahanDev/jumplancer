<?php

namespace App\Http\Controllers;

use App\Enums\Availability;
use App\Enums\CompanySize;
use App\Enums\MentoringStyle;
use App\Http\Requests\ProfileUpdateRequest;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Account details plus the profile of every marketplace role the user holds.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user()->load(['freelancerProfile', 'employerProfile', 'mentorProfile', 'wallet']);

        return Inertia::render('Shared/Profile', [
            'account' => [
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'phone' => $user->phone,
                'bio' => $user->bio,
                'created_at' => $user->created_at?->toIso8601String(),
                'last_login_at' => $user->last_login_at?->toIso8601String(),
            ],
            'profiles' => $this->roleProfiles($user),
            'options' => [
                'availability' => array_column(Availability::cases(), 'value'),
                'companySize' => array_column(CompanySize::cases(), 'value'),
                'mentoringStyle' => array_column(MentoringStyle::cases(), 'value'),
            ],
            'routes' => [
                'update' => route('profile.update'),
                'password' => route('profile.password.update'),
            ],
        ]);
    }

    /**
     * Save the account and the role profiles in one go.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();

        DB::transaction(function () use ($request, $user): void {
            $user->update($request->safe()->only(['name', 'username', 'email', 'phone', 'bio']));

            if ($request->has('freelancer')) {
                $user->freelancerProfile()->updateOrCreate([], $request->safe()->collect('freelancer')->only(['headline', 'hourly_rate', 'availability'])->all());
            }

            if ($request->has('employer')) {
                $user->employerProfile()->updateOrCreate([], $request->safe()->collect('employer')->only(['company_name', 'company_size', 'industry', 'website', 'open_to_beginners'])->all());
            }

            if ($request->has('mentor')) {
                $user->mentorProfile()->updateOrCreate([], $request->safe()->collect('mentor')->only(['expertise_summary', 'years_experience', 'mentoring_style', 'max_mentees'])->all());
            }
        });

        $this->toast(__('Your profile was saved.'));

        return back();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function roleProfiles(User $user): array
    {
        $profiles = [];

        if ($profile = $user->freelancerProfile) {
            $profiles['freelancer'] = [
                'headline' => $profile->headline,
                'level' => $profile->level->value,
                'readiness_score' => $profile->readiness_score,
                'hourly_rate' => $profile->hourly_rate,
                'availability' => $profile->availability->value,
                'verified_skills' => $user->skills()->wherePivot('is_verified', true)->orderBy('name')->get(['skills.id', 'skills.name'])
                    ->map(fn (Skill $skill): array => ['id' => $skill->id, 'name' => $skill->name])->values(),
            ];
        }

        if ($profile = $user->employerProfile) {
            $profiles['employer'] = [
                'company_name' => $profile->company_name,
                'company_size' => $profile->company_size?->value,
                'industry' => $profile->industry,
                'website' => $profile->website,
                'open_to_beginners' => $profile->open_to_beginners,
            ];
        }

        if ($profile = $user->mentorProfile) {
            $profiles['mentor'] = [
                'expertise_summary' => $profile->expertise_summary,
                'years_experience' => $profile->years_experience,
                'mentoring_style' => $profile->mentoring_style->value,
                'max_mentees' => $profile->max_mentees,
                'is_verified' => $profile->is_verified,
            ];
        }

        return $profiles;
    }
}
