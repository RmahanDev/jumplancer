<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlanResource;
use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Paid plans employers buy once their free postings are used.
 */
class PlanController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Plans/Index', [
            'plans' => PlanResource::collection(Plan::withCount('subscriptions')->orderBy('price')->get()),
            'routes' => [
                'store' => route('admin.plans.store'),
                'update' => route('admin.plans.update', ':id'),
                'destroy' => route('admin.plans.destroy', ':id'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $plan = Plan::create($this->validated($request));

        $this->toast(__('Plan ":name" was created.', ['name' => $plan->name]));

        return back();
    }

    public function update(Request $request, Plan $plan): RedirectResponse
    {
        $plan->update($this->validated($request));

        $this->toast(__('Plan ":name" was updated.', ['name' => $plan->name]));

        return back();
    }

    /**
     * A plan that was ever sold stays for the ledger; deactivate it instead.
     */
    public function destroy(Plan $plan): RedirectResponse
    {
        if ($plan->subscriptions()->exists()) {
            throw ValidationException::withMessages(['plan' => __('This plan has subscribers. Deactivate it instead of deleting it.')]);
        }

        $plan->delete();

        $this->toast(__('Plan ":name" was deleted.', ['name' => $plan->name]));

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'price' => ['required', 'integer', 'min:0', 'max:10000000000'],
            'project_quota' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'duration_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
        ]);
    }
}
