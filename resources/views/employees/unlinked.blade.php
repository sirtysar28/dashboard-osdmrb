@extends('layouts.app')

@section('page_title', 'Profil Pegawai')
@section('page_subtitle', 'Data kepegawaian mandiri')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-7 col-md-9">
        <div class="card shadow-sm border-0" style="border-radius: 1rem;">
            <div class="card-body p-5 text-center">

                <div class="mb-4">
                    <span class="d-inline-flex align-items-center justify-content-center rounded-circle"
                          style="width: 84px; height: 84px; background: rgba(232, 161, 60, .15); color: var(--osdmrb-accent, #e8a13c);">
                        <i class="bi bi-person-x" style="font-size: 2.4rem;"></i>
                    </span>
                </div>

                <h4 class="fw-semibold mb-2" style="color: var(--osdmrb-primary, #163d4f);">
                    Data Kepegawaian Belum Tersedia
                </h4>

                <p class="text-secondary mb-1">
                    Halo, <strong>{{ auth()->user()->name }}</strong>.
                </p>
                <p class="text-secondary">
                    Akun Anda belum terhubung dengan data pegawai di instansi, sehingga
                    profil kepegawaian belum dapat ditampilkan. Silakan hubungi
                    <strong>Admin Bagian / Biro SDM</strong> agar data Anda segera
                    dihubungkan ke dalam aplikasi.
                </p>

                <div class="alert alert-light border d-flex align-items-start text-start rounded-3 mt-4" role="alert">
                    <i class="bi bi-info-circle-fill me-2 mt-1" style="color: var(--osdmrb-primary-light, #2b5f78);"></i>
                    <div>
                        Sementara menunggu data dihubungkan, Anda tetap dapat memperbarui
                        <strong>informasi akun</strong> (nama, email &amp; kata sandi) melalui
                        halaman Pengaturan Akun.
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-2 justify-content-center mt-4">
                    <a href="{{ route('home') }}" class="btn btn-outline-secondary">
                        <i class="bi bi-house-door me-1"></i> Kembali ke Beranda
                    </a>
                    <a href="{{ route('profile.edit') }}" class="btn text-white"
                       style="background: var(--osdmrb-primary, #163d4f);">
                        <i class="bi bi-gear me-1"></i> Pengaturan Akun
                    </a>
                </div>

            </div>
        </div>
    </div>
</div>
@endsection
