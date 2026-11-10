<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', config('app.name')) — {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/core@1.4.0/dist/css/tabler.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.45.0/dist/tabler-icons.min.css">
    <link rel="stylesheet" href="{{ asset('css/style.css') }}?v=4">
    @stack('head')
    <script>
        if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
            document.documentElement.setAttribute('data-bs-theme', 'dark');
        }
    </script>
</head>
<body>
<div class="page">
    @if (auth()->check() && !View::hasSection('public-shell'))
        @php
            $navConnectionsIncomplete = !auth()->user()->hasGoogleOfflineAccess() || !auth()->user()->hasBingOfflineAccess();
        @endphp
        <aside class="navbar navbar-vertical navbar-expand-lg app-sidebar d-print-none">
            <div class="container-fluid">
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#sidebar-menu" aria-controls="sidebar-menu" aria-expanded="false" aria-label="{{ __('Menü') }}">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <h1 class="navbar-brand navbar-brand-autodark">
                    <a href="{{ route('dashboard') }}" class="app-brand">
                        <span class="app-brand-mark" aria-hidden="true"><span></span><span></span><span></span><span></span></span>
                        <span>{{ config('app.name') }}</span>
                    </a>
                </h1>
                <div class="collapse navbar-collapse" id="sidebar-menu">
                    <ul class="navbar-nav pt-lg-3">
                        <li class="nav-item {{ request()->routeIs('dashboard', 'domains.*', 'keywords.*') ? 'active' : '' }}">
                            <a class="nav-link" href="{{ route('dashboard') }}">
                                <span class="nav-link-icon"><i class="ti ti-world"></i></span>
                                <span class="nav-link-title">{{ __('Domainler') }}</span>
                            </a>
                        </li>
                        <li class="nav-item {{ request()->routeIs('connections') ? 'active' : '' }}">
                            <a class="nav-link" href="{{ route('connections') }}">
                                <span class="nav-link-icon"><i class="ti ti-plug-connected"></i></span>
                                <span class="nav-link-title">{{ __('Bağlantılar') }}</span>
                                @if ($navConnectionsIncomplete)
                                    <span class="badge badge-dot bg-warning ms-auto" title="{{ __('Eksik bağlantı var') }}"></span>
                                @endif
                            </a>
                        </li>
                        <li class="nav-item {{ request()->routeIs('settings') ? 'active' : '' }}">
                            <a class="nav-link" href="{{ route('settings') }}">
                                <span class="nav-link-icon"><i class="ti ti-settings"></i></span>
                                <span class="nav-link-title">{{ __('Ayarlar') }}</span>
                            </a>
                        </li>
                        @if (auth()->user()->is_admin)
                            <li class="nav-item {{ request()->routeIs('admin.*') ? 'active' : '' }}">
                                <a class="nav-link" href="{{ route('admin.users.index') }}">
                                    <span class="nav-link-icon"><i class="ti ti-users-group"></i></span>
                                    <span class="nav-link-title">{{ __('Kullanıcılar') }}</span>
                                </a>
                            </li>
                        @endif
                    </ul>

                    <div class="app-sidebar-footer mt-auto">
                        <div class="d-flex align-items-center gap-2 px-3 py-3">
                            <span class="avatar avatar-sm bg-primary-lt">{{ strtoupper(mb_substr(auth()->user()->name ?: auth()->user()->email, 0, 1)) }}</span>
                            <div class="flex-fill text-truncate" style="min-width: 0">
                                <div class="text-truncate fw-medium">{{ auth()->user()->name }}</div>
                                <div class="small text-secondary text-truncate">{{ auth()->user()->email }}</div>
                            </div>
                            <form method="post" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="btn btn-ghost-secondary btn-icon" aria-label="{{ __('Çıkış yap') }}" title="{{ __('Çıkış yap') }}">
                                    <i class="ti ti-logout"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </aside>
    @else
        <header class="navbar navbar-expand-md d-print-none">
            <div class="container-xl">
                <h1 class="navbar-brand navbar-brand-autodark pe-0 pe-md-3">
                    <a href="{{ route('dashboard') }}" class="app-brand">
                        <span class="app-brand-mark" aria-hidden="true"><span></span><span></span><span></span><span></span></span>
                        <span>{{ config('app.name') }}</span>
                    </a>
                </h1>
            </div>
        </header>
    @endif

    <div class="page-wrapper">
        @hasSection('page-title')
            <div class="page-header d-print-none">
                <div class="container-xl">
                    <div class="row g-3 align-items-center">
                        <div class="col">
                            @hasSection('page-pretitle')
                                <div class="page-pretitle">@yield('page-pretitle')</div>
                            @endif
                            <h2 class="page-title">@yield('page-title')</h2>
                            @hasSection('page-subtitle')
                                <div class="text-secondary mt-1">@yield('page-subtitle')</div>
                            @endif
                        </div>
                        @hasSection('page-actions')
                            <div class="col-12 col-xl-auto ms-auto d-print-none">
                                <div class="btn-list">@yield('page-actions')</div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        <div class="page-body">
            <div class="container-xl">
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
                            <li class="list-inline-item"><a href="{{ route('privacy-policy') }}" class="link-secondary">{{ __('Gizlilik Politikası') }}</a></li>
                            <li class="list-inline-item"><a href="{{ route('terms-of-service') }}" class="link-secondary">{{ __('Kullanım Koşulları') }}</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </footer>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/@tabler/core@1.4.0/dist/js/tabler.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/apexcharts@3.54.1/dist/apexcharts.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
            new tabler.Tooltip(el);
        });

        // Skor halkalari ve sayaclar: ekrana girince 0'dan hedefe dolar.
        var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        function animateCount(el) {
            var target = parseFloat(el.dataset.countTo);
            if (isNaN(target)) { return; }
            var decimals = parseInt(el.dataset.countDecimals || '0', 10);
            var locale = document.documentElement.lang || 'tr';
            var fmt = function (v) { return v.toLocaleString(locale, { minimumFractionDigits: decimals, maximumFractionDigits: decimals }); };
            if (reduceMotion) { el.textContent = fmt(target); return; }
            var start = null, duration = 900;
            function step(ts) {
                if (start === null) { start = ts; }
                var p = Math.min(1, (ts - start) / duration);
                var eased = 1 - Math.pow(1 - p, 3);
                el.textContent = fmt(target * eased);
                if (p < 1) { requestAnimationFrame(step); }
            }
            requestAnimationFrame(step);
        }
        var revealObserver = 'IntersectionObserver' in window ? new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) { return; }
                var el = entry.target;
                el.classList.add('is-visible');
                if (el.dataset.countTo !== undefined) { animateCount(el); }
                revealObserver.unobserve(el);
            });
        }, { threshold: 0.2 }) : null;
        document.querySelectorAll('.score-ring, [data-count-to], .meter-bar').forEach(function (el) {
            if (revealObserver) { revealObserver.observe(el); } else {
                el.classList.add('is-visible');
                if (el.dataset.countTo !== undefined) { animateCount(el); }
            }
        });

        // Cizgi grafikler (components/line-chart): ApexCharts ile animasyonlu,
        // hover'da imlecli ve ipuclu.
        if (window.ApexCharts) {
            var isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
            document.querySelectorAll('[data-apex-line]').forEach(function (el) {
                var cfg;
                try { cfg = JSON.parse(el.dataset.apexLine); } catch (e) { return; }
                var suffix = cfg.suffix || '';
                var decimals = cfg.decimals;
                var formatValue = function (v) {
                    if (v === null || v === undefined) { return '—'; }
                    return parseFloat(Number(v).toFixed(decimals)) + suffix;
                };
                var chart = new ApexCharts(el, {
                    chart: {
                        type: 'area',
                        height: cfg.height,
                        fontFamily: 'Inter, system-ui, sans-serif',
                        foreColor: isDark ? '#9aa4b2' : '#5a6172',
                        background: 'transparent',
                        toolbar: { show: false },
                        zoom: { enabled: false },
                        parentHeightOffset: 0,
                        animations: {
                            enabled: !reduceMotion,
                            easing: 'easeinout',
                            speed: 900,
                            animateGradually: { enabled: true, delay: 120 },
                            dynamicAnimation: { enabled: true, speed: 400 }
                        }
                    },
                    theme: { mode: isDark ? 'dark' : 'light' },
                    series: cfg.series,
                    colors: cfg.colors,
                    stroke: { curve: 'smooth', width: 2.5 },
                    fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.28, opacityTo: 0.02, stops: [0, 95] } },
                    markers: { size: cfg.categories.length <= 12 ? 3 : 0, strokeWidth: 0, hover: { size: 6 } },
                    dataLabels: { enabled: false },
                    grid: { borderColor: isDark ? 'rgba(255,255,255,0.08)' : 'rgba(21,23,28,0.08)', strokeDashArray: 4, padding: { left: 4, right: 8 } },
                    xaxis: {
                        categories: cfg.categories,
                        tickAmount: Math.max(1, Math.min(Math.floor(el.clientWidth / 75), 6, cfg.categories.length - 1)),
                        tickPlacement: 'on',
                        labels: {
                            rotate: 0,
                            hideOverlappingLabels: true,
                            style: { fontSize: '11px' },
                            // "2026-07-12" -> "12.07": dar grafiklerde etiketler cakismasin.
                            formatter: function (v) {
                                var m = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(v));
                                return m ? m[3] + '.' + m[2] : v;
                            }
                        },
                        axisBorder: { show: false },
                        axisTicks: { show: false },
                        tooltip: { enabled: false }
                    },
                    yaxis: {
                        min: cfg.min === null ? undefined : cfg.min,
                        max: cfg.max === null ? undefined : cfg.max,
                        reversed: !!cfg.invertY,
                        tickAmount: 4,
                        labels: { formatter: formatValue, style: { fontSize: '11px' } }
                    },
                    tooltip: { shared: true, intersect: false, x: { formatter: function (v, opts) { return (opts && cfg.categories[opts.dataPointIndex]) || v; } }, y: { formatter: formatValue } },
                    legend: { show: cfg.series.length > 1, position: 'bottom', horizontalAlign: 'left', fontSize: '12px', markers: { size: 5 } },
                    noData: { text: '' }
                });
                el._apexChart = chart;
            });
            // Gizli sekmedeki grafik 0 genislikle cizilmesin diye her grafik
            // ilk kez gorunur oldugunda render edilir (animasyon da o an oynar).
            var chartObserver = 'IntersectionObserver' in window ? new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting && entry.target._apexChart) {
                        entry.target._apexChart.render();
                        chartObserver.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.15 }) : null;
            document.querySelectorAll('[data-apex-line]').forEach(function (el) {
                if (!el._apexChart) { return; }
                if (chartObserver) { chartObserver.observe(el); } else { el._apexChart.render(); }
            });
        }

        // Domain sayfasi sekmeleri: secili sekme URL hash'inde ve (form
        // gonderimleri hash'siz geri yonlendirdigi icin) sessionStorage'da
        // saklanir, sayfa yenilenince ayni sekmede acilir.
        document.querySelectorAll('[data-remember-tabs]').forEach(function (nav) {
            var storageKey = 'tabs:' + nav.dataset.rememberTabs;
            var links = Array.from(nav.querySelectorAll('[data-bs-toggle="tab"]'));
            function activate(hash) {
                var link = links.find(function (l) { return l.getAttribute('href') === hash; });
                if (link) { new tabler.Tab(link).show(); return true; }
                return false;
            }
            var initial = window.location.hash;
            if (!initial) {
                try { initial = sessionStorage.getItem(storageKey) || ''; } catch (e) { initial = ''; }
            }
            if (initial) { activate(initial); }
            links.forEach(function (link) {
                link.addEventListener('shown.bs.tab', function () {
                    var hash = link.getAttribute('href');
                    history.replaceState(null, '', hash);
                    try { sessionStorage.setItem(storageKey, hash); } catch (e) {}
                    window.dispatchEvent(new Event('resize'));
                });
            });
            document.querySelectorAll('[data-tab-jump]').forEach(function (a) {
                a.addEventListener('click', function (ev) {
                    if (activate(a.getAttribute('data-tab-jump'))) {
                        ev.preventDefault();
                        nav.scrollIntoView({ behavior: reduceMotion ? 'auto' : 'smooth', block: 'start' });
                    }
                });
            });
        });

        // Panoya kopyala butonlari (ornek: paylasilan rapor linki).
        document.querySelectorAll('[data-copy-text]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var text = btn.getAttribute('data-copy-text');
                var original = btn.textContent;
                var done = function () {
                    btn.textContent = btn.getAttribute('data-copied-label') || original;
                    setTimeout(function () { btn.textContent = original; }, 1500);
                };
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(text).then(done).catch(function () {});
                } else {
                    var ta = document.createElement('textarea');
                    ta.value = text;
                    document.body.appendChild(ta);
                    ta.select();
                    try { document.execCommand('copy'); done(); } catch (e) {}
                    ta.remove();
                }
            });
        });

        // Bir islemden sonra (ornek: rapor linki olusturuldu) ilgili modal'i yeniden ac.
        document.querySelectorAll('.modal[data-auto-open]').forEach(function (el) {
            new tabler.Modal(el).show();
        });

        // Dashboard: domain arama + durum filtresi (tamamen istemci tarafi).
        var domainGrid = document.querySelector('[data-domain-grid]');
        if (domainGrid) {
            var search = document.querySelector('[data-domain-search]');
            var filterButtons = document.querySelectorAll('[data-domain-filter]');
            var activeFilter = 'all';
            var emptyNote = document.querySelector('[data-domain-empty-filter]');
            function applyDomainFilter() {
                var q = (search ? search.value : '').trim().toLowerCase();
                var visible = 0;
                domainGrid.querySelectorAll('[data-domain-card]').forEach(function (card) {
                    var matchesText = !q || card.dataset.domainName.indexOf(q) !== -1;
                    var matchesStatus = activeFilter === 'all' || card.dataset.domainStatus === activeFilter;
                    var show = matchesText && matchesStatus;
                    card.hidden = !show;
                    if (show) { visible++; }
                });
                if (emptyNote) { emptyNote.hidden = visible !== 0; }
            }
            if (search) { search.addEventListener('input', applyDomainFilter); }
            filterButtons.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    activeFilter = btn.dataset.domainFilter;
                    filterButtons.forEach(function (b) {
                        var on = b === btn;
                        b.classList.toggle('active', on);
                        b.setAttribute('aria-pressed', on ? 'true' : 'false');
                    });
                    applyDomainFilter();
                });
            });
        }

        document.querySelectorAll('[data-ga4-account-select]').forEach(function (accountSelect) {
            var domainId = accountSelect.dataset.ga4AccountSelect;
            var propertySelect = document.querySelector('[data-ga4-property-select="' + domainId + '"]');
            if (!propertySelect) {
                return;
            }

            var placeholderOption = propertySelect.querySelector('option[value=""]');

            function applyAccountFilter() {
                var chosenAccount = accountSelect.value;
                var hasVisibleSelection = false;

                Array.from(propertySelect.options).forEach(function (option) {
                    if (!option.value) {
                        return;
                    }
                    var matches = chosenAccount !== '' && option.dataset.account === chosenAccount;
                    option.hidden = !matches;
                    if (matches && option.selected) {
                        hasVisibleSelection = true;
                    }
                });

                propertySelect.disabled = chosenAccount === '';
                if (!hasVisibleSelection) {
                    propertySelect.value = '';
                }
                if (placeholderOption) {
                    placeholderOption.textContent = chosenAccount === ''
                        ? {!! json_encode(__('— Önce hesap seçin —')) !!}
                        : {!! json_encode(__('— Seçiniz —')) !!};
                }
            }

            accountSelect.addEventListener('change', applyAccountFilter);
        });
    });
</script>
</body>
</html>
