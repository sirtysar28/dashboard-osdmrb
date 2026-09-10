<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Membuat role "Biro SDM" — hampir setara admin untuk verifikasi &
 * persetujuan (approval), namun master data bersifat read-only
 * (pembatasan dilakukan pada route & tampilan).
 */
return new class extends Migration
{
    public function up(): void
    {
        $role = Role::firstOrCreate(['name' => 'biro_sdm']);

        foreach (['verify letters', 'approve letters'] as $permission) {
            $permission = Permission::firstOrCreate(['name' => $permission]);

            $role->givePermissionTo($permission);
        }
    }

    public function down(): void
    {
        Role::where('name', 'biro_sdm')->delete();
    }
};
