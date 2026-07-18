<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', config('app.name')) — {{ config('app.name') }}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/core@1.4.0/dist/css/tabler.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.45.0/dist/tabler-icons.min.css">
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
    <script>
        if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
            document.documentElement.setAttribute('data-bs-theme', 'dark');
        }
    </script>
</head>
<body class="d-flex flex-column">
<div class="page page-center">
    <div class="container @yield('container-class', 'container-tight') py-4">
        <div class="text-center mb-4">
            <a href="{{ route('dashboard') }}" class="navbar-brand navbar-brand-autodark">
                <i class="ti ti-search text-primary me-1"></i>{{ config('app.name') }}
            </a>
        </div>

        @if (session('flash'))
            <div class="alert alert-{{ session('flash')['type'] === 'error' ? 'danger' : 'success' }}" role="alert">
                {{ session('flash')['message'] }}
            </div>
        @endif

        @yield('content')

        <div class="text-center text-secondary mt-4">
            <ul class="list-inline list-inline-dots mb-0">
                <li class="list-inline-item"><a href="{{ route('privacy-policy') }}" class="link-secondary">{{ __('Gizlilik Politikası') }}</a></li>
                <li class="list-inline-item"><a href="{{ route('terms-of-service') }}" class="link-secondary">{{ __('Kullanım Koşulları') }}</a></li>
            </ul>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/@tabler/core@1.4.0/dist/js/tabler.min.js"></script>
</body>
</html>
