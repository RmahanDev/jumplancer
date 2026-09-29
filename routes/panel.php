<?php

use App\Enums\AdminPermission;
use App\Enums\Panel;
use App\Http\Controllers\Admin;
use App\Http\Controllers\BankCardController;
use App\Http\Controllers\ContractDisputeController;
use App\Http\Controllers\Employer;
use App\Http\Controllers\Freelancer;
use App\Http\Controllers\ImpersonationController;
use App\Http\Controllers\Mentor;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\PortfolioMediaFileController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SuperAdmin;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\WalletDepositController;
use App\Http\Controllers\WithdrawalController;
use App\Http\Controllers\WithdrawalReceiptController;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Middleware\PermissionMiddleware;

/*
|--------------------------------------------------------------------------
| Dashboards (Inertia + React)
|--------------------------------------------------------------------------
|
| Loaded from routes/web.php inside the "auth" + "active" middleware group.
| Every panel is guarded by its roles (see App\Enums\Panel); admin pages are
| additionally guarded by a staff permission (see App\Enums\AdminPermission).
|
*/

Route::prefix('panel')->group(function () {

    // Shared by every role.
    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('profile/password', [PasswordController::class, 'update'])->name('profile.password.update');
    Route::get('wallet', WalletController::class)->name('wallet.show');
    Route::post('wallet/deposits', WalletDepositController::class)->name('wallet.deposits.store');
    Route::post('wallet/cards', [BankCardController::class, 'store'])->name('wallet.cards.store');
    Route::delete('wallet/cards/{bankCard}', [BankCardController::class, 'destroy'])->name('wallet.cards.destroy');
    Route::post('wallet/withdrawals', [WithdrawalController::class, 'store'])->name('wallet.withdrawals.store');
    Route::delete('wallet/withdrawals/{withdrawalRequest}', [WithdrawalController::class, 'destroy'])->name('wallet.withdrawals.destroy');
    Route::get('withdrawals/{withdrawalRequest}/receipt', WithdrawalReceiptController::class)->name('withdrawals.receipt');
    Route::post('contracts/{contract}/disputes', ContractDisputeController::class)->name('contracts.disputes.store');

    // Mentoring requests are for freelancers only (employers do not get mentoring).
    Route::middleware(Panel::Freelancer->middleware())->group(function () {
        Route::get('tickets', [TicketController::class, 'index'])->name('tickets.index');
        Route::post('tickets', [TicketController::class, 'store'])->name('tickets.store');
    });
    Route::get('portfolio-media/{portfolioMedia}', PortfolioMediaFileController::class)->name('portfolio-media.show');

    // Super admin: developer tools with full access.
    Route::prefix('super')->name('super.')->middleware(Panel::SuperAdmin->middleware())->group(function () {
        Route::get('/', SuperAdmin\DashboardController::class)->name('dashboard');
        Route::get('permissions', [SuperAdmin\PermissionMatrixController::class, 'index'])->name('permissions.index');
        Route::put('permissions/{staff}', [SuperAdmin\PermissionMatrixController::class, 'update'])->name('permissions.update');
        Route::get('logs', [SuperAdmin\LogController::class, 'index'])->name('logs.index');
        Route::delete('logs', [SuperAdmin\LogController::class, 'destroy'])->name('logs.destroy');
        Route::get('routes', SuperAdmin\RouteListController::class)->name('routes.index');
        Route::get('jobs', [SuperAdmin\FailedJobController::class, 'index'])->name('jobs.index');
        Route::post('jobs/{uuid}/retry', [SuperAdmin\FailedJobController::class, 'update'])->name('jobs.retry');
        Route::delete('jobs/{uuid}', [SuperAdmin\FailedJobController::class, 'destroy'])->name('jobs.destroy');
        Route::delete('cache', SuperAdmin\CacheController::class)->name('cache.clear');
        Route::post('impersonate/{user}', [ImpersonationController::class, 'store'])->name('impersonate');
    });

    // Admin: business operations; each section needs its staff permission.
    Route::prefix('admin')->name('admin.')->middleware(Panel::Admin->middleware())->group(function () {
        Route::get('/', Admin\DashboardController::class)->name('dashboard');

        Route::middleware(PermissionMiddleware::using(AdminPermission::ManageUsers))->group(function () {
            Route::resource('users', Admin\UserController::class)->only(['index', 'store', 'update', 'destroy']);
            Route::put('users/{user}/status', Admin\UserStatusController::class)->name('users.status');
        });

        Route::middleware(PermissionMiddleware::using(AdminPermission::ManageAdmins))->group(function () {
            Route::resource('admins', Admin\StaffController::class)->only(['index', 'store', 'update', 'destroy'])
                ->parameters(['admins' => 'staff']);
        });

        Route::middleware(PermissionMiddleware::using(AdminPermission::ManageProjects))->group(function () {
            Route::resource('projects', Admin\ProjectController::class)->only(['index', 'update', 'destroy']);
            Route::put('projects/{project}/review', Admin\ProjectReviewController::class)->name('projects.review');
        });

        Route::middleware(PermissionMiddleware::using(AdminPermission::ManageContracts))->group(function () {
            Route::get('contracts', Admin\ContractController::class)->name('contracts.index');
            Route::put('disputes/{dispute}', Admin\DisputeController::class)->name('disputes.update');
        });

        Route::middleware(PermissionMiddleware::using(AdminPermission::ManageWithdrawals))->group(function () {
            Route::get('withdrawals', [Admin\WithdrawalController::class, 'index'])->name('withdrawals.index');
            Route::post('withdrawals/{withdrawalRequest}/payment', [Admin\WithdrawalController::class, 'pay'])->name('withdrawals.pay');
            Route::put('withdrawals/{withdrawalRequest}/rejection', [Admin\WithdrawalController::class, 'reject'])->name('withdrawals.reject');
        });

        Route::get('transactions', Admin\TransactionController::class)
            ->middleware(PermissionMiddleware::using(AdminPermission::ViewFinance))
            ->name('transactions.index');

        Route::middleware(PermissionMiddleware::using(AdminPermission::ManageCatalog))->group(function () {
            Route::resource('categories', Admin\CategoryController::class)->only(['index', 'store', 'update', 'destroy']);
            Route::put('categories/{category}/budget-range', Admin\BudgetRangeController::class)->name('categories.budget-range');
            Route::resource('skills', Admin\SkillController::class)->only(['store', 'update', 'destroy']);
            Route::resource('plans', Admin\PlanController::class)->only(['index', 'store', 'update', 'destroy']);
        });

        Route::middleware(PermissionMiddleware::using(AdminPermission::ManageMentoring))->group(function () {
            Route::resource('tickets', Admin\TicketController::class)->only(['index', 'update']);
        });

        Route::middleware(PermissionMiddleware::using(AdminPermission::ManageExams))->group(function () {
            Route::resource('exams', Admin\ExamController::class)->except(['show'])->parameters(['exams' => 'assessment']);
            Route::put('exams/{assessment}/status', [Admin\ExamController::class, 'status'])->name('exams.status');
        });

        Route::middleware(PermissionMiddleware::using(AdminPermission::ManageContent))->group(function () {
            Route::resource('contents', Admin\LearningContentController::class)->only(['index', 'store', 'update', 'destroy'])
                ->parameters(['contents' => 'learningContent']);
        });

        Route::middleware(PermissionMiddleware::using(AdminPermission::ManageModeration))->group(function () {
            Route::get('moderation', [Admin\ModerationController::class, 'index'])->name('moderation.index');
            Route::get('moderation/fields', [Admin\ModerationController::class, 'fields'])->name('moderation.fields');
            Route::get('moderation/violations', [Admin\ModerationController::class, 'violations'])->name('moderation.violations');
            Route::get('moderation/portfolio', [Admin\ModerationController::class, 'portfolio'])->name('moderation.portfolio');
            Route::put('violations/{violation}', Admin\ViolationController::class)->name('violations.update');
            Route::put('portfolio-media/{portfolioMedia}', Admin\PortfolioMediaController::class)->name('portfolio-media.update');
            Route::put('freelancer-fields/{freelancerField}', Admin\FreelancerFieldController::class)->name('freelancer-fields.update');
        });

        Route::middleware(PermissionMiddleware::using(AdminPermission::ManageSettings))->group(function () {
            Route::get('settings', [Admin\PlatformSettingController::class, 'index'])->name('settings.index');
            Route::put('settings/{platformSetting}', [Admin\PlatformSettingController::class, 'update'])->name('settings.update');
        });
    });

    // Mentor: tickets, mentorship programs, proposal reviews and learning content.
    Route::prefix('mentor')->name('mentor.')->middleware(Panel::Mentor->middleware())->group(function () {
        Route::get('/', Mentor\DashboardController::class)->name('dashboard');
        Route::resource('tickets', Mentor\TicketController::class)->only(['index', 'update']);
        Route::resource('programs', Mentor\ProgramController::class)->only(['index', 'store', 'update'])
            ->parameters(['programs' => 'mentorshipProgram']);
        Route::post('programs/{mentorshipProgram}/sessions', [Mentor\SessionController::class, 'store'])->name('sessions.store');
        Route::put('sessions/{mentorshipSession}', [Mentor\SessionController::class, 'update'])->name('sessions.update');
        Route::delete('sessions/{mentorshipSession}', [Mentor\SessionController::class, 'destroy'])->name('sessions.destroy');
        Route::resource('reviews', Mentor\ProposalReviewController::class)->only(['index', 'update'])
            ->parameters(['reviews' => 'proposal']);
        Route::resource('contents', Mentor\LearningContentController::class)->only(['index', 'store', 'update', 'destroy'])
            ->parameters(['contents' => 'learningContent']);
    });

    // Freelancer: find work, send proposals, deliver milestones, build a portfolio.
    Route::prefix('freelancer')->name('freelancer.')->middleware(Panel::Freelancer->middleware())->group(function () {
        Route::get('/', Freelancer\DashboardController::class)->name('dashboard');
        Route::get('projects', Freelancer\ProjectController::class)->name('projects.index');
        Route::resource('proposals', Freelancer\ProposalController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::get('contracts', Freelancer\ContractController::class)->name('contracts.index');
        Route::post('milestones/{milestone}/submission', Freelancer\MilestoneSubmissionController::class)->name('milestones.submit');
        Route::resource('portfolio', Freelancer\PortfolioItemController::class)->only(['index', 'store', 'update', 'destroy'])
            ->parameters(['portfolio' => 'portfolioItem']);
        Route::resource('fields', Freelancer\FieldController::class)->only(['index', 'store', 'destroy'])
            ->parameters(['fields' => 'freelancerField']);

        // Skill exams: start (pays the fee), the exam page, autosaved answers, leaving the page, submit.
        Route::post('exams/{assessment}/attempts', [Freelancer\ExamController::class, 'start'])->name('exams.start');
        Route::get('exam-attempts/{attempt}', [Freelancer\ExamController::class, 'show'])->name('attempts.show');
        Route::put('exam-attempts/{attempt}/answers', [Freelancer\ExamController::class, 'answers'])->name('attempts.answers');
        Route::post('exam-attempts/{attempt}/violations', [Freelancer\ExamController::class, 'violation'])->name('attempts.violation');
        Route::post('exam-attempts/{attempt}/submit', [Freelancer\ExamController::class, 'submit'])->name('attempts.submit');
    });

    // Employer: post projects, hire from proposals, fund and release milestones.
    Route::prefix('employer')->name('employer.')->middleware(Panel::Employer->middleware())->group(function () {
        Route::get('/', Employer\DashboardController::class)->name('dashboard');
        Route::resource('projects', Employer\ProjectController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::post('projects/{project}/publication', Employer\ProjectPublicationController::class)->name('projects.publish');
        Route::resource('proposals', Employer\ProposalController::class)->only(['index', 'update']);
        Route::post('proposals/{proposal}/contract', [Employer\ContractController::class, 'store'])->name('contracts.store');
        Route::get('contracts', [Employer\ContractController::class, 'index'])->name('contracts.index');
        Route::put('contracts/{contract}', [Employer\ContractController::class, 'update'])->name('contracts.update');
        Route::post('contracts/{contract}/milestones', [Employer\MilestoneController::class, 'store'])->name('milestones.store');
        Route::put('milestones/{milestone}', [Employer\MilestoneController::class, 'update'])->name('milestones.update');
        Route::post('contracts/{contract}/review', Employer\ReviewController::class)->name('reviews.store');
        Route::get('plans', [Employer\PlanController::class, 'index'])->name('plans.index');
        Route::post('plans/{plan}/subscription', [Employer\PlanController::class, 'store'])->name('plans.subscribe');
    });
});
