<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlanResource;
use App\Http\Resources\SubscriptionResource;
use App\Models\Plan;
use App\Models\PlatformSetting;
use App\Services\ProjectPostingService;
use App\Services\WalletLedger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Free postings left, the current subscription, and plans to buy from the wallet.
 */
class PlanController extends Controller
{
    public function index(Request $request, ProjectPostingService $posting): Response
    {
        $employer = $request->user()->load('employerProfile');
        $next = $posting->nextPostingFor($employer->id);

        return Inertia::render('Employer/Plans/Index', [
            'plans' => PlanResource::collection(Plan::where('is_active', true)->orderBy('price')->get()),
            'subscriptions' => SubscriptionResource::collection($employer->subscriptions()->with('plan')->latest('started_at')->latest('id')->limit(10)->get()),
            'free' => [
                'limit' => (int) (PlatformSetting::firstWhere('setting_key', 'employer_free_projects')?->typed_value ?? 2),
                'used' => $employer->employerProfile?->free_projects_used ?? 0,
                'first_free_project_at' => $employer->employerProfile?->first_free_project_at?->toIso8601String(),
                'second_free_until' => $employer->employerProfile?->second_free_until?->toIso8601String(),
            ],
            'next' => $next['type']?->value,
            'balance' => $employer->ensureWallet()->balance,
            'routes' => [
                'subscribe' => route('employer.plans.subscribe', ':id'),
                'wallet' => route('wallet.show'),
            ],
        ]);
    }

    public function store(Request $request, Plan $plan, WalletLedger $ledger): RedirectResponse
    {
        abort_unless($plan->is_active, 404);

        $ledger->purchasePlan($request->user(), $plan);

        $this->toast(__('Plan ":name" is active. You can publish new projects now.', ['name' => $plan->name]));

        return back();
    }
}
