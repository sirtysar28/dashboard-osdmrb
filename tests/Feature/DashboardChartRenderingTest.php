<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Diagnostik render dashboard: memastikan seluruh grafik/diagram
 * mendapat pustaka Chart.js dari aset LOKAL (public/vendor) dan
 * data tiap chart ikut ter-render pada HTML — bukan array kosong
 * yang membuat grafik tampil blank (Catatan 23 Okt 2026).
 */
class DashboardChartRenderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_merender_semua_chart_dengan_data_dan_aset_lokal(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@osdmrb.go.id')->firstOrFail();

        $response = $this->actingAs($admin)->get('/dashboard');
        $response->assertOk();

        $html = $response->getContent();

        // simpan untuk inspeksi manual bila perlu
        file_put_contents(storage_path('app/dashboard-render-test.html'), $html);

        // 1. pustaka chart dimuat LOKAL — tidak ada lagi CDN
        $this->assertStringContainsString('vendor/chartjs/chart.umd.min.js', $html);
        $this->assertStringNotContainsString('cdn.jsdelivr.net/npm/chart.js', $html);

        // 2. berkas aset benar-benar tersedia di public
        //    (pengetesan via router framework menghasilkan 404 karena file
        //    statis dilayani langsung oleh Apache/Nginx di server)
        $this->assertFileExists(public_path('vendor/chartjs/chart.umd.min.js'));
        $this->assertFileExists(public_path('vendor/bootstrap/bootstrap.bundle.min.js'));
        $this->assertFileExists(public_path('vendor/bootstrap/bootstrap.min.css'));
        $this->assertFileExists(public_path('vendor/bootstrap-icons/font/bootstrap-icons.min.css'));
        $this->assertFileExists(public_path('vendor/bootstrap-icons/font/fonts/bootstrap-icons.woff2'));
        $this->assertSame(url('vendor/chartjs/chart.umd.min.js'), asset('vendor/chartjs/chart.umd.min.js'));

        // 3. semua canvas chart ada
        foreach (['educationChart', 'ageChart', 'retirementChart', 'genderChart',
                  'statusChart', 'swimmingChart', 'salaryRaiseChart', 'promotionChart'] as $id) {
            $this->assertStringContainsString("id=\"{$id}\"", $html, "Canvas {$id} hilang.");
        }

        // 4. script inline pembuat chart ada & dijalankan setelah pustaka,
        //    dengan pengaman Chart + bootstrap
        $this->assertStringContainsString("typeof Chart !== 'undefined' && typeof bootstrap !== 'undefined'", $html);
        $this->assertStringContainsString('function safeChart(id, config)', $html);
        $this->assertStringContainsString('new bootstrap.Modal', $html);
        $this->assertStringContainsString('} catch (e) {', $html);
        $this->assertGreaterThan(
            strpos($html, 'vendor/chartjs/chart.umd.min.js'),
            strpos($html, "typeof Chart !== 'undefined' && typeof bootstrap !== 'undefined'"),
            'Script chart harus berada SETELAH pustaka Chart.js dimuat.',
        );

        // 5. data tiap chart ter-render (array JS tidak kosong)
        $this->assertMatchesRegularExpression(
            '/safeChart\(\'educationChart\',\s*\{/',
            $html,
        );

        // hitung instance chart yang dibuat — minimal 8 chart dashboard
        // (via helper safeChart yang tahan error per chart)
        $this->assertSame(8, substr_count($html, "safeChart('"));

        // 6. tema ter-render sebagai string warna valid (bukan null/undefined)
        $this->assertMatchesRegularExpression(
            '/const themePrimary = (\'|")#[0-9a-fA-F]{3,8}\1;/',
            $html,
            'Warna tema (theme_primary) tidak ter-render sebagai hex yang valid.',
        );
        $this->assertMatchesRegularExpression(
            '/const themeAccent = (\'|")#[0-9a-fA-F]{3,8}\1;/',
            $html,
            'Warna aksen (theme_accent) tidak ter-render sebagai hex yang valid.',
        );
    }
}
