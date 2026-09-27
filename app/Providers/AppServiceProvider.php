<?php

namespace App\Providers;

use App\Enums\AdminPermission;
use App\Models\Message;
use App\Models\PortfolioItem;
use App\Models\PortfolioMedia;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Short, stable names in *_type columns (violations, roles, tokens) instead of PHP class names.
        Relation::enforceMorphMap([
            'user' => User::class,
            'project' => Project::class,
            'proposal' => Proposal::class,
            'message' => Message::class,
            'portfolio_item' => PortfolioItem::class,
            'portfolio_media' => PortfolioMedia::class,
        ]);

        // Outside production, fail loudly on lazy loading (N+1), unknown attributes and non-fillable writes.
        Model::shouldBeStrict(! $this->app->isProduction());

        // Inertia props: a single resource is sent as-is; paginated collections keep data/links/meta.
        JsonResource::withoutWrapping();

        // Super admins hold every staff permission. Policy abilities (update, delete, impersonate...)
        // still run their own rules, so the root account and super admins stay protected.
        Gate::before(function (User $user, string $ability): ?bool {
            return in_array($ability, AdminPermission::values(), true) && $user->isSuperAdmin() ? true : null;
        });

        Password::defaults(fn (): Password => Password::min(8)->letters()->numbers());

        // Failed logins are limited per identifier + IP inside LoginRequest; sign-ups per IP here.
        RateLimiter::for('register', fn (Request $request): Limit => Limit::perMinute(5)->by($request->ip()));
    }
}
