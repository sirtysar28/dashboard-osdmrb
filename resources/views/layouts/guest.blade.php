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

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="{{ asset('css/app-dashboard.css') }}?v={{ filemtime(public_path('css/app-dashboard.css')) }}" rel="stylesheet">
</head>
<body class="auth-page">

{{-- ================= LOADER / SPINNER GLOBAL ================= --}}
<div class="page-loader" id="pageLoader" aria-live="polite">
    <div class="loader-spinner"></div>
    <p class="loader-text" id="pageLoaderText">Memuat<span class="loader-dots"><span></span><span></span><span></span></span></p>
    <div class="loader-brand">Dashboard Biro OSDMRB</div>
</div>

<div class="auth-wrapper">

    <div class="auth-shell">

        <!-- ================= PANEL KIRI : FORM LOGIN ================= -->
        <main class="auth-main">

            <div class="auth-main-head">
                <div class="auth-main-logo">
                    <img src="{{ asset('images/logo-kementerian.svg') }}" alt="Logo Kementerian Transmigrasi Republik Indonesia">
                </div>
                <div>
                    <strong>Kementerian Transmigrasi RI</strong>
                    <span>Biro OSDMRB</span>
                </div>
            </div>

            <h2 class="auth-title">Selamat Datang Kembali</h2>
            <p class="auth-subtitle">Masuk menggunakan akun instansi Anda untuk melanjutkan.</p>

            @if (session('status'))
                <div class="alert alert-success py-2 small">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger py-2 small">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    {{ $errors->first() }}
                </div>
            @endif

            {{ $slot }}

        </main>

        <!-- ================= PANEL KANAN : INFORMASI APLIKASI ================= -->
        <aside class="auth-info">

            <div class="auth-info-logo">
                <img src="{{ asset('images/logo-kementerian.svg') }}" alt="Logo Kementerian Transmigrasi RI">
            </div>

            <span class="auth-info-badge"><i class="bi bi-shield-check"></i> Biro OSDMRB</span>

            <h1>Dashboard Biro OSDMRB</h1>
            <p class="auth-info-lead">Organisasi, Sumber Daya Manusia &amp; Reformasi Birokrasi</p>

            <p class="auth-info-desc">
                Sistem informasi terintegrasi untuk pengelolaan data pegawai ASN, layanan
                persuratan, kearsipan digital, hingga pemantauan reformasi birokrasi &mdash;
                cepat, aman, dan akuntabel.
            </p>

            <ul class="auth-info-features">
                <li><i class="bi bi-people"></i> Manajemen data kepegawaian ASN</li>
                <li><i class="bi bi-envelope-paper"></i> Layanan persuratan &amp; verifikasi</li>
                <li><i class="bi bi-archive"></i> Kearsipan digital &amp; peminjaman arsip</li>
                <li><i class="bi bi-graph-up-arrow"></i> Analitik SDM &amp; reformasi birokrasi</li>
            </ul>

            <div class="auth-info-footer">
                <span>&copy; {{ date('Y') }} Biro OSDMRB</span>
                <span>Sistem Informasi Manajemen Kepegawaian</span>
            </div>

        </aside>

    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
    /* ================== LOADER GLOBAL (login & navigasi) ==================
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

        // pengaman: sembunyikan otomatis bila navigasi tidak terjadi (mis. diblokir browser)
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

    /* Spinner saat berpindah halaman internal (login -> dashboard, dsb.) */
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
            window.piShowLoader('Memuat halaman');
        }
    });

    /* Spinner saat submit form non-AJAX (diabaikan bila loader sudah aktif, mis. dari handler khusus login) */
    document.addEventListener('submit', function (event) {
        var existing = document.getElementById('pageLoader');
        if (existing && existing.classList.contains('show')) return;

        var form = event.target;

        if (form.matches('[data-no-loader]') || form.matches('.no-loader')) return;
        if (form.getAttribute('method') && form.getAttribute('method').toUpperCase() !== 'POST') return;

        var button = form.querySelector('button[type="submit"], button:not([type])');
        var text   = button && button.dataset.loaderText ? button.dataset.loaderText : 'Memproses permintaan';

        window.piShowLoader(text);
    });
</script>
@stack('scripts')
</body>
</html>
