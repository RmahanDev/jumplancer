<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FreelancerFieldStatus;
use App\Http\Controllers\Controller;
use App\Models\FreelancerField;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FreelancerFieldController extends Controller
{
    /**
     * Record the result of a field entry exam: the field becomes active (proposals allowed) or rejected.
     */
    public function __invoke(Request $request, FreelancerField $freelancerField): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in([FreelancerFieldStatus::Active->value, FreelancerFieldStatus::Rejected->value])],
        ]);

        $status = FreelancerFieldStatus::from($validated['status']);

        $freelancerField->update([
            'status' => $status,
            'verified_at' => $status === FreelancerFieldStatus::Active ? now() : null,
        ]);

        $this->toast($status === FreelancerFieldStatus::Active ? __('The work field was activated.') : __('The work field was rejected.'));

        return back();
    }
}
