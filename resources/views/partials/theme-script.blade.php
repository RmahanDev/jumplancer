{{-- Applies the saved theme before the first paint so dark mode never flashes white. --}}
<script>
    (function () {
        try {
            var preference = localStorage.getItem('jl-theme') || 'auto';
            var dark = preference === 'dark' || (preference === 'auto' && window.matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.setAttribute('data-bs-theme', dark ? 'dark' : 'light');
            document.documentElement.setAttribute('data-theme-preference', preference);
        } catch (error) {
            document.documentElement.setAttribute('data-bs-theme', 'light');
        }
    })();
</script>
