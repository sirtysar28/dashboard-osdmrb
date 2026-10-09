# Dashboard Biro OSDMRB

klo tampilan dekstop card ny

Dibangun dengan **Laravel 11**, **PHP 8.2+**, **MySQL/MariaDB**, **Bootstrap 5**, **Chart.js**, **Spatie Laravel Permission**, dan **DomPDF**.

---

## 🧭 Riwayat Versi

Format `MAJOR.FEATURE.PATCH` (env `APP_VERSION`, tampil di footer aplikasi):

- **Update besar / fitur baru** → digit ke-2 naik satu tingkat (mis. `1.2.0` → `1.3.0`)
- **Update kecil / perbaikan** → digit ke-3 naik satu tingkat (mis. `1.2.0` → `1.2.1`)

| Versi | Tanggal | Ringkasan |
|---|---|---|
| `1.0.3` | 9 Okt 2026 | **Catatan Masukan 7 Okt** — urutan pilihan unit kerja dirapikan di semua dropdown (Eselon I dashboard, form pegawai ASN/Non ASN, master data), filter pendidikan/golongan/gender tidak lagi tergabung Non ASN, CV riwayat pendidikan dimulai dari Pendidikan 1 + jenjang dituliskan (Diploma/Sarjana/Magister/Doktor, migrasi `2026_10_09_000002` merapikan data terbalik), analis jabatan fungsional: pemangku diganti jumlah pegawai yang bisa diklik (popup daftar pegawai), formasi kosong tidak dimunculkan (fungsional & struktural), jabatan salah nama "Sekretaris Direktur Jenderal" dihapus, penyempurnaan tampilan mobile (input 16px anti-zoom iOS, safe-area, tombol filter mudah di-tap) |
| `1.0.2` | 7 Okt 2026 | **Catatan Masukan 30 Sept** — urutan unit kerja resmi (eselon I–III & balai, migrasi `sort_order`), card hasil filter dashboard dipindah ke atas, filter unit kerja "kosong", urutan riwayat pendidikan (P1=S1) + Tingkat terakhir pindah ke Data Personal CV, dropdown perguruan tinggi dengan kotak pencarian, analis jabatan fungsional/struktural/pelaksana (kolom Nomor, filter nama jabatan & jenjang, pagination, nama jabatan umum, data pelaksana dari data pegawai), menu SOP Kementerian → Dokumen Kepegawaian, riwayat diklat wajib upload sertifikat, pencarian cuti khusus Admin Bagian & Super Admin |
| `1.0.1` | 28 Sep 2026 | Paritas filter ASN↔Direktori, analis jabatan fungsional/struktural mengenali semua jabatan + pemangku, kop form cuti format resmi |
| `1.0.0` | ≤ 25 Sep 2026 | Baseline — seluruh update hingga 25 September 2026 (chat internal, analisis jabatan, formulir cuti, dedupe unit kerja, dll.) |

---

## ✨ Fitur

### 1. Dashboard Kepegawaian (Admin Instansi)
- KPI: total pegawai, jabatan struktural/fungsional, rata-rata usia, proyeksi pensiun
- Chart: tingkat pendidikan, distribusi usia, proyeksi pensiun 5 tahun, komposisi gender, distribusi unit kerja, status kepegawaian (Chart.js)
- Filter dinamis: Eselon I, Eselon II, Balai, status ASN, golongan, pendidikan, pencarian nama/NIP
- API endpoint `GET /api/dashboard/statistics` untuk kebutuhan integrasi lain
- Arsitektur **DashboardService** terpisah dari controller (mudah dikembangkan)

### 2. Manajemen Data Pegawai (Admin)
- CRUD data pegawai lengkap: identitas, kepegawaian, pendidikan, administrasi
- Riwayat jabatan (`employee_positions`) dengan jabatan saat ini (`is_current`)
- Detail pegawai menampilkan profil, riwayat jabatan, dan riwayat pengajuan surat
- **Export ke Excel (.xlsx) & PDF** — mengikuti filter aktif (pencarian/status/unit)
- **Import massal** dari Excel/CSV (`/employees/import`) dengan unduh template;
  NIP yang sudah ada otomatis diperbarui, baris tidak valid dilaporkan

### 3. Master Data (Admin)
- Unit kerja bertingkat: **Kementerian → Eselon I → Eselon II → Bagian (Es III) → Balai**
- Tingkat pendidikan, golongan/pangkat (PNS & PPPK), status kepegawaian, level jabatan, jenis & nama jabatan, klasifikasi arsip
- **Tombol ubah (edit) + modal** untuk semua tabel master, termasuk jenis surat & pengguna
- **Export master data ke Excel (multi-sheet) & PDF**

### 4. Layanan Persuratan (Semua Pengguna)
Workflow pengajuan surat:

```
Pegawai mengajukan (PENDING)
   → Admin memverifikasi (VERIFIED)
      → Admin menyetujui & nomor otomatis terbit (APPROVED)
         → Surat dicetak/diunduh PDF (kop resmi + tanda tangan)
   ↘ Dapat ditolak pada tahap verifikasi/persetujuan (REJECTED, wajib alasan)
```

- 6 jenis surat siap pakai: Surat Tugas, Surat Keterangan, Surat Keterangan Kerja, Surat Rekomendasi, Surat Izin Kegiatas, Surat Keterangan Penghasilan
- Penomoran otomatis dengan format konfigurabel, mis. `001/ST/OSDMRB/VIII/2026`
- Timeline riwayat workflow setiap surat (siapa, kapan, aksi apa)
- cetak PDF resmi berkop (DomPDF)
- **Export daftar surat ke Excel & PDF** (mengikuti filter aktif)
- **Integrasi kearsipan**: surat yang disetujui otomatis terarsipkan (`ARS/YYYY/0001`)

### 5. Layanan Kearsipan / Dokumen Arsip (Semua Pengguna)
Workflow peminjaman arsip:

```
Pegawai mengajukan peminjaman dari katalog (PENDING)
   → Admin menyetujui → status arsip otomatis DIPINJAM (APPROVED)
      → Pegawai/admin menandai pengembalian → arsip kembali TERSEDIA (RETURNED)
   ↘ Dapat ditolak dengan alasan (REJECTED)
```

- Katalog arsip dengan pencarian & filter (klasifikasi, jenis, tahun, status) + **export Excel & PDF**
- **Export daftar peminjaman arsip ke Excel & PDF**
- Metadata kearsipan mengacu praktik ANRI: klasifikasi, retensi (Aktif/Inaktif/Musnah/Dinilai Kembali/Permanen), masa & batas simpan, lokasi fisik (ruang/rak/boks)
- Jenis dokumen: Surat Masuk, Surat Keluar, SK, Kontrak, Laporan, Dokumen Pegawai, Lainnya
- Unggah & unduh salinan digital (maks 10 MB)
- Visibilitas arsip: `PUBLIK` (semua pegawai) atau `INTERNAL` (admin saja, mis. kontrak PPPK)
- Arsip dapat diasosiasikan ke pegawai, unit pengolah, maupun surat terkait
- Statistik kearsipan admin: total, tersedia, dipinjam, inaktif, permanen, permintaan pinjam

