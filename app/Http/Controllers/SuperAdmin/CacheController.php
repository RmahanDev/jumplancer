<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;

class CacheController extends Controller
{
    /**
     * Clear the application, config, route, view and event caches ("php artisan optimize:clear").
     */
    public function __invoke(): RedirectResponse
    {
        Artisan::call('optimize:clear');

        $this->toast(__('All caches were cleared.'));

        return back();
    }
}
