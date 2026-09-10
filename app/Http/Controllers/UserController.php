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

    public function destroy(User $user)
    {
        abort_if($user->id === auth()->id(), 422, 'Anda tidak dapat menghapus akun sendiri.');

        $user->delete();

        return back()->with('success', 'Akun pengguna berhasil dihapus.');
    }
}
