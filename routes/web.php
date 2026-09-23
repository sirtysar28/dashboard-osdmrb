<?php

use App\Http\Controllers\ArchiveController;
use App\Http\Controllers\ArchiveLoanController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LetterController;
use App\Http\Controllers\LetterTypeController;
use App\Http\Controllers\LeaveRequestController;
use App\Http\Controllers\MasterDataController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SurveyController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes — Dashboard Biro OSDMRB
|--------------------------------------------------------------------------
|
| Hak akses 3 jenis:
| - pegawai     : beranda, profil & layanan mandiri
| - admin       : admin setiap bagian (data pegawai, verifikasi, dll)
| - super_admin : Administrator Utama (semua akses + pengaturan aplikasi)
| - biro_sdm    : setara admin untuk approval (master data read-only)
|
*/

Route::get('/', fn () => redirect()->route(auth()->check() ? (auth()->user()->isPrivileged() ? 'dashboard' : 'home') : 'login'));

/* ================= SURVEI MASUKAN & SARAN ================= */
Route::middleware('auth')->group(function () {
    Route::post('/survei', [SurveyController::class, 'store'])->name('surveys.store');
});

/* ================= PESAN / CHAT PRIVATE ANTAR PEGAWAI =================
   Widget melayang di pojok kanan bawah semua halaman (khusus login). */
Route::middleware('auth')->group(function () {
    Route::get('/pesan/kontak', [ChatController::class, 'contacts'])->name('chat.contacts');
    Route::get('/pesan/belum-dibaca', [ChatController::class, 'unread'])->name('chat.unread');
    Route::get('/pesan/{user}', [ChatController::class, 'messages'])->name('chat.messages');
    Route::post('/pesan/{user}', [ChatController::class, 'store'])->name('chat.send');
});

