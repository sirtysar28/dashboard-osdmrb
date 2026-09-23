@extends('layouts.app')

@section('page_title', 'Pengaturan SMTP & Notifikasi')
@section('page_subtitle', 'Konfigurasi email server & notifikasi aplikasi (Administrator Utama)')

@section('content')

@include('settings.partials.nav')

<form method="POST" action="{{ route('settings.smtp.update') }}">
    @csrf

    <div class="row g-3">

        {{-- ===================== KONEKSI SMTP ===================== --}}
        <div class="col-lg-7">
            <div class="chart-card h-100">
                <h5><i class="bi bi-envelope-check me-2"></i>Koneksi SMTP</h5>

                <div class="alert {{ $smtp_enabled ? 'alert-success' : 'alert-warning' }} small py-2">
                    @if ($smtp_enabled)
                        <i class="bi bi-check-circle me-1"></i> SMTP aktif — semua email notifikasi dikirim lewat server berikut.
                    @else
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        SMTP nonaktif — email ditulis ke log aplikasi (mode pengembangan).
                        Aktifkan &amp; isi konfigurasi untuk pengiriman email sesungguhnya.
                    @endif
                </div>

                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" role="switch" name="smtp_enabled" value="1"
                           id="smtpEnabled" {{ $smtp_enabled ? 'checked' : '' }}>
                    <label class="form-check-label" for="smtpEnabled">Aktifkan SMTP kustom</label>
                </div>

                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label">SMTP Host</label>
                        <input type="text" name="smtp_host" class="form-control form-control-sm"
                               value="{{ old('smtp_host', $smtp_host) }}" placeholder="smtp.kementrans.go.id">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Port</label>
                        <input type="number" name="smtp_port" class="form-control form-control-sm"
                               value="{{ old('smtp_port', $smtp_port) }}" placeholder="587">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Enkripsi</label>
                        <select name="smtp_encryption" class="form-select form-select-sm">
                            @foreach (['tls' => 'TLS', 'ssl' => 'SSL', 'none' => 'Tanpa Enkripsi'] as $value => $label)
                                <option value="{{ $value }}" {{ old('smtp_encryption', $smtp_encryption) === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Username</label>
                        <input type="text" name="smtp_username" class="form-control form-control-sm"
                               value="{{ old('smtp_username', $smtp_username) }}" autocomplete="off">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Password</label>
                        <input type="password" name="smtp_password" class="form-control form-control-sm"
                               value="" placeholder="{{ $smtp_password ? '•••••••• (biarkan kosong agar tetap)' : '' }}" autocomplete="new-password">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email Pengirim</label>
                        <input type="email" name="smtp_from_address" class="form-control form-control-sm"
                               value="{{ old('smtp_from_address', $smtp_from_address) }}">
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Nama Pengirim</label>
                        <input type="text" name="smtp_from_name" class="form-control form-control-sm"
                               value="{{ old('smtp_from_name', $smtp_from_name) }}">
                    </div>
                </div>

                <hr>

                <label class="form-label">Kirim Email Percobaan</label>
                <div class="input-group input-group-sm">
                    <input type="email" name="test_email" class="form-control" placeholder="email@tujuan.go.id">
                    <button type="submit" class="btn btn-outline-osdmrb" data-loader-text="Mengirim...">
                        <i class="bi bi-send"></i> Test Kirim
                    </button>
                </div>
                <div class="form-text">Simpan konfigurasi sekaligus kirim email percobaan berformat HTML ke alamat tersebut.</div>
            </div>
        </div>

        {{-- ===================== NOTIFIKASI ===================== --}}
        <div class="col-lg-5">
            <div class="chart-card h-100">
                <h5><i class="bi bi-bell me-2"></i>Notifikasi Email</h5>
                <p class="small text-muted">
                    Pilih peristiwa yang memicu pengiriman email (desain HTML mengikuti template aplikasi).
                </p>

                @php
                    $notifications = [
                        ['key' => 'notify_login', 'label' => 'Login baru', 'desc' => 'Dikirim ke pemilik akun saat berhasil login'],
                        ['key' => 'notify_password', 'label' => 'Ganti password', 'desc' => 'Dikirim ke pemilik akun saat password diubah'],
                        ['key' => 'notify_register', 'label' => 'Registrasi akun baru', 'desc' => 'Dikirim ke Administrator Utama'],
                        ['key' => 'notify_sop', 'label' => 'Pengajuan dokumen SOP', 'desc' => 'Dikirim ke Administrator Utama saat SOP diunggah'],
                        ['key' => 'notify_letter', 'label' => 'Pengajuan surat', 'desc' => 'Dikirim ke Administrator Utama saat pegawai mengajukan surat'],
                        ['key' => 'notify_leave', 'label' => 'Pengajuan cuti', 'desc' => 'Dikirim ke Administrator Utama saat pegawai mengajukan cuti'],
                    ];
                @endphp

                @foreach ($notifications as $notification)
                    <div class="d-flex align-items-center justify-content-between border rounded-3 px-3 py-2 mb-2">
                        <div>
                            <div class="fw-semibold" style="font-size:13.5px">{{ $notification['label'] }}</div>
                            <small class="text-muted">{{ $notification['desc'] }}</small>
                        </div>
                        <div class="form-check form-switch m-0">
                            <input class="form-check-input" type="checkbox" role="switch"
                                   name="{{ $notification['key'] }}" value="1"
                                   id="{{ $notification['key'] }}"
                                   {{ \App\Models\Setting::bool($notification['key']) ? 'checked' : '' }}>
                        </div>
                    </div>
                @endforeach

                {{-- login 2 lapis: kode OTP via email --}}
                <div class="d-flex align-items-center justify-content-between border rounded-3 px-3 py-2 mb-2"
                     style="border-color: var(--osdmrb-primary) !important;">
                    <div>
                        <div class="fw-semibold" style="font-size:13.5px">Login 2 Lapis — Kode OTP ke Email</div>
                        <small class="text-muted">Setelah password benar, wajib input kode OTP yang dikirim ke email terdaftar</small>
                    </div>
                    <div class="form-check form-switch m-0">
                        <input class="form-check-input" type="checkbox" role="switch" name="otp_enabled" value="1"
                               id="otp_enabled" {{ $otp_enabled ? 'checked' : '' }}>
                        <label class="form-check-label small" for="otp_enabled">{{ $otp_enabled ? 'Aktif' : 'Nonaktif' }}</label>
                    </div>
                </div>

                <div class="mt-3">
                    <label class="form-label">Email Tujuan Notifikasi Admin</label>
                    <input type="text" name="notify_recipient" class="form-control form-control-sm"
                           value="{{ old('notify_recipient', $notify_recipient) }}"
                           placeholder="Kosong = semua email Administrator Utama">
                    <div class="form-text">Bisa beberapa email, pisahkan dengan koma.</div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <button type="submit" class="btn btn-osdmrb px-4" data-loader-text="Menyimpan pengaturan SMTP">
                <i class="bi bi-check-lg"></i> Simpan Pengaturan
            </button>
        </div>
    </div>
</form>

@endsection