### 6. Halaman User Pegawai
- Beranda ringkas: profil kepegawaian pribadi + statistik pengajuan surat
- Formulir pengajuan surat mandiri
- Riwayat pengajuan + unduh surat yang disetujui
- Telusuri katalog arsip, ajukan peminjaman arsip, pantau status peminjaman

### 7. Administrasi (Admin)
- Manajemen pengguna: buat akun admin/pegawai, tautkan ke data pegawai (**+ edit & export Excel/PDF**)
- Role & permission (Spatie): role `admin` (verifikasi + persetujuan) dan `pegawai`
- Master klasifikasi arsip (tab Master Data)

### 8. UX & Keamanan Tambahan
- **Spinner/loader global**: tampil saat login, logout, pindah antar halaman, dan masuk dashboard
- **Login modern** (glass card + gradient bergerak) dengan **captcha huruf** (case-insensitive, bisa di-refresh)
- Layout formulir "tambah data" dirapikan: lebar maksimal konsisten & baris aksi kompak
- **Logo Kementerian Transmigrasi RI** di halaman login, header, sidebar, kop surat PDF, header PDF export & favicon (SVG + ICO/PNG)

---

## 🚀 Cara Menjalankan

### Persyaratan
- PHP >= 8.2 (ekstensi: pdo_mysql, mbstring, openssl, gd, zip)
- Composer
- MySQL / MariaDB

### Instalasi

```bash
cd dashboard-osdmrb

# 1. Install dependency
composer install

# 2. Salin & konfigurasi environment
cp .env.example .env
php artisan key:generate
# sesuaikan DB_DATABASE / DB_USERNAME / DB_PASSWORD pada .env

# 3. Buat database
mysql -u root -e "CREATE DATABASE dashboard_osdmrb CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 4. Migrasi + seed (master data, 197 pegawai riil dari Excel, contoh surat & arsip)
php artisan migrate:fresh --seed

# 5. Tautkan penyimpanan (untuk salinan digital arsip)
php artisan storage:link

# 6. Jalankan
php artisan serve
```



---

## 📊 Sumber Data

Data pegawai riil **197 pegawai** (PNS 110, PPPK Penuh Waktu 37, PPPK Paruh Waktu 50) bersumber dari `Data Dashboard Revisi.xlsx` (sheet *DATA PEG*), disimpan sebagai `database/data/employees.json` dan di-seed oleh `EmployeeSeeder`. Kolom yang dipetakan meliputi: nama, status, level eselon, level jabatan/fungsional, pangkat & golongan, TMT jabatan/golongan, pendidikan (3 tingkat), tempat/tanggal lahir, usia, batas usia pensiun, jenis kelamin, dan agama.

> NIP dibuat dummy (18 digit) karena tidak tersedia di file sumber.

---

## 🗂️ Struktur Utama

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── DashboardController.php      # Dashboard + API statistik
│   │   ├── EmployeeController.php       # CRUD pegawai
│   │   ├── MasterDataController.php     # Semua master data
│   │   ├── LetterController.php         # Workflow persuratan
│   │   ├── LetterTypeController.php     # Jenis surat
│   │   ├── ArchiveController.php        # Kearsipan (katalog, CRUD, unduh)
│   │   ├── ArchiveLoanController.php    # Peminjaman arsip
│   │   ├── UserController.php           # Manajemen pengguna
│   │   └── HomeController.php           # Beranda pegawai
│   └── Middleware/EnsureRole.php        # Guard role admin/pegawai
├── Models/                              # 15 model + relasi Eloquent
└── Services/DashboardService.php        # Agregasi KPI & chart

database/
├── data/employees.json                  # 197 data pegawai dari Excel
├── migrations/                          # 4 migration aplikasi
└── seeders/                             # Master, pegawai, surat, role

resources/views/
├── layouts/app.blade.php                # Layout sidebar + topbar
├── dashboard/index.blade.php            # Halaman dashboard
├── employees/                           # index, form, show
├── master/index.blade.php               # Tab master data (termasuk klasifikasi arsip)
├── letters/                             # index, form, show, pdf, types
├── archives/                            # index, form, show, loans (peminjaman)
├── users/index.blade.php
└── home.blade.php                       # Beranda pegawai
```

### Skema Database

```
units (parent-child: KEMENTERIAN→ES_I→ES_II→ES_III→BALAI)
education_levels ┐
ranks            │
employment_statuses ├─ employees ── employee_positions ── positions
job_levels       │        │                              └─ position_types
                 └────────┤                              └─ job_levels
                          ├── letters ── letter_types
                          │      └── letter_logs (riwayat workflow)
                          ├── archives ── archive_categories (klasifikasi)
                          │      ├── archive_loans (peminjaman arsip)
                          │      └── letter_id (tautan surat tersetujui)
                          └── users (role: admin / pegawai)
