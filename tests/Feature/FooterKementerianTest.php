<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Footer bawah dashboard mengikuti situs Kementerian Transmigrasi:
 * copyright + ikon media sosial resmi, background #debb7f.
 */
class FooterKementerianTest extends TestCase
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

    public function test_footer_dashboard_menampilkan_copyright_dan_sosial_media(): void
    {
        $this->actingAs($this->admin)
            ->get('/dashboard')
            ->assertOk()
            // bar footer kementerian
            ->assertSee('app-footer', false)
            ->assertSee('Kementerian Transmigrasi Republik Indonesia')
            // 5 ikon sosial media resmi
            ->assertSee('https://www.facebook.com/kementrans.ri', false)
            ->assertSee('https://x.com/Kementrans_RI', false)
            ->assertSee('https://www.instagram.com/kementrans.ri/', false)
            ->assertSee('https://www.tiktok.com/@kementrans.ri', false)
            ->assertSee('https://www.youtube.com/@kementrans_ri', false);
    }

    public function test_catatan_analisis_jabatan_fungsional_selebar_full(): void
    {
        $this->actingAs($this->admin)
            ->get('/modul/analisis-jabatan-fungsional')
            ->assertOk()
            ->assertSee('Catatan Analisis')
            // card catatan kini berada di luar kolom (selebar penuh)
            ->assertSee('CATATAN ANALISIS (selebar full)', false);
    }
}
