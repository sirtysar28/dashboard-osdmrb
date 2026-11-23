<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\User;
use App\Traits\ExportsTable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    use ExportsTable;

    /**
     * Aturan reset password:
     * - Administrator Utama (super_admin) boleh me-reset password semua akun.
     * - Admin Bagian (admin) HANYA boleh me-reset password akun berperan Pegawai.
     */
    private function canResetPasswordOf(User $target): bool
    {
        $actor = auth()->user();

        return $actor->isSuperAdmin()
            || ($actor->isAdmin() && $target->hasRole('pegawai'));
    }

    public function index(Request $request)
    {
        // filter status persetujuan: semua | pending | approved | rejected
        $status = $request->input('status', 'all');
        if (! in_array($status, ['all', 'pending', 'approved', 'rejected'])) {
            $status = 'all';
        }

        $users = User::with(['roles', 'employee'])
            ->when($status !== 'all', fn ($q) => $q->where('registration_status', $status))
            ->when($request->search, fn ($q, $v) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$v}%")
                ->orWhere('email', 'like', "%{$v}%")))
            // pendaftar yang menunggu persetujuan selalu tampil paling atas
            ->orderByRaw("CASE WHEN registration_status = 'pending' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('users.index', [
            'users' => $users,
            'employees' => Employee::orderBy('name')->doesntHave('user')->get(),
            'roles' => [
                'super_admin' => 'Administrator Utama (semua akses + pengaturan)',
                'admin' => 'Admin Bagian',
                'biro_sdm' => 'Biro SDM (verifikasi & persetujuan)',
                'pegawai' => 'Pegawai',
            ],
            'statusFilter' => $status,
            'statusCounts' => [
                'all' => User::count(),
                'pending' => User::pendingApproval()->count(),
                'approved' => User::where('registration_status', User::REGISTRATION_APPROVED)->count(),
                'rejected' => User::where('registration_status', User::REGISTRATION_REJECTED)->count(),
            ],
        ]);
    }

    /**
     * Export daftar pengguna ke Excel / PDF.
     */
    public function export(Request $request)
    {
        $rows = User::with(['roles', 'employee'])
            ->when($request->search, fn ($q, $v) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$v}%")
                ->orWhere('email', 'like', "%{$v}%")))
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => [
                $user->name,
                $user->email,
                $user->employee?->name ?? '-',
                $user->isPrivileged() ? $user->role_label : 'Pegawai',
                match (true) {
                    $user->isPendingApproval() => 'Menunggu Persetujuan',
                    $user->isRejected() => 'Ditolak',
                    default => $user->is_active ? 'Aktif' : 'Non-aktif',
                },
                $user->created_at->format('d/m/Y'),
            ]);

        return $this->exportTable(
            $request->input('format', 'xlsx'),
            'Manajemen Pengguna',
            ['Nama', 'Email', 'Data Pegawai', 'Peran', 'Status', 'Dibuat'],
            $rows,
            'data-pengguna',
            'Total: '.$rows->count().' akun',
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::defaults()],
            'employee_id' => ['nullable', 'exists:employees,id', Rule::unique('users', 'employee_id')],
            'role' => ['required', 'in:super_admin,admin,biro_sdm,pegawai'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'employee_id' => $validated['employee_id'] ?? null,
            // akun buatan admin dianggap sudah disetujui
            'registration_status' => User::REGISTRATION_APPROVED,
            'approved_at' => now(),
            'approved_by' => auth()->id(),
        ]);

        $user->assignRole($validated['role']);

        return back()->with('success', 'Akun pengguna berhasil dibuat.');
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => ['required', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'password' => ['nullable', Password::defaults()],
            'employee_id' => ['nullable', 'exists:employees,id', Rule::unique('users', 'employee_id')->ignore($user)],
            'role' => ['required', 'in:super_admin,admin,biro_sdm,pegawai'],
            'is_active' => ['boolean'],
        ]);

        // Admin Bagian hanya boleh mengubah password akun berperan Pegawai
        if (!empty($validated['password']) && !$this->canResetPasswordOf($user)) {
            return back()->with('error', 'Admin Bagian hanya dapat membantu reset password akun Pegawai.');
        }

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'employee_id' => $validated['employee_id'] ?? null,
            // checkbox yang tidak dicentang tidak terkirim -> paksa boolean
            'is_active' => $request->boolean('is_active'),
            'password' => $validated['password'] ?? $user->password,
        ]);

        $user->syncRoles([$validated['role']]);

        return back()->with('success', 'Akun pengguna berhasil diperbarui.');
    }

    /**
     * Setujui pendaftaran akun pegawai mandiri.
     * Akun berubah menjadi aktif dan pendaftar dikirimi email notifikasi.
     */
    public function approve(Request $request, User $user)
    {
        abort_if($user->id === auth()->id(), 422, 'Tidak dapat memproses akun sendiri.');

        if (! $user->isPendingApproval() && ! $user->isRejected()) {
            return back()->with('error', 'Akun ini tidak berstatus menunggu persetujuan.');
        }

        $user->forceFill([
            'registration_status' => User::REGISTRATION_APPROVED,
            'is_active' => true,
            'approved_at' => now(),
            'approved_by' => auth()->id(),
        ])->save();

        \App\Models\AuditLog::record(
            \App\Models\AuditLog::EVENT_UPDATE,
            'users',
            'Menyetujui pendaftaran akun '.$user->name.' ('.$user->email.')',
        );

        // kabari pendaftar bahwa akunnya sudah bisa digunakan
        \App\Services\Notifier::send(
            to: $user->email,
            type: 'register',
            title: 'Akun Anda Telah Disetujui',
            greeting: 'Halo '.$user->name,
            lines: [
                'Selamat! Pendaftaran akun Anda pada aplikasi Dashboard Biro OSDMRB telah disetujui oleh Administrator.',
                'Silakan login menggunakan email dan password yang Anda daftarkan.',
                'Setelah login, Anda dapat melengkapi data diri pada menu Profil.',
            ],
            fields: [
                'Nama' => $user->name,
                'Email' => $user->email,
                'Disetujui Oleh' => auth()->user()->name.' ('.auth()->user()->role_label.')',
                'Waktu' => now()->setTimezone(config('app.timezone'))->format('d F Y H:i'),
            ],
            actionUrl: route('login'),
            actionText: 'Login Sekarang',
        );

        return back()->with('success', "Pendaftaran akun {$user->name} berhasil disetujui dan akun kini aktif.");
    }

    /**
     * Tolak pendaftaran akun pegawai mandiri.
     * Akun tetap tersimpan sebagai catatan namun tidak akan bisa login.
     */
    public function reject(Request $request, User $user)
    {
        abort_if($user->id === auth()->id(), 422, 'Tidak dapat memproses akun sendiri.');

        if (! $user->isPendingApproval() && ! $user->isRejected()) {
            return back()->with('error', 'Akun ini tidak berstatus menunggu persetujuan.');
        }

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $user->forceFill([
            'registration_status' => User::REGISTRATION_REJECTED,
            'is_active' => false,
        ])->save();

        // pastikan tidak ada sesi tersisa milik akun ini
        DB::table('sessions')->where('user_id', $user->id)->delete();

        \App\Models\AuditLog::record(
            \App\Models\AuditLog::EVENT_UPDATE,
            'users',
            'Menolak pendaftaran akun '.$user->name.' ('.$user->email.')'.(filled($validated['reason'] ?? null) ? ' — alasan: '.$validated['reason'] : ''),
        );

        // kabari pendaftar bahwa pendaftarannya ditolak
        \App\Services\Notifier::send(
            to: $user->email,
            type: 'register',
            title: 'Pendaftaran Akun Tidak Disetujui',
            greeting: 'Halo '.$user->name,
            lines: array_values(array_filter([
                'Mohon maaf, pendaftaran akun Anda pada aplikasi Dashboard Biro OSDMRB tidak disetujui oleh Administrator.',
                'Bila Anda merasa ini keliru, silakan hubungi Administrator Utama atau Biro OSDMRB dengan membawa identitas kepegawaian yang sah.',
                filled($validated['reason'] ?? null) ? 'Catatan Administrator: '.$validated['reason'] : null,
            ])),
            fields: [
                'Nama' => $user->name,
                'Email' => $user->email,
                'Waktu' => now()->setTimezone(config('app.timezone'))->format('d F Y H:i'),
            ],
        );

        return back()->with('success', "Pendaftaran akun {$user->name} ditolak. Akun tidak dapat digunakan untuk login.");
    }

    /**
     * Bantu reset password akun pegawai (oleh Admin Bagian / Administrator Utama).
     */
    public function resetPassword(Request $request, User $user)
    {
        abort_if($user->id === auth()->id(), 422, 'Gunakan halaman Profil untuk mengubah password Anda sendiri.');

        if (!$this->canResetPasswordOf($user)) {
            return back()->with('error', 'Admin Bagian hanya dapat membantu reset password akun Pegawai.');
        }

        $validated = $request->validate([
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        \App\Models\AuditLog::record(
            \App\Models\AuditLog::EVENT_PASSWORD,
            'users',
            'Reset password akun '.$user->name.' oleh '.auth()->user()->name
        );

        // notifikasi email ke pemilik akun bahwa passwordnya direset admin
        \App\Services\Notifier::send(
            to: $user->email,
            type: 'password',
            title: 'Password Akun Anda Direset oleh Admin',
            greeting: 'Halo '.$user->name,
            lines: [
                'Password akun Dashboard Biro OSDMRB Anda baru saja direset oleh '.auth()->user()->name.' ('.auth()->user()->role_label.').',
                'Silakan login menggunakan password baru tersebut, kemudian segera ganti password Anda melalui menu Profil.',
            ],
            fields: [
                'Waktu' => now()->setTimezone(config('app.timezone'))->format('d F Y H:i'),
            ],
            actionUrl: route('login'),
            actionText: 'Login',
        );

        return back()->with('success', "Password akun {$user->name} berhasil direset.");
    }

    public function destroy(User $user)
    {
        abort_if($user->id === auth()->id(), 422, 'Anda tidak dapat menghapus akun sendiri.');

        $user->delete();

        return back()->with('success', 'Akun pengguna berhasil dihapus.');
    }
}
