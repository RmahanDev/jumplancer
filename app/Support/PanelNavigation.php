<?php

namespace App\Support;

use App\Enums\AdminPermission;
use App\Enums\DisputeStatus;
use App\Enums\FreelancerFieldStatus;
use App\Enums\ModerationStatus;
use App\Enums\Panel;
use App\Enums\ProjectStatus;
use App\Enums\ProposalStatus;
use App\Enums\TicketStatus;
use App\Enums\WithdrawalStatus;
use App\Models\Dispute;
use App\Models\FreelancerField;
use App\Models\PortfolioMedia;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Violation;
use App\Models\WithdrawalRequest;
use Closure;

/**
 * Sidebar items (and search-palette entries) of each dashboard.
 */
class PanelNavigation
{
    /**
     * @return list<array{key: string, label: string, icon: string, href: string, active: bool, badge: ?int, group: ?string}>
     */
    public static function for(User $user, Panel $panel): array
    {
        $items = match ($panel) {
            Panel::SuperAdmin => self::superAdminItems(),
            Panel::Admin => self::adminItems(),
            Panel::Mentor => self::mentorItems($user),
            Panel::Freelancer => self::freelancerItems(),
            Panel::Employer => self::employerItems($user),
        };

        $visible = array_filter(
            $items,
            fn (array $item): bool => $item['permission'] === null || $user->can($item['permission']->value),
        );

        return array_values(array_map(fn (array $item): array => [
            'key' => $item['route'],
            'label' => $item['label'],
            'icon' => $item['icon'],
            'href' => route($item['route']),
            'active' => request()->routeIs($item['active'] ?? $item['route']),
            'badge' => isset($item['badge']) ? (($count = ($item['badge'])()) > 0 ? $count : null) : null,
            'group' => $item['group'] ?? null,
        ], $visible));
    }

