// Entry for the Inertia (React) dashboards. Page components live in resources/js/Pages and
// every page is wrapped in the persistent PanelLayout (sidebar, topbar, palette, toasts), except BARE_PAGES.
import './app';
import { createInertiaApp, router } from '@inertiajs/react';
import { toast } from './Components/UI/Toaster';
import PanelLayout from './Layouts/PanelLayout';

const APP_NAME = 'جامپ‌لنسر';

// Pages that render without the panel chrome (the exam page has its own top bar).
const BARE_PAGES = ['Freelancer/Exams/Take'];

// One-time messages from the server: $this->toast('...') in a controller (Inertia flash data).
router.on('flash', (event) => {
    const message = event.detail.flash?.toast;

    if (message?.message) {
        toast.show(message.type ?? 'success', message.message);
    }
});

// The page was open so long that the session (and its CSRF token) expired.
router.on('httpException', (event) => {
    if (event.detail.response?.status === 419) {
        event.preventDefault();
        toast.warning('نشست کاری منقضی شده بود؛ صفحه دوباره بارگذاری می‌شود…');
        window.setTimeout(() => window.location.reload(), 1500);
    }
});

router.on('networkError', (event) => {
    event.preventDefault();
    toast.error('ارتباط با سرور برقرار نشد. اتصال اینترنت را بررسی کن و دوباره امتحان کن.');
});

createInertiaApp({
    title: (title) => (title ? `${title} | ${APP_NAME}` : APP_NAME),
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.jsx');
        const page = pages[`./Pages/${name}.jsx`];

        if (!page) {
            throw new Error(`Inertia page "${name}" was not found in resources/js/Pages.`);
        }

        return page();
    },
    layout: (name) => (BARE_PAGES.includes(name) ? null : PanelLayout),
    progress: {
        color: '#f7941d',
        delay: 200,
        showSpinner: false,
    },
});
