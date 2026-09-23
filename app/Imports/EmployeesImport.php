<?php

namespace App\Imports;

use App\Models\EducationLevel;
use App\Models\Employee;
use App\Models\EmploymentStatus;
use App\Models\Rank;
use App\Models\Unit;
use Carbon\Carbon;
use Carbon\CarbonInterval;
use DateTimeInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Import massal data pegawai dari Excel/CSV.
 *
 * Mendukung DUA format berkas:
 * 1. Format "Data Dashboard.xlsx" milik instansi (header: NAMA, NIP, STATUS,
 *    Nama Jabatan, Level Eselon, TMT JABATAN, PANGKAT, GOLONGAN, KENAIKAN
 *    PANGKAT, TMT CPNS, PENDIDIKAN TERAKHIR, BATAS USIA PENSIUN, dst)
 *    — tidak perlu mengunduh template khusus lagi.
 * 2. Format template import lama (nama, nip, jenis_kelamin, status_kode, dst).
 *
 * - Baris diproses per baris; NIP yang sudah ada akan diperbarui (update).
 * - Unit kerja yang belum ada di master otomatis dibuat (eselon I / II).
 * - Tanggal pensiun kosong dihitung dari BUP:
 *   60 th untuk Eselon I/II & Fungsional Madya, selain itu 58 th.
 */
class EmployeesImport implements ToCollection, WithHeadingRow
{
    public int $created = 0;
    public int $updated = 0;
    public array $errors = [];

    protected array $statusMap;
    protected array $rankMap;
    protected array $educationMap;
    protected array $unitMap;

    /** alias nilai master -> kunci peta */
    protected array $statusAliases = [
        'pppk' => 'pppk penuh waktu',
        'pppk ft' => 'pppk penuh waktu',
        'pns' => 'asn',            // istilah lama -> ASN (penamaan konsisten)
        'pns baru' => 'asn',
        'asn baru' => 'asn',
    ];

    protected array $educationAliases = [
        'slta' => 'sma',
        'sma/smk' => 'sma',
        'smk' => 'sma',
        'd4/s1' => 's1',
        's1/d4' => 's1',
        'profesi' => 's2',
    ];

