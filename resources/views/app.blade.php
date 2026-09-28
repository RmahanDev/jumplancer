<!DOCTYPE html>
<html lang="fa" dir="rtl">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#006b82">

        {{-- Root template of the Inertia (React) dashboards: RTL, Persian, light/dark. --}}
        @include('partials.theme-script')
        @viteReactRefresh
        @vite(['resources/sass/panel.scss', 'resources/js/inertia.jsx'])
        @inertiaHead
    </head>
    <body>
        @inertia
    </body>
</html>
