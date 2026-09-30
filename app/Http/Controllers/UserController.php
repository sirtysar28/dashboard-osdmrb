<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\User;
use App\Traits\ExportsTable;
use Illuminate\Http\Request;
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
        $users = User::with(['roles', 'employee'])
            ->when($request->search, fn ($q, $v) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$v}%")
                ->orWhere('email', 'like', "%{$v}%")))
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
                $user->is_active ? 'Aktif' : 'Non-aktif',
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