```

---

## 🔒 Hak Akses

| Halaman | Admin | Biro SDM | Pegawai |
|---|:---:|:---:|:---:|
| Dashboard statistik | ✅ | ✅ | ❌ (punya Beranda `/home`) |
| CRUD data pegawai (termasuk import/export) | ✅ | ✅ | ❌ (hanya profil sendiri `/pegawai/profil`) |
| Master data (unit, golongan, dll.) | ✅ (kelola) | 👁 hanya lihat + export | ❌ |
| Jenis surat | ✅ (kelola) | 👁 hanya lihat + export | ❌ |
| Persuratan: ajukan, riwayat, cetak | ✅ (semua) | ✅ (semua) | ✅ (miliknya) |
| Verifikasi / persetujuan / penolakan surat | ✅ | ✅ | ❌ |
| Kearsipan: katalog, detail, unduh | ✅ (semua) | ✅ (semua) | ✅ (publik + miliknya) |
| Unggah/ubah/hapus dokumen arsip | ✅ | ✅ | ❌ |
| Peminjaman arsip: kelola/approval | ✅ | ✅ | ✅ (miliknya) |
| Manajemen pengguna | ✅ | ❌ | ❌ |
| Profil akun | ✅ | ✅ | ✅ |

> Role `biro_sdm` dibuat oleh migration `2026_09_01_000001_create_biro_sdm_role.php`
> (untuk database yang sudah berjalan cukup jalankan `php artisan migrate`)
> dan otomatis mendapat permission `verify letters` + `approve letters`.

---

## 📝 Catatan Pengembangan

- Format nomor surat dapat diubah per jenis surat, placeholder: `{no}`, `{romawi}`, `{tahun}`
- Nama pejabat penandatangan surat dapat diatur via `config('app.ttd_nama')` & `config('app.ttd_nip')`
- Struktur unit kerja, jenis jabatan, dan seluruh master dapat ditambah dari halaman **Master Data**
- Bahasa antarmasa & format tanggal: Indonesia (`APP_LOCALE=id`)

---

## 🚀 Update 6 September 2026

### Hak akses menjadi 3 jenis
| Fitur | Administrator Utama (`super_admin`) | Admin Bagian (`admin` / `biro_sdm`) | Pegawai (`pegawai`) |
|---|:---:|:---:|:---:|
| Semua fitur admin bagian | ✅ | ✅ | ❌ |
| Manajemen pengguna | ✅ | ❌ | ❌ |
| Pengaturan tampilan/menu/tema/pengumuman/SMTP | ✅ | ❌ | ❌ |
| Beranda & layanan mandiri | ❌ | ❌ | ✅ |

> Akun dengan role `admin` lama otomatis mendapat role `super_admin` saat migrasi.

### Menu bisa di-hide/unhide (Pengaturan → Tampilan & Menu, khusus super admin)
Menu berikut **default disembunyikan** dan bisa diaktifkan lagi kapan saja:
Persuratan, Kearsipan (termasuk upload dokumen), Reformasi Birokrasi, Manajemen Talenta, Diklat & Pengembangan.

### Pengumuman dashboard (Pengaturan → Pengumuman)
Banner gambar + running text di paling atas **semua** dashboard (admin & pegawai). Hanya super admin yang dapat mengelola.

### Dashboard baru
- **8 stat-card dalam 2 baris × 4 kolom, semuanya bisa diklik** ke menu datanya
  (Total ASN, Non ASN, Struktural, Fungsional, PPPK, Akan Pensiun Tahun Ini, Pensiun ≤ 2 Tahun, Pengunjung Hari Ini).
  Kartu "Surat Menunggu" diganti kartu pensiun.
- Widget **survei masukan & saran** aplikasi (hasilnya di menu *Survei & Masukan* untuk admin).
- **Statistik pengunjung** + audit log di menu *Audit Log & Pengunjung*.

### Data pegawai ASN & Non ASN
- Menu **Data Pegawai ASN** dan menu baru **Pegawai Non ASN** (pramubakti, security, cleaning service).
- Detail pegawai menampilkan **kapan naik pangkat/jabatan** & **BUP pensiun** —
  aturan: 60 th untuk Eselon I/II & Fungsional Madya, selain itu 58 th (dihitung otomatis bila kolom kosong).

### Import Excel tanpa template baru
Form import menerima **langsung berkas "Data Dashboard.xlsx"** instansi (header: NAMA, NIP, STATUS,
Es. I/II, PANGKAT, GOLONGAN, KENAIKAN PANGKAT, BATAS USIA PENSIUN, dst). Sel formula otomatis dihitung
(mis. `=EDATE()` untuk kenaikan pangkat). Template unduhan pun dibuat dengan format identik.

Import via command (untuk server):
```bash
php artisan employees:import "Data Dashboard.xlsx"                        # ASN (auto-deteksi)
php artisan employees:import "berkas.xlsx" --type=nonasn                  # Non ASN (kategori otomatis)
php artisan employees:import "berkas.xlsx" --type=nonasn --category=Pramubakti
```

### Pegawai Non ASN — form & import terpisah
Halaman **Pegawai Non ASN** (`/employees/non-asn`) memakai form tersendiri yang jauh lebih sederhana
dari form ASN (nama, ID, kategori, jabatan, penempatan, kontak) — tanpa kolom pangkat/eselon/pendidikan/BUP.

Tombol **Import Excel** di halaman tersebut dapat mengunggah **langsung** berkas daftar non ASN instansi
apa adanya — posisi header yang tidak beraturan dikenali otomatis:
- **Format bank data baru (Okt 2026)**: `NO. | NAMA LENGKAP | NIK | NO. HP | EMAIL | AGAMA | TEMPAT |
  TANGGAL LAHIR | ALAMAT DOMISILI | UNIT PENEMPATAN` — seluruh kolom terbaca lengkap: NIK menjadi ID
  pegawai, unit penempatan dipetakan ke unit kerja dashboard (segment sebelum koma, tahan typo/singkatan
  seperti "Ditjen", "Transmigarsi"), tanggal lahir mendukung sel tanggal Excel, serial, `24/09/1985`,
  maupun `6 Januari 2000` (typo seperti "Deseember" tetap lolos). Satu berkas multi-sheet
  (Pramubakti/Driver/Teknisi) diproses sekaligus — kategori mengikuti judul sheet.
- **Format lama**: daftar Personil PB dengan ID + unit kerja, daftar Security dengan jabatan,
  daftar Cleaning Service nama saja.

Kategori terdeteksi dari nama berkas / judul sheet (`security` / `cleaning` / `pramubakti` / `driver` /
`teknisi` / `personil pb`), baris kosong & kolom tanda tangan otomatis dilewati, dan import ulang bersifat
**aman** (pegawai lama diperbarui berdasarkan NIK/ID atau nama+kategori, tidak mendobel data).

### Login 2 lapis OTP email (default NONAKTIF)
Sekarang login seperti biasa (email + password + captcha). Fitur kode OTP ke email terdaftar
dapat **diaktifkan kapan saja dari Pengaturan → SMTP & Notifikasi → “Login 2 Lapis — Kode OTP ke Email”**.

### SMTP & notifikasi email (Pengaturan → SMTP & Notifikasi, khusus super admin)
- Konfigurasi SMTP runtime + tombol **Test Kirim** email percobaan.
- Notifikasi HTML berdesain template aplikasi untuk: login baru, ganti password, registrasi akun,
  pengajuan dokumen SOP, dan pengajuan surat (masing-masing bisa dinyalakan/dimatikan).

### Tema warna (Pengaturan → Tampilan & Menu)
Warna tombol, menu sidebar, header tabel & grafik dapat diubah-ubah (plus preset cepat).

### Deployment update ini
```bash
composer install --no-dev
php artisan migrate --force
# lalu import data pegawai bila perlu (lihat perintah di atas)
```

## 🚀 Update 8 September 2026

### Zona waktu GMT+7 (WIB)
Seluruh tampilan waktu (audit log, email notifikasi, OTP, dsb.) kini memakai **Asia/Jakarta (GMT+7)**.
Data lama yang sempat terekam dengan jam UTC otomatis digeser +7 jam oleh migrasi agar tetap konsisten.

### Statistik pengunjung tidak lagi 0
- Statistik "Pengunjung" kini menghitung **kunjungan halaman + keberhasilan login** sehingga selalu terisi
  setiap ada pengguna yang masuk ke aplikasi (dashboard & menu Audit Log & Pengunjung).
- Middleware pencatat kunjungan ditulis ulang (kunci sesi datar, bebas ambiguitas) dan kegagalan
  pencatatan kini tercatat di `laravel.log` alih-alih disembunyikan diam-diam.

### Kenaikan jabatan/pangkat di dashboard
- 3 kartu statistik baru: kenaikan tahun ini, kenaikan ≤ 1 tahun, dan jatuh tempo kenaikan.
- Chart **Proyeksi Kenaikan Jabatan/Pangkat 5 tahun** + tabel **Kenaikan Terdekat**
  (estimasi: kolom *Kenaikan Pangkat/Jabatan* bila terisi, jika tidak TMT golongan + 4 tahun).
- Filter daftar pegawai **Kenaikan Jabatan ≤ 4 tahun** (`?naik=1`), juga tersedia di endpoint
  `GET /api/dashboard/statistics` (kunci `promotion`).

### Master kampus + dropdown pendidikan terakhir
- Tab **Kampus** baru di Master Data (negeri/swasta/luar negeri, ±120 kampus terisi otomatis) + CRUD & export.
- Form pegawai: Pendidikan 1/2/3 kini **dropdown kampus** + kolom jurusan (opsional) + pilihan *Isi Manual*
  untuk kampus di luar master. Data lama hasil import dikenali otomatis dan dropdown terpilih saat edit.

### Tombol kirim ulang OTP aktif otomatis
Tombol "Kirim ulang kode" di halaman OTP kini punya **countdown 60 detik** dan aktif kembali sendiri
tanpa perlu memuat ulang halaman.

### Link reset password sampai ke email
Email reset password kini dikirim memakai **SMTP yang dikonfigurasi di Pengaturan** (template HTML aplikasi).
Bila SMTP belum diatur sama sekali, pengguna diberi pesan jelas untuk menghubungi Administrator Utama —
tidak lagi "terkirim" padahal hanya masuk file log.

### Captcha konsisten 5 karakter
Kode captcha login **selalu 5 huruf** (sebelumnya acak 5–6).

### Deployment update ini
```bash
composer install --no-dev
php artisan migrate --force   # WAJIB dijalankan bersamaan dengan deploy (pergeseran data lama +7 jam)
php artisan config:clear
php artisan route:clear
```
> Catatan: migrasi `2026_09_08_000001` menggeser data lama +7 jam berdasarkan baris yang ada saat
> migrasi dijalankan — deploy kode & migrate dilakukan dalam satu rangkaian (jangan beri jeda pemakaian).

---

## 🚀 Update 8 September 2026 — Bagian 2 (catatan sore)

### Menu baru: Analisis Jabatan Struktural
Modul baru di sidebar (di bawah Analisis Jabatan Fungsional) berisi:
- Daftar jabatan struktural (JPT & pejabat administratif) beserta jumlah pemangku & status formasi,
- Distribusi pejabat struktural per jenjang eselon (II/III/IV) dan sebaran per unit eselon II.

### Struktur organisasi bisa diperkecil & diperbesar
Halaman **Struktur Organisasi** kini punya tombol **− / % / + / reset** untuk *minimize–maximize* bagan:
- Berlaku untuk bagan unit kerja (tree) maupun bagan resmi,
- Bagan yang diperbesar bisa digeser (drag) & di-zoom dengan `Ctrl` + scroll.

### Bagan resmi Kementerian (SOTK)
Gambar struktur organisasi resmi ditambahkan ke halaman Struktur Organisasi —
diambil dari <https://www.transmigrasi.go.id/profil/struktur-organisasi/> (`public/images/struktur-organisasi.png`)
lengkap dengan tautan sumbernya.

### Timer slider pengumuman mengikuti running text
Slider banner pengumuman tidak lagi berganti tiap 7 detik tetap. Durasi tiap slide kini
**dihitung dari panjang running text** (kecepatan tetap ±85 px/detik, min. 8 detik) sehingga slide
berganti **tepat setelah running text selesai berputar**; slide tanpa running text memakai fallback 8 detik.

### Daftar unit kerja diperbarui sesuai file resmi (SOTK)
37 unit kerja diperbarui dari file *Daftar Nama Unit Kerja* Kementerian:
- **Eselon I**: Setjen, Itjen, Ditjen Pengembangan Ekonomi & Pemberdayaan Masyarakat Transmigrasi,
  Ditjen Pembangunan & Pengembangan Kawasan Transmigrasi,
- **Eselon II**: seluruh biro, pusat, sekretariat ditjen, direktorat, inspektorat & staf ahli,
- **Balai**: BBTN Yogyakarta + BMT Pekanbaru, Banjarmasin, Denpasar.

Data lama (`DJ-01 Direktorat Jenderal Pembinaan`) digabung otomatis ke unit baru oleh migrasi
`2026_09_08_000002` sehingga relasi pegawai tetap tersambung.

### Info ukuran banner pada menu upload
Form tambah/ubah pengumuman kini mencantumkan **ukuran ideal 1084 × 250 px dan maksimal 1 MB**
(validasi ikut diperketat menjadi 1 MB).

### Filter kenaikan jabatan lebih lengkap
Filter **Kenaikan Jabatan** pada daftar pegawai kini memiliki opsi:
**Semua · Tahun ini · Kurang dari = 1 tahun · Kurang dari = 4 tahun** —
dengan estimasi yang sama seperti dashboard (kolom kenaikan bila terisi, jika tidak TMT golongan + 4 tahun).
Kartu dashboard "Kenaikan tahun ini" & "Kenaikan ≤ 1 tahun" langsung membuka filter terkait.

### Deployment update ini
```bash
composer install --no-dev
php artisan migrate --force   # menjalankan update unit kerja (seed idempotent)
php artisan config:clear
php artisan route:clear
```
# dashboard-osdmrb

---

## 🚀 Update 28 September 2026 (v1.0.1)

Sumber: `Update/Catatan Masukan 28 Sept.docx`. Dua poin pertama sudah sesuai (dipertahankan
tanpa perubahan), empat poin berikut diterapkan:

1. **(Sudah sesuai — tidak diubah)** Pilihan unit kerja pada edit data kepegawaian tidak lagi
   menampilkan nama unit yang double.
2. **(Sudah sesuai — tidak diubah)** Pencarian pegawai ASN tidak lagi menampilkan nama unit kerja
   di luar daftar (nama bagian).
3. **Hasil pencarian menu ASN & Direktori Pegawai kini SAMA** — direktori pegawai memakai logika
   *status efektif* yang sama dengan daftar pegawai ASN (`applyEffectiveStatusFilter`): saat
   difilter **CPNS**, pegawai berstatus ASN dengan TMT ASN kosong ikut tampil di **kedua menu**;
   filter ASN hanya menampilkan yang TMT ASN-nya terisi.
4. **Analis Jabatan Fungsional mengidentifikasi SEMUA jabatan dari data pegawai** — tidak lagi
   terbatas 10 jenis jabatan di master. Nama jabatan (fungsional & struktural) pada data pegawai
   otomatis dipindahkan ke master `positions` oleh `JabatanSyncService` (idempoten — aman
   diulang), jenjang `AHLI_UTAMA` ditambahkan, dan pemangku dihitung dari *nama jabatan* pada data
   pegawai + riwayat jabatan aktif. Nama pemangku (maks. 3 + "…lainnya") tampil di tabel.
5. **Analis Jabatan Struktural menampilkan pimpinan di tiap jabatan** — status tidak lagi kosong:
   tiap jabatan (mis. "Kepala Bagian Perencanaan dan Data") menampilkan pemangkunya (badge
   **Terisi**), pegawai tanpa riwayat jabatan otomatis diberi riwayat jabatan aktif, dan jabatan
   terisi diurutkan paling atas.
6. **Kop formulir permohonan cuti mengikuti format resmi** (`FORM CUTI KOSONG — ASN`): alamat
   *Jalan TMP. Kalibata No. 17* dan situs web **www.kemendesa.go.id** kini baris terpisah
   (sebelumnya satu baris & tertulis "kemendes"), ukuran huruf kop disesuaikan dokumen resmi.

**Penomoran versi mulai diberlakukan** — lihat [Riwayat Versi](#-riwayat-versi). Update ini
berupa perbaikan/penyempurnaan (minor) → versi naik dari `1.0.0` ke **`1.0.1`**, ditampilkan di
footer aplikasi (`config('app.version')`, env `APP_VERSION`).

### Deployment update ini
```bash
composer install --no-dev
php artisan migrate --force   # sinkronisasi master jabatan dari data pegawai
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

