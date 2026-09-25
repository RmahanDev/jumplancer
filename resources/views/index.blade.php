@extends('layouts.app')

@section('content')
    {{-- Hero --}}
    <section class="bg-primary text-white py-5">
        <div class="container py-lg-4">
            <div class="row align-items-center g-4">
                <div class="col-lg-7">
                    <h1 class="display-5 fw-bold">Take your first jump into freelancing</h1>
                    <p class="lead text-white-50">Find real projects, get mentoring when you need it, and grow your career — or hire skilled freelancers for your next idea.</p>

                    <form action="#" method="GET" class="bg-white rounded-3 p-2 shadow-sm" role="search">
                        <div class="input-group input-group-lg">
                            <span class="input-group-text bg-white border-0"><i class="bi bi-search text-muted"></i></span>
                            <input type="search" name="q" class="form-control border-0" placeholder="Search projects, e.g. Laravel, logo design…" aria-label="Search projects">
                            <button class="btn btn-primary rounded-2" type="submit">Search</button>
                        </div>
                    </form>

                    <div class="mt-3 small">
                        <span class="text-white-50">Popular:</span>
                        @foreach (['Laravel', 'UI/UX', 'Translation', 'SEO'] as $popularSearch)
                            <a href="#" class="badge rounded-pill text-bg-light text-decoration-none ms-1">{{ $popularSearch }}</a>
                        @endforeach
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="row g-3 text-center">
                        <div class="col-6">
                            <div class="bg-white bg-opacity-10 rounded-3 p-3">
                                <div class="fs-2 fw-bold">3</div>
                                <div class="small text-white-50">Free projects for new employers</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="bg-white bg-opacity-10 rounded-3 p-3">
                                <div class="fs-2 fw-bold">20%</div>
                                <div class="small text-white-50">Simple, flat platform fee</div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="bg-white bg-opacity-10 rounded-3 p-3">
                                <i class="bi bi-people-fill fs-3"></i>
                                <div class="small text-white-50">Mentors ready to help you finish your first projects</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Categories --}}
    <section class="py-5">
        <div class="container">
            <div class="d-flex justify-content-between align-items-end mb-4">
                <div>
                    <h2 class="h3 fw-bold mb-1">Browse by category</h2>
                    <p class="text-muted mb-0">Find the work that matches your skills.</p>
                </div>
            </div>
            <div class="row g-4">
                @foreach ($categories as $category)
                    <div class="col-sm-6 col-lg-3">
                        <a href="#" class="card h-100 text-decoration-none border-0 shadow-sm">
                            <div class="card-body">
                                <div class="rounded-3 bg-primary-subtle text-primary d-inline-flex p-3 mb-3">
                                    <i class="bi {{ $category['icon'] }} fs-3"></i>
                                </div>
                                <h3 class="h6 fw-bold text-body">{{ $category['name'] }}</h3>
                                <p class="small text-muted mb-0">{{ implode(' · ', $category['subcategories']) }}</p>
                            </div>
                        </a>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Latest projects --}}
    <section id="projects" class="py-5 bg-body">
        <div class="container">
            <div class="d-flex justify-content-between align-items-end mb-4">
                <div>
                    <h2 class="h3 fw-bold mb-1">Latest projects</h2>
                    <p class="text-muted mb-0">Fresh work posted by employers.</p>
                </div>
                <a href="#" class="btn btn-outline-primary btn-sm">View all <i class="bi bi-arrow-right"></i></a>
            </div>
            <div class="row g-4">
                @foreach ($latestProjects as $project)
                    <div class="col-md-6">
                        <div class="card h-100 shadow-sm">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <span class="badge text-bg-primary-subtle text-primary-emphasis">{{ $project['category'] }}</span>
                                    <small class="text-muted"><i class="bi bi-clock"></i> {{ $project['posted'] }}</small>
                                </div>
                                <h3 class="h5 card-title">
                                    <a href="#" class="stretched-link text-body text-decoration-none">{{ $project['title'] }}</a>
                                </h3>
                                <div class="mb-3">
                                    @foreach ($project['skills'] as $skill)
                                        <span class="badge rounded-pill text-bg-light border">{{ $skill }}</span>
                                    @endforeach
                                </div>
                                <div class="d-flex justify-content-between small">
                                    <span class="fw-semibold text-success"><i class="bi bi-cash-coin"></i> {{ $project['budget'] }}</span>
                                    <span class="text-muted"><i class="bi bi-chat-left-text"></i> {{ $project['proposals'] }} proposals</span>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- How it works --}}
    <section id="how-it-works" class="py-5">
        <div class="container">
            <h2 class="h3 fw-bold text-center mb-5">How Jump Lancer works</h2>
            <div class="row g-4 text-center">
                @foreach ([
                    ['icon' => 'bi-person-plus', 'title' => 'Create your profile', 'text' => 'Sign up as a freelancer or employer and add your skills or company details.'],
                    ['icon' => 'bi-clipboard-check', 'title' => 'Post or find a project', 'text' => 'Employers post their first 3 projects free. Freelancers send proposals.'],
                    ['icon' => 'bi-file-earmark-text', 'title' => 'Agree on a contract', 'text' => 'Set milestones and the budget. Payment is protected until work is delivered.'],
                    ['icon' => 'bi-trophy', 'title' => 'Deliver and get reviewed', 'text' => 'Finish the job, get paid, and build your reputation with reviews.'],
                ] as $step)
                    <div class="col-sm-6 col-lg-3">
                        <div class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center mb-3" style="width: 64px; height: 64px;">
                            <i class="bi {{ $step['icon'] }} fs-3"></i>
                        </div>
                        <h3 class="h6 fw-bold">{{ $loop->iteration }}. {{ $step['title'] }}</h3>
                        <p class="small text-muted">{{ $step['text'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Top freelancers --}}
    <section id="freelancers" class="py-5 bg-body">
        <div class="container">
            <h2 class="h3 fw-bold mb-4">Top freelancers</h2>
            <div class="row g-4">
                @foreach ($topFreelancers as $freelancer)
                    <div class="col-md-4">
                        <div class="card h-100 shadow-sm text-center">
                            <div class="card-body">
                                <div class="rounded-circle bg-secondary-subtle text-secondary-emphasis fw-bold d-inline-flex align-items-center justify-content-center mb-3 fs-4" style="width: 72px; height: 72px;">
                                    {{ collect(explode(' ', $freelancer['name']))->map(fn (string $namePart) => mb_substr($namePart, 0, 1))->implode('') }}
                                </div>
                                <h3 class="h6 fw-bold mb-0">{{ $freelancer['name'] }}</h3>
                                <p class="small text-muted">{{ $freelancer['title'] }}</p>
                                <div class="d-flex justify-content-center gap-3 small">
                                    <span><i class="bi bi-star-fill text-warning"></i> {{ number_format($freelancer['rating'], 1) }}</span>
                                    <span class="text-muted">{{ $freelancer['completedProjects'] }} projects</span>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Mentoring --}}
    <section id="mentoring" class="py-5">
        <div class="container">
            <div class="card border-0 bg-primary-subtle">
                <div class="card-body p-4 p-lg-5">
                    <div class="row align-items-center g-4">
                        <div class="col-lg-8">
                            <h2 class="h3 fw-bold">New to freelancing? Get a mentor.</h2>
                            <p class="mb-0 text-muted">Stuck on a technical problem or need motivation? Open a support ticket and a mentor will help you — technical help via tickets, motivational support by ticket or phone. Mentored contracts have a 25% platform fee.</p>
                        </div>
                        <div class="col-lg-4 text-lg-end">
                            <a href="#" class="btn btn-primary btn-lg">Start as a freelancer</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
