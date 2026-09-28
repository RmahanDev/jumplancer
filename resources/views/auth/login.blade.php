@extends('layouts.auth', ['title' => 'ورود'])

@section('content')
    <h1>خوش برگشتی!</h1>
    <p class="jl-auth-lead">برای ورود به پنل، نام کاربری، ایمیل یا شماره موبایلت را وارد کن.</p>

    @if (session('status'))
        <div class="alert alert-info d-flex gap-2 align-items-center" role="status">
            <i class="bi bi-info-circle"></i> {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('login.store') }}" class="d-grid gap-3" data-busy-form novalidate>
        @csrf

        <div>
            <label for="identifier" class="form-label">نام کاربری، ایمیل یا موبایل</label>
            <div class="jl-input">
                <i class="bi bi-person jl-input-icon"></i>
                <input
                    id="identifier"
                    name="identifier"
                    type="text"
                    dir="auto"
                    value="{{ old('identifier') }}"
                    class="form-control @error('identifier') is-invalid @enderror"
                    placeholder="mahan / 09121234567"
                    autocomplete="username"
                    autocapitalize="none"
                    spellcheck="false"
                    required
                    autofocus
                >
            </div>
            @error('identifier')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>

        <div>
            <div class="d-flex justify-content-between align-items-center">
                <label for="password" class="form-label">رمز عبور</label>
            </div>
            <div class="jl-input has-action">
                <i class="bi bi-lock jl-input-icon"></i>
                <input
                    id="password"
                    name="password"
                    type="password"
                    dir="ltr"
                    class="form-control text-start @error('password') is-invalid @enderror"
                    autocomplete="current-password"
                    required
                >
                <button type="button" class="jl-input-action" data-toggle-password="password" aria-label="نمایش رمز عبور" data-tip="نمایش / پنهان">
                    <i class="bi bi-eye"></i>
                </button>
            </div>
            @error('password')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="remember" value="1" id="remember" @checked(old('remember'))>
            <label class="form-check-label" for="remember">مرا به خاطر بسپار</label>
        </div>

        <button type="submit" class="btn btn-primary btn-lg w-100">
            <span class="jl-busy-label">ورود به پنل</span>
            <i class="bi bi-box-arrow-in-left"></i>
        </button>
    </form>

    <p class="text-center text-muted-2 mt-4 mb-0">
        هنوز حساب نداری؟
        <a href="{{ route('register') }}" class="fw-semibold">رایگان ثبت‌نام کن</a>
    </p>

    @if ($demoAccounts !== [])
        <div class="mt-4 pt-3 border-top">
            <div class="jl-divider-text mb-2">حساب‌های آزمایشی (فقط محیط توسعه)</div>
            <div class="jl-demo-accounts justify-content-center">
                @foreach ($demoAccounts as $account)
                    <button type="button" data-demo-account data-username="{{ $account['username'] }}" data-password="{{ $account['password'] }}">
                        {{ $account['label'] }}
                    </button>
                @endforeach
            </div>
        </div>
    @endif
@endsection