Pengujian: `tests/Feature/UpdateSeptember28Test.php`.

Migration: `2026_09_28_000001_sync_jabatan_dari_data_pegawai.php`

---

## 🚀 Update 25 September 2026 (Tindak lanjut perbaikan filter & Non ASN)

1. **Kartu Non ASN kini mengikuti filter (diperbaiki)** — seluruh pegawai **Non ASN dipindahkan ke
   Sekretariat Jenderal** (migrasi `2026_09_25_000001`), karena unit kerjanya tidak diketahui.
   Kartu KPI Non ASN berubah ketika filter Eselon I/II/Balai diganti: filter **Sekretariat Jenderal**
   menampilkan seluruh Non ASN, filter eselon/balai lain menampilkan jumlah yang berbeda (0 bila
   tidak ada). Non ASN yang `unit_id`-nya NULL tetap dianggap berada di Setjen saat dihitung.
2. **Import/tambah Non ASN otomatis diberi unit Setjen** — `NonAsnEmployeesImport` & form Non ASN
   mengisi `unit_id` Sekretariat Jenderal bila tidak dipilih, sehingga data baru langsung terfilter
   benar. Kolom `employees.gender` dibuat **nullable** (berkas daftar Non ASN tidak memuat jenis
   kelamin, import gagal pada database mode ketat).
