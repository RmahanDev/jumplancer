<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FreelancerFieldStatus;
use App\Enums\ModerationStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\FreelancerFieldResource;
use App\Http\Resources\PortfolioMediaResource;
use App\Http\Resources\ViolationResource;
use App\Models\FreelancerField;
use App\Models\PortfolioMedia;
use App\Models\Violation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The three review queues, each on its own page: work-field exams, contact-sharing
 * violations and portfolio files. Every queue opens on what still needs a decision.
 */
class ModerationController extends Controller
{
    /**
     * Old single-page address: open the portfolio queue.
     */
    public function index(): RedirectResponse
    {
        return to_route('admin.moderation.portfolio');
    }

    /**
     * Work fields waiting for the exam result.
     */
    public function fields(Request $request): Response
    {
        $status = $this->status($request, array_column(FreelancerFieldStatus::cases(), 'value'), FreelancerFieldStatus::PendingExam->value);

        return Inertia::render('Admin/Moderation/Fields', [
            'fields' => FreelancerFieldResource::collection(
                FreelancerField::with(['freelancer', 'category'])
                    ->when($status !== 'all', fn (Builder $query) => $query->where('status', $status))
                    ->latest()
                    ->latest('id')
                    ->paginate(config('jumplancer.per_page'))
                    ->withQueryString(),
            ),
            'filters' => ['status' => $status],
            'counts' => FreelancerField::query()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status'),
            'routes' => ['update' => route('admin.freelancer-fields.update', ':id')],
        ]);
    }

    /**
     * Phone numbers, e-mails and links caught in chats and proposals.
     */
    public function violations(Request $request): Response
    {
        $status = $this->status($request, ['unreviewed', 'reviewed'], 'unreviewed');

        return Inertia::render('Admin/Moderation/Violations', [
            'violations' => ViolationResource::collection(
                Violation::with(['user', 'reviewer'])
                    ->when($status === 'unreviewed', fn (Builder $query) => $query->whereNull('reviewed_at'))
                    ->when($status === 'reviewed', fn (Builder $query) => $query->whereNotNull('reviewed_at'))
                    ->latest()
                    ->latest('id')
                    ->paginate(config('jumplancer.per_page'))
                    ->withQueryString(),
            ),
            'filters' => ['status' => $status],
            'counts' => [
                'unreviewed' => Violation::whereNull('reviewed_at')->count(),
                'reviewed' => Violation::whereNotNull('reviewed_at')->count(),
            ],
            'routes' => ['update' => route('admin.violations.update', ':id')],
        ]);
    }

    /**
     * Portfolio files checked before employers can see them.
     */
    public function portfolio(Request $request): Response
    {
        $status = $this->status($request, array_column(ModerationStatus::cases(), 'value'), ModerationStatus::PendingReview->value);

        return Inertia::render('Admin/Moderation/Portfolio', [
            'media' => PortfolioMediaResource::collection(
                PortfolioMedia::with(['portfolioItem.freelancer', 'reviewer'])
                    ->when($status !== 'all', fn (Builder $query) => $query->where('status', $status))
                    ->latest()
                    ->latest('id')
                    ->paginate(config('jumplancer.per_page'))
                    ->withQueryString(),
            ),
            'filters' => ['status' => $status],
            'counts' => PortfolioMedia::query()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status'),
            'routes' => ['update' => route('admin.portfolio-media.update', ':id')],
        ]);
    }

    /**
     * @param  list<string>  $allowed
     */
    private function status(Request $request, array $allowed, string $default): string
    {
        return $request->validate([
            'status' => ['nullable', Rule::in([...$allowed, 'all'])],
        ])['status'] ?? $default;
    }
}
