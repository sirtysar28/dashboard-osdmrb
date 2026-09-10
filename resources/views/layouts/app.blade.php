<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Dashboard Biro OSDMRB') }}</title>

    {{-- Favicon & logo Kementerian --}}
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/logo-kementerian.svg') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">

    <!-- Bootstrap 5 + Icons + Chart.js -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="{{ asset('css/app-dashboard.css') }}?v={{ filemtime(public_path('css/app-dashboard.css')) }}" rel="stylesheet">

    {{-- Tema warna dari Pengaturan (hanya diubah Administrator Utama) --}}
    @php
        $themePrimary = \App\Models\Setting::get('theme_primary', '#163d4f');
        $themePrimaryDark = \App\Models\Setting::get('theme_primary_dark', '#0e2a37');
        $themePrimaryLight = \App\Models\Setting::get('theme_primary_light', '#2b5f78');
        $themeAccent = \App\Models\Setting::get('theme_accent', '#e8a13c');
        $themeSidebar = \App\Models\Setting::get('theme_sidebar', '#10222d');
    @endphp
    <style>
        :root {
            --osdmrb-primary: {{ $themePrimary }};
            --osdmrb-primary-dark: {{ $themePrimaryDark }};
            --osdmrb-primary-light: {{ $themePrimaryLight }};
            --osdmrb-accent: {{ $themeAccent }};
            --osdmrb-sidebar: {{ $themeSidebar }};
        }
    </style>

    {{-- Terapkan mode sidebar minimize SEBELUM render agar tidak ada kedipan --}}
    <script>
        try {
            if (localStorage.getItem('pi-sidebar-min') === '1') {
                document.documentElement.classList.add('sidebar-min');
            }
        } catch (e) {}
    </script>
</head>
<body>

{{-- ================= LOADER / SPINNER GLOBAL =================
     Muncul saat: masuk ke dashboard, pindah antar halaman,
     submit form, dan proses logout. --}}
<div class="page-loader" id="pageLoader" aria-live="polite">
    <div class="loader-spinner"></div>
    <p class="loader-text" id="pageLoaderText">Memuat<span class="loader-dots"><span></span><span></span><span></span></span></p>
    <div class="loader-brand">Dashboard Biro OSDMRB</div>
</div>