3. **Status efektif: TMT ASN kosong = CPNS** — pegawai berstatus **ASN dengan TMT ASN masih kosong**
   kini dihitung **CPNS** secara konsisten: muncul saat filter **CPNS** (dashboard & daftar pegawai),
   kartu KPI **ASN** hanya menghitung yang TMT ASN terisi, infografis *Status Kepegawaian* menampilkan
   segmen CPNS, dan profil/CV menampilkan status **CPNS** (`Employee::display_status`). PPPK tidak
   terpengaruh aturan ini.
4. **Filter & pencarian Master Data ➜ Unit Kerja** — pencarian **nama/kode unit**, filter **level/eselon**
   (Kementerian, Eselon I–III, Balai, Lainnya) dan **induk unit**; pagination mempertahankan filter aktif.
5. **Kop CV satu baris** — judul "KEMENTERIAN TRANSMIGRASI REPUBLIK INDONESIA" tidak lagi turun ke
   baris kedua (`white-space: nowrap`, ukuran disesuaikan) sehingga kop lebih ringkas dan isi CV
   naik ke atas.

Pengujian: `tests/Feature/UpdateSeptember25Test.php`.

### Deployment update ini
```bash
composer install --no-dev
php artisan migrate --force   # Non ASN dipindah ke Setjen + gender nullable
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

---

## 🚀 Update 24 September 2026 (Revisi catatan rapat 23 Sept)

Sumber: `Update/Catatan Rapat dashboard Osdmrb 23 Sept REV.docx`. Semua 8 poin telah diterapkan:

1. **Tanpa kata "aktif" pada nama ASN & PPPK** — kartu KPI dashboard kini cukup "ASN", "PPPK",
   dan "ASN & PPPK + Non ASN" (sebelumnya "PPPK Aktif", "berstatus ASN aktif", dst.).
2. **Data pribadi Detail Pegawai disembunyikan bagi user pegawai** — **No. HP** dan **Alamat**
   hanya terlihat oleh admin (admin/biro SDM/super admin) atau pemilik profil sendiri;
   pegawai lain melihat `•••••••• (disembunyikan — hanya admin)`.
3. **Unduh CV dibatasi** — CV (PDF) hanya dapat diunduh oleh **pegawai yang bersangkutan**
   atau **admin**; pegawai lain mendapat 403 dan tombol unduh tidak tampil di direktori/detail
   (proteksi di `EmployeeController@cv`, bukan hanya di tombol).
4. **Perbaikan judul CV** — judul resmi **"KEMENTERIAN TRANSMIGRASI REPUBLIK INDONESIA"**
   (bukan "Transigrasi"), **subjudul Biro OSDMRB dihilangkan**, dan **logo Kementerian
   Transmigrasi ditambahkan di paling atas**. Typo "TRANSIGRASI" juga diperbaiki pada kop
   surat (`letters/pdf`) dan formulir cuti (`cuti/pdf`).
5. **Filter dashboard menampilkan jumlah ASN & PPPK berikut infografisnya** — seluruh kartu KPI
   (Total, ASN, PPPK, Non ASN) dan semua grafik mengikuti filter aktif secara konsisten.
6. **Non ASN tidak lagi muncul sama di semua filter** — jumlah KPI Non ASN kini **mengikuti filter
   unit kerja (Eselon I/II/Balai beserta turunannya) & pencarian** melalui `DashboardService::nonAsnQuery()`,
   sehingga jumlah Non ASN berbeda-beda per eselon/balai.
7. **Pencarian direktori pegawai diperbaiki** — pencarian nama/NIP di `/direktori-pegawai` sebelumnya
   selalu mencari nilai `1` (bug parameter `when($request->filled('search'))`); kini berfungsi normal.
8. **Struktur Unit Kerja Eselon I tidak dobel** — total **tepat 4 unit Eselon I**
   (Setjen, Itjen, Ditjen Pengembangan Ekonomi & Pemberdayaan Masyarakat Transmigrasi, Ditjen
   Pembangunan & Pengembangan Kawasan Transmigrasi). Duplikat digabungkan otomatis (pegawai,
   riwayat jabatan, arsip, dan unit turunan dipindah lebih dulu), bagan berakar pada unit
   KEMENTERIAN yang sebenarnya, dan **pegawai Non ASN tanpa unit kerja dimasukkan ke
   Sekretariat Jenderal** (migrasi `2026_09_24_000001`, idempoten & aman diulang).

### Deployment update ini
```bash
composer install --no-dev
php artisan migrate --force   # dedupe Eselon I + Non ASN masuk Setjen
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

---

## 🚀 Update 22 September 2026

