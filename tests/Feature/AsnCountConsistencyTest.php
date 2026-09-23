<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Angka KPI dashboard (Total Keseluruhan Pegawai / ASN / PPPK / Non ASN)
 * HARUS konsisten dengan jumlah pada halaman Data Pegawai ASN — pegawai
 * non ASN (aktif) hanya terhitung pada kartu "Non ASN" & total keseluruhan.
 */
class AsnCountConsistencyTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->admin = User::firstWhere('email', 'admin@osdmrb.go.id')
            ?: User::create([
                'name' => 'Admin',
                'email' => 'admin@osdmrb.go.id',
                'password' => Hash::make('password'),
                'employee_id' => null,
                'is_active' => true,
            ]);

        $this->admin->syncRoles(['admin', 'super_admin']);
    }

    public function test_total_asn_dashboard_sama_dengan_daftar_pegawai_asn(): void
    {
        // pegawai NON ASN aktif — hanya boleh terhitung pada KPI Non ASN & total keseluruhan
        Employee::create([
            'nip' => 'N-TEST-1',
            'name' => 'Pramubakti Non Asn',
            'gender' => 'L',
            'employee_type' => Employee::TYPE_NON_ASN,
            'category' => 'Pramubakti',
            'is_active' => true,
        ]);
        Employee::create([
            'nip' => 'N-TEST-2',
            'name' => 'Security Non Asn',
            'gender' => 'L',
            'employee_type' => Employee::TYPE_NON_ASN,
            'category' => 'Security',
            'is_active' => true,
        ]);

        $asnActive = Employee::where('is_active', true)
            ->where('employee_type', '!=', Employee::TYPE_NON_ASN)
            ->count();

        $nonAsnActive = Employee::where('is_active', true)
            ->where('employee_type', Employee::TYPE_NON_ASN)
            ->count();

        // KPI "Total Keseluruhan Pegawai" = ASN aktif + Non ASN aktif
        $this->actingAs($this->admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('<h1>'.number_format($asnActive + $nonAsnActive).'</h1>', false)
            // KPI "Non ASN" hanya menghitung pegawai non ASN aktif
            ->assertSee('<h1>'.number_format($nonAsnActive).'</h1>', false);

        // header halaman Data Pegawai ASN menampilkan jumlah ASN (tanpa non ASN)
        $this->actingAs($this->admin)
            ->get('/employees')
            ->assertOk()
            ->assertSee('Daftar Pegawai ('.$asnActive.')');
    }

    public function test_pegawai_non_asn_tidak_muncul_di_daftar_asn(): void
    {
        Employee::create([
            'nip' => 'N-TEST-3',
            'name' => 'Cleaning Service Non Asn',
            'gender' => 'P',
            'employee_type' => Employee::TYPE_NON_ASN,
            'category' => 'Cleaning Service',
            'is_active' => true,
        ]);

        $this->actingAs($this->admin)
            ->get('/employees')
            ->assertOk()
            ->assertDontSee('Cleaning Service Non Asn');
    }
}