    /**
     * @param  (Closure(): int)|null  $badge
     * @param  string|list<string>|null  $active
     * @return array{route: string, label: string, icon: string, permission: ?AdminPermission, badge: (Closure(): int)|null, group: ?string, active: string|list<string>|null}
     */
    private static function item(
        string $route,
        string $label,
        string $icon,
        ?AdminPermission $permission = null,
        ?Closure $badge = null,
        ?string $group = null,
        string|array|null $active = null,
    ): array {
        return compact('route', 'label', 'icon', 'permission', 'badge', 'group', 'active');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function superAdminItems(): array
    {
        return [
            self::item('super.dashboard', 'وضعیت سیستم', 'bi-cpu'),
            self::item('super.permissions.index', 'نقش‌ها و دسترسی‌ها', 'bi-shield-lock', active: 'super.permissions.*'),
            self::item('super.logs.index', 'لاگ‌های برنامه', 'bi-journal-code', active: 'super.logs.*', group: 'ابزار برنامه‌نویس'),
            self::item('super.routes.index', 'مسیرها (Routes)', 'bi-signpost-split', group: 'ابزار برنامه‌نویس'),
            self::item('super.jobs.index', 'صف و جاب‌های ناموفق', 'bi-stack', active: 'super.jobs.*', group: 'ابزار برنامه‌نویس'),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function adminItems(): array
    {
        return [
            self::item('admin.dashboard', 'داشبورد', 'bi-grid-1x2'),
            self::item('admin.users.index', 'کاربران', 'bi-people', AdminPermission::ManageUsers, group: 'کاربران', active: 'admin.users.*'),
            self::item('admin.admins.index', 'مدیران و دسترسی‌ها', 'bi-person-badge', AdminPermission::ManageAdmins, group: 'کاربران', active: 'admin.admins.*'),
            self::item('admin.projects.index', 'پروژه‌ها', 'bi-kanban', AdminPermission::ManageProjects,
                fn (): int => Project::where('status', ProjectStatus::PendingReview)->count(), 'بازارگاه', 'admin.projects.*'),
            self::item('admin.contracts.index', 'قراردادها و اختلاف‌ها', 'bi-file-earmark-text', AdminPermission::ManageContracts,
                fn (): int => Dispute::whereIn('status', [DisputeStatus::Open, DisputeStatus::UnderReview])->count(), 'بازارگاه', ['admin.contracts.*', 'admin.disputes.*']),
            self::item('admin.categories.index', 'دسته‌ها و مهارت‌ها', 'bi-diagram-3', AdminPermission::ManageCatalog, group: 'بازارگاه', active: ['admin.categories.*', 'admin.skills.*']),
            self::item('admin.plans.index', 'پلن‌های کارفرما', 'bi-gem', AdminPermission::ManageCatalog, group: 'بازارگاه', active: 'admin.plans.*'),
            self::item('admin.transactions.index', 'تراکنش‌ها', 'bi-cash-stack', AdminPermission::ViewFinance, group: 'مالی'),
            self::item('admin.withdrawals.index', 'بازگشت وجه به کارت', 'bi-bank', AdminPermission::ManageWithdrawals,
                fn (): int => WithdrawalRequest::where('status', WithdrawalStatus::Pending)->count(), 'مالی', 'admin.withdrawals.*'),
            self::item('admin.tickets.index', 'تیکت‌های منتورینگ', 'bi-life-preserver', AdminPermission::ManageMentoring,
                fn (): int => Ticket::where('status', TicketStatus::Open)->count(), 'منتورینگ و محتوا', 'admin.tickets.*'),
            self::item('admin.contents.index', 'محتوای آموزشی', 'bi-journal-richtext', AdminPermission::ManageContent, group: 'منتورینگ و محتوا', active: 'admin.contents.*'),
            self::item('admin.moderation.fields', 'آزمون و حوزه‌ها', 'bi-bullseye', AdminPermission::ManageModeration,
                fn (): int => FreelancerField::where('status', FreelancerFieldStatus::PendingExam)->count(), 'تخلفات و بازبینی', 'admin.moderation.fields'),
            self::item('admin.moderation.violations', 'تخلفات تماس', 'bi-shield-exclamation', AdminPermission::ManageModeration,
                fn (): int => Violation::whereNull('reviewed_at')->count(), 'تخلفات و بازبینی', 'admin.moderation.violations'),
            self::item('admin.moderation.portfolio', 'فایل‌های نمونه‌کار', 'bi-images', AdminPermission::ManageModeration,
                fn (): int => PortfolioMedia::where('status', ModerationStatus::PendingReview)->count(), 'تخلفات و بازبینی', 'admin.moderation.portfolio'),
            self::item('admin.settings.index', 'تنظیمات پلتفرم', 'bi-sliders', AdminPermission::ManageSettings, group: 'تنظیمات', active: 'admin.settings.*'),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function mentorItems(User $user): array
    {
        return [
            self::item('mentor.dashboard', 'داشبورد', 'bi-grid-1x2'),
            self::item('mentor.tickets.index', 'تیکت‌ها', 'bi-inbox',
                badge: fn (): int => Ticket::where('status', TicketStatus::Open)->whereNull('assigned_mentor_id')->count(), active: 'mentor.tickets.*'),
            self::item('mentor.programs.index', 'برنامه‌های منتورینگ', 'bi-people', active: ['mentor.programs.*', 'mentor.sessions.*']),
            self::item('mentor.reviews.index', 'بازبینی پیشنهادها', 'bi-chat-square-quote',
                badge: fn (): int => Proposal::query()->reviewableBy($user)->whereNull('mentor_reviewed_by')->count(), active: 'mentor.reviews.*'),
            self::item('mentor.contents.index', 'محتوای آموزشی من', 'bi-journal-richtext', active: 'mentor.contents.*'),
            self::item('wallet.show', 'کیف پول', 'bi-wallet2', group: 'حساب', active: 'wallet.*'),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function freelancerItems(): array
    {
        return [
            self::item('freelancer.dashboard', 'داشبورد', 'bi-grid-1x2'),
            self::item('freelancer.projects.index', 'پیدا کردن پروژه', 'bi-search', active: 'freelancer.projects.*'),
            self::item('freelancer.proposals.index', 'پیشنهادهای من', 'bi-send', active: 'freelancer.proposals.*'),
            self::item('freelancer.contracts.index', 'قراردادها', 'bi-file-earmark-check', active: ['freelancer.contracts.*', 'freelancer.milestones.*']),
            self::item('freelancer.portfolio.index', 'نمونه‌کارها', 'bi-images', group: 'پروفایل حرفه‌ای', active: 'freelancer.portfolio.*'),
            self::item('freelancer.fields.index', 'حوزه‌های کاری', 'bi-bullseye', group: 'پروفایل حرفه‌ای', active: 'freelancer.fields.*'),
            self::item('wallet.show', 'کیف پول', 'bi-wallet2', group: 'حساب', active: 'wallet.*'),
            self::item('tickets.index', 'درخواست منتورینگ', 'bi-life-preserver', group: 'حساب', active: 'tickets.*'),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function employerItems(User $user): array
    {
        return [
            self::item('employer.dashboard', 'داشبورد', 'bi-grid-1x2'),
            self::item('employer.projects.index', 'پروژه‌های من', 'bi-kanban', active: 'employer.projects.*'),
            self::item('employer.proposals.index', 'پیشنهادهای دریافتی', 'bi-inboxes',
                badge: fn (): int => Proposal::where('status', ProposalStatus::Pending)
                    ->whereHas('project', fn ($query) => $query->where('employer_id', $user->id))
                    ->count(), active: 'employer.proposals.*'),
            self::item('employer.contracts.index', 'قراردادها', 'bi-file-earmark-check', active: ['employer.contracts.*', 'employer.milestones.*']),
            self::item('employer.plans.index', 'پلن و اشتراک', 'bi-gem', active: 'employer.plans.*'),
            self::item('wallet.show', 'کیف پول', 'bi-wallet2', group: 'حساب', active: 'wallet.*'),
        ];
    }
}