Penyempurnaan filter & formulir cuti (poin tambahan setelah catatan 17-09):

1. **Dropdown multi-select checklist untuk filter** — filter yang bisa memilih lebih dari satu
   (status kepegawaian, unit kerja, kategori non ASN, jenis cuti, status pengajuan) kini berupa
   **dropdown yang membuka panel checklist saat diklik** (tinggi panel mengikuti jumlah opsi,
   maks. 280px lalu scroll). Pilihan dapat dicentang lebih dari satu, tersedia aksi *Pilih semua* /
   *Hapus*, dan label tombol menampilkan pilihan aktif (mis. "ASN, PPPK" atau "3 dipilih: …").
   Diterapkan di: daftar pegawai ASN, direktori pegawai, pegawai non ASN, dan daftar pengajuan cuti
   (jenis & status cuti kini juga bisa >1, kompatibel dengan tautan lama satu nilai).
2. **Formulir pengajuan cuti — autofill pegawai login** — data pegawai (Bagian I) terisi otomatis
   sesuai pegawai yang login; Admin / Biro SDM (HRD) dapat mengganti pegawai melalui
   **pencarian nama/NIP** pada formulir.
3. **Bagian V Catatan Cuti — nominal sisa cuti otomatis** — kolom **N-2 / N-1 / N** terisi otomatis
   dari sistem: hak 12 hari/tahun dikurangi cuti tahunan yang pernah diajukan (yang tidak ditolak),
   per tahun berjalan; nominal mengikuti pegawai yang dipilih admin.
   Pegawai: readonly (nilai sistem). **Admin/HRD: dapat menyunting nominal** dan mengisi kolom
   **Keterangan cuti** (tersimpan & tercetak di detail + PDF).
   Kolom baru: `balance_year`, `annual_n2`, `annual_n1`, `annual_n`, `leave_note`
   (migrasi `2026_09_22_000001`).
4. **Semua kartu statistik dashboard kini dapat diklik** — termasuk dua kartu yang sebelumnya statis:
   **Jatuh Tempo Kenaikan** (menuju filter `?naik=overdue` — estimasi kenaikan sudah terlewat) dan
   **Jatuh Tempo KGB** (menuju `?kgb=overdue` — TMT golongan sudah > 2 tahun, KGB belum diproses).
   Opsi "Jatuh tempo (terlewat)" juga tersedia di dropdown filter Kenaikan Jabatan & KGB pada
   daftar pegawai; angka kartu konsisten dengan jumlah hasil filter.

## 🚀 Update 17 September 2026

Sumber: `Update/catatan - 17-09-2026.txt`. Semua 12 poin tambahan & revisi telah diterapkan:

1. **Penamaan konsisten PNS → ASN** — seluruh tampilan, master status kepegawaian (kode & nama),
   header export, template import, dan seeder kini memakai istilah **ASN** (CPNS & PPPK tetap).
   Import lama tetap kompatibel (nilai `PNS` otomatis dipetakan ke `ASN`).
2. **Grafik kemampuan berenang** — donut chart “Kemampuan Berenang” (bisa / tidak / belum diisi)
   pada dashboard.
3. **Chart KGB (Kenaikan Gaji Berkala)** — berkala 2 tahun untuk ASN, CPNS & PPPK/P3K;
   dihitung dari TMT golongan (TMT + kelipatan 2 tahun). Tersedia kartu statistik, line chart,
   tabel jadwal KGB terdekat, info KGB, serta filter `kgb` (tahun ini / ≤1 th / ≤2 th)
   pada daftar pegawai. Estimasi KGB per pegawai juga tampil di detail pegawai & CV.
4. **Kolom kemampuan Bahasa Inggris** — data personal pegawai (Tidak Bisa / Dasar / Menengah /
   Lanjutan) pada form ASN, form Non ASN, detail pegawai, dan CV.
5. **Data seminar/pelatihan dalam & luar negeri** — tipe baru **Seminar** & **Pelatihan** pada riwayat
   pengembangan kompetensi plus kolom **Lingkup** (Dalam/Luar Negeri); dapat diinput admin
   maupun pegawai sendiri (lihat poin 9).
6. **Unduh CV pegawai (PDF)** — tombol *Unduh CV* pada detail pegawai & daftar pegawai;
   layout CV resmi (kop, data personal, kepegawaian, pendidikan, riwayat pangkat, jabatan,
   diklat/seminar/pelatihan, tanda tangan) siap cetak.
7. **Menu Analisis Jabatan Pelaksana** — pemetaan formasi pelaksana/fungsional umum:
   distribusi perjenjang (Pelaksana/Penyelia/Mahir/Terampil), sebaran unit, formasi kosong.
8. **Cetak formulir cuti (PDF)** — tombol *Cetak / Unduh PDF* pada detail & daftar cuti;
   format formulir resmi Bagian I–VIII lengkap dgn kop kementerian (siap print/tanda tangan).
9. **Riwayat diklat & pelatihan di profil** — kartu “Riwayat Diklat, Seminar & Pelatihan” pada
   detail pegawai; admin bagian maupun pegawai (profil sendiri) dapat menambah/menghapus
   langsung dari halaman profil, termasuk unggah sertifikat.
10. **Riwayat kenaikan pangkat** — tabel `employee_rank_histories` (contoh III/a → III/b):
    input manual dari profil + **pencatatan otomatis** saat golongan pegawai diubah admin.
11. **Direktori pegawai (view only)** — menu baru “Direktori Pegawai” untuk semua role;
    pegawai biasa kini dapat **mencari & melihat profil pegawai lain** tanpa tombol ubah/hapus
    (akses edit/CRUD tetap khusus admin).
12. **Filter lebih dari satu (multi-select)** — status kepegawaian & unit kerja pada daftar
    pegawai ASN, kategori pada pegawai Non ASN, dan status ASN pada filter dashboard kini
    dapat dipilih lebih dari satu sekaligus (mis. ASN + PPPK).

Migration: `2026_09_17_000001_update_features_september_17.php`
(rename status PNS→ASN, kolom `english_skill`, tipe & lingkup `employee_trainings`,
tabel `employee_rank_histories`). Test: `tests/Feature/UpdateSeptember17Test.php`.

## 🚀 Update 11 September 2026

### KPI Total Keseluruhan Pegawai di dashboard
Baris kartu KPI baru **tepat di bawah filter** dashboard berisi data yang **tidak dobel**
dengan stat-card-link:
- **Total Keseluruhan Pegawai** — ASN & PPPK aktif + Non ASN (istilah PNS diganti ASN per 17 Sept 2026)
- **ASN** — pegawai berstatus ASN aktif
- **PPPK Aktif** — penuh & paruh waktu
- **Non ASN** — pramubakti, security, dll

