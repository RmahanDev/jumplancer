@extends('layouts.auth', ['title' => 'ثبت‌نام'])

@section('content')
    <h1>به جامپ‌لنسر خوش آمدی</h1>
    <p class="jl-auth-lead">چند ثانیه تا شروع فاصله داری. اول بگو با چه هدفی آمده‌ای.</p>

    <form method="POST" action="{{ route('register.store') }}" class="d-grid gap-3" data-busy-form novalidate>
        @csrf

        <div class="jl-role-picker" role="radiogroup" aria-label="نوع حساب">
            <label>
                <input type="radio" name="role" value="freelancer" @checked(old('role', 'freelancer') === 'freelancer')>
                <i class="bi bi-laptop"></i>
                <strong>فریلنسر هستم</strong>
                <small>دنبال پروژه و منتور هستم</small>
            </label>
            <label>
                <input type="radio" name="role" value="employer" @checked(old('role') === 'employer')>
                <i class="bi bi-briefcase"></i>
                <strong>کارفرما هستم</strong>
                <small>می‌خواهم پروژه تعریف کنم</small>
            </label>
        </div>
        @error('role')
            <div class="invalid-feedback d-block mt-n2">{{ $message }}</div>
        @enderror

        <div>
            <label for="name" class="form-label jl-required">نام و نام خانوادگی</label>
            <input id="name" name="name" type="text" value="{{ old('name') }}" class="form-control @error('name') is-invalid @enderror" autocomplete="name" required autofocus>
            @error('name')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="row g-3">
            <div class="col-sm-6">
                <label for="username" class="form-label jl-required">نام کاربری</label>
                <input id="username" name="username" type="text" dir="ltr" value="{{ old('username') }}" class="form-control text-start @error('username') is-invalid @enderror" placeholder="sara.dev" autocomplete="username" autocapitalize="none" spellcheck="false" required>
                @error('username')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-sm-6">
                <label for="phone" class="form-label">موبایل <span class="text-muted fw-normal">(اختیاری)</span></label>
                <input id="phone" name="phone" type="tel" dir="ltr" inputmode="tel" value="{{ old('phone') }}" class="form-control text-start @error('phone') is-invalid @enderror" placeholder="09121234567" autocomplete="tel">
                @error('phone')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div>
            <label for="email" class="form-label jl-required">ایمیل</label>
            <input id="email" name="email" type="email" dir="ltr" value="{{ old('email') }}" class="form-control text-start @error('email') is-invalid @enderror" placeholder="you@example.com" autocomplete="email" required>
            @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="row g-3">
            <div class="col-sm-6">
                <label for="password" class="form-label jl-required">رمز عبور</label>
                <div class="jl-input has-action">
                    <input id="password" name="password" type="password" dir="ltr" class="form-control text-start @error('password') is-invalid @enderror" autocomplete="new-password" required>
                    <button type="button" class="jl-input-action" data-toggle-password="password" aria-label="نمایش رمز عبور"><i class="bi bi-eye"></i></button>
                </div>
                {{-- React island: live strength meter bound to the input above. --}}
                <div data-react-island="PasswordStrength" data-props='@json(['inputId' => 'password'])'></div>
                @error('password')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-sm-6">
                <label for="password_confirmation" class="form-label jl-required">تکرار رمز عبور</label>
                <input id="password_confirmation" name="password_confirmation" type="password" dir="ltr" class="form-control text-start" autocomplete="new-password" required>
            </div>
        </div>

        <button type="submit" class="btn btn-primary btn-lg w-100 mt-1">
            <span class="jl-busy-label">ساخت حساب و ورود</span>
            <i class="bi bi-stars"></i>
        </button>

        <p class="form-text text-center mb-0">با ثبت‌نام، قوانین جامپ‌لنسر درباره‌ی ممنوعیت تبادل اطلاعات تماس خارج از سایت را می‌پذیری.</p>
    </form>

    <p class="text-center text-muted-2 mt-4 mb-0">
        قبلاً ثبت‌نام کرده‌ای؟
        <a href="{{ route('login') }}" class="fw-semibold">وارد شو</a>
    </p>
@endsection
