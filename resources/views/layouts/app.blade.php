<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ isset($title) ? $title.' - ' : '' }}{{ config('app.name', 'Jump Lancer') }}</title>

        @vite(['resources/sass/app.scss', 'resources/js/app.js'])
        @stack('head')
    </head>
    <body class="d-flex flex-column min-vh-100 bg-body-tertiary">
        <nav class="navbar navbar-expand-lg bg-body border-bottom sticky-top">
            <div class="container">
                <a class="navbar-brand fw-bold text-primary" href="{{ route('home') }}">
                    <i class="bi bi-rocket-takeoff-fill"></i> Jump Lancer
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="mainNav">
                    <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                        <li class="nav-item"><a class="nav-link" href="#projects">Find Work</a></li>
                        <li class="nav-item"><a class="nav-link" href="#freelancers">Find Talent</a></li>
                        <li class="nav-item"><a class="nav-link" href="#how-it-works">How It Works</a></li>
                        <li class="nav-item"><a class="nav-link" href="#mentoring">Mentoring</a></li>
                    </ul>
                    <div class="d-flex gap-2">
                        <a class="btn btn-outline-primary" href="#">Log in</a>
                        <a class="btn btn-primary" href="#">Sign up</a>
                    </div>
                </div>
            </div>
        </nav>

        <main class="flex-grow-1">
            @yield('content')
        </main>

        <footer class="bg-dark text-white-50 pt-5 pb-3 mt-5">
            <div class="container">
                <div class="row g-4">
                    <div class="col-md-4">
                        <h5 class="text-white"><i class="bi bi-rocket-takeoff-fill"></i> Jump Lancer</h5>
                        <p class="small">A freelance marketplace that helps new freelancers take their first jump — with mentoring along the way.</p>
                    </div>
                    <div class="col-6 col-md-2">
                        <h6 class="text-white">For Freelancers</h6>
                        <ul class="list-unstyled small">
                            <li><a class="link-light link-opacity-50 link-underline-opacity-0" href="#projects">Browse projects</a></li>
                            <li><a class="link-light link-opacity-50 link-underline-opacity-0" href="#mentoring">Mentoring</a></li>
                        </ul>
                    </div>
                    <div class="col-6 col-md-2">
                        <h6 class="text-white">For Employers</h6>
                        <ul class="list-unstyled small">
                            <li><a class="link-light link-opacity-50 link-underline-opacity-0" href="#">Post a project</a></li>
                            <li><a class="link-light link-opacity-50 link-underline-opacity-0" href="#freelancers">Find talent</a></li>
                        </ul>
                    </div>
                    <div class="col-md-4">
                        <h6 class="text-white">Support</h6>
                        <p class="small mb-0">Need help? Open a support ticket from your dashboard.</p>
                    </div>
                </div>
                <hr class="border-secondary">
                <p class="small text-center mb-0">&copy; {{ date('Y') }} Jump Lancer. All rights reserved.</p>
            </div>
        </footer>

        @stack('scripts')
    </body>
</html>
