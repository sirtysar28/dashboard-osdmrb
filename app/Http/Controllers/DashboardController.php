<?php

namespace App\Http\Controllers;

use App\Models\EducationLevel;
use App\Models\EmploymentStatus;
use App\Models\Rank;
use App\Models\Unit;
use App\Services\DashboardService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(protected DashboardService $service)
    {
    }

    /**
     * Dashboard utama (admin instansi).
     */
    public function index(Request $request)
    {
        $filters = $request->only(['es1', 'es2', 'balai', 'status_asn', 'rank', 'education', 'gender', 'search']);

        $query = $this->service->applyFilters($this->service->baseQuery(), $filters);

        $data = [
            'summary' => $this->service->getSummary($query, $this->service->nonAsnQuery($filters)),
            'statusComposition' => $this->service->getStatusComposition($query),
            'educationChart' => $this->service->getEducationChart($query),
            'rankChart' => $this->service->getRankChart($query),
            'ageDistribution' => $this->service->getAgeDistribution($query),
            'genderComposition' => $this->service->getGenderComposition($query),
            'swimmingComposition' => $this->service->getSwimmingComposition($query),
            'retirementProjection' => $this->service->getRetirementProjection($query),
            'unitDistribution' => $this->service->getUnitDistribution($query),
            'promotionStats' => $this->service->getPromotionStats($query),
            'upcomingPromotions' => $this->service->getUpcomingPromotions($query),
            'salaryRaiseStats' => $this->service->getSalaryRaiseStats($query),
            'upcomingSalaryRaises' => $this->service->getUpcomingSalaryRaises($query),
            'employees' => $this->service->getEmployeeTable($query),
            'letterStats' => $this->service->getLetterStats(),
            'visitorStats' => \App\Models\AuditLog::visitorStats(),
            'surveyStats' => \App\Models\Survey::stats(),
            'announcement' => \App\Models\Announcement::active(),
            'announcements' => \App\Models\Announcement::activeAll(),
            'filters' => $filters,
            'filterOptions' => [
                'es1List' => Unit::where('level', 'ES_I')->ordered()->get(),
                'es2List' => Unit::where('level', 'ES_II')->ordered()->get(),
                'balaiList' => Unit::where('level', 'BALAI')->ordered()->get(),
                'statusList' => EmploymentStatus::orderBy('name')->get(),
                'rankList' => Rank::orderBy('sort_order')->get(),
                'educationList' => EducationLevel::orderBy('sort_order')->get(),
            ],
        ];

        return view('dashboard.index', $data);
    }

    /**
     * Endpoint API statistik (bisa dipakai fetch AJAX).
     */
    public function statistics(Request $request)
    {
        $filters = $request->only(['es1', 'es2', 'balai', 'status_asn', 'rank', 'education', 'gender', 'search']);

        $query = $this->service->applyFilters($this->service->baseQuery(), $filters);

        return response()->json([
            'summary' => $this->service->getSummary($query),
            'education' => $this->service->getEducationChart($query),
            'age' => $this->service->getAgeDistribution($query),
            'gender' => $this->service->getGenderComposition($query),
            'swimming' => $this->service->getSwimmingComposition($query),
            'retirement' => $this->service->getRetirementProjection($query),
            'promotion' => $this->service->getPromotionStats($query),
            'salary_raise' => $this->service->getSalaryRaiseStats($query),
        ]);
    }

    /**
     * Popup daftar pegawai saat angka grafik/infografis dashboard diklik
     * (Catatan Masukan 21 Okt 2026): pendidikan, usia, gender, renang,
     * pensiun, status, kenaikan pangkat, KGB, hingga distribusi unit kerja.
     * Catatan Masukan 23 Okt 2026: tampilan dibatasi 10 baris per halaman
     * (paginasi) + pencarian popup (parameter `q`) atas nama/NIP/jabatan.
     */
    public function drilldown(Request $request)
    {
        $filters = $request->only(['es1', 'es2', 'balai', 'status_asn', 'rank', 'education', 'gender', 'search']);

        $query = $this->service->applyFilters($this->service->baseQuery(), $filters);

        $items = $this->service->getDrilldownList(
            $query,
            (string) $request->input('category', ''),
            $request->input('value'),
            (string) $request->input('q', '')
        );

        // paginasi popup: maks. 10 baris per halaman
        $perPage = 10;
        $total = $items->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, (int) $request->input('page', 1)), $lastPage);

        return response()->json([
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => $lastPage,
            'items' => $items->forPage($page, $perPage)->values(),
        ]);
    }

    /**
     * Informasi pegawai berulang tahun (Catatan Masukan 21 Okt 2026):
     * bulan ini / beberapa bulan ke depan, maks. 5 item per halaman +
     * paginasi; tersedia mode daftar & grafik per bulan.
     */
    public function birthdays(Request $request)
    {
        $filters = $request->only(['es1', 'es2', 'balai', 'status_asn', 'rank', 'education', 'gender', 'search']);

        $query = $this->service->applyFilters($this->service->baseQuery(), $filters);

        return response()->json($this->service->getBirthdays(
            $query,
            (int) $request->input('months', 1),
            5,
            $request->input('month') !== null && $request->input('month') !== '' ? (int) $request->input('month') : null,
            (int) $request->input('page', 1),
        ));
    }
}
