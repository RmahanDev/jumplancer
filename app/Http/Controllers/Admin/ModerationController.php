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
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Review queues: portfolio files, contact-sharing violations and work fields waiting for an exam.
 */
class ModerationController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $tab = $request->validate([
            'tab' => ['nullable', Rule::in(['media', 'violations', 'fields'])],
        ])['tab'] ?? 'media';

        $perPage = config('jumplancer.per_page');

        return Inertia::render('Admin/Moderation/Index', [
            'tab' => $tab,
            'media' => $tab === 'media' ? PortfolioMediaResource::collection(
                PortfolioMedia::with('portfolioItem.freelancer')
                    ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [ModerationStatus::PendingReview->value])
                    ->latest()
                    ->latest('id')
                    ->paginate($perPage)
                    ->withQueryString(),
            ) : null,
            'violations' => $tab === 'violations' ? ViolationResource::collection(
                Violation::with(['user', 'reviewer'])
                    ->orderByRaw('CASE WHEN reviewed_at IS NULL THEN 0 ELSE 1 END')
                    ->latest()
                    ->latest('id')
                    ->paginate($perPage)
                    ->withQueryString(),
            ) : null,
            'fields' => $tab === 'fields' ? FreelancerFieldResource::collection(
                FreelancerField::with(['freelancer', 'category'])
                    ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [FreelancerFieldStatus::PendingExam->value])
                    ->latest()
                    ->latest('id')
                    ->paginate($perPage)
                    ->withQueryString(),
            ) : null,
            'counts' => [
                'media' => PortfolioMedia::where('status', ModerationStatus::PendingReview)->count(),
                'violations' => Violation::whereNull('reviewed_at')->count(),
                'fields' => FreelancerField::where('status', FreelancerFieldStatus::PendingExam)->count(),
            ],
            'routes' => [
                'media' => route('admin.portfolio-media.update', ':id'),
                'violation' => route('admin.violations.update', ':id'),
                'field' => route('admin.freelancer-fields.update', ':id'),
            ],
        ]);
    }
}
