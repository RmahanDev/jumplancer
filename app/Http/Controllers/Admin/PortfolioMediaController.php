<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ModerationStatus;
use App\Http\Controllers\Controller;
use App\Models\PortfolioMedia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PortfolioMediaController extends Controller
{
    /**
     * Approve a portfolio file for employers to see, or reject it (e.g. it shows contact details).
     */
    public function __invoke(Request $request, PortfolioMedia $portfolioMedia): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in([ModerationStatus::Approved->value, ModerationStatus::Rejected->value])],
            'rejection_reason' => ['nullable', 'required_if:status,'.ModerationStatus::Rejected->value, 'string', 'max:255'],
        ]);

        $status = ModerationStatus::from($validated['status']);

        $portfolioMedia->forceFill([
            'status' => $status,
            'rejection_reason' => $status === ModerationStatus::Rejected ? $validated['rejection_reason'] : null,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ])->save();

        $this->toast($status === ModerationStatus::Approved ? __('The file was approved.') : __('The file was rejected.'));

        return back();
    }
}
