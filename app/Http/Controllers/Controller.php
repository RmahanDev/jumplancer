<?php

namespace App\Http\Controllers;

use Inertia\Inertia;

abstract class Controller
{
    /**
     * Queue a one-time toast for the next Inertia page (Inertia flash data, not kept in browser history).
     *
     * @param  'success'|'danger'|'warning'|'info'  $type
     */
    protected function toast(string $message, string $type = 'success'): void
    {
        Inertia::flash('toast', ['type' => $type, 'message' => $message]);
    }
}
