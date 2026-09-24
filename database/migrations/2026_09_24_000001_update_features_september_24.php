<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pembaruan fitur 24 September 2026 (tindak lanjut catatan rapat 23 Sept):
 *
 * 1. STRUKTUR UNIT KERJA ESELON I TIDAK DOBEL — total tepat 4 unit Eselon I
 *    (Sekretariat Jenderal, Inspektorat Jenderal, Ditjen Pengembangan Ekonomi
 *    dan Pemberdayaan Masyarakat Transmigrasi, Ditjen Pembangunan dan
 *    Pengembangan Kawasan Transmigrasi). Duplikat unit Eselon I (hasil tambah
 *    manual / data lama) digabungkan ke unit resmi: relasi pegawai, jabatan,
 *    arsip, dan unit turunannya dipindah lebih dulu agar tidak ada data yatim.
 *
 * 2. PEGAWAI NON ASN DIMASUKKAN KE SEKRETARIAT JENDERAL — unit kerja Non ASN
 *    tidak diketahui, sehingga jumlah Non ASN per eselon/balai kini
 *    berbeda-beda saat dashboard difilter (tidak lagi muncul sama di semua
 *    filter).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('units')) {
            return;
        }

        // pastikan 4 unit Eselon I resmi ada & terhubung ke root KEMENTERIAN
        Artisan::call('db:seed', [
            '--class' => 'MasterDataSeeder',
            '--force' => true,
        ]);

        $this->dedupeKementerianRoot();
        $this->dedupeEselonSatu();
        $this->assignNonAsnToSetjen();
    }

    /**
     * Pastikan hanya ada SATU unit level KEMENTERIAN (kode KEMEN).
     * Duplikat root lain digabungkan ke KEMEN.
     */
    private function dedupeKementerianRoot(): void
    {
        $root = DB::table('units')->where('code', 'KEMEN')->first();

        if (! $root) {
            return; // seeder selalu membuat KEMEN; guard untuk keamanan
        }

        DB::table('units')
            ->where('level', 'KEMENTERIAN')
            ->where('id', '!=', $root->id)
            ->pluck('id')
            ->each(fn ($id) => $this->mergeUnit($id, $root->id));
    }

    /**
     * Gabungkan SEMUA unit Eselon I di luar 4 unit resmi ke unit resmi yang
     * paling sesuai — total Eselon I berakhir tepat 4.
     */
    private function dedupeEselonSatu(): void
    {
        $root = DB::table('units')->where('code', 'KEMEN')->first();

        $canonical = DB::table('units')
            ->where('level', 'ES_I')
            ->whereIn('code', ['SETJEN', 'ITJEN', 'DJ-EKBANG', 'DJ-KAWASAN'])
            ->get();

        if ($canonical->isEmpty()) {
            return; // seeder gagal dijalankan — jangan lakukan apa pun
        }

        // pastikan seluruh Eselon I resmi berinduk ke root KEMENTERIAN
        if ($root) {
            DB::table('units')
                ->whereIn('id', $canonical->pluck('id'))
                ->update(['parent_id' => $root->id]);
        }

        $duplicates = DB::table('units')
            ->where('level', 'ES_I')
            ->whereNotIn('id', $canonical->pluck('id'))
            ->get();

        foreach ($duplicates as $dupe) {
            $target = $this->resolveCanonicalTarget($dupe->name, $canonical);

            $this->mergeUnit($dupe->id, $target->id);
        }
    }

    /**
     * Cari unit resmi yang paling sesuai untuk duplikat:
     * 1) nama persis sama (normalisasi huruf/tanda baca),
     * 2) kata kunci khas unit (sekretariat/inspektorat/ekonomi/kawasan),
     * 3) kemiripan teks tertinggi — fallback ke Sekretariat Jenderal.
     */
    private function resolveCanonicalTarget(string $name, $canonical)
    {
        $normalize = fn ($v) => strtolower(preg_replace('/[^a-z0-9]+/i', '', $v));

        // 1) nama sama persis (setelah normalisasi)
        $exact = $canonical->first(fn ($u) => $normalize($u->name) === $normalize($name));

        if ($exact) {
            return $exact;
        }

        // 2) kata kunci khas
        $lower = strtolower($name);

        $keyword = match (true) {
            str_contains($lower, 'inspektorat') => 'ITJEN',
            str_contains($lower, 'ekonomi') || str_contains($lower, 'ekbang') => 'DJ-EKBANG',
            str_contains($lower, 'kawasan') => 'DJ-KAWASAN',
            str_contains($lower, 'sekretariat') => 'SETJEN',
            default => null,
        };

        if ($keyword && $match = $canonical->firstWhere('code', $keyword)) {
            return $match;
        }

        // 3) kemiripan teks tertinggi
        $best = null;
        $bestScore = 0;

        foreach ($canonical as $unit) {
            similar_text($normalize($unit->name), $normalize($name), $score);

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $unit;
            }
        }

        return $best ?: $canonical->firstWhere('code', 'SETJEN');
    }

    /**
     * Pindahkan seluruh relasi unit $from ke unit $to lalu hapus $from.
     * Relasi: pegawai, riwayat jabatan, arsip, dan unit turunan.
     */
    private function mergeUnit(int $from, int $to): void
    {
        if ($from === $to) {
            return;
        }

        if (Schema::hasTable('employees') && Schema::hasColumn('employees', 'unit_id')) {
            DB::table('employees')->where('unit_id', $from)->update(['unit_id' => $to]);
        }

        if (Schema::hasTable('employee_positions') && Schema::hasColumn('employee_positions', 'unit_id')) {
            DB::table('employee_positions')->where('unit_id', $from)->update(['unit_id' => $to]);
        }

        if (Schema::hasTable('archives') && Schema::hasColumn('archives', 'unit_id')) {
            DB::table('archives')->where('unit_id', $from)->update(['unit_id' => $to]);
        }

        // unit turunan (eselon di bawahnya) dipindah ke unit penggabung
        DB::table('units')->where('parent_id', $from)->update(['parent_id' => $to]);

        DB::table('units')->where('id', $from)->delete();
    }

    /**
     * Pegawai Non ASN yang belum punya unit kerja dimasukkan ke
     * Sekretariat Jenderal (unit kerjanya tidak diketahui).
     */
    private function assignNonAsnToSetjen(): void
    {
        if (! Schema::hasTable('employees') || ! Schema::hasColumn('employees', 'employee_type')) {
            return;
        }

        $setjen = DB::table('units')->where('code', 'SETJEN')->value('id');

        if (! $setjen) {
            return;
        }

        DB::table('employees')
            ->where('employee_type', 'non_asn')
            ->whereNull('unit_id')
            ->update(['unit_id' => $setjen]);
    }

    public function down(): void
    {
        // pembenahan data master tidak dikembalikan otomatis
    }
};