### Stat-card-link di bawah KPI (setelah filter)
Urutan dashboard kini: banner pengumuman → filter → KPI → kartu statistik. Kartu yang datanya dobel dengan KPI
di atas (Total Pegawai ASN, Pegawai Non ASN, Pegawai PPPK) **dihapus** sehingga tidak ada informasi
tertulis ganda. Kartu yang dipertahankan: Jabatan Struktural, Jabatan Fungsional, Akan Pensiun Tahun Ini,
Pensiun ≤ 2 Tahun, Pengunjung Hari Ini, dan 3 kartu kenaikan jabatan/pangkat.

### Pencarian pegawai pada halaman Pengguna
Kolom "Data Pegawai" saat menambah pengguna kini berupa **pencarian interaktif** (ketik nama/NIP,
klik hasil) — menggantikan dropdown panjang.

### Kemampuan Berenang pada Informasi Personal pegawai
Field baru `swimming_skill` (Bisa Berenang / Tidak Bisa Berenang) tersedia pada form pegawai ASN,
form pegawai Non ASN, dan ditampilkan di kartu **Informasi Personal** halaman detail pegawai.
Migrasi: `2026_09_11_000001`.

### Pengajuan Cuti (fitur disiapkan — default NONAKTIF)
Modul Pengajuan Cuti lengkap dengan workflow seperti persuratan:
**PENDING → VERIFIED (Admin/Biro SDM) → APPROVED**, dengan opsi penolakan berikut alasannya.
Pegawai dapat membatalkan pengajuan yang masih menunggu verifikasi.
Formulir & halaman detail **mengikuti formulir resmi** *"FORM CUTI KOSONG — PNS dan PPPK"*
Kementerian Transmigrasi RI (Bagian I Data Pegawai, II Jenis Cuti, III Alasan, IV Lamanya,
V Catatan Cuti, VI Alamat Selama Cuti, VII Pertimbangan Atasan Langsung & VIII Keputusan Pejabat):
- Jenis cuti: Tahunan, Besar, Sakit, Melahirkan, Karena Alasan Penting, di Luar Tanggungan Negara.
- Data pegawai (nama, NIP, jabatan, **masa kerja**, unit eselon II) terisi otomatis & autofill saat admin memilih pegawai.
- Jumlah hari cuti dihitung otomatis dari rentang tanggal (Bagian IV).
- Alamat & telepon selama menjalankan cuti tersimpan (Bagian VI, kolom `address_during_leave` / `phone_during_leave`).
- Menunggu kepastian **ttd digital** sehingga default-nya nonaktif.
- Aktifkan/nonaktifkan: **Pengaturan → Tampilan & Menu → Pengajuan Cuti** (kunci `menu_cuti`).
- Notifikasi email ke Administrator Utama dapat dinyalakan pada **Pengaturan → SMTP & Notifikasi**.
- Tabel baru `leave_requests` (migrasi `2026_09_11_000002` + `2026_09_11_000003`), menu sidebar "Pengajuan Cuti".

### Informasi login demo dihapus
Blok akun demo (email & password contoh) dihapus dari form login.

### Deployment update ini
```bash
composer install --no-dev
php artisan migrate --force   # swimming_skill + leave_requests (formulir cuti)
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

---

## 🚀 Update 2 Oktober 2026 — Import Non ASN format bank data baru

Berkas **bank data Non ASN format baru** (BANK DATA CLEANING SERVIS, Data Pramubakti/Driver/Teknisi,
Format Data Non ASN) kini dapat diunggah **langsung apa adanya** dari halaman *Pegawai Non ASN →
Import Excel* maupun `php artisan employees:import --type=nonasn`:

1. **Kolom lengkap terbaca** — `NAMA LENGKAP`, `NIK`, `NO. HP`, `EMAIL`, `AGAMA`, `TEMPAT`,
   `TANGGAL LAHIR`, `ALAMAT DOMISILI`, `UNIT PENEMPATAN` (kolom `NO.` dilewati). NIK menjadi
   ID pegawai (`nip`); tanda petik Excel di depan NIK (`'3175...`) dan sel numeric besar
   dibaca tanpa notasi ilmiah.
2. **Tanggal lahir campuran format** — sel tanggal Excel, serial Excel (mis. `26040`), teks
   `24/09/1985`, `21-02-1995`, `6 Januari 2000`, sampai salah ketik (`16 Deseember 2000`,
   `20-Desember-1999`, `04 Agustus1989`) tetap terbaca. Data yang memang rusak (mis. `16/052003`)
   dibiarkan kosong, bukan error.
3. **Email & telepon dibersihkan otomatis** — email berspasi akibat salah ketik dirapikan &
   divalidasi; nomor HP dari sel numeric mendapat angka 0 di depan kembali.
4. **Unit Penempatan → unit kerja dashboard** — segment sebelum koma dipetakan ke master unit
   (`"Biro Keuangan dan Barang Milik Negara, Sekretariat Jenderal"` → biro terkait), tahan
   potongan nama (`"Pusat Data dan Informasi"`), singkatan (`"Sekretariat Ditjen ..."`) dan
   typo (`"Transmigarsi"`, `"Kementrian"`). Yang tak dikenal (mis. `"Cleaning Servis"`) tetap
   jatuh ke **Sekretariat Jenderal** sesuai kebijakan lama.
5. **Multi-sheet** — berkas dengan sheet Pramubakti / Driver / Teknisi diproses sekaligus;
   kategori mengikuti judul sheet (daftar kategori dashboard otomatis bertambah `Driver`/`Teknisi`).
6. **Import ulang aman** — pegawai dicocokkan via NIK atau nama+kategori; data lama
   diperbarui/dilengkapi, tidak mendobel. Format lama (Personil PB / Security / Cleaning
   nama saja) tetap didukung.

Smoke test: `tests/Feature/UpdateOctober2Test.php`.

### Deployment update ini
```bash
composer install --no-dev        # tanpa dependency baru
php artisan config:clear
php artisan route:clear
php artisan view:clear
```
Lalu unggah berkas bank data baru melalui menu **Pegawai Non ASN → Import Excel**
(kategori terdeteksi otomatis dari nama berkas/judul sheet).

---

## 🚀 Update 7 Oktober 2026 (v1.0.2) — Catatan Masukan 30 Sept 2026

> Butir 14 (layanan kepegawaian baru: SLSKS, Tugas Belajar, Uji Kompetensi, KGB,
> Kenaikan Pangkat, Tunjangan Keluarga) **belum dikerjakan** menunggu instruksi lanjut.
> Butir 3 (import Non ASN format baru) sudah selesai pada update 2 Okt.

### 1. Urutan unit kerja sesuai struktur resmi kementerian
Kolom baru `units.sort_order` (migrasi `2026_10_07_000001`) mengikuti lampiran
**"urutan jabatan.xlsx"**:
- Eselon I: Setjen → Ditjen Pengembangan Ekonomi & Pemberdayaan Masyarakat →
  Ditjen Pembangunan & Pengembangan Kawasan → Itjen.
- Biro/pusat Setjen: Perencanaan-Kerjasama-Humas → Organisasi-SDM-RB → Umum &
  Pengadaan → Keuangan & BMN → Hukum → Pusat Strategi Kebijakan → Pusat
  Pengembangan SDM → Pusat Data & Informasi.
