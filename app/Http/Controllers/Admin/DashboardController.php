<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ContractStatus;
use App\Enums\DisputeStatus;
use App\Enums\ProjectStatus;
use App\Enums\RoleName;
use App\Enums\TicketStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Http\Resources\DisputeResource;
use App\Http\Resources\ProjectResource;
use App\Http\Resources\UserResource;
use App\Models\Contract;
use App\Models\Dispute;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Business overview: headline numbers now, charts loaded right after the first paint.
 */
class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'users' => User::count(),
                'new_users_week' => User::where('created_at', '>=', now()->subDays(7))->count(),
                'open_projects' => Project::where('status', ProjectStatus::Open)->count(),
                'pending_projects' => Project::where('status', ProjectStatus::PendingReview)->count(),
                'active_contracts' => Contract::where('status', ContractStatus::Active)->count(),
                'escrow' => (int) Wallet::sum('held_balance'),
                'revenue' => (int) abs($this->revenueQuery()->sum('amount')),
                'open_tickets' => Ticket::where('status', TicketStatus::Open)->count(),
                'open_disputes' => Dispute::whereIn('status', [DisputeStatus::Open, DisputeStatus::UnderReview])->count(),
            ],
            'charts' => Inertia::defer(fn (): array => [
                'signups' => $this->dailyCounts(User::query()->where('created_at', '>=', now()->subDays(29)->startOfDay())->pluck('created_at'), 30),
                'revenue' => $this->weeklySums($this->revenue(now()->subWeeks(25)->startOfWeek(CarbonInterface::SATURDAY)), 26),
                'revenueLast30' => (int) $this->revenue(now()->subDays(29)->startOfDay())->sum('amount'),
                'projectsByStatus' => collect(ProjectStatus::cases())->mapWithKeys(fn (ProjectStatus $status): array => [
                    $status->value => Project::where('status', $status)->count(),
                ]),
                'usersByRole' => collect([RoleName::Freelancer, RoleName::Employer, RoleName::Mentor, RoleName::Admin, RoleName::Support])->mapWithKeys(fn (RoleName $role): array => [
                    $role->value => User::role($role)->count(),
                ]),
            ]),
            'recent' => [
                'users' => UserResource::collection(User::with('roles')->latest()->latest('id')->limit(6)->get()),
                'pendingProjects' => ProjectResource::collection(
                    Project::with(['employer', 'category'])->where('status', ProjectStatus::PendingReview)->oldest('submitted_at')->limit(5)->get(),
                ),
                'disputes' => DisputeResource::collection(
                    Dispute::with(['contract.project', 'initiator'])->whereIn('status', [DisputeStatus::Open, DisputeStatus::UnderReview])->latest()->limit(5)->get(),
                ),
            ],
        ]);
    }

    /**
     * Platform income: fees taken on released milestones plus plan sales (stored as negative rows).
     *
     * @return Collection<int, array{amount: int, created_at: CarbonImmutable}>
     */
    private function revenue(DateTimeInterface $since): Collection
    {
        return $this->revenueQuery()
            ->where('created_at', '>=', $since)
            ->get(['amount', 'created_at'])
            ->map(fn (Transaction $transaction): array => [
                'amount' => abs($transaction->amount),
                'created_at' => $transaction->created_at->toImmutable(),
            ]);
    }

    /**
     * @return Builder<Transaction>
     */
    private function revenueQuery(): Builder
    {
        return Transaction::query()
            ->whereIn('type', [TransactionType::Fee, TransactionType::PlanPurchase])
            ->where('status', TransactionStatus::Succeeded);
    }

    /**
     * @param  Collection<int, mixed>  $dates
     * @return list<array{date: string, value: int}>
     */
    private function dailyCounts(Collection $dates, int $days): array
    {
        $counts = $dates->countBy(fn ($date): string => $date->toDateString());

        return $this->series($days, fn (string $day): int => $counts[$day] ?? 0);
    }

    /**
     * @param  Collection<int, array{amount: int, created_at: CarbonImmutable}>  $rows
     * @return list<array{date: string, value: int}>
     */
    private function dailySums(Collection $rows, int $days): array
    {
        $sums = $rows->groupBy(fn (array $row): string => $row['created_at']->toDateString())
            ->map(fn (Collection $group): int => (int) $group->sum('amount'));

        return $this->series($days, fn (string $day): int => $sums[$day] ?? 0);
    }

    /**
     * Sums per week (Persian weeks start on Saturday), oldest first; `date` is the week's first day.
     *
     * @param  Collection<int, array{amount: int, created_at: CarbonImmutable}>  $rows
     * @return list<array{date: string, value: int}>
     */
    private function weeklySums(Collection $rows, int $weeks): array
    {
        $weekOf = fn (CarbonInterface $date): string => $date->copy()->startOfWeek(CarbonInterface::SATURDAY)->toDateString();
        $sums = $rows->groupBy(fn (array $row): string => $weekOf($row['created_at']))
            ->map(fn (Collection $group): int => (int) $group->sum('amount'));

        return collect(range($weeks - 1, 0))
            ->map(fn (int $weeksAgo): string => $weekOf(now()->subWeeks($weeksAgo)))
            ->map(fn (string $week): array => ['date' => $week, 'value' => $sums[$week] ?? 0])
            ->all();
    }

    /**
     * One point per day, oldest first, so charts have no gaps.
     *
     * @param  callable(string): int  $value
     * @return list<array{date: string, value: int}>
     */
    private function series(int $days, callable $value): array
    {
        return collect(range($days - 1, 0))
            ->map(fn (int $daysAgo): string => now()->subDays($daysAgo)->toDateString())
            ->map(fn (string $day): array => ['date' => $day, 'value' => $value($day)])
            ->all();
    }
}
