<?php

namespace App\Providers;

use App\Models\Message;
use App\Models\PortfolioItem;
use App\Models\PortfolioMedia;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

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
    }
}
