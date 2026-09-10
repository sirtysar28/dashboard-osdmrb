<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Angka "Total ASN" di kartu dashboard HARUS konsisten dengan jumlah pada
 * halaman Data Pegawai ASN — pegawai non ASN (aktif) tidak boleh ikut
 * terhitung sebagai ASN.
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
        // pegawai NON ASN aktif — tidak boleh terhitung sebagai ASN
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

        // KPI dashboard "Total ASN" hanya menghitung ASN aktif
        $this->actingAs($this->admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('<h1>'.number_format($asnActive).'</h1>', false);

        // header halaman Data Pegawai ASN menampilkan jumlah yang sama
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
