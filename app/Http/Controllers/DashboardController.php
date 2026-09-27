<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Send the user to the first dashboard their roles open.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $panel = $request->user()->homePanel();

        abort_if($panel === null, 403, __('Your account has no dashboard yet.'));

        return redirect()->route($panel->dashboardRoute());
    }
}