Route::middleware('auth')->group(function () {

    /* ================= BERSAMA ================= */
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    /* ================= USER PEGAWAI ================= */
    Route::middleware('role:pegawai,admin,biro_sdm,super_admin')->group(function () {
        Route::get('/home', [HomeController::class, 'index'])->name('home');
    });

    /* ================= PROFIL PEGAWAI MANDIRI ================= */
    Route::middleware('role:pegawai,admin,biro_sdm,super_admin')->group(function () {
        Route::get('/pegawai/profil', [EmployeeController::class, 'myProfile'])->name('pegawai.profile');
    });

    /* ================= MODUL BIRO OSDMRB ================= */
    Route::middleware('role:pegawai,admin,biro_sdm,super_admin')->group(function () {
        Route::get('/modul/analisis-jabatan-fungsional', [ModuleController::class, 'analisisJabatan'])->name('modules.analisis-jabatan');
        Route::get('/modul/analisis-jabatan-struktural', [ModuleController::class, 'analisisJabatanStruktural'])->name('modules.analisis-jabatan-struktural');
        Route::get('/modul/analisis-jabatan-pelaksana', [ModuleController::class, 'analisisJabatanPelaksana'])->name('modules.analisis-jabatan-pelaksana');
        Route::get('/modul/reformasi-birokrasi', [ModuleController::class, 'reformasiBirokrasi'])->name('modules.reformasi-birokrasi');
        Route::get('/modul/manajemen-talenta', [ModuleController::class, 'manajemenTalenta'])->name('modules.manajemen-talenta');
        Route::get('/modul/diklat', [ModuleController::class, 'diklat'])->name('modules.diklat');
        Route::get('/modul/sop-kementerian', [ModuleController::class, 'sop'])->name('modules.sop');
        Route::get('/modul/struktur-organisasi', [ModuleController::class, 'strukturOrganisasi'])->name('modules.struktur');

        // unduhan dokumen modul (semua role)
        Route::get('/modul/diklat/{training}/sertifikat', [ModuleController::class, 'diklatDownload'])->name('modules.diklat.download');
        Route::get('/modul/sop-kementerian/{sop}/unduh', [ModuleController::class, 'sopDownload'])->name('modules.sop.download');
        Route::get('/modul/sop-kementerian/{sop}/preview', [ModuleController::class, 'sopPreview'])->name('modules.sop.preview');
    });

    /* ================= RIWAYAT DIKLAT PEGAWAI =================
       Admin/biro SDM dapat input utk pegawai mana pun; pegawai dapat
       menambahkan sendiri pada halaman profilnya (validasi di controller). */
    Route::middleware('role:pegawai,admin,biro_sdm,super_admin')->group(function () {
        Route::post('/modul/diklat', [ModuleController::class, 'diklatStore'])->name('modules.diklat.store');
        Route::delete('/modul/diklat/{training}', [ModuleController::class, 'diklatDestroy'])->name('modules.diklat.destroy');
    });

    /* ================= DIREKTORI PEGAWAI (semua role — view only) ================= */
    Route::middleware('role:pegawai,admin,biro_sdm,super_admin')->group(function () {
        Route::get('/direktori-pegawai', [EmployeeController::class, 'directory'])->name('employees.directory');
    });

    /* ================= DETAIL PEGAWAI & CV (view only — semua role) =================
       whereNumber mencegah bentrok dgn route literal /employees/non-asn, /employees/export, dll. */
    Route::middleware('role:pegawai,admin,biro_sdm,super_admin')->group(function () {
        Route::get('/employees/{employee}/cv', [EmployeeController::class, 'cv'])->name('employees.cv')->whereNumber('employee');
        Route::get('/employees/{employee}', [EmployeeController::class, 'show'])->name('employees.show')->whereNumber('employee');
    });

    /* ================= RIWAYAT KENAIKAN PANGKAT (admin atau pegawai pemilik profil) ================= */
    Route::middleware('role:pegawai,admin,biro_sdm,super_admin')->group(function () {
        Route::post('/employees/{employee}/riwayat-pangkat', [EmployeeController::class, 'storeRankHistory'])->name('employees.rank-histories.store')->whereNumber('employee');
        Route::delete('/employees/riwayat-pangkat/{rank_history}', [EmployeeController::class, 'destroyRankHistory'])->name('employees.rank-histories.destroy');
    });

    /* ================= MUTASI MODUL (ADMIN & BIRO SDM) ================= */
    Route::middleware('role:admin,biro_sdm,super_admin')->group(function () {
        // SOP Kementerian
        Route::post('/modul/sop-kementerian', [ModuleController::class, 'sopStore'])->name('modules.sop.store');
        Route::delete('/modul/sop-kementerian/{sop}', [ModuleController::class, 'sopDestroy'])->name('modules.sop.destroy');
    });

    /* ================= PENGAJUAN CUTI (bisa diaktifkan/nonaktifkan dari Pengaturan) =================
       Fitur menunggu kepastian tanda tangan digital (ttd digital). */
    Route::middleware('role:pegawai,admin,biro_sdm,super_admin')->group(function () {
        Route::get('/cuti', [LeaveRequestController::class, 'index'])->name('leaves.index');
        Route::get('/cuti/ajukan', [LeaveRequestController::class, 'create'])->name('leaves.create');
        Route::post('/cuti', [LeaveRequestController::class, 'store'])->name('leaves.store');
        Route::get('/cuti/{leave}', [LeaveRequestController::class, 'show'])->name('leaves.show');
        Route::get('/cuti/{leave}/cetak', [LeaveRequestController::class, 'print'])->name('leaves.print');
        Route::post('/cuti/{leave}/verifikasi', [LeaveRequestController::class, 'verify'])->name('leaves.verify');
        Route::post('/cuti/{leave}/setujui', [LeaveRequestController::class, 'approve'])->name('leaves.approve');
        Route::post('/cuti/{leave}/tolak', [LeaveRequestController::class, 'reject'])->name('leaves.reject');
        Route::delete('/cuti/{leave}', [LeaveRequestController::class, 'destroy'])->name('leaves.destroy');
    });

    /* ================= LAYANAN PERSURATAN ================= */
    Route::middleware('role:pegawai,admin,biro_sdm,super_admin')->group(function () {
        Route::get('/surat', [LetterController::class, 'index'])->name('letters.index');
        Route::get('/surat/export', [LetterController::class, 'export'])->name('letters.export');
        Route::get('/surat/ajukan', [LetterController::class, 'create'])->name('letters.create');
        Route::post('/surat', [LetterController::class, 'store'])->name('letters.store');
        Route::get('/surat/{letter}', [LetterController::class, 'show'])->name('letters.show');
        Route::post('/surat/{letter}/verifikasi', [LetterController::class, 'verify'])->name('letters.verify');
        Route::post('/surat/{letter}/setujui', [LetterController::class, 'approve'])->name('letters.approve');
        Route::post('/surat/{letter}/tolak', [LetterController::class, 'reject'])->name('letters.reject');
        Route::get('/surat/{letter}/cetak', [LetterController::class, 'print'])->name('letters.print');
        Route::delete('/surat/{letter}', [LetterController::class, 'destroy'])->name('letters.destroy');
    });

    /* ================= LAYANAN KEARSIPAN ================= */
    Route::middleware('role:pegawai,admin,biro_sdm,super_admin')->group(function () {
        Route::get('/arsip', [ArchiveController::class, 'index'])->name('archives.index');
        Route::get('/arsip/export', [ArchiveController::class, 'export'])->name('archives.export');
        Route::get('/arsip/{archive}', [ArchiveController::class, 'show'])->name('archives.show');
        Route::get('/arsip/{archive}/unduh', [ArchiveController::class, 'download'])->name('archives.download');

        // peminjaman arsip
        Route::get('/arsip-pinjam', [ArchiveLoanController::class, 'index'])->name('archive-loans.index');
        Route::get('/arsip-pinjam/export', [ArchiveLoanController::class, 'export'])->name('archive-loans.export');
        Route::post('/arsip/{archive}/pinjam', [ArchiveLoanController::class, 'store'])->name('archive-loans.store');
        Route::post('/arsip-pinjam/{loan}/kembali', [ArchiveLoanController::class, 'returned'])->name('archive-loans.returned');
    });

    /* ================= ADMIN BAGIAN, BIRO SDM & SUPER ADMIN ================= */
    Route::middleware('role:admin,biro_sdm,super_admin')->group(function () {

        // Dashboard
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/api/dashboard/statistics', [DashboardController::class, 'statistics'])->name('dashboard.statistics');

        // Manajemen pegawai (ASN)
        Route::get('/employees', [EmployeeController::class, 'index'])->name('employees.index');
        Route::get('/employees/export', [EmployeeController::class, 'export'])->name('employees.export');
        Route::get('/employees/import', [EmployeeController::class, 'importForm'])->name('employees.import');
        Route::post('/employees/import', [EmployeeController::class, 'importStore'])->name('employees.import.store');
        Route::get('/employees/template-import', [EmployeeController::class, 'template'])->name('employees.template');
        Route::post('/employees', [EmployeeController::class, 'store'])->name('employees.store');
        Route::get('/employees/create', [EmployeeController::class, 'create'])->name('employees.create');

        // Manajemen pegawai NON ASN (harus di atas route {employee})
        Route::get('/employees/non-asn', [EmployeeController::class, 'nonAsn'])->name('employees.non-asn');
        Route::get('/employees/non-asn/create', [EmployeeController::class, 'nonAsnCreate'])->name('employees.non-asn.create');
        Route::post('/employees/non-asn/create', [EmployeeController::class, 'nonAsnStore'])->name('employees.non-asn.store');
        Route::get('/employees/non-asn/import', [EmployeeController::class, 'nonAsnImportForm'])->name('employees.non-asn.import');
        Route::post('/employees/non-asn/import', [EmployeeController::class, 'nonAsnImportStore'])->name('employees.non-asn.import.store');
        Route::get('/employees/non-asn/{employee}/edit', [EmployeeController::class, 'nonAsnEdit'])->name('employees.non-asn.edit');
        Route::put('/employees/non-asn/{employee}', [EmployeeController::class, 'nonAsnUpdate'])->name('employees.non-asn.update');

        Route::get('/employees/{employee}/edit', [EmployeeController::class, 'edit'])->name('employees.edit');
        Route::put('/employees/{employee}', [EmployeeController::class, 'update'])->name('employees.update');
        Route::delete('/employees/{employee}', [EmployeeController::class, 'destroy'])->name('employees.destroy');

        // Kearsipan (kelola)
        Route::get('/arsip-admin/buat', [ArchiveController::class, 'create'])->name('archives.create');
        Route::post('/arsip-admin', [ArchiveController::class, 'store'])->name('archives.store');
        Route::get('/arsip-admin/{archive}/ubah', [ArchiveController::class, 'edit'])->name('archives.edit');
        Route::put('/arsip-admin/{archive}', [ArchiveController::class, 'update'])->name('archives.update');
        Route::delete('/arsip-admin/{archive}', [ArchiveController::class, 'destroy'])->name('archives.destroy');
        Route::post('/arsip-pinjam/{loan}/setujui', [ArchiveLoanController::class, 'approve'])->name('archive-loans.approve');
        Route::post('/arsip-pinjam/{loan}/tolak', [ArchiveLoanController::class, 'reject'])->name('archive-loans.reject');

        // Master data (Biro SDM: hanya lihat & export)
        Route::get('/master', [MasterDataController::class, 'index'])->name('master.index');
        Route::get('/master/export', [MasterDataController::class, 'export'])->name('master.export');

        // Jenis surat (Biro SDM: hanya lihat & export)
        Route::get('/master/jenis-surat', [LetterTypeController::class, 'index'])->name('letter-types.index');

        // Audit log & statistik pengunjung
        Route::get('/audit-log', [AuditLogController::class, 'index'])->name('audit.index');
        Route::get('/audit-log/export', [AuditLogController::class, 'export'])->name('audit.export');

        // Hasil survei masukan & saran
        Route::get('/survei', [SurveyController::class, 'index'])->name('surveys.index');
        Route::delete('/survei/{survey}', [SurveyController::class, 'destroy'])->name('surveys.destroy');
    });

    /* ================= KHUSUS SUPER ADMIN ================= */
    Route::middleware('role:super_admin')->group(function () {

        // Pengaturan tampilan, menu sidebar & tema
        Route::get('/pengaturan/tampilan', [SettingsController::class, 'appearance'])->name('settings.appearance');
        Route::post('/pengaturan/tampilan', [SettingsController::class, 'updateAppearance'])->name('settings.appearance.update');

        // Pengumuman dashboard
        Route::get('/pengaturan/pengumuman', [SettingsController::class, 'announcements'])->name('settings.announcements');
        Route::post('/pengaturan/pengumuman', [SettingsController::class, 'storeAnnouncement'])->name('settings.announcements.store');
        Route::put('/pengaturan/pengumuman/{announcement}', [SettingsController::class, 'updateAnnouncement'])->name('settings.announcements.update');
        Route::delete('/pengaturan/pengumuman/{announcement}', [SettingsController::class, 'destroyAnnouncement'])->name('settings.announcements.destroy');

        // SMTP & notifikasi email
        Route::get('/pengaturan/smtp', [SettingsController::class, 'smtp'])->name('settings.smtp');
        Route::post('/pengaturan/smtp', [SettingsController::class, 'updateSmtp'])->name('settings.smtp.update');
    });

    /* ================= KHUSUS ADMIN BAGIAN & SUPER ADMIN ================= */
    Route::middleware('role:admin,super_admin')->group(function () {

        // Mutasi master data
        Route::post('/master/units', [MasterDataController::class, 'storeUnit'])->name('master.units.store');
        Route::put('/master/units/{unit}', [MasterDataController::class, 'updateUnit'])->name('master.units.update');
        Route::delete('/master/units/{unit}', [MasterDataController::class, 'destroyUnit'])->name('master.units.destroy');
        Route::post('/master/education', [MasterDataController::class, 'storeEducation'])->name('master.education.store');
        Route::put('/master/education/{education_level}', [MasterDataController::class, 'updateEducation'])->name('master.education.update');
        Route::delete('/master/education/{education_level}', [MasterDataController::class, 'destroyEducation'])->name('master.education.destroy');
        Route::post('/master/kampus', [MasterDataController::class, 'storeCampus'])->name('master.campuses.store');
        Route::put('/master/kampus/{campus}', [MasterDataController::class, 'updateCampus'])->name('master.campuses.update');
        Route::delete('/master/kampus/{campus}', [MasterDataController::class, 'destroyCampus'])->name('master.campuses.destroy');
        Route::post('/master/ranks', [MasterDataController::class, 'storeRank'])->name('master.ranks.store');
        Route::put('/master/ranks/{rank}', [MasterDataController::class, 'updateRank'])->name('master.ranks.update');
        Route::delete('/master/ranks/{rank}', [MasterDataController::class, 'destroyRank'])->name('master.ranks.destroy');
        Route::post('/master/statuses', [MasterDataController::class, 'storeStatus'])->name('master.statuses.store');
        Route::put('/master/statuses/{employment_status}', [MasterDataController::class, 'updateStatus'])->name('master.statuses.update');
        Route::delete('/master/statuses/{employment_status}', [MasterDataController::class, 'destroyStatus'])->name('master.statuses.destroy');
        Route::post('/master/job-levels', [MasterDataController::class, 'storeJobLevel'])->name('master.job-levels.store');
        Route::put('/master/job-levels/{job_level}', [MasterDataController::class, 'updateJobLevel'])->name('master.job-levels.update');
        Route::delete('/master/job-levels/{job_level}', [MasterDataController::class, 'destroyJobLevel'])->name('master.job-levels.destroy');
        Route::post('/master/position-types', [MasterDataController::class, 'storePositionType'])->name('master.position-types.store');
        Route::put('/master/position-types/{position_type}', [MasterDataController::class, 'updatePositionType'])->name('master.position-types.update');
        Route::delete('/master/position-types/{position_type}', [MasterDataController::class, 'destroyPositionType'])->name('master.position-types.destroy');
        Route::post('/master/positions', [MasterDataController::class, 'storePosition'])->name('master.positions.store');
        Route::put('/master/positions/{position}', [MasterDataController::class, 'updatePosition'])->name('master.positions.update');
        Route::delete('/master/positions/{position}', [MasterDataController::class, 'destroyPosition'])->name('master.positions.destroy');

        // Mutasi master klasifikasi arsip
        Route::post('/master/arsip-kategori', [ArchiveController::class, 'storeCategory'])->name('master.archive-categories.store');
        Route::put('/master/arsip-kategori/{archive_category}', [ArchiveController::class, 'updateCategory'])->name('master.archive-categories.update');
        Route::delete('/master/arsip-kategori/{archive_category}', [ArchiveController::class, 'destroyCategory'])->name('master.archive-categories.destroy');

        // Mutasi jenis surat
        Route::post('/master/jenis-surat', [LetterTypeController::class, 'store'])->name('letter-types.store');
        Route::put('/master/jenis-surat/{letter_type}', [LetterTypeController::class, 'update'])->name('letter-types.update');
        Route::delete('/master/jenis-surat/{letter_type}', [LetterTypeController::class, 'destroy'])->name('letter-types.destroy');

        // Manajemen pengguna
        Route::get('/pengguna', [UserController::class, 'index'])->name('users.index');
        Route::get('/pengguna/export', [UserController::class, 'export'])->name('users.export');
        Route::post('/pengguna', [UserController::class, 'store'])->name('users.store');
        Route::put('/pengguna/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('/pengguna/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    });
});

require __DIR__.'/auth.php';
