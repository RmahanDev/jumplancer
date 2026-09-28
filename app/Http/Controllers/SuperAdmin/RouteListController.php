<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The application's routes, like "php artisan route:list", for developers in the browser.
 */
class RouteListController extends Controller
{
    public function __invoke(Router $router): Response
    {
        $routes = collect($router->getRoutes()->getRoutes())
            ->map(fn (Route $route): array => [
                'methods' => array_values(array_diff($route->methods(), ['HEAD'])),
                'uri' => '/'.ltrim($route->uri(), '/'),
                'name' => $route->getName(),
                'action' => Str::after($route->getActionName(), 'App\\Http\\Controllers\\'),
                'middleware' => array_values(array_map(
                    fn (string $middleware): string => class_basename(Str::before($middleware, ':')).(Str::contains($middleware, ':') ? ':'.Str::after($middleware, ':') : ''),
                    $route->gatherMiddleware(),
                )),
                'vendor' => ! Str::startsWith($route->getActionName(), 'App\\'),
            ])
            ->sortBy('uri')
            ->values();

        return Inertia::render('SuperAdmin/Routes', [
            'routes' => $routes,
        ]);
    }
}
