<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Skill;
use App\Support\PersianText;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Skills belong to a sub-category.
 */
class SkillController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $skill = Skill::create($this->validated($request));

        $this->toast(__('Skill ":name" was added.', ['name' => $skill->name]));

        return back();
    }

    public function update(Request $request, Skill $skill): RedirectResponse
    {
        $skill->update($this->validated($request, $skill));

        $this->toast(__('Skill ":name" was updated.', ['name' => $skill->name]));

        return back();
    }

    /**
     * Removing a skill also removes it from projects, freelancers and case studies (pivot rows).
     */
    public function destroy(Skill $skill): RedirectResponse
    {
        $skill->delete();

        $this->toast(__('Skill ":name" was deleted.', ['name' => $skill->name]));

        return back();
    }

    /**
     * @return array{category_id: int, name: string, slug: string}
     */
    private function validated(Request $request, ?Skill $skill = null): array
    {
        $request->merge([
            'name' => PersianText::normalize($request->string('name')->toString()),
            'slug' => mb_strtolower($request->string('slug')->trim()->toString()),
        ]);

        return $request->validate([
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->whereNotNull('parent_id')],
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('skills')->ignore($skill)],
        ]);
    }
}
