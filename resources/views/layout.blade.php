<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'SEO / AI Overview Checker')</title>
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
</head>
<body>
<header class="topbar">
    <a href="{{ route('dashboard') }}" class="brand">SEO / AI Overview Checker</a>
    @auth
        <div class="topbar-user">
            <span>{{ auth()->user()->name }} ({{ auth()->user()->email }})</span>
            <form method="post" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="link-button">Cikis</button>
            </form>
        </div>
    @endauth
</header>
<main class="container">
    @if (session('flash'))
        <div class="flash flash-{{ session('flash')['type'] }}">{{ session('flash')['message'] }}</div>
    @endif
    @if ($errors->any())
        <div class="flash flash-error">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    @yield('content')
</main>
</body>
</html>
