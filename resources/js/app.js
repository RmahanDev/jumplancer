// Entry for Blade pages: Bootstrap 5 JS (dropdowns, modals, tooltips, ...)
import * as bootstrap from 'bootstrap';

window.bootstrap = bootstrap;

// Small progressive enhancements for server-rendered (Blade) forms such as login and sign-up.
// Everything works without them; they only make the forms nicer to use.
document.addEventListener('click', (event) => {
    const toggle = event.target.closest('[data-toggle-password]');

    if (toggle) {
        const input = document.getElementById(toggle.dataset.togglePassword);
        const icon = toggle.querySelector('.bi');

        if (input) {
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            icon?.classList.toggle('bi-eye', !show);
            icon?.classList.toggle('bi-eye-slash', show);
            toggle.setAttribute('aria-label', show ? 'پنهان کردن رمز عبور' : 'نمایش رمز عبور');
            input.focus();
        }

        return;
    }

    const demo = event.target.closest('[data-demo-account]');

    if (demo) {
        const form = demo.closest('.jl-auth-card')?.querySelector('form');
        form.querySelector('[name="identifier"]').value = demo.dataset.username;
        form.querySelector('[name="password"]').value = demo.dataset.password;
        form.requestSubmit();
    }
});

// Disable the submit button and show a spinner while a Blade form is being sent.
document.addEventListener('submit', (event) => {
    const form = event.target;

    if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-busy-form')) {
        return;
    }

    const button = form.querySelector('[type="submit"]');

    if (button && !button.disabled) {
        button.disabled = true;
        button.insertAdjacentHTML('afterbegin', '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span>');
        button.querySelector('.bi')?.classList.add('d-none');
    }
});

// Persian and Arabic digits typed in number-like Blade inputs become Latin digits.
document.addEventListener('input', (event) => {
    const input = event.target;

    if (!(input instanceof HTMLInputElement) || !['tel', 'number'].includes(input.type) && input.inputMode !== 'numeric') {
        return;
    }

    const latin = input.value.replace(/[۰-۹]/g, (digit) => String(digit.charCodeAt(0) - 0x06f0)).replace(/[٠-٩]/g, (digit) => String(digit.charCodeAt(0) - 0x0660));

    if (latin !== input.value) {
        input.value = latin;
    }
});
