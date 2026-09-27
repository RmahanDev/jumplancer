<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\AdminPermission;
use App\Enums\Panel;
use App\Enums\RoleName;
use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * Every person on the platform. Roles (super_admin, admin, employer, freelancer, mentor) come from spatie/laravel-permission.
 */
#[Fillable(['name', 'username', 'email', 'phone', 'password', 'avatar_path', 'bio'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'suspended_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
        ];
    }

    /**
     * @return HasOne<FreelancerProfile, $this>
     */
    public function freelancerProfile(): HasOne
    {
        return $this->hasOne(FreelancerProfile::class);
    }

    /**
     * @return HasOne<EmployerProfile, $this>
     */
    public function employerProfile(): HasOne
    {
        return $this->hasOne(EmployerProfile::class);
    }

    /**
     * @return HasOne<MentorProfile, $this>
     */
    public function mentorProfile(): HasOne
    {
        return $this->hasOne(MentorProfile::class);
    }

    /**
     * @return HasOne<Wallet, $this>
     */
    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class);
    }

    /**
     * Ledger rows of this user's wallet.
     *
     * @return HasManyThrough<Transaction, Wallet, $this>
     */
    public function transactions(): HasManyThrough
    {
        return $this->hasManyThrough(Transaction::class, Wallet::class);
    }

    /**
     * Skills of a freelancer (skill_user pivot).
     *
     * @return BelongsToMany<Skill, $this>
     */
    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class)->withPivot('level', 'is_verified');
    }

    /**
     * @return HasMany<FreelancerField, $this>
     */
    public function freelancerFields(): HasMany
    {
        return $this->hasMany(FreelancerField::class, 'freelancer_id');
    }

    /**
     * @return HasMany<PortfolioItem, $this>
     */
    public function portfolioItems(): HasMany
    {
        return $this->hasMany(PortfolioItem::class, 'freelancer_id');
    }

    /**
     * Projects this user posted as an employer.
     *
     * @return HasMany<Project, $this>
     */
    public function postedProjects(): HasMany
    {
        return $this->hasMany(Project::class, 'employer_id');
    }

    /**
     * Projects this user supervises as a mentor.
     *
     * @return HasMany<Project, $this>
     */
    public function mentoredProjects(): HasMany
    {
        return $this->hasMany(Project::class, 'mentor_id');
    }

    /**
     * @return HasMany<Proposal, $this>
     */
    public function proposals(): HasMany
    {
        return $this->hasMany(Proposal::class, 'freelancer_id');
    }

    /**
     * Contracts where this user is the employer.
     *
     * @return HasMany<Contract, $this>
     */
    public function employerContracts(): HasMany
    {
        return $this->hasMany(Contract::class, 'employer_id');
    }

    /**
     * Contracts where this user is the freelancer.
     *
     * @return HasMany<Contract, $this>
     */
    public function freelancerContracts(): HasMany
    {
        return $this->hasMany(Contract::class, 'freelancer_id');
    }

    /**
     * Contracts this user supports as a mentor.
     *
     * @return HasMany<Contract, $this>
     */
    public function mentoredContracts(): HasMany
    {
        return $this->hasMany(Contract::class, 'mentor_id');
    }

    /**
     * Plan subscriptions bought by this user as an employer.
     *
     * @return HasMany<EmployerSubscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(EmployerSubscription::class, 'employer_id');
    }

    /**
     * @return HasMany<Review, $this>
     */
    public function reviewsWritten(): HasMany
    {
        return $this->hasMany(Review::class, 'reviewer_id');
    }

    /**
     * @return HasMany<Review, $this>
     */
    public function reviewsReceived(): HasMany
    {
        return $this->hasMany(Review::class, 'reviewee_id');
    }

    /**
     * @return HasMany<Dispute, $this>
     */
    public function disputesRaised(): HasMany
    {
        return $this->hasMany(Dispute::class, 'raised_by');
    }

    /**
     * Mentoring tickets this user opened.
     *
     * @return HasMany<Ticket, $this>
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'requester_id');
    }

    /**
     * Tickets assigned to this user as a mentor.
     *
     * @return HasMany<Ticket, $this>
     */
    public function assignedTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'assigned_mentor_id');
    }

    /**
     * @return HasMany<MentorshipProgram, $this>
     */
    public function mentorshipsAsMentor(): HasMany
    {
        return $this->hasMany(MentorshipProgram::class, 'mentor_id');
    }

    /**
     * @return HasMany<MentorshipProgram, $this>
     */
    public function mentorshipsAsMentee(): HasMany
    {
        return $this->hasMany(MentorshipProgram::class, 'mentee_id');
    }

    /**
     * @return HasMany<AssessmentAttempt, $this>
     */
    public function assessmentAttempts(): HasMany
    {
        return $this->hasMany(AssessmentAttempt::class);
    }

    /**
     * @return HasMany<LearningContent, $this>
     */
    public function learningContents(): HasMany
    {
        return $this->hasMany(LearningContent::class, 'author_id');
    }

    /**
     * @return BelongsToMany<Badge, $this>
     */
    public function badges(): BelongsToMany
    {
        return $this->belongsToMany(Badge::class)->withPivot('awarded_at');
    }

    /**
     * @return BelongsToMany<Conversation, $this>
     */
    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(Conversation::class, 'conversation_participants')->withPivot('joined_at', 'last_read_at');
    }

    /**
     * @return HasMany<Message, $this>
     */
    public function sentMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    /**
     * @return HasMany<Violation, $this>
     */
    public function violations(): HasMany
    {
        return $this->hasMany(Violation::class);
    }

    /**
     * Whether the account is blocked from using the platform (e.g. after sharing contact info in chat).
     */
    public function isSuspended(): bool
    {
        return in_array($this->status, [UserStatus::Suspended, UserStatus::Banned], true);
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(RoleName::SuperAdmin);
    }

    /**
     * Super admins and admins, i.e. accounts managed from the "admins" page.
     */
    public function isStaff(): bool
    {
        return $this->hasAnyRole([RoleName::SuperAdmin, RoleName::Admin]);
    }

    /**
     * The super admin created from .env (config/jumplancer.php). Nobody can demote, suspend or delete it.
     */
    public function isRootSuperAdmin(): bool
    {
        return $this->username !== null
            && $this->username === config('jumplancer.super_admin.username')
            && $this->isSuperAdmin();
    }

    /**
     * Staff permissions this user holds. Super admins implicitly hold all of them.
     *
     * @return list<string>
     */
    public function staffPermissions(): array
    {
        if ($this->isSuperAdmin()) {
            return AdminPermission::values();
        }

        $held = $this->getAllPermissions()->pluck('name')->all();

        return array_values(array_intersect(AdminPermission::values(), $held));
    }

    /**
     * Dashboards this user can open, in landing priority order.
     *
     * @return list<Panel>
     */
    public function panels(): array
    {
        $roleNames = $this->getRoleNames()->all();

        return array_values(array_filter(
            Panel::cases(),
            fn (Panel $panel): bool => array_intersect(array_column($panel->roles(), 'value'), $roleNames) !== [],
        ));
    }

    public function homePanel(): ?Panel
    {
        return $this->panels()[0] ?? null;
    }

    /**
     * The user's wallet with every column loaded, created on first use.
     */
    public function ensureWallet(): Wallet
    {
        $wallet = $this->wallet()->firstOrCreate();

        return $wallet->wasRecentlyCreated ? $wallet->refresh() : $wallet;
    }

    /**
     * Create the profile rows and wallet that the user's marketplace roles need.
     */
    public function ensureRoleProfiles(): void
    {
        if ($this->hasRole(RoleName::Freelancer)) {
            $this->freelancerProfile()->firstOrCreate();
        }

        if ($this->hasRole(RoleName::Employer)) {
            $this->employerProfile()->firstOrCreate();
        }

        if ($this->hasRole(RoleName::Mentor)) {
            $this->mentorProfile()->firstOrCreate();
        }

        $this->wallet()->firstOrCreate();
    }
}
