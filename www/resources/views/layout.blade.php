<!DOCTYPE html>
<html lang="tr" data-bs-theme="light">
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
<body>
<div class="page">
    <header class="navbar navbar-expand-md d-print-none">
        <div class="container-xl">
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbar-menu" aria-label="Menu">
                <span class="navbar-toggler-icon"></span>
            </button>
            <h1 class="navbar-brand navbar-brand-autodark d-none-navbar-horizontal pe-0 pe-md-3">
                <a href="{{ route('dashboard') }}">
                    <i class="ti ti-search text-primary me-1"></i>{{ config('app.name') }}
                </a>
            </h1>
            @auth
                <div class="navbar-nav flex-row order-md-last">
                    <div class="nav-item dropdown">
                        <a href="#" class="nav-link d-flex lh-1 text-reset p-0" data-bs-toggle="dropdown" aria-label="Kullanıcı menüsü">
                            <span class="avatar avatar-sm bg-primary-lt">{{ strtoupper(substr(auth()->user()->name ?: auth()->user()->email, 0, 1)) }}</span>
                            <div class="d-none d-xl-block ps-2">
                                <div>{{ auth()->user()->name }}</div>
                                <div class="mt-1 small text-secondary">{{ auth()->user()->email }}</div>
                            </div>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
                            @if (auth()->user()->is_admin)
                                <a href="{{ route('admin.users.index') }}" class="dropdown-item">
                                    <i class="ti ti-users-group icon me-1"></i>Kullanıcılar
                                </a>
                                <div class="dropdown-divider"></div>
                            @endif
                            <form method="post" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="dropdown-item">
                                    <i class="ti ti-logout icon me-1"></i>Çıkış yap
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @endauth
        </div>
    </header>

    @auth
        <div class="navbar-expand-md">
            <div class="collapse navbar-collapse" id="navbar-menu">
                <div class="navbar navbar-light">
                    <div class="container-xl">
                        <ul class="navbar-nav">
                            <li class="nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                                <a class="nav-link" href="{{ route('dashboard') }}">
                                    <span class="nav-link-icon"><i class="ti ti-world"></i></span>
                                    <span class="nav-link-title">Domainler</span>
                                </a>
                            </li>
                            @if (auth()->user()->is_admin)
                                <li class="nav-item {{ request()->routeIs('admin.*') ? 'active' : '' }}">
                                    <a class="nav-link" href="{{ route('admin.users.index') }}">
                                        <span class="nav-link-icon"><i class="ti ti-users-group"></i></span>
                                        <span class="nav-link-title">Kullanıcılar</span>
                                    </a>
                                </li>
                            @endif
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    @endauth

    <div class="page-wrapper">
        @hasSection('page-title')
            <div class="page-header d-print-none">
                <div class="container-xl">
                    <div class="row g-2 align-items-center">
                        <div class="col">
                            @hasSection('page-pretitle')
                                <div class="page-pretitle">@yield('page-pretitle')</div>
                            @endif
                            <h2 class="page-title">@yield('page-title')</h2>
                        </div>
                        @hasSection('page-actions')
                            <div class="col-auto ms-auto d-print-none">
                                <div class="btn-list">@yield('page-actions')</div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        <div class="page-body">
            <div class="container-xl">
                @auth
                    @if (!auth()->user()->hasGoogleOfflineAccess())
                        <div class="alert alert-info alert-dismissible" role="alert">
                            Search Console ve GA4 verilerini görebilmek için Google hesabınızı yeniden bağlamanız gerekiyor.
                            <a href="{{ route('google.redirect') }}" class="alert-link">Google hesabını bağla</a>
                            <a class="btn-close" data-bs-dismiss="alert" aria-label="close"></a>
                        </div>
                    @endif
                @endauth
                @if (session('flash'))
                    <div class="alert alert-{{ session('flash')['type'] === 'error' ? 'danger' : 'success' }} alert-dismissible" role="alert">
                        {{ session('flash')['message'] }}
                        <a class="btn-close" data-bs-dismiss="alert" aria-label="close"></a>
                    </div>
                @endif
                @if ($errors->any())
                    <div class="alert alert-danger alert-dismissible" role="alert">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                        <a class="btn-close" data-bs-dismiss="alert" aria-label="close"></a>
                    </div>
                @endif

                @yield('content')
            </div>
        </div>

        <footer class="footer footer-transparent d-print-none">
            <div class="container-xl">
                <div class="row text-center align-items-center flex-row-reverse">
                    <div class="col-lg-auto ms-lg-auto">
                        <ul class="list-inline list-inline-dots mb-0">
                            <li class="list-inline-item"><a href="{{ route('privacy-policy') }}" class="link-secondary">Gizlilik Politikası</a></li>
                            <li class="list-inline-item"><a href="{{ route('terms-of-service') }}" class="link-secondary">Kullanım Koşulları</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </footer>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/@tabler/core@1.4.0/dist/js/tabler.min.js"></script>
</body>
</html>
