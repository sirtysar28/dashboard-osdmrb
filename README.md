# Dashboard Biro OSDMRB

klo tampilan dekstop card ny

Dibangun dengan **Laravel 11**, **PHP 8.2+**, **MySQL/MariaDB**, **Bootstrap 5**, **Chart.js**, **Spatie Laravel Permission**, dan **DomPDF**.

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

Buka `http://127.0.0.1:8000`.

### Akun Demo

| Peran | Email | Password | Akses |
|---|---|---|---|
| Admin Instansi | `admin@osdmrb.go.id` | `password` | Semua fitur |
| Biro SDM | `sdm@osdmrb.go.id` | `password` | Dashboard, pegawai, surat/arsip + approval; master data hanya lihat |
| Pegawai | `pegawai@osdmrb.go.id` | `password` | Beranda, profil sendiri, pengajuan surat/arsip |

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
apa adanya — posisi header yang tidak beraturan dikenali otomatis (didukung: daftar Personil PB dengan
ID + unit kerja, daftar Security dengan jabatan, daftar Cleaning Service nama saja).
Kategori terdeteksi dari nama berkas (`security` / `cleaning` / `pramubakti` / `personil pb`),
baris kosong & kolom tanda tangan otomatis dilewati, dan import ulang bersifat **aman**
(pegawai lama diperbarui berdasarkan ID atau nama+kategori, tidak mendobel data).

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