<div class="app-wrapper">

    <!-- ============================ SIDEBAR ============================ -->
    <aside class="sidebar" id="sidebar">

        <div class="sidebar-brand">
            <div class="brand-logo">
                <img src="{{ asset('images/logo-kementerian.svg') }}" alt="Logo Kementerian Transmigrasi RI">
            </div>
            <div class="brand-text">
                <h4>DASHBOARD</h4>
                <span>BIRO OSDMRB</span>
            </div>

            {{-- Tombol tutup drawer sidebar (khusus mobile, tampil saat drawer terbuka) --}}
            <button class="sidebar-close d-lg-none" id="sidebarClose" type="button" aria-label="Tutup menu">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        @auth
            @php($privileged = auth()->user()->isPrivileged())
            @php($menuLetters = \App\Models\Setting::menuVisible('letters'))
            @php($menuArchives = \App\Models\Setting::menuVisible('archives'))

            <div class="sidebar-section">Menu Utama</div>

            <nav class="sidebar-menu">

                @if($privileged)
                    <a href="{{ route('dashboard') }}"
                       class="menu-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                        <i class="bi bi-speedometer2"></i><span>Dashboard</span>
                    </a>
                @else
                    <a href="{{ route('home') }}"
                       class="menu-item {{ request()->routeIs('home') ? 'active' : '' }}">
                        <i class="bi bi-house-door"></i><span>Beranda</span>
                    </a>
                @endif

                <div class="sidebar-section mt-2">Data &amp; Master</div>

                @if($privileged)
                    <a href="{{ route('employees.index') }}"
                       class="menu-item {{ request()->routeIs('employees.index', 'employees.show', 'employees.edit', 'employees.create', 'employees.import') ? 'active' : '' }}">
                        <i class="bi bi-people"></i><span>Data Pegawai ASN</span>
                    </a>
                    <a href="{{ route('employees.non-asn') }}"
                       class="menu-item {{ request()->routeIs('employees.non-asn') ? 'active' : '' }}">
                        <i class="bi bi-person-badge"></i><span>Pegawai Non ASN</span>
                    </a>
                @else
                    <a href="{{ route('pegawai.profile') }}"
                       class="menu-item {{ request()->routeIs('pegawai.profile', 'employees.show') ? 'active' : '' }}">
                        <i class="bi bi-person-vcard"></i><span>Profil Saya</span>
                    </a>
                @endif

                @if($privileged)
                    <a href="{{ route('master.index') }}"
                       class="menu-item {{ request()->routeIs('master.index', 'master.export') ? 'active' : '' }}">
                        <i class="bi bi-database-fill-check"></i><span>Master Data</span>
                    </a>
                @endif

                <div class="sidebar-section mt-2">Modul Biro OSDMRB</div>

                <a href="{{ route('modules.analisis-jabatan') }}"
                   class="menu-item {{ request()->routeIs('modules.analisis-jabatan') ? 'active' : '' }}">
                    <i class="bi bi-clipboard2-data"></i><span>Analisis Jabatan Fungsional</span>
                </a>
                <a href="{{ route('modules.analisis-jabatan-struktural') }}"
                   class="menu-item {{ request()->routeIs('modules.analisis-jabatan-struktural') ? 'active' : '' }}">
                    <i class="bi bi-diagram-2"></i><span>Analisis Jabatan Struktural</span>
                </a>
                @if(\App\Models\Setting::menuVisible('reformasi_birokrasi'))
                    <a href="{{ route('modules.reformasi-birokrasi') }}"
                       class="menu-item {{ request()->routeIs('modules.reformasi-birokrasi') ? 'active' : '' }}">
                        <i class="bi bi-arrow-repeat"></i><span>Reformasi Birokrasi</span>
                    </a>
                @endif
                @if(\App\Models\Setting::menuVisible('manajemen_talenta'))
                    <a href="{{ route('modules.manajemen-talenta') }}"
                       class="menu-item {{ request()->routeIs('modules.manajemen-talenta') ? 'active' : '' }}">
                        <i class="bi bi-stars"></i><span>Manajemen Talenta</span>
                    </a>
                @endif
                @if(\App\Models\Setting::menuVisible('diklat'))
                    <a href="{{ route('modules.diklat') }}"
                       class="menu-item {{ request()->routeIs('modules.diklat') ? 'active' : '' }}">
                        <i class="bi bi-mortarboard"></i><span>Diklat &amp; Pengembangan</span>
                    </a>
                @endif
                <a href="{{ route('modules.sop') }}"
                   class="menu-item {{ request()->routeIs('modules.sop') ? 'active' : '' }}">
                    <i class="bi bi-journal-text"></i><span>SOP Kementerian</span>
                </a>
                <a href="{{ route('modules.struktur') }}"
                   class="menu-item {{ request()->routeIs('modules.struktur') ? 'active' : '' }}">
                    <i class="bi bi-diagram-3"></i><span>Struktur Organisasi</span>
                </a>

                @if($menuLetters || $menuArchives)
                    <div class="sidebar-section mt-2">Layanan Kepegawaian</div>
                @endif

                @if($menuLetters)
                    <a href="{{ route('letters.index') }}"
                       class="menu-item {{ request()->routeIs('letters.index') ? 'active' : '' }}">
                        <i class="bi bi-envelope-paper"></i><span>Persuratan</span>
                    </a>
                    <a href="{{ route('letters.create') }}"
                       class="menu-item {{ request()->routeIs('letters.create') ? 'active' : '' }}">
                        <i class="bi bi-file-earmark-plus"></i><span>Ajukan Surat</span>
                    </a>
                @endif

                @if($menuArchives)
                    <a href="{{ route('archives.index') }}"
                       class="menu-item {{ request()->routeIs('archives.index') || request()->routeIs('archives.show') ? 'active' : '' }}">
                        <i class="bi bi-archive"></i><span>Kearsipan</span>
                    </a>

                    <a href="{{ route('archive-loans.index') }}"
                       class="menu-item {{ request()->routeIs('archive-loans.*') ? 'active' : '' }}">
                        <i class="bi bi-box-arrow-up-right"></i><span>Peminjaman Arsip</span>
                    </a>
                @endif

                @if($privileged && $menuLetters)
                    <a href="{{ route('letter-types.index') }}"
                       class="menu-item {{ request()->routeIs('letter-types.*') ? 'active' : '' }}">
                        <i class="bi bi-collection"></i><span>Jenis Surat</span>
                    </a>
                @endif

                @if($privileged)
                    <div class="sidebar-section mt-2">Monitoring</div>

                    <a href="{{ route('audit.index') }}"
                       class="menu-item {{ request()->routeIs('audit.*') ? 'active' : '' }}">
                        <i class="bi bi-shield-check"></i><span>Audit Log &amp; Pengunjung</span>
                    </a>
                    <a href="{{ route('surveys.index') }}"
                       class="menu-item {{ request()->routeIs('surveys.*') && !request()->routeIs('surveys.store') ? 'active' : '' }}">
                        <i class="bi bi-chat-square-heart"></i><span>Survei &amp; Masukan</span>
                    </a>
                @endif

                @if(auth()->user()->isSuperAdmin())
                    <div class="sidebar-section mt-2">Administrasi</div>

                    <a href="{{ route('users.index') }}"
                       class="menu-item {{ request()->routeIs('users.*') ? 'active' : '' }}">
                        <i class="bi bi-person-gear"></i><span>Pengguna</span>
                    </a>

                    <div class="sidebar-section mt-2">Pengaturan</div>

                    <a href="{{ route('settings.appearance') }}"
                       class="menu-item {{ request()->routeIs('settings.appearance*') ? 'active' : '' }}">
                        <i class="bi bi-palette"></i><span>Tampilan &amp; Menu</span>
                    </a>
                    <a href="{{ route('settings.announcements') }}"
                       class="menu-item {{ request()->routeIs('settings.announcements*') ? 'active' : '' }}">
                        <i class="bi bi-megaphone"></i><span>Pengumuman</span>
                    </a>
                    <a href="{{ route('settings.smtp') }}"
                       class="menu-item {{ request()->routeIs('settings.smtp*') ? 'active' : '' }}">
                        <i class="bi bi-envelope-check"></i><span>SMTP &amp; Notifikasi</span>
                    </a>
                @endif

            </nav>
        @endauth

    </aside>

    <!-- Overlay gelap saat drawer sidebar terbuka (mobile) -->
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

    <!-- ============================ MAIN ============================ -->
    <div class="main-content">

        <header class="top-header">

            <div class="d-flex align-items-center gap-2 min-width-0">
                {{-- Hamburger utk MEMBUKA sidebar (mobile / tablet < 992px) --}}
                <button class="sidebar-toggle d-lg-none" id="sidebarToggle" type="button"
                        aria-label="Buka menu navigasi" aria-controls="sidebar">
                    <i class="bi bi-list"></i>
                </button>
                {{-- Hamburger utk mengecilkan sidebar (desktop) --}}
                <button type="button" class="nav-hamburger d-none d-lg-inline-flex" id="sidebarMinimize"
                        aria-expanded="true" title="Kecilkan sidebar (icon saja)">
                    <i class="bi bi-list"></i>
                </button>
                <img src="{{ asset('images/logo-kementerian.svg') }}" alt="Logo Kementerian Transmigrasi RI" class="header-logo d-none d-sm-block">
                <div class="min-width-0">
                    <h3 class="text-truncate">@yield('page_title', 'Dashboard Biro OSDMRB')</h3>
                    <p class="subtitle text-truncate d-none d-sm-block">@yield('page_subtitle', 'Sistem Informasi Manajemen Kepegawaian &amp; Layanan Persuratan')</p>
                </div>
            </div>

            <div class="d-flex align-items-center gap-3">

                <div class="dropdown user-chip">
                    <a href="#" class="d-flex align-items-center text-decoration-none gap-2" data-bs-toggle="dropdown">
                        <div class="meta d-none d-sm-block">
                            <strong>{{ auth()->user()->name }}</strong>
                            <small>{{ auth()->user()->role_label }}</small>
                        </div>
                        <div class="avatar">{{ auth()->user()->initials }}</div>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow">
                        <li><a class="dropdown-item" href="{{ route('profile.edit') }}">
                            <i class="bi bi-person me-2"></i>Profil Saya</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form method="POST" action="{{ route('logout') }}" id="logoutForm">
                                @csrf
                                <button class="dropdown-item text-danger" type="submit">
                                    <i class="bi bi-box-arrow-right me-2"></i>Keluar
                                </button>
                            </form>
                        </li>
                    </ul>
                </div>

            </div>

        </header>

        <main class="content">

            @if (session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle me-1"></i> {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if (session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-x-circle me-1"></i> {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    <strong>Periksa kembali data yang diisi:</strong>
                    <ul class="mb-0 mt-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @yield('content')

        </main>

    </div>

</div>

{{-- ================= WIDGET PESAN / CHAT PRIVATE (pojok kanan bawah) ================= --}}
@auth
    @include('chat.widget')
@endauth

{{-- ================= FOOTER BAWAH (mengikuti situs Kementerian Transmigrasi) ================= --}}
<footer class="app-footer">
    <div class="app-footer-inner">
        <p class="app-footer-copyright">Copyright &copy; {{ date('Y') }} Kementerian Transmigrasi Republik Indonesia</p>

        <div class="app-footer-social">
            <a href="https://www.facebook.com/kementrans.ri" aria-label="Facebook" target="_blank" rel="noopener noreferrer" data-no-loader class="soc-facebook">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512" fill="currentColor"><path d="M400 32H48A48 48 0 0 0 0 80v352a48 48 0 0 0 48 48h137.25V327.69h-63V256h63v-54.64c0-62.15 37-96.48 93.67-96.48 27.14 0 55.52 4.84 55.52 4.84v61h-31.27c-30.81 0-40.42 19.12-40.42 38.73V256h68.78l-11 71.69h-57.78V480H400a48 48 0 0 0 48-48V80a48 48 0 0 0-48-48z"></path></svg>
            </a>
            <a href="https://x.com/Kementrans_RI" aria-label="Twitter / X" target="_blank" rel="noopener noreferrer" data-no-loader class="soc-twitter">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" fill="currentColor"><path d="M459.37 151.716c.325 4.548.325 9.097.325 13.645 0 138.72-105.583 298.558-298.558 298.558-59.452 0-114.68-17.219-161.137-47.106 8.447.974 16.568 1.299 25.34 1.299 49.055 0 94.213-16.568 130.274-44.832-46.132-.975-84.792-31.188-98.112-72.772 6.498.974 12.995 1.624 19.818 1.624 9.421 0 18.843-1.3 27.614-3.573-48.081-9.747-84.143-51.98-84.143-102.985v-1.299c13.969 7.797 30.214 12.67 47.431 13.319-28.264-18.843-46.781-51.005-46.781-87.391 0-19.492 5.197-37.36 14.294-52.954 51.655 63.675 129.3 105.258 216.365 109.807-1.624-7.797-2.599-15.918-2.599-24.04 0-57.828 46.782-104.934 104.934-104.934 30.213 0 57.502 12.67 76.67 33.137 23.715-4.548 46.456-13.32 66.599-25.34-7.798 24.366-24.366 44.833-46.132 57.827 21.117-2.273 41.584-8.122 60.426-16.243-14.292 20.791-32.161 39.308-52.628 54.253z"></path></svg>
            </a>
            <a href="https://www.instagram.com/kementrans.ri/" aria-label="Instagram" target="_blank" rel="noopener noreferrer" data-no-loader class="soc-instagram">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512" fill="currentColor"><path d="M224,202.66A53.34,53.34,0,1,0,277.36,256,53.38,53.38,0,0,0,224,202.66Zm124.71-41a54,54,0,0,0-30.41-30.41c-21-8.29-71-6.43-94.3-6.43s-73.25-1.93-94.31,6.43a54,54,0,0,0-30.41,30.41c-8.28,21-6.43,71.05-6.43,94.33S91,329.26,99.32,350.33a54,54,0,0,0 30.41,30.41c21,8.29,71,6.43,94.31,6.43s73.24,1.93,94.3-6.43a54,54,0,0,0 30.41-30.41c8.35-21,6.43-71.05,6.43-94.33S357.1,182.74,348.75,161.67ZM224,338a82,82,0,1,1,82-82A81.9,81.9,0,0,1,224,338Zm85.38-148.3a19.14,19.14,0,1,1 19.13-19.14A19.1,19.1,0,0,1,309.42,189.74ZM400,32H48A48,48,0,0,0,0,80V432a48,48,0,0,0 48,48H400a48,48,0,0,0 48-48V80A48,48,0,0,0,400,32ZM382.88,322c-1.29,25.63-7.14,48.34-25.85,67s-41.4,24.63-67,25.85c-26.41,1.49-105.59,1.49-132,0-25.63-1.29-48.26-7.15-67-25.85s-24.63-41.42-25.85-67c-1.49-26.42-1.49-105.61,0-132,1.29-25.63,7.07-48.34,25.85-67s41.47-24.56,67-25.78c26.41-1.49,105.59-1.49,132,0,25.63,1.29,48.33,7.15,67,25.85s24.63,41.42,25.85,67.05C384.37,216.44,384.37,295.56,382.88,322Z"></path></svg>
            </a>
            <a href="https://www.tiktok.com/@kementrans.ri" aria-label="TikTok" target="_blank" rel="noopener noreferrer" data-no-loader class="soc-tiktok">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" fill="currentColor"><path d="M16.708 0.027c1.745-0.027 3.48-0.011 5.213-0.027 0.105 2.041 0.839 4.12 2.333 5.563 1.491 1.479 3.6 2.156 5.652 2.385v5.369c-1.923-0.063-3.855-0.463-5.6-1.291-0.76-0.344-1.468-0.787-2.161-1.24-0.009 3.896 0.016 7.787-0.025 11.667-0.104 1.864-0.719 3.719-1.803 5.255-1.744 2.557-4.771 4.224-7.88 4.276-1.907 0.109-3.812-0.411-5.437-1.369-2.693-1.588-4.588-4.495-4.864-7.615-0.032-0.667-0.043-1.333-0.016-1.984 0.24-2.537 1.495-4.964 3.443-6.615 2.208-1.923 5.301-2.839 8.197-2.297 0.027 1.975-0.052 3.948-0.052 5.923-1.323-0.428-2.869-0.308-4.025 0.495-0.844 0.547-1.485 1.385-1.819 2.333-0.276 0.676-0.197 1.427-0.181 2.145 0.317 2.188 2.421 4.027 4.667 3.828 1.489-0.016 2.916-0.88 3.692-2.145 0.251-0.443 0.532-0.896 0.547-1.417 0.131-2.385 0.079-4.76 0.095-7.145 0.011-5.375-0.016-10.735 0.025-16.093z"></path></svg>
            </a>
            <a href="https://www.youtube.com/@kementrans_ri" aria-label="YouTube" target="_blank" rel="noopener noreferrer" data-no-loader class="soc-youtube">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 576 512" fill="currentColor"><path d="M549.655 124.083c-6.281-23.65-24.787-42.276-48.284-48.597C458.781 64 288 64 288 64S117.22 64 74.629 75.486c-23.497 6.322-42.003 24.947-48.284 48.597-11.412 42.867-11.412 132.305-11.412 132.305s0 89.438 11.412 132.305c6.281 23.65 24.787 41.5 48.284 47.821C117.22 448 288 448 288 448s170.78 0 213.371-11.486c23.497-6.321 42.003-24.171 48.284-47.821 11.412-42.867 11.412-132.305 11.412-132.305s0-89.438-11.412-132.305zm-317.51 213.508V175.185l142.739 81.205-142.739 81.201z"></path></svg>
            </a>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
    /* ================== LOADER GLOBAL ==================
       piShowLoader(text)  : tampilkan overlay spinner
       piHideLoader()      : sembunyikan overlay spinner */
    window.piLoaderTimer = null;

    window.piShowLoader = function (text) {
        var loader = document.getElementById('pageLoader');
        var label  = document.getElementById('pageLoaderText');
        if (!loader) return;

        if (text) {
            label.innerHTML = text + '<span class="loader-dots"><span></span><span></span><span></span></span>';
        }

        loader.classList.add('show');
        document.body.style.overflow = 'hidden';

        clearTimeout(window.piLoaderTimer);
        window.piLoaderTimer = setTimeout(window.piHideLoader, 12000);
    };

    window.piHideLoader = function () {
        var loader = document.getElementById('pageLoader');
        if (!loader) return;

        loader.classList.remove('show');
        document.body.style.overflow = '';
        clearTimeout(window.piLoaderTimer);
    };

    window.addEventListener('pageshow', window.piHideLoader);

    /* ---------- Spinner saat masuk halaman (dashboard/halaman utama) ---------- */
    window.piShowLoader();
    window.addEventListener('load', function () {
        setTimeout(window.piHideLoader, 350);
    });

    /* ---------- Spinner saat berpindah halaman internal ---------- */
    document.addEventListener('click', function (event) {
        var link = event.target.closest('a[href]');

        if (!link) return;
        if (link.hasAttribute('data-no-loader')) return;
        if (link.target === '_blank' || link.hasAttribute('download')) return;
        if (link.dataset.bsToggle || link.hasAttribute('data-bs-dismiss')) return;

        var href = link.getAttribute('href');
        if (!href || href.charAt(0) === '#' || href.startsWith('javascript:') || href.startsWith('tel:') || href.startsWith('mailto:')) return;

        var url;
        try { url = new URL(link.href, window.location.href); } catch (e) { return; }

        if (url.origin !== window.location.origin) return;

        if (url.pathname !== window.location.pathname || url.search !== window.location.search) {
            window.piShowLoader('Memuat "' + (link.textContent.trim().split('\n')[0] || 'halaman') + '"');
        }
    });

    /* ---------- Spinner saat submit form ---------- */
    document.addEventListener('submit', function (event) {
        var existing = document.getElementById('pageLoader');
        if (existing && existing.classList.contains('show')) return;

        var form = event.target;

        if (form.matches('[data-no-loader]') || form.matches('.no-loader')) return;
        if (form.id === 'logoutForm') {
            window.piShowLoader('Sedang keluar dari aplikasi...');
            return;
        }

        if (form.getAttribute('method') && form.getAttribute('method').toUpperCase() !== 'POST') return;

        var button = form.querySelector('button[type="submit"], button:not([type])');
        var text   = button && button.dataset.loaderText ? button.dataset.loaderText : 'Menyimpan data';

        window.piShowLoader(text);
    });

    /* ---------- Sidebar minimize / perbesar (khusus desktop) ---------- */
    var htmlEl = document.documentElement;
    var minBtn = document.getElementById('sidebarMinimize');

    // tooltip native untuk tiap menu (berguna saat sidebar minimize)
    document.querySelectorAll('.sidebar .menu-item').forEach(function (link) {
        link.title = link.textContent.trim();
    });

    function syncMinimizeButton() {
        if (!minBtn) return;

        var minimized = htmlEl.classList.contains('sidebar-min');

        minBtn.classList.toggle('active', minimized);
        minBtn.title = minimized ? 'Tampilkan menu lengkap' : 'Kecilkan sidebar (icon saja)';
        minBtn.setAttribute('aria-expanded', minimized ? 'false' : 'true');
    }

    if (minBtn) {
        minBtn.addEventListener('click', function () {
            htmlEl.classList.toggle('sidebar-min');

            try {
                localStorage.setItem(
                    'pi-sidebar-min',
                    htmlEl.classList.contains('sidebar-min') ? '1' : '0'
                );
            } catch (e) {}

            syncMinimizeButton();
        });

        syncMinimizeButton();
    }

    /* ---------- Drawer sidebar (mobile) ---------- */
    document.addEventListener('DOMContentLoaded', function () {
        var body = document.body;
        var toggle = document.getElementById('sidebarToggle');
        var backdrop = document.getElementById('sidebarBackdrop');
        var closeBtn = document.getElementById('sidebarClose');

        function openSidebar()  { body.classList.add('sidebar-open'); }
        function closeSidebar() { body.classList.remove('sidebar-open'); }

        if (toggle)   toggle.addEventListener('click', openSidebar);
        if (backdrop) backdrop.addEventListener('click', closeSidebar);
        if (closeBtn) closeBtn.addEventListener('click', closeSidebar);

        // Tutup drawer setelah menu dipilih (khusus mobile)
        document.querySelectorAll('.sidebar .menu-item').forEach(function (link) {
            link.addEventListener('click', function () {
                if (window.innerWidth < 992) closeSidebar();
            });
        });

        // Reset saat layar kembali ke ukuran desktop
        window.addEventListener('resize', function () {
            if (window.innerWidth >= 992) closeSidebar();
        });

        // Tutup dengan tombol ESC
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeSidebar();
        });
    });
</script>

@stack('scripts')
</body>
</html>
