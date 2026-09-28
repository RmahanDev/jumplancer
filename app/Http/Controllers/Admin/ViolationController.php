<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\Violation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ViolationController extends Controller
{
    /**
     * Mark a violation as reviewed, optionally lifting the automatic suspension it caused.
     */
    public function __invoke(Request $request, Violation $violation): RedirectResponse
    {
        $validated = $request->validate([
            'lift_suspension' => ['boolean'],
        ]);

        DB::transaction(function () use ($request, $violation, $validated): void {
            $violation->forceFill(['reviewed_by' => $request->user()->id, 'reviewed_at' => now()])->save();

            if (($validated['lift_suspension'] ?? false) && $violation->user->isSuspended()) {
                $violation->user->forceFill([
                    'status' => UserStatus::Active,
                    'suspended_at' => null,
                    'suspension_reason' => null,
                ])->save();
            }
        });

        $this->toast(($validated['lift_suspension'] ?? false)
            ? __('The violation was reviewed and :name can use the platform again.', ['name' => $violation->user->name])
            : __('The violation was marked as reviewed.'));

        return back();
    }
}
