<?php

namespace App\Http\Controllers\Freelancer;

use App\Enums\ExperienceLevel;
use App\Enums\FreelancerFieldStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\FreelancerFieldResource;
use App\Models\Category;
use App\Models\FreelancerField;
use App\Models\PlatformSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Work fields (top-level categories). A freelancer can only bid inside an active field (v4 rules 3 and 5).
 */
class FieldController extends Controller
{
    public function index(Request $request): Response
    {
        $fields = $request->user()->freelancerFields()->with('category')->orderByDesc('is_primary')->oldest()->get();

        return Inertia::render('Freelancer/Fields/Index', [
            'fields' => FreelancerFieldResource::collection($fields),
            'categories' => CategoryResource::collection(Category::topLevel()->orderBy('sort_order')->get()),
            'levels' => array_column(ExperienceLevel::cases(), 'value'),
            'rules' => [
                'exam_from_level' => $this->examLevel()->value,
                'extra_field_fee' => PlatformSetting::firstWhere('setting_key', 'extra_field_exam_fee')?->typed_value,
            ],
            'routes' => [
                'store' => route('freelancer.fields.store'),
                'destroy' => route('freelancer.fields.destroy', ':id'),
            ],
        ]);
    }

    /**
     * An exam is required at or above the configured level, and for every field after the first;
     * only the first field's exam is free. Without an exam the field is active immediately.
     */
    public function store(Request $request): RedirectResponse
    {
        $freelancer = $request->user();

        $validated = $request->validate([
            'category_id' => [
                'required',
                'integer',
                Rule::exists('categories', 'id')->whereNull('parent_id'),
                Rule::unique('freelancer_fields')->where('freelancer_id', $freelancer->id),
            ],
            'claimed_level' => ['required', Rule::enum(ExperienceLevel::class)],
        ], [
            'category_id.unique' => __('You already registered this field.'),
        ]);

        $isFirst = ! $freelancer->freelancerFields()->exists();
        $examRequired = ! $isFirst || ExperienceLevel::from($validated['claimed_level'])->isAtLeast($this->examLevel());

        $field = $freelancer->freelancerFields()->create([
            'category_id' => $validated['category_id'],
            'claimed_level' => $validated['claimed_level'],
            'is_primary' => $isFirst,
            'exam_required' => $examRequired,
            'exam_fee_required' => ! $isFirst,
            'status' => $examRequired ? FreelancerFieldStatus::PendingExam : FreelancerFieldStatus::Active,
            'verified_at' => $examRequired ? null : now(),
        ]);

        $this->toast($field->status === FreelancerFieldStatus::Active
            ? __('The field is active: you can send proposals for its projects now.')
            : __('The field was registered. It becomes active after you pass its entry exam.'));

        return back();
    }

    /**
     * The primary field stays; others can be removed.
     */
    public function destroy(FreelancerField $freelancerField): RedirectResponse
    {
        Gate::authorize('delete', $freelancerField);

        if ($freelancerField->is_primary) {
            throw ValidationException::withMessages(['field' => __('Your primary field cannot be removed.')]);
        }

        $freelancerField->delete();

        $this->toast(__('The field was removed.'));

        return back();
    }

    private function examLevel(): ExperienceLevel
    {
        $value = PlatformSetting::firstWhere('setting_key', 'exam_required_from_level')?->typed_value;

        return ExperienceLevel::tryFrom((string) $value) ?? ExperienceLevel::Intermediate;
    }
}
