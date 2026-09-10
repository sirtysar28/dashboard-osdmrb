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
            'summary' => $this->service->getSummary($query),
            'statusComposition' => $this->service->getStatusComposition($query),
            'educationChart' => $this->service->getEducationChart($query),
            'rankChart' => $this->service->getRankChart($query),
            'ageDistribution' => $this->service->getAgeDistribution($query),
            'genderComposition' => $this->service->getGenderComposition($query),
            'retirementProjection' => $this->service->getRetirementProjection($query),
            'unitDistribution' => $this->service->getUnitDistribution($query),
            'promotionStats' => $this->service->getPromotionStats($query),
            'upcomingPromotions' => $this->service->getUpcomingPromotions($query),
            'employees' => $this->service->getEmployeeTable($query),
            'letterStats' => $this->service->getLetterStats(),
            'visitorStats' => \App\Models\AuditLog::visitorStats(),
            'surveyStats' => \App\Models\Survey::stats(),
            'announcement' => \App\Models\Announcement::active(),
            'announcements' => \App\Models\Announcement::activeAll(),
            'filters' => $filters,
            'filterOptions' => [
                'es1List' => Unit::where('level', 'ES_I')->orderBy('name')->get(),
                'es2List' => Unit::where('level', 'ES_II')->orderBy('name')->get(),
                'balaiList' => Unit::where('level', 'BALAI')->orderBy('name')->get(),
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
            'retirement' => $this->service->getRetirementProjection($query),
            'promotion' => $this->service->getPromotionStats($query),
        ]);
    }
}
