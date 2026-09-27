<!DOCTYPE html>
<html lang="fa" dir="rtl">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#006b82">

        <title>{{ isset($title) ? $title.' | ' : '' }}جامپ‌لنسر</title>

        @include('partials.theme-script')
        @vite(['resources/sass/panel.scss', 'resources/js/app.js', 'resources/js/islands.jsx'])
    </head>
    <body>
        <div class="jl-auth">
            <main class="jl-auth-main">
                <div class="jl-auth-top">
                    <a href="{{ route('home') }}" class="text-decoration-none" aria-label="صفحه‌ی اصلی جامپ‌لنسر">
                        <x-logo :size="38" />
                    </a>
                    {{-- React island: the same component the dashboards use in their topbar. --}}
                    <div data-react-island="ThemeToggle"></div>
                </div>

                <div class="jl-auth-card">
                    @yield('content')
                </div>
            </main>

            <aside class="jl-auth-side" aria-hidden="true">
                <div>
                    <span class="badge rounded-pill text-bg-accent mb-3">سکوی پرش تازه‌کارها</span>
                    <h2>اولین <mark>پرش حرفه‌ای</mark> خودت را با خیال راحت بزن</h2>
                    <p class="mt-3">پروژه‌های واقعی و ساده برای شروع، منتورهایی که کنارت هستند و پرداخت امن امانی؛ همه در یک پنل.</p>

                    <ul class="jl-auth-points">
                        <li>
                            <i class="bi bi-rocket-takeoff"></i>
                            <div>
                                <strong>پروژه‌های مناسب شروع</strong>
                                <span>کارفرماهایی که به تازه‌کارها فرصت می‌دهند.</span>
                            </div>
                        </li>
                        <li>
                            <i class="bi bi-people"></i>
                            <div>
                                <strong>منتورینگ فنی و انگیزشی</strong>
                                <span>دو جلسه‌ی اول برای تازه‌کارها رایگان است.</span>
                            </div>
                        </li>
                        <li>
                            <i class="bi bi-shield-check"></i>
                            <div>
                                <strong>پرداخت امانی</strong>
                                <span>پول هر مرحله تا تحویل کار نزد پلتفرم می‌ماند.</span>
                            </div>
                        </li>
                    </ul>
                </div>
                <div class="jl-auth-foot">© {{ date('Y') }} جامپ‌لنسر — ساخته‌شده با Laravel، Bootstrap و React</div>
            </aside>
        </div>
    </body>
</html>