- Sekretariat/direktorat tiap Ditjen, Sekretariat Itjen & Inspektorat I–II, bagian
  Eselon III, lalu balai (Balai Besar Yogyakarta, Pekanbaru, Banjarmasin, Denpasar).

Seluruh daftar pilihan unit (filter dashboard, data pegawai, direktori, master data,
arsip, struktur organisasi, export) kini memakai urutan tersebut (`Unit::ordered()`).

### 2. Card hasil filter dashboard dipindah ke urutan atas
Tabel **"Hasil Filter — Data Pegawai"** kini berada **sebelum** kartu-kartu Kenaikan
Pangkat dan Kenaikan Gaji Berkala (KGB).

### 4. Filter unit kerja "kosong" pada Data Pegawai ASN
Opsi **"— Unit Kerja belum diisi (kosong) —"** pada filter unit kerja (menu Data
Pegawai ASN & Direktori) menampilkan pegawai yang `unit_id`-nya masih kosong —
memudahkan Admin Pegawai melengkapi data secara mandiri.

### 5 & 7. Riwayat pendidikan: Pendidikan 1 = S1, tanpa "Tingkat"
- Migrasi `2026_10_09_000002` merapikan data lama: jenjang terendah (S1/D4/D3) ke
  Pendidikan 1, disusul S2, lalu S3.
- Label form menjadi "Pendidikan 1 (S1)", "Pendidikan 2 (S2)", "Pendidikan 3 (S3)";
  baris "Tingkat" di halaman detail pegawai dihapus.
- CV: bagian C. Riwayat Pendidikan langsung dimulai dari Pendidikan 1; **Tingkat
  Pendidikan Terakhir** dipindah ke bagian A. Data Personal.

### 6. Pemilihan perguruan tinggi dengan kotak pencarian
Komponen baru `<x-search-select>` (dropdown + kotak cari) menggantikan dropdown
kampus biasa pada form pegawai — ketik kata kunci, nama perguruan tinggi yang cocok
langsung tersaring. Opsi "Isi Manual" tetap tersedia.

### 8–10. Analis jabatan fungsional / struktural / pelaksana
Ketiga halaman kini seragam:
- **Kolom Nomor** paling awal pada tabel.
- **Filter nama jabatan & jenjang** + **pagination** (15 baris/halaman).
- Fungsional: jabatan yang kosong (tanpa pemangku) **tidak ditampilkan**.
- Struktural: dikelompokkan memakai **nama jabatan umum** ("Kepala Biro
  Organisasi, …" → "Kepala Biro"; `JabatanSyncService::genericJabatan()`).
- Pelaksana: pemangku dihitung **langsung dari nama jabatan pada data pegawai**
  (sebelumnya dari relasi riwayat jabatan sehingga selalu kosong).

### 11. Menu "SOP Kementerian" → "Dokumen Kepegawaian"
Label sidebar, judul halaman, tombol, modal unggah, notifikasi email, dan pesan
sukses/error semuanya memakai nama baru (route & struktur data tidak berubah).

### 12. Riwayat diklat wajib upload dokumen
Pengisian riwayat diklat/seminar/pelatihan (modul Diklat & profil pegawai) kini
**mewajibkan** unggah dokumen/sertifikat bukti keikutsertaan (PDF/JPG maks 10 MB).
Pengecekan hak akses dilakukan **sebelum** validasi.

### 13. Pencarian cuti hanya untuk Admin Bagian & Super Admin
Kolom pencarian pada menu Pengajuan Cuti disembunyikan dari akun pegawai (pegawai
tetap hanya melihat pengajuan miliknya); parameter `search` juga diabaikan di sisi
server untuk akun non-admin.

### Deployment update ini
```bash
composer install --no-dev        # tanpa dependency baru
php artisan migrate --force      # 2 migrasi baru (sort_order unit + urutan pendidikan)
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

Smoke test: `tests/Feature/UpdateSeptember30Test.php` (13 test).

---

## 🚀 Update 9 Oktober 2026 (v1.0.3) — Catatan Masukan 7 Okt 2026

### 1. Urutan pilihan unit kerja eselon I–III (penyempurnaan)
Urutan resmi (migrasi `sort_order`) kini dipakai di **semua** pilihan unit kerja:
- Filter **Eselon I dashboard** (sebelumnya masih urut nama).
- **Form pegawai ASN** (tambah/ubah — sebelumnya Balai mendahului Eselon I).
- **Form pegawai Non ASN** (sekaligus memperbaiki filter level yang kosong di MySQL).
- Dropdown induk unit pada **Master Data**.

### 2. Filter pendidikan tidak tergabung dengan data Non ASN
Saat filter **Pendidikan** (mis. S3), **Golongan**, atau **Jenis Kelamin** dipakai,
pegawai Non ASN tidak memiliki data tersebut sehingga kartu **Non ASN otomatis 0**
dan **Total Keseluruhan Pegawai** hanya berisi ASN yang cocok filter
(`DashboardService::nonAsnQuery()`).

### 3. CV — Riwayat Pendidikan dimulai dari Pendidikan 1 + jenjang
- Baris pertama = **Pendidikan 1 — Diploma / Sarjana (S1)**, lalu
  **Pendidikan 2 — Magister (S2)**, **Pendidikan 3 — Doktor (S3)**.
- Migrasi `2026_10_09_000002` merapikan data lama yang terbalik
  (jenjang terendah → `education_1`), dikenali dari teks jenjang pada isian.

### 4. Analis jabatan fungsional — jumlah pegawai bisa diklik
Kolom **Pemangku** diganti **Jumlah Pegawai**; angkanya berupa tombol yang
membuka **popup daftar pegawai** pemangku jabatan (nama + unit kerja, tautan ke
profil pegawai).

### 5. Formasi kosong tidak dimunculkan (fungsional & struktural)
Selain menu fungsional (sudah), menu **Analisis Jabatan Struktural** kini juga
menyembunyikan jabatan umum yang tidak memiliki pemangku sama sekali.

### 6. Jabatan "Sekretaris Direktur Jenderal" dihapus
Penamaan salah (seharusnya *Sekretaris **Direktorat** Jenderal*) dihapus dari
master (migrasi `2026_10_09_000001` + seeder); nama jabatan salah ketik pada
data pegawai ikut diluruskan.

### 7. Penyempurnaan tampilan mobile (tanpa mengubah desain)
- Input form 16px di layar kecil — mencegah auto-zoom iOS.
- Dukungan **safe-area** (notch/home bar iPhone) untuk widget chat, footer,
  panel chat & baris aksi form.
- Filter analisis jabatan menumpuk rapi di HP (tombol terapkan/reset mudah di-tap).
- Gulir tabel/dropdown lebih mulus di layar sentuh; tombol tabel tidak terpotong.

**Deploy:** `php artisan migrate` (2 migrasi baru), `composer install --no-dev`,
`php artisan config:clear && php artisan route:clear && php artisan view:clear`.
