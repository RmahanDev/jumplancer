<?php

namespace App\Http\Requests\Freelancer;

use App\Enums\FreelancerFieldStatus;
use App\Enums\ProjectStatus;
use App\Models\Project;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * A freelancer's bid. On create it also enforces the marketplace rules: the project is open,
 * not the freelancer's own, not already bid on, and inside one of their active work fields.
 */
class SaveProposalRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $creating = $this->route('proposal') === null;

        return [
            'project_id' => [$creating ? 'required' : 'prohibited', 'integer', Rule::exists('projects', 'id')->whereNull('deleted_at')],
            'cover_letter' => ['required', 'string', 'min:30', 'max:5000'],
            'proposed_price' => ['required', 'integer', 'min:1000', 'max:10000000000'],
            'delivery_days' => ['required', 'integer', 'min:1', 'max:365'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        if ($this->route('proposal') !== null) {
            return [];
        }

        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('project_id')) {
                    return;
                }

                $project = Project::with('category')->find($this->integer('project_id'));
                $freelancer = $this->user();

                $error = match (true) {
                    $project->status !== ProjectStatus::Open => __('This project is not accepting proposals.'),
                    $project->employer_id === $freelancer->id => __('You cannot send a proposal to your own project.'),
                    $project->proposals()->where('freelancer_id', $freelancer->id)->exists() => __('You already sent a proposal for this project.'),
                    ! $freelancer->freelancerFields()
                        ->where('status', FreelancerFieldStatus::Active)
                        ->where('category_id', $project->category->parent_id ?? $project->category_id)
                        ->exists() => __('Activate this field in "work fields" before bidding on its projects.'),
                    default => null,
                };

                if ($error !== null) {
                    $validator->errors()->add('project_id', $error);
                }
            },
        ];
    }
}
