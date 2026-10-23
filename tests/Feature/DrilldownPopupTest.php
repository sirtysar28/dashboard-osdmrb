<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Popup drill-down grafik dashboard (Catatan Masukan 23 Okt 2026):
 * tampilan dibatasi 10 baris per halaman (paginasi) + pencarian popup
 * khusus nama / NIP / jabatan.
 */
class DrilldownPopupTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::where('email', 'admin@osdmrb.go.id')->firstOrFail();
    }

    /** Simpel GET JSON helper ke endpoint drill-down. */
    private function drilldown(array $params): array
    {
        return $this->actingAs($this->admin())
            ->getJson(route('dashboard.drilldown', $params))
            ->assertOk()
            ->assertJsonStructure(['total', 'page', 'per_page', 'last_page', 'items'])
            ->json();
    }

    public function test_drilldown_dibatasi_10_baris_per_halaman_dengan_paginasi(): void
    {
        $this->seed(DatabaseSeeder::class);

        $first = $this->drilldown(['category' => 'education', 'value' => 'S1 / Sarjana']);

        $this->assertSame(10, $first['per_page']);
        $this->assertCount(min(10, $first['total']), $first['items']);
        $this->assertSame(1, $first['page']);
        $this->assertSame((int) ceil($first['total'] / 10), $first['last_page']);

        // halaman 2 berisi lanjutan daftar (bukan pengulangan halaman 1)
        if ($first['last_page'] > 1) {
            $second = $this->drilldown(['category' => 'education', 'value' => 'S1 / Sarjana', 'page' => 2]);

            $this->assertSame(2, $second['page']);
            $this->assertSame($first['total'], $second['total'], 'Total tidak boleh berubah antar halaman.');
            $this->assertLessThanOrEqual(10, count($second['items']));

            $idsFirst = array_column($first['items'], 'id');
            $idsSecond = array_column($second['items'], 'id');
            $this->assertEmpty(array_intersect($idsFirst, $idsSecond), 'Halaman 2 tidak boleh mengulang baris halaman 1.');
        }

        // halaman di luar rentang otomatis dipatok ke halaman terakhir
        $overflow = $this->drilldown(['category' => 'education', 'value' => 'S1 / Sarjana', 'page' => 9999]);
        $this->assertSame($first['last_page'], $overflow['page']);
    }

    public function test_drilldown_pencarian_hanya_nama_nip_dan_jabatan(): void
    {
        $this->seed(DatabaseSeeder::class);

        // nama pegawai riil dari seeder
        $sample = \App\Models\Employee::query()
            ->where('employee_type', '!=', \App\Models\Employee::TYPE_NON_ASN)
            ->where('is_active', true)
            ->firstOrFail();

        // 1) cari berdasarkan NIP -> pasti ketemu & minimal berisi pegawai tsb.
        $byNip = $this->drilldown([
            'category' => 'education',
            'value' => 'S1 / Sarjana',
            'q' => $sample->nip,
        ]);

        // NIP unik: hasil harus tepat pegawai tersebut (bila pendidikannya S1);
        // bila bukan S1 hasil kosong — pastikan konsisten dgn filter kategori.
        $expected = $sample->education?->name === 'S1 / Sarjana' ? 1 : 0;
        $this->assertSame($expected, $byNip['total']);

        // 2) cari potongan nama
        $fragment = mb_substr($sample->name, 0, 3);
        $byName = $this->drilldown([
            'category' => 'education',
            'value' => 'S1 / Sarjana',
            'q' => $fragment,
        ]);

        $this->assertGreaterThanOrEqual($expected, $byName['total']);
        foreach ($byName['items'] as $row) {
            $haystack = mb_strtolower($row['name'].' '.$row['nip'].' '.$row['position']);
            $this->assertStringContainsString(mb_strtolower($fragment), $haystack);
        }

        // 3) pencarian yang pasti tidak cocok -> nol baris, halaman tetap valid
        $none = $this->drilldown([
            'category' => 'education',
            'value' => 'S1 / Sarjana',
            'q' => 'ZZZTIDAKADA999',
        ]);
        $this->assertSame(0, $none['total']);
        $this->assertSame([], $none['items']);
        $this->assertSame(1, $none['last_page']);
    }

    public function test_drilldown_renang_belum_diisi_mengembalikan_baris_kosong(): void
    {
        $this->seed(DatabaseSeeder::class);

        $data = $this->drilldown(['category' => 'swimming', 'value' => 'Belum Diisi']);

        $this->assertGreaterThanOrEqual(0, $data['total']); // endpoint tidak error

        // total wajib sama dengan jumlah pegawai aktif ASN yang kolom
        // swimming_skill-nya NULL/nilainya selain lulus|belum
        $expected = \App\Models\Employee::query()
            ->where('is_active', true)
            ->where('employee_type', '!=', \App\Models\Employee::TYPE_NON_ASN)
            ->where(fn ($q) => $q->whereNull('swimming_skill')
                ->orWhereNotIn('swimming_skill', ['lulus', 'belum']))
            ->count();
        $this->assertSame($expected, $data['total']);
    }

    public function test_drilldown_semua_kategori_mengembalikan_struktur_seragam(): void
    {
        $this->seed(DatabaseSeeder::class);

        foreach ([
            ['education', 'S1 / Sarjana'],
            ['gender', 'Perempuan'],
            ['age', '31-40'],
            ['status', 'ASN'],
        ] as [$category, $value]) {
            $data = $this->drilldown(['category' => $category, 'value' => $value]);

            foreach ($data['items'] as $row) {
                $this->assertSame(
                    ['id', 'name', 'nip', 'position', 'unit', 'status', 'age', 'due_year'],
                    array_keys($row),
                    "Struktur baris kategori {$category} tidak seragam.",
                );
            }
        }
    }
}
