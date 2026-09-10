<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        /* ================= ROLES & PERMISSIONS ================= */

        // firstOrCreate agar aman bila role sudah dibuat oleh migration
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $pegawaiRole = Role::firstOrCreate(['name' => 'pegawai']);
        $biroSdmRole = Role::firstOrCreate(['name' => 'biro_sdm']);
        $superAdminRole = Role::firstOrCreate(['name' => 'super_admin']);

        $verifyPermission = Permission::firstOrCreate(['name' => 'verify letters']);
        $approvePermission = Permission::firstOrCreate(['name' => 'approve letters']);

        $adminRole->givePermissionTo([$verifyPermission, $approvePermission]);
        $biroSdmRole->givePermissionTo([$verifyPermission, $approvePermission]);
        $superAdminRole->givePermissionTo([$verifyPermission, $approvePermission]);

        /* ================= MASTER DATA ================= */

        $this->call([
            MasterDataSeeder::class,
            EmployeeSeeder::class,
        ]);

        /* ================= AKUN PENGGUNA ================= */

        // Admin instansi — Kepala Biro (Tunggak Santosa)
        $biroChief = Employee::where('eselon', 'II')->first()
            ?? Employee::first();

        // updateOrCreate: aman bila seeder dijalankan berulang
        $admin = User::updateOrCreate(
            ['email' => 'admin@osdmrb.go.id'],
            [
                'name' => $biroChief?->name ?? 'Admin OSDMRB',
                'password' => Hash::make('password'),
                'employee_id' => $biroChief?->id,
                'is_active' => true,
            ]
        );
        $admin->syncRoles(['admin', 'super_admin']);

        // User pegawai — pegawai pertama non-struktural
        $staff = Employee::whereNull('eselon')->orderBy('id')->first();

        if ($staff) {
            $userPegawai = User::updateOrCreate(
                ['email' => 'pegawai@osdmrb.go.id'],
                [
                    'name' => $staff->name,
                    'password' => Hash::make('password'),
                    'employee_id' => $staff->id,
                    'is_active' => true,
                ]
            );
            $userPegawai->syncRoles('pegawai');
        }

        // User Biro SDM — pegawai kedua non-struktural (akun verifikasi/persetujuan)
        $sdmStaff = Employee::whereNull('eselon')->orderByDesc('id')->first();

        if ($sdmStaff) {
            $userSdm = User::updateOrCreate(
                ['email' => 'sdm@osdmrb.go.id'],
                [
                    'name' => $sdmStaff->name,
                    'password' => Hash::make('password'),
                    'employee_id' => $sdmStaff->id,
                    'is_active' => true,
                ]
            );
            $userSdm->syncRoles('biro_sdm');
        }

        /* ================= LAYANAN PERSURATAN ================= */

        $this->call([
            LetterSeeder::class,
        ]);

        /* ================= LAYANAN KEARSIPAN ================= */

        $this->call([
            ArchiveSeeder::class,
        ]);
    }
}
