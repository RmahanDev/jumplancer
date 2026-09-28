<?php

namespace App\Http\Controllers\Freelancer;

use App\Enums\PortfolioMediaType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Freelancer\SavePortfolioItemRequest;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\PortfolioItemResource;
use App\Models\Category;
use App\Models\PortfolioItem;
use App\Models\PortfolioMedia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Case studies with files that stay hidden until a moderator approves them.
 */
class PortfolioItemController extends Controller
{
    public function index(Request $request): Response
    {
        $items = $request->user()->portfolioItems()
            ->with(['category', 'skills', 'media'])
            ->latest()
            ->latest('id')
            ->get();

        return Inertia::render('Freelancer/Portfolio/Index', [
            'items' => PortfolioItemResource::collection($items),
            'categories' => CategoryResource::collection(
                Category::topLevel()->with(['children.skills' => fn ($query) => $query->orderBy('name')])->orderBy('sort_order')->get(),
            ),
            'maxFiles' => SavePortfolioItemRequest::MAX_FILES,
            'routes' => [
                'store' => route('freelancer.portfolio.store'),
                'update' => route('freelancer.portfolio.update', ':id'),
                'destroy' => route('freelancer.portfolio.destroy', ':id'),
            ],
        ]);
    }

    public function store(SavePortfolioItemRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $item = $request->user()->portfolioItems()->create($this->attributes($request));
            $item->skills()->sync($request->validated('skills', []));
            $this->storeFiles($item, $request->file('files', []));
        });

        $this->toast(__('The case study was added. New files are shown to employers after review.'));

        return back();
    }

    public function update(SavePortfolioItemRequest $request, PortfolioItem $portfolioItem): RedirectResponse
    {
        DB::transaction(function () use ($request, $portfolioItem): void {
            $portfolioItem->update($this->attributes($request));
            $portfolioItem->skills()->sync($request->validated('skills', []));

            $portfolioItem->media()
                ->whereIn('id', $request->validated('remove_media', []))
                ->get()
                ->each(fn (PortfolioMedia $media) => $this->deleteMedia($media));

            $this->storeFiles($portfolioItem, $request->file('files', []));
        });

        $this->toast(__('The case study was updated.'));

        return back();
    }

    public function destroy(PortfolioItem $portfolioItem): RedirectResponse
    {
        Gate::authorize('delete', $portfolioItem);

        DB::transaction(function () use ($portfolioItem): void {
            $portfolioItem->media->each(fn (PortfolioMedia $media) => $this->deleteMedia($media));
            $portfolioItem->delete();
        });

        $this->toast(__('The case study was deleted.'));

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(SavePortfolioItemRequest $request): array
    {
        return [
            ...$request->safe()->only(['title', 'category_id', 'role', 'description', 'outcome', 'duration_days']),
            'is_visible' => $request->boolean('is_visible', true),
        ];
    }

    /**
     * Files go to private storage; they are streamed back only to allowed viewers.
     *
     * @param  list<UploadedFile>  $files
     */
    private function storeFiles(PortfolioItem $item, array $files): void
    {
        foreach ($files as $file) {
            $item->media()->create([
                'file_path' => $file->store("portfolio/{$item->freelancer_id}", 'local'),
                'file_type' => $file->getClientOriginalExtension() === 'pdf' || $file->getMimeType() === 'application/pdf'
                    ? PortfolioMediaType::Pdf
                    : PortfolioMediaType::Image,
                'original_filename' => mb_substr($file->getClientOriginalName(), 0, 255),
            ]);
        }
    }

    private function deleteMedia(PortfolioMedia $media): void
    {
        Storage::disk('local')->delete($media->file_path);
        $media->delete();
    }
}
