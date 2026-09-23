<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EducationLevel;
use App\Models\EmploymentStatus;
use App\Models\Rank;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class DashboardService
{
    /**
     * Query dasar pegawai ASN aktif beserta relasi yang dibutuhkan dashboard.
     * (Hanya ASN — konsisten dengan daftar "Data Pegawai ASN";
     * pegawai non ASN dihitung terpisah pada KPI Non ASN.)
     */
    public function baseQuery(): Builder
    {
        return Employee::query()
            ->with([
                'unit', 'education', 'rank', 'employmentStatus',
                'currentPosition.position.positionType',
                'currentPosition.position.jobLevel',
            ])
            ->where('employees.is_active', true)
            ->where('employees.employee_type', '!=', Employee::TYPE_NON_ASN);
    }

    /**
     * Terapkan filter (unit es1/es2/balai, status ASN — bisa lebih dari satu,
     * golongan, jenis kelamin, pendidikan, pencarian).
     */
    /**
     * Terapkan filter dashboard.
     *
     * Semua filter bersifat MULTI-PILIH (checklist dropdown — parameter array
     * `es1[]`, `es2[]`, dst.); nilai tunggal dari URL lama tetap didukung.
     * Bila tidak ada yang dicentang → filter tidak diterapkan (semua data).
     *
     * Filter unit (Eselon I/II/Balai) mencakup unit terpilih beserta
     * SELURUH TURUNANNYA (eselon di bawahnya s.d. bagian/subbagian),
     * sehingga jumlah di kartu, grafik, dan tabel selalu sinkron.
     */
    public function applyFilters(Builder $query, array $filters): Builder
    {
        // Eselon I — unit terpilih + seluruh turunannya (es II, bagian/es III, dst.)
        $es1 = collect($filters['es1'] ?? [])->filter()->values();
        $query->when($es1->isNotEmpty(), fn (Builder $q) => $q->whereIn('unit_id', $this->unitDescendants($es1)));

        // Eselon II — unit terpilih + seluruh turunannya (bagian/es III, dst.)
        $es2 = collect($filters['es2'] ?? [])->filter()->values();
        $query->when($es2->isNotEmpty(), fn (Builder $q) => $q->whereIn('unit_id', $this->unitDescendants($es2)));

        // Balai — unit terpilih + seluruh turunannya
        $balai = collect($filters['balai'] ?? [])->filter()->values();
        $query->when($balai->isNotEmpty(), fn (Builder $q) => $q->whereIn('unit_id', $this->unitDescendants($balai)));

        // Status ASN — mis. ASN + PPPK + CPNS sekaligus
        $statusAsn = collect($filters['status_asn'] ?? [])->filter()->values();
        $query->when($statusAsn->isNotEmpty(), fn (Builder $q) => $q->whereIn('employment_status_id', $statusAsn));

        // Golongan — bisa pilih lebih dari satu
        $rank = collect($filters['rank'] ?? [])->filter()->values();
        $query->when($rank->isNotEmpty(), fn (Builder $q) => $q->whereIn('rank_id', $rank));

        // Pendidikan — bisa pilih lebih dari satu
        $education = collect($filters['education'] ?? [])->filter()->values();
        $query->when($education->isNotEmpty(), fn (Builder $q) => $q->whereIn('education_level_id', $education));

        $query->when($filters['gender'] ?? null, fn (Builder $q, $v) => $q->whereIn('gender', collect($v)->filter()->values()));
        $query->when($filters['search'] ?? null, function (Builder $q, $v) {
            $q->where(fn ($w) => $w->where('name', 'like', "%{$v}%")->orWhere('nip', 'like', "%{$v}%"));
        });

        return $query;
    }

    /**
     * Kumpulkan ID unit terpilih beserta SELURUH TURUNANNYA
     * (berjenjang ke bawah: es I -> es II -> bagian -> subbagian ...)
     * supaya pegawai di unit turunan mana pun ikut terhitung.
     *
     * @param  \Illuminate\Support\Collection<int, mixed>  $unitIds
     * @return array<int, int>
     */
    private function unitDescendants(\Illuminate\Support\Collection $unitIds): array
    {
        $all = $unitIds->map(fn ($v) => (int) $v)->unique()->values();
        $parents = $all->values();

        while ($parents->isNotEmpty()) {
            $parents = Unit::whereIn('parent_id', $parents)->pluck('id');
            $all = $all->merge($parents)->unique()->values();
        }

        return $all->all();
    }

    /**
     * Ringkasan KPI dashboard.
     */
    public function getSummary(Builder $query): array
    {
        $totalAsn = (clone $query)->count();

        $averageAge = $totalAsn > 0
            ? round((clone $query)->whereNotNull('birth_date')->get()->avg(fn ($e) => $e->birth_date->age) ?? 0, 1)
            : 0;

        $functionalCount = (clone $query)->whereNotNull('functional_level')
            ->where('functional_level', 'not like', '%Umum%')
            ->count();

        $structuralCount = (clone $query)->whereNotNull('eselon')->count();

        $pppkCount = (clone $query)->whereHas('employmentStatus', fn ($q) => $q->where('name', 'like', 'PPPK%'))->count();

        // Pegawai ASN (tanpa PPPK / CPNS) untuk KPI total keseluruhan pegawai
        $asnStatusCount = (clone $query)->whereHas('employmentStatus', fn ($q) => $q->where('name', 'ASN'))->count();

        $retiringSoon = (clone $query)->whereNotNull('retirement_date')
            ->whereBetween('retirement_date', [now(), now()->addYears(2)])
            ->count();

        // pegawai non ASN (tidak terpengaruh filter unit ASN)
        $nonAsnCount = Employee::where('employee_type', Employee::TYPE_NON_ASN)
            ->where('is_active', true)
            ->count();

        // total keseluruhan pegawai aktif: ASN & PPPK + Non ASN
        $totalAll = $totalAsn + $nonAsnCount;

        // pensiun tahun ini
        $retiringThisYear = (clone $query)->whereNotNull('retirement_date')
            ->where('retirement_date', '>=', today()->toDateString())
            ->whereYear('retirement_date', now()->year)
            ->count();

        return compact('totalAsn', 'averageAge', 'functionalCount', 'structuralCount', 'pppkCount', 'retiringSoon', 'nonAsnCount', 'retiringThisYear', 'asnStatusCount', 'totalAll');
    }

    /**
     * Chart komposisi status ASN.
     */
    public function getStatusComposition(Builder $query): array
    {
        // pegawai yang BUP-nya sudah terlewati tetap dihitung, namun
        // otomatis digolongkan berstatus "Pensiun"
        return (clone $query)
            ->join('employment_statuses', 'employment_statuses.id', '=', 'employees.employment_status_id')
            ->selectRaw("CASE WHEN employees.retirement_date IS NOT NULL AND employees.retirement_date < ? THEN 'Pensiun' ELSE employment_statuses.name END as label, COUNT(*) as total", [today()->toDateString()])
            ->groupBy('label')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => ['label' => $row->label, 'total' => (int) $row->total])
            ->all();    }

    /**
     * Chart tingkat pendidikan.
     */
    public function getEducationChart(Builder $query): array
    {
        return EducationLevel::query()
            ->orderBy('sort_order')
            ->withCount(['employees' => fn ($q) => $q->whereIn('employees.id', (clone $query)->select('employees.id'))])
            ->get()
            ->map(fn ($level) => ['label' => $level->name, 'total' => $level->employees_count])
            ->all();
    }

    /**
     * Chart distribusi golongan/pangkat.
     */
    public function getRankChart(Builder $query): array
    {
        return Rank::query()
            ->orderBy('sort_order')
            ->withCount(['employees' => fn ($q) => $q->whereIn('employees.id', (clone $query)->select('employees.id'))])
            ->get()
            ->filter(fn ($rank) => $rank->employees_count > 0)
            ->map(fn ($rank) => ['label' => $rank->code, 'total' => $rank->employees_count])
            ->values()
            ->all();
    }

    /**
     * Chart distribusi usia (kelompok usia).
     */
    public function getAgeDistribution(Builder $query): array
    {
        $groups = ['20-30' => [0, 30], '31-40' => [31, 40], '41-50' => [41, 50], '51-58' => [51, 58], '>58' => [59, 200]];
        $result = [];

        foreach ($groups as $label => [$min, $max]) {
            $count = (clone $query)
                ->whereNotNull('birth_date')
                ->get()
                ->filter(fn ($employee) => $employee->birth_date->age >= $min && $employee->birth_date->age <= $max)
                ->count();
            $result[] = ['label' => $label, 'total' => $count];
        }

        return $result;
    }

    /**
     * Chart komposisi gender (persentase).
     */
    public function getGenderComposition(Builder $query): array
    {
        $total = (clone $query)->count();
        $male = (clone $query)->where('gender', 'L')->count();
        $female = $total - $male;

        $pct = fn ($count) => $total > 0 ? round($count / $total * 100, 1) : 0;

        return [
            ['label' => 'Laki-Laki', 'total' => $male, 'percent' => $pct($male)],
            ['label' => 'Perempuan', 'total' => $female, 'percent' => $pct($female)],
        ];
    }

    /**
     * Chart komposisi kemampuan berenang pegawai.
     */
    public function getSwimmingComposition(Builder $query): array
    {
        $canSwim = (clone $query)->where('swimming_skill', 'bisa')->count();
        $cannotSwim = (clone $query)->where('swimming_skill', 'tidak')->count();
        $unfilled = (clone $query)->count() - $canSwim - $cannotSwim;

        return [
            ['label' => 'Bisa Berenang', 'total' => $canSwim],
            ['label' => 'Tidak Bisa Berenang', 'total' => $cannotSwim],
            ['label' => 'Belum Diisi', 'total' => max($unfilled, 0)],
        ];
    }

    /* ================= KENAIKAN GAJI BERKALA (KGB) ================= */

    /**
     * Pegawai ASN yang TMT golongannya diketahui — dasar perhitungan
     * Kenaikan Gaji Berkala (KGB) berkala 2 tahun (ASN, CPNS & PPPK/P3K).
     *
     * @return \Illuminate\Support\Collection<int, Employee>
     */
    private function salaryRaiseCandidates(Builder $query): \Illuminate\Support\Collection
    {
        return (clone $query)
            ->whereNotNull('employees.tmt_golongan')
            // pegawai yang sudah pensiun tidak diikutkan proyeksi KGB
            ->where(fn (Builder $q) => $q->whereNull('employees.retirement_date')
                ->orWhere('employees.retirement_date', '>=', today()->toDateString()))
            ->get();
    }

    /**
     * Statistik Kenaikan Gaji Berkala (KGB) — periode 2 tahun.
     */
    public function getSalaryRaiseStats(Builder $query): array
    {
        $candidates = $this->salaryRaiseCandidates($query);

        $dates = $candidates
            ->map(fn (Employee $e) => $e->next_salary_raise)
            ->filter()
            ->values();

        $today = today();
        $oneYearAhead = now()->addYear();

        $thisYear = $dates->filter(fn ($d) => $d->year === $today->year)->count();
        $nextYear = $dates->filter(fn ($d) => $d->year === $today->year + 1)->count();

        // jatuh tempo KGB dalam ≤ 1 tahun ke depan
        $dueSoon = $dates->filter(fn ($d) => $d->greaterThanOrEqualTo($today)
            && $d->lessThanOrEqualTo($oneYearAhead))->count();

        // sudah melewati jadwal KGB (TMT golongan > 2 tahun, kenaikan belum
        // diproses) — definisi sama dengan filter daftar pegawai ?kgb=overdue
        $overdue = $candidates
            ->filter(fn (Employee $e) => $e->tmt_golongan?->copy()->addYears(2)->lessThan($today))
            ->count();

        // proyeksi 5 tahun ke depan (untuk chart garis)
        $projection = [];

        for ($i = 0; $i < 5; $i++) {
            $year = $today->year + $i;
            $projection[] = [
                'label' => (string) $year,
                'total' => $dates->filter(fn ($d) => $d->year === $year)->count(),
            ];
        }

        return compact('thisYear', 'nextYear', 'dueSoon', 'overdue', 'projection');
    }

    /**
     * Daftar pegawai dengan estimasi KGB terdekat.
     *
     * @return \Illuminate\Support\Collection<int, Employee>
     */
    public function getUpcomingSalaryRaises(Builder $query, int $limit = 8): \Illuminate\Support\Collection
    {
        return $this->salaryRaiseCandidates($query)
            ->filter(fn (Employee $e) => $e->next_salary_raise?->greaterThanOrEqualTo(today()) ?? false)
            ->sortBy(fn (Employee $e) => $e->next_salary_raise->getTimestamp())
            ->take($limit)
            ->values();
    }

    /**
     * Chart proyeksi pensiun 5 tahun ke depan.
     */
    public function getRetirementProjection(Builder $query): array
    {
        $result = [];

        for ($i = 0; $i < 5; $i++) {
            $year = now()->year + $i;
            $count = (clone $query)
                ->whereNotNull('retirement_date')
                ->whereYear('retirement_date', $year)
                ->count();
            $result[] = ['label' => (string) $year, 'total' => $count];
        }

        return $result;
    }

    /* ================= KENAIKAN JABATAN / PANGKAT ================= */

    /**
     * Pegawai yang tanggal kenaikan jabatan/pangkatnya diketahui:
     * memakai kolom next_promotion_date bila ada, jika tidak TMT golongan + 4 tahun
     * (estimasi sama dengan accessor Employee::next_promotion_estimated).
     *
     * @return \Illuminate\Support\Collection<int, Employee>
     */
    private function promotionCandidates(Builder $query): \Illuminate\Support\Collection
    {
        return (clone $query)
            ->where(function (Builder $q) {
                $q->whereNotNull('employees.next_promotion_date')
                    ->orWhereNotNull('employees.tmt_golongan');
            })
            // pegawai yang sudah pensiun tidak diikutkan proyeksi kenaikan pangkat
            ->where(fn (Builder $q) => $q->whereNull('employees.retirement_date')
                ->orWhere('employees.retirement_date', '>=', today()->toDateString()))
            ->get();
    }

    /**
     * Statistik kenaikan jabatan/pangkat untuk kartu & chart dashboard.
     */
    public function getPromotionStats(Builder $query): array
    {
        $dates = $this->promotionCandidates($query)
            ->map(fn (Employee $e) => $e->next_promotion_estimated)
            ->filter() // buang yang tidak bisa diestimasi
            ->values();

        $today = today();
        $oneYearAhead = now()->addYear();

        $thisYear = $dates->filter(fn ($d) => $d->year === $today->year)->count();
        $nextYear = $dates->filter(fn ($d) => $d->year === $today->year + 1)->count();

        $dueSoon = $dates->filter(fn ($d) => $d->greaterThanOrEqualTo($today)
            && $d->lessThanOrEqualTo($oneYearAhead))->count();

        // sudah melewati estimasi kenaikan namun belum naik (jatuh tempo)
        $overdue = $dates->filter(fn ($d) => $d->lessThan($today))->count();

        // proyeksi 5 tahun ke depan (untuk chart garis)
        $projection = [];

        for ($i = 0; $i < 5; $i++) {
            $year = $today->year + $i;
            $projection[] = [
                'label' => (string) $year,
                'total' => $dates->filter(fn ($d) => $d->year === $year)->count(),
            ];
        }

        return compact('thisYear', 'nextYear', 'dueSoon', 'overdue', 'projection');
    }

    /**
     * Daftar pegawai dengan estimasi kenaikan jabatan/pangkat terdekat.
     *
     * @return \Illuminate\Support\Collection<int, Employee>
     */
    public function getUpcomingPromotions(Builder $query, int $limit = 8): \Illuminate\Support\Collection
    {
        return $this->promotionCandidates($query)
            ->filter(fn (Employee $e) => $e->next_promotion_estimated?->greaterThanOrEqualTo(today()) ?? false)
            ->sortBy(fn (Employee $e) => $e->next_promotion_estimated->getTimestamp())
            ->take($limit)
            ->values();
    }

    /**
     * Distribusi pegawai per unit kerja.
     */
    public function getUnitDistribution(Builder $query): array
    {
        return (clone $query)
            ->join('units', 'units.id', '=', 'employees.unit_id')
            ->selectRaw('units.name as label, COUNT(*) as total')
            ->groupBy('units.name')
            ->orderByDesc('total')
            ->limit(12)
            ->get()
            ->map(fn ($row) => ['label' => $row->label, 'total' => (int) $row->total])
            ->all();
    }

    /**
     * Daftar pegawai terbaru untuk tabel dashboard.
     */
    public function getEmployeeTable(Builder $query, int $perPage = 10)
    {
        return (clone $query)->orderBy('name')->paginate($perPage)->withQueryString();
    }

    /**
     * Statistik pengajuan surat untuk dashboard.
     */
    public function getLetterStats(): array
    {
        $letters = \App\Models\Letter::query();

        return [
            'total' => (clone $letters)->count(),
            'pending' => (clone $letters)->where('status', \App\Models\Letter::STATUS_PENDING)->count(),
            'verified' => (clone $letters)->where('status', \App\Models\Letter::STATUS_VERIFIED)->count(),
            'approved' => (clone $letters)->where('status', \App\Models\Letter::STATUS_APPROVED)->count(),
            'rejected' => (clone $letters)->where('status', \App\Models\Letter::STATUS_REJECTED)->count(),
        ];
    }

    /**
     * Helper nama bulan Indonesia.
     */
    public static function monthName(?Carbon $date): string
    {
        if (! $date) {
            return '-';
        }

        $months = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

        return $months[(int) $date->format('n')];
    }
}
