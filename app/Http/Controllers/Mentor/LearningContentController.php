<?php

namespace App\Http\Controllers\Mentor;

use App\Http\Controllers\Admin\LearningContentController as AdminLearningContentController;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveLearningContentRequest;
use App\Http\Resources\LearningContentResource;
use App\Models\LearningContent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Articles, videos, checklists and roadmaps the mentor wrote.
 */
class LearningContentController extends Controller
{
    public function index(Request $request): Response
    {
        $contents = $request->user()->learningContents()
            ->with('category')
            ->latest()
            ->latest('id')
            ->paginate(config('jumplancer.per_page'))
            ->withQueryString();

        return Inertia::render('Mentor/Contents/Index', [
            'contents' => LearningContentResource::collection($contents),
            ...AdminLearningContentController::formOptions(),
            'routes' => [
                'store' => route('mentor.contents.store'),
                'update' => route('mentor.contents.update', ':id'),
                'destroy' => route('mentor.contents.destroy', ':id'),
            ],
        ]);
    }

    public function store(SaveLearningContentRequest $request): RedirectResponse
    {
        $content = $request->user()->learningContents()->create($request->contentAttributes());

        $this->toast(__('":title" was saved.', ['title' => $content->title]));

        return back();
    }

    public function update(SaveLearningContentRequest $request, LearningContent $learningContent): RedirectResponse
    {
        Gate::authorize('update', $learningContent);

        $learningContent->update($request->contentAttributes($learningContent->published_at?->toDateTimeString()));

        $this->toast(__('":title" was saved.', ['title' => $learningContent->title]));

        return back();
    }

    public function destroy(LearningContent $learningContent): RedirectResponse
    {
        Gate::authorize('delete', $learningContent);

        $learningContent->delete();

        $this->toast(__('":title" was deleted.', ['title' => $learningContent->title]));

        return back();
    }
}