    public function __construct(protected bool $createUnits = true)
    {
        $this->statusMap = EmploymentStatus::query()
            ->get(['id', 'code', 'name'])
            ->flatMap(fn ($r) => [mb_strtolower($r->code) => $r->id, mb_strtolower($r->name) => $r->id])
            ->toArray();

        $this->rankMap = Rank::query()
            ->get(['id', 'code', 'name'])
            ->flatMap(fn ($r) => [
                mb_strtolower($r->code) => $r->id,
                mb_strtolower((string) $r->name) => $r->id,
            ])
            ->toArray();

        $this->educationMap = EducationLevel::query()
            ->get(['id', 'code', 'name'])
            ->flatMap(fn ($r) => [mb_strtolower($r->code) => $r->id, mb_strtolower($r->name) => $r->id])
            ->toArray();

        $this->unitMap = Unit::query()
            ->get(['id', 'code', 'name'])
            ->flatMap(fn ($r) => [
                $this->normalizeName($r->name) => $r->id,
                mb_strtolower((string) $r->code) => $r->id,
            ])
            ->toArray();
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            // +2 : baris 1 = header, index mulai dari 0
            $line = $index + 2;

            $nip = $this->normalizeNip($this->val($row, ['nip', 'no_nip', 'id_pegawai', 'nip_baru']));
            $name = trim((string) $this->val($row, ['nama', 'name'], ''));

            if ($nip === '' && $name === '') {
                continue; // baris kosong
            }

            $errors = [];

            if ($name === '') {
                $errors[] = 'Nama wajib diisi';
            }

            $genderRaw = $this->val($row, ['jenis_kelamin', 'gender', 'jk']);
            $gender = $this->normalizeGender($genderRaw);
            if ($gender === null) {
                $errors[] = 'Jenis kelamin harus L / P';
            }

            $birthDate = $this->normalizeDate($this->val($row, ['tanggal_lahir', 'tgl_lahir']));

            // ---- status kepegawaian ----
            $statusRaw = $this->val($row, ['status_kode', 'status', 'status_asn']);
            $statusId = $this->resolveStatus($statusRaw, $errors);

            // ---- golongan/pangkat ----
            // "GOL" (angka romawi murni) TIDAK dipakai: berisiko salah cocok dgn Klaster
            $rankRaw = $this->val($row, ['golongan_kode', 'golongan']) ?: $this->val($row, ['pangkat']);
            $rankId = $this->resolveRank($rankRaw);

            // ---- pendidikan ----
            $educationRaw = $this->val($row, ['pendidikan_kode', 'level_pendidikan', 'pendidikan']);
            $educationId = $this->resolveEducation($educationRaw);

            // ---- unit kerja ----
            $unitId = $this->resolveUnit(
                es2: $this->val($row, ['unit_kode', 'es_ii', 'unit_kerja_eselon_ii', 'unit']),
                es1: $this->val($row, ['es_i', 'unit_kerja_eselon_i']),
            );

            // ---- eselon & fungsional ----
            $eselon = $this->normalizeEselon($this->val($row, ['eselon', 'level_eselon', 'jenjang']));
            $functional = $this->val($row, ['level_fungsional', 'fungsional']);

            if ($eselon !== null && ! in_array($eselon, ['I', 'II', 'III', 'IV'])) {
                $eselon = null;
            }

            if ($errors) {
                $this->errors[] = "Baris {$line} ({$name}): ".implode('; ', $errors);
                continue;
            }

            // ---- pensiun & kenaikan jabatan ----
            $retirement = $this->normalizeDate($this->val($row, ['tanggal_pensiun', 'batas_usia_pensiun', 'bup']));
            $nextPromotion = $this->normalizeDate($this->val($row, ['kenaikan_pangkat', 'kenaikan_jabatan']));

            if (! $retirement && $birthDate) {
                $retirement = $this->computeBup($birthDate, $eselon, $functional);
            }

            $data = [
                'name' => $name,
                'gender' => $gender,
                'birth_place' => $this->text($this->val($row, ['tempat_lahir'])),
                'birth_date' => $birthDate,
                'religion' => $this->text($this->val($row, ['agama'])),
                'email' => $this->text($this->val($row, ['email'])),
                'phone' => $this->text($this->val($row, ['telepon', 'no_tlp', 'tlp', 'hp'])),
                'address' => $this->text($this->val($row, ['alamat'])),
                'employment_status_id' => $statusId,
                'rank_id' => $rankId,
                'education_level_id' => $educationId,
                'unit_id' => $unitId,
                'position_name' => $this->text($this->val($row, ['jabatan', 'nama_jabatan'])),
                'eselon' => $eselon,
                'functional_level' => $this->text($functional),
                'tmt_jabatan' => $this->normalizeDate($this->val($row, ['tmt_jabatan'])),
                'tmt_golongan' => $this->normalizeDate($this->val($row, ['tmt_golongan', 'tmt_gol'])),
                'next_promotion_date' => $nextPromotion,
                'tmt_cpns' => $this->normalizeDate($this->val($row, ['tmt_cpns'])),
                'tmt_pns' => $this->normalizeDate($this->val($row, ['tmt_pns', 'tmt_asn'])),
                'retirement_date' => $retirement,
                'npwp' => $this->text($this->val($row, ['npwp'])),
                'karpeg' => $this->text($this->val($row, ['karpeg'])),
                'education_1' => $this->text($this->val($row, ['pendidikan_1', 'pendidikan_terakhir_1'])),
                'education_2' => $this->text($this->val($row, ['pendidikan_2', 'pendidikan_terakhir_2'])),
                'education_3' => $this->text($this->val($row, ['pendidikan_3', 'pendidikan_terakhir_3'])),
                'is_active' => $this->normalizeBool($this->val($row, ['aktif', 'status_aktif']), true),
            ];

            $employee = $nip !== ''
                ? Employee::where('nip', $nip)->first()
                : Employee::where('name', $name)->first();

            if ($employee) {
                $employee->update($data);
                $this->updated++;
            } else {
                Employee::create($data + ['nip' => $nip !== '' ? $nip : $this->generateNip($name)]);
                $this->created++;
            }
        }
    }

    /* ================= HELPERS ================= */

    /**
     * Ambil nilai baris dari beberapa kemungkinan kunci kolom.
     */
    protected function val(Collection $row, array $keys, mixed $default = null): mixed
    {
        foreach ($keys as $key) {
            $value = $row[$key] ?? $row[Str::slug($key, '_')] ?? null;

            if ($value !== null && trim((string) $value) !== '' && $value !== '-') {
                return $value;
            }
        }

        return $default;
    }

    protected function text(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_float($value) || is_int($value)) {
            return (string) $value; // nomor telepon dll.
        }

        $value = trim((string) $value);

        return $value !== '' && $value !== '-' ? $value : null;
    }

    protected function normalizeNip(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_int($value)) {
            return (string) $value;
        }

        if (is_float($value)) {
            return sprintf('%.0f', $value);
        }

        return trim((string) $value);
    }

    protected function normalizeGender(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return 'L'; // default bila kosong (mengikuti perilaku import lama)
        }

        $value = mb_strtolower(trim((string) $value));

        return match (true) {
            in_array($value, ['l', 'lk', 'pria', 'laki-laki', 'laki laki', 'lelaki', 'm', 'male', '1']) => 'L',
            in_array($value, ['p', 'pr', 'wanita', 'perempuan', 'f', 'female', '2']) => 'P',
            default => null,
        };
    }

    protected function normalizeEselon(mixed $value): ?string
    {
        $value = strtoupper(trim((string) ($value ?? '')));

        if ($value === '' || $value === '-') {
            return null;
        }

        // "Fungsional Tertentu*" bukan eselon struktural
        if (str_contains($value, 'FUNGSIONAL') || str_contains($value, 'JFT')) {
            return null;
        }

        $value = str_replace(['ES.', 'ES', 'ESELON', ' '], '', $value);

        return in_array($value, ['I', 'II', 'III', 'IV', '1', '2', '3', '4']) ? strtr($value, ['1' => 'I', '2' => 'II', '3' => 'III', '4' => 'IV']) : null;
    }

    /**
     * Hitung tanggal BUP dari tanggal lahir.
     * 60 th: Eselon I/II & Fungsional Madya — selain itu 58 th.
     */
    protected function computeBup(?string $birthDate, ?string $eselon, mixed $functional): ?string
    {
        if (! $birthDate) {
            return null;
        }

        $isMadya = $functional && str_contains(mb_strtolower((string) $functional), 'madya');
        $limit = (in_array($eselon, ['I', 'II']) || $isMadya) ? 60 : 58;

        try {
            return Carbon::parse($birthDate)->addYears($limit)->endOfMonth()->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    protected function normalizeDate(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '' || $value === '-') {
            return null;
        }

        try {
            if ($value instanceof Carbon || $value instanceof DateTimeInterface) {
                return Carbon::instance($value instanceof Carbon ? $value->startOfDay() : $value)->toDateString();
            }

            if ($value instanceof CarbonInterval) {
                return null;
            }

            if (is_int($value) || is_float($value)) {
                // angka serial excel (1 = 1900-01-01)
                if ($value > 20000 && $value < 80000) {
                    return ExcelDate::excelToDateTimeObject($value)->format('Y-m-d');
                }

                // format angka murni YYYYMMDD
                $digits = (string) (int) $value;
                if (strlen($digits) === 8) {
                    return Carbon::createFromFormat('Ymd', $digits)?->toDateString();
                }

                return null;
            }

            $value = trim((string) $value);

            // tanggal format Indonesia: 16 November 1969 / 01 April 2028
            $bulan = [
                'januari' => '01', 'februari' => '02', 'maret' => '03', 'april' => '04',
                'mei' => '05', 'juni' => '06', 'juli' => '07', 'agustus' => '08',
                'september' => '09', 'oktober' => '10', 'november' => '11', 'desember' => '12',
                'jan' => '01', 'feb' => '02', 'mar' => '03', 'apr' => '04', 'jun' => '06',
                'jul' => '07', 'aug' => '08', 'sept' => '09', 'okt' => '10', 'nov' => '11', 'des' => '12',
            ];

            if (preg_match('/^(\d{1,2})[\s\/\-]+([A-Za-z]+)[\s\/\-]+(\d{4})$/', $value, $m)) {
                $month = $bulan[mb_strtolower($m[2])] ?? null;
                if ($month) {
                    return sprintf('%04d-%02d-%02d', $m[3], $month, $m[1]);
                }
            }

            if (preg_match('/^([A-Za-z]+)[\s\/\-]+(\d{1,2})[\s,]+(\d{4})$/', $value, $m)) {
                $month = $bulan[mb_strtolower($m[1])] ?? null;
                if ($month) {
                    return sprintf('%04d-%02d-%02d', $m[3], $month, $m[2]);
                }
            }

            foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'Y/m/d', 'd M Y', 'd F Y', 'd.m.Y'] as $format) {
                try {
                    return Carbon::createFromFormat($format, $value)?->toDateString();
                } catch (\Throwable) {
                    continue;
                }
            }

            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    protected function normalizeBool(mixed $value, bool $default): bool
    {
        if ($value === null || trim((string) $value) === '' || $value === '-') {
            return $default;
        }

        $value = mb_strtolower(trim((string) $value));

        return ! in_array($value, ['0', 'tidak', 'no', 'n', 'nonaktif', 'non-aktif', 'false', 'x'], true);
    }

    /**
     * Cari id status kepegawaian dari kode/nama + alias umum.
     */
    protected function resolveStatus(mixed $value, array &$errors): ?int
    {
        $key = mb_strtolower(trim((string) ($value ?? '')));

        if ($key === '' || $key === '-' || $key === '0') {
            return null; // "0" / kosong pada berkas tidak dianggap error
        }

        $key = str_replace(['_', '.'], ' ', $key);
        $key = $this->statusAliases[$key] ?? $key;

        if (isset($this->statusMap[$key])) {
            return $this->statusMap[$key];
        }

        // "PPPK Penuh Waktu" ditulis "PPPK Penuh" dll.
        foreach ($this->statusMap as $mapKey => $id) {
            if (str_starts_with($mapKey, $key) || str_starts_with($key, $mapKey)) {
                return $id;
            }
        }

        $errors[] = "Status kepegawaian \"{$key}\" tidak ditemukan di master data";

        return null;
    }

    /**
     * Cari id golongan dari kode "(IV.d)" / "IV/d" / nama pangkat.
     */
    protected function resolveRank(mixed $value): ?int
    {
        $key = trim((string) ($value ?? ''));

        if ($key === '' || $key === '-' || $key === 'X') {
            return null;
        }

        // "(IV.d)" -> "IV.d" ; "IV/d" tetap
        $key = trim($key, '()');
        $normalized = mb_strtolower(str_replace(['.', '-', ' '], '/', $key));

        if (isset($this->rankMap[$normalized])) {
            return $this->rankMap[$normalized];
        }

        $lower = mb_strtolower($key);
        if (isset($this->rankMap[$lower])) {
            return $this->rankMap[$lower];
        }

        // nama pangkat: "Pembina Utama Madya"
        foreach ($this->rankMap as $mapKey => $id) {
            if ($mapKey === $lower || str_contains($mapKey, $lower) || str_contains($lower, $mapKey)) {
                return $id;
            }
        }

        return null;
    }

    protected function resolveEducation(mixed $value): ?int
    {
        $key = mb_strtolower(trim((string) ($value ?? '')));

        if ($key === '' || $key === '-') {
            return null;
        }

        $key = str_replace(['.', '  '], ['', ' '], $key);
        $key = $this->educationAliases[$key] ?? $key;

        if (isset($this->educationMap[$key])) {
            return $this->educationMap[$key];
        }

        foreach ($this->educationMap as $mapKey => $id) {
            if (str_starts_with($mapKey, $key) || str_starts_with($key, $mapKey)) {
                return $id;
            }
        }

        return null;
    }

    /**
     * Cari id unit dari nama (kolom Es. II / Es. I pada Data Dashboard).
     * Unit baru otomatis dibuat agar data tidak menggantung.
     */
    protected function resolveUnit(mixed $es2, mixed $es1): ?int
    {
        $name = trim((string) ($es2 ?? ''), " \t\n\r\0\x0B,");

        if ($name === '' || $name === '-') {
            return null;
        }

        $normalized = $this->normalizeName($name);

        if (isset($this->unitMap[$normalized])) {
            return $this->unitMap[$normalized];
        }

        if (! $this->createUnits) {
            return null;
        }

        // cari parent eselon I
        $parentId = null;

        $es1Name = trim((string) ($es1 ?? ''), " \t\n\r\0\x0B,");
        if ($es1Name !== '' && $es1Name !== '-') {
            $es1Normalized = $this->normalizeName($es1Name);
            $parentId = $this->unitMap[$es1Normalized] ?? null;

            if (! $parentId) {
                $parent = Unit::create([
                    'name' => $es1Name,
                    'code' => $this->generateUnitCode($es1Name),
                    'level' => 'ES_I',
                    'parent_id' => Unit::where('level', 'KEMENTERIAN')->value('id'),
                ]);
                $parentId = $this->unitMap[$es1Normalized] = $parent->id;
            }
        }

        // apakah unit ini sebenarnya setingkat eselon I? (nama berakhir "Jenderal")
        $level = (bool) preg_match('/(jenderal|direktorat jenderal|inspektorat jenderal|sekretariat jenderal)/i', $name) ? 'ES_I' : 'ES_II';

        $unit = Unit::create([
            'name' => $name,
            'code' => $this->generateUnitCode($name),
            'level' => $level,
            'parent_id' => $parentId,
        ]);

        return $this->unitMap[$normalized] = $unit->id;
    }

    /**
     * Kode unit unik dari nama (mis. "Biro Hukum" -> BIRO-HUKUM).
     */
    protected function generateUnitCode(string $name): string
    {
        $base = strtoupper(preg_replace('/[^a-z0-9]+/i', '-', trim($name)) ?? '');
        $base = trim($base, '-') ?: 'UNIT';
        $base = \Illuminate\Support\Str::limit($base, 40, '');

        $code = $base;
        $i = 1;
        while (Unit::where('code', $code)->exists()) {
            $code = $base.'-'.(++$i);
        }

        return $code;
    }

    protected function normalizeName(string $name): string
    {
        $name = mb_strtolower(trim($name));
        $name = preg_replace('/[^a-z0-9]+/', '', $name) ?? $name;

        return trim($name);
    }

    /**
     * NIP generik untuk pegawai tanpa NIP (non ASN).
     */
    protected function generateNip(string $name): string
    {
        $base = 'N-'.$this->normalizeName(Str::ascii($name) ?: 'pegawai');
        $candidate = $base;

        $i = 1;
        while (Employee::where('nip', $candidate)->exists()) {
            $candidate = $base.'-'.(++$i);
        }

        return $candidate;
    }
}
