@extends('layouts.app')

@section('page_title', 'Pengaturan Tampilan & Menu')
@section('page_subtitle', 'Atur visibilitas menu sidebar & tema warna aplikasi (Administrator Utama)')

@section('content')

@include('settings.partials.nav')

<form method="POST" action="{{ route('settings.appearance.update') }}">
    @csrf

    <div class="row g-3">

        {{-- ===================== VISIBILITAS MENU ===================== --}}
        <div class="col-lg-7">
            <div class="chart-card h-100">
                <h5><i class="bi bi-layout-sidebar-inset me-2"></i>Visibilitas Menu Sidebar</h5>
                <p class="small text-muted">
                    Menu yang dinonaktifkan akan disembunyikan dari seluruh pengguna
                    (fitur sementara tidak digunakan). Aktifkan kembali kapan saja dari sini.
                </p>

                @foreach ($menus as $menu)
                    <div class="d-flex align-items-center justify-content-between border rounded-3 px-3 py-2 mb-2">
                        <div class="d-flex align-items-center gap-3">
                            <div class="stat-icon" style="width:40px;height:40px;font-size:18px;">
                                <i class="bi {{ $menu['icon'] }}"></i>
                            </div>
                            <div>
                                <div class="fw-semibold" style="font-size:13.5px">{{ $menu['label'] }}</div>
                                <small class="text-muted">{{ $menu['desc'] }}</small>
                            </div>
                        </div>
                        <div class="form-check form-switch m-0">
                            <input class="form-check-input" type="checkbox" role="switch"
                                   name="menus[{{ $menu['key'] }}]" value="1"
                                   id="menu_{{ $menu['key'] }}"
                                   {{ \App\Models\Setting::menuVisible($menu['key']) ? 'checked' : '' }}>
                            <label class="form-check-label small" for="menu_{{ $menu['key'] }}">
                                {{ \App\Models\Setting::menuVisible($menu['key']) ? 'Tampil' : 'Disembunyikan' }}
                            </label>
                        </div>
                    </div>
                @endforeach

                <hr>
            </div>
        </div>

        {{-- ===================== TEMA WARNA ===================== --}}
        <div class="col-lg-5">
            <div class="chart-card h-100">
                <h5><i class="bi bi-palette me-2"></i>Tema &amp; Desain Template</h5>
                <p class="small text-muted">
                    Warna tombol, header tabel, menu sidebar &amp; aksen grafik mengikuti
                    kombinasi warna berikut.
                </p>

                <div class="row g-3">
                    <div class="col-sm-6">
                        <label class="form-label">Warna Utama (tombol)</label>
                        <input type="color" name="theme_primary" class="form-control form-control-color w-100"
                               value="{{ $theme['primary'] }}">
                        <div class="theme-swatch mt-2" style="background:{{ $theme['primary'] }}"></div>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label">Warna Utama (hover)</label>
                        <input type="color" name="theme_primary_dark" class="form-control form-control-color w-100"
                               value="{{ $theme['primary_dark'] }}">
                        <div class="theme-swatch mt-2" style="background:{{ $theme['primary_dark'] }}"></div>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label">Warna Utama (terang)</label>
                        <input type="color" name="theme_primary_light" class="form-control form-control-color w-100"
                               value="{{ $theme['primary_light'] }}">
                        <div class="theme-swatch mt-2" style="background:{{ $theme['primary_light'] }}"></div>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label">Warna Aksen</label>
                        <input type="color" name="theme_accent" class="form-control form-control-color w-100"
                               value="{{ $theme['accent'] }}">
                        <div class="theme-swatch mt-2" style="background:{{ $theme['accent'] }}"></div>
                    </div>
                </div>

                <div class="mt-3 d-flex gap-2 flex-wrap">
                    <button type="button" class="btn btn-sm btn-outline-secondary preset-theme"
                            data-primary="#163d4f" data-dark="#0e2a37" data-light="#2b5f78" data-accent="#e8a13c">
                        Bawaan (Kementerian)
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary preset-theme"
                            data-primary="#0f766e" data-dark="#115e59" data-light="#14b8a6" data-accent="#f59e0b">
                        Hijau Instansi
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary preset-theme"
                            data-primary="#b91c1c" data-dark="#991b1b" data-light="#ef4444" data-accent="#f59e0b">
                        Merah Klasik
                    </button>
                </div>
            </div>
        </div>

        <div class="col-12">
            <button type="submit" class="btn btn-osdmrb px-4" data-loader-text="Menyimpan pengaturan">
                <i class="bi bi-check-lg"></i> Simpan Pengaturan
            </button>
        </div>
    </div>
</form>

@endsection

@push('scripts')
<script>
    /* tombol label switch */
    document.querySelectorAll('.form-check.form-switch input').forEach(function (toggle) {
        var label = toggle.closest('.form-check').querySelector('.form-check-label');
        toggle.addEventListener('change', function () {
            if (label) {
                label.textContent = toggle.checked
                    ? (label.textContent === 'Aktif' ? 'Aktif' : 'Tampil')
                    : (label.textContent === 'Aktif' ? 'Nonaktif' : 'Disembunyikan');
            }
        });
    });

    /* preset tema */
    document.querySelectorAll('.preset-theme').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var form = btn.closest('form');
            form.querySelector('[name="theme_primary"]').value = btn.dataset.primary;
            form.querySelector('[name="theme_primary_dark"]').value = btn.dataset.dark;
            form.querySelector('[name="theme_primary_light"]').value = btn.dataset.light;
            form.querySelector('[name="theme_accent"]').value = btn.dataset.accent;
        });
    });
</script>
@endpush
