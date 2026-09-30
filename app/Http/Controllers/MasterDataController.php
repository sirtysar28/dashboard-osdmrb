<?php

namespace App\Http\Controllers;

use App\Models\ArchiveCategory;
use App\Models\Campus;
use App\Models\EducationLevel;
use App\Models\EmploymentStatus;
use App\Models\JobLevel;
use App\Models\Position;
use App\Models\PositionType;
use App\Models\Rank;
use App\Models\Unit;
use App\Services\MasterDataExporter;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Pengelolaan seluruh master data (admin instansi).
 */
class MasterDataController extends Controller
{
    public function index(Request $request)
    {
        /* Filter pencarian di SEMUA tab — kata kunci & pilihan terkait
           (setiap tab memakai prefiks parameter sendiri, mis. unit_q,
           edu_q, campus_q, ...) agar tidak saling timpa antar tab. */
        $unitFilters = $request->only(['unit_q', 'unit_level', 'unit_parent']);
        $eduFilters = $request->only(['edu_q']);
        $campusFilters = $request->only(['campus_q', 'campus_type']);
        $rankFilters = $request->only(['rank_q', 'rank_type']);
        $statusFilters = $request->only(['status_q']);
        $jobLevelFilters = $request->only(['joblevel_q']);
        $positionTypeFilters = $request->only(['postype_q']);
        $positionFilters = $request->only(['pos_q', 'pos_type', 'pos_level']);
        $arsipFilters = $request->only(['arsip_q']);

        /* Koleksi LENGKAP (tanpa pagination) untuk kebutuhan dropdown
           & pilihan form di seluruh tab. */
        $allUnits = Unit::orderBy('level')->orderBy('name')->get();
        $allPositionTypes = PositionType::orderBy('name')->get();
        $allJobLevels = JobLevel::orderBy('sort_order')->get();

        /* Setiap tab di-paginate terpisah dengan nama parameter halaman
           yang berbeda agar tab aktif & halaman tiap tab tidak saling timpa. */
        return view('master.index', [
            'allUnits' => $allUnits,
            'allPositionTypes' => $allPositionTypes,
            'allJobLevels' => $allJobLevels,
            'unitFilters' => $unitFilters,
            'eduFilters' => $eduFilters,
            'campusFilters' => $campusFilters,
            'rankFilters' => $rankFilters,
            'statusFilters' => $statusFilters,
            'jobLevelFilters' => $jobLevelFilters,
            'positionTypeFilters' => $positionTypeFilters,
            'positionFilters' => $positionFilters,
            'arsipFilters' => $arsipFilters,

            'units' => Unit::with('parent', 'children')
                ->when(trim((string) ($unitFilters['unit_q'] ?? '')) !== '', function ($q) use ($unitFilters) {
                    $v = trim((string) $unitFilters['unit_q']);
                    $q->where(fn ($w) => $w
                        ->where('name', 'like', "%{$v}%")
                        ->orWhere('code', 'like', "%{$v}%"));
                })
                ->when(! empty($unitFilters['unit_level']), fn ($q) => $q->where('level', $unitFilters['unit_level']))
                ->when(! empty($unitFilters['unit_parent']), fn ($q) => $q->where('parent_id', (int) $unitFilters['unit_parent']))
                ->orderBy('level')->orderBy('name')
                ->paginate(10, ['*'], 'pageUnits')
                ->appends(['tab' => 'units'] + $unitFilters),
            'educationLevels' => EducationLevel::query()
                ->when(trim((string) ($eduFilters['edu_q'] ?? '')) !== '', fn ($q) => $this->keywordWhere($q, $eduFilters['edu_q'], ['code', 'name']))
                ->orderBy('sort_order')
                ->paginate(10, ['*'], 'pageEdu')
                ->appends(['tab' => 'education'] + $eduFilters),
            'campuses' => Campus::query()
                ->when(trim((string) ($campusFilters['campus_q'] ?? '')) !== '', fn ($q) => $this->keywordWhere($q, $campusFilters['campus_q'], ['name', 'city']))
                ->when(! empty($campusFilters['campus_type']), fn ($q) => $q->where('type', $campusFilters['campus_type']))
                ->orderBy('sort_order')->orderBy('name')
                ->paginate(10, ['*'], 'pageCampus')
                ->appends(['tab' => 'campuses'] + $campusFilters),
            'ranks' => Rank::query()
                ->when(trim((string) ($rankFilters['rank_q'] ?? '')) !== '', fn ($q) => $this->keywordWhere($q, $rankFilters['rank_q'], ['code', 'name', 'group_name']))
                ->when(! empty($rankFilters['rank_type']), fn ($q) => $q->where('is_pppk', $rankFilters['rank_type'] === 'pppk'))
                ->orderBy('sort_order')
                ->paginate(10, ['*'], 'pageRank')
                ->appends(['tab' => 'ranks'] + $rankFilters),
            'employmentStatuses' => EmploymentStatus::query()
                ->when(trim((string) ($statusFilters['status_q'] ?? '')) !== '', fn ($q) => $this->keywordWhere($q, $statusFilters['status_q'], ['code', 'name']))
                ->orderBy('name')
                ->paginate(10, ['*'], 'pageStatus')
                ->appends(['tab' => 'statuses'] + $statusFilters),
            'jobLevels' => JobLevel::query()
                ->when(trim((string) ($jobLevelFilters['joblevel_q'] ?? '')) !== '', fn ($q) => $this->keywordWhere($q, $jobLevelFilters['joblevel_q'], ['code', 'name']))
                ->orderBy('sort_order')
                ->paginate(10, ['*'], 'pageJobLevel')
                ->appends(['tab' => 'joblevels'] + $jobLevelFilters),
            'positionTypes' => PositionType::query()
                ->when(trim((string) ($positionTypeFilters['postype_q'] ?? '')) !== '', fn ($q) => $this->keywordWhere($q, $positionTypeFilters['postype_q'], ['code', 'name']))
                ->orderBy('name')
                ->paginate(10, ['*'], 'pagePosType')
                ->appends(['tab' => 'positiontypes'] + $positionTypeFilters),
            'positions' => Position::with(['positionType', 'jobLevel'])
                ->when(trim((string) ($positionFilters['pos_q'] ?? '')) !== '', fn ($q) => $this->keywordWhere($q, $positionFilters['pos_q'], ['code', 'name']))
                ->when(! empty($positionFilters['pos_type']), fn ($q) => $q->where('position_type_id', (int) $positionFilters['pos_type']))
                ->when(! empty($positionFilters['pos_level']), fn ($q) => $q->where('job_level_id', (int) $positionFilters['pos_level']))
                ->orderBy('name')
                ->paginate(10, ['*'], 'pagePos')
                ->appends(['tab' => 'positions'] + $positionFilters),
            'archiveCategories' => ArchiveCategory::withCount('archives')
                ->when(trim((string) ($arsipFilters['arsip_q'] ?? '')) !== '', fn ($q) => $this->keywordWhere($q, $arsipFilters['arsip_q'], ['code', 'name']))
                ->orderBy('code')
                ->paginate(10, ['*'], 'pageArsip')
                ->appends(['tab' => 'arsip'] + $arsipFilters),
        ]);
    }

    /**
     * Terapkan pencarian kata kunci LIKE pada beberapa kolom sekaligus.
     */
    private function keywordWhere($query, string $keyword, array $columns)
    {
        $v = trim($keyword);

        if ($v === '') {
            return $query;
        }

        return $query->where(function ($w) use ($v, $columns) {
            foreach ($columns as $column) {
                $w->orWhere($column, 'like', "%{$v}%");
            }
        });
    }

    /**
     * Export seluruh master data ke Excel (multi-sheet) atau PDF.
     */
    public function export(Request $request)
    {
        return MasterDataExporter::handle($request->input('format', 'xlsx'));
    }

    /* ================= UNITS ================= */

    public function storeUnit(Request $request)
    {
        $validated = $request->validate([
            'parent_id' => ['nullable', 'exists:units,id'],
            'code' => ['required', 'max:50', 'unique:units,code'],
            'name' => ['required', 'max:255'],
            'level' => ['required', 'in:KEMENTERIAN,ES_I,ES_II,ES_III,BALAI,LAINNYA'],
            'address' => ['nullable'],
        ]);

        Unit::create($validated + ['is_active' => true]);

        return back()->with('success', 'Unit kerja berhasil ditambahkan.');
    }

    public function updateUnit(Request $request, Unit $unit)
    {
        $validated = $request->validate([
            'parent_id' => ['nullable', 'exists:units,id'],
            'code' => ['required', 'max:50', Rule::unique('units', 'code')->ignore($unit)],
            'name' => ['required', 'max:255'],
            'level' => ['required', 'in:KEMENTERIAN,ES_I,ES_II,ES_III,BALAI,LAINNYA'],
            'address' => ['nullable'],
        ]);

        $unit->update($validated);

        return back()->with('success', 'Unit kerja berhasil diperbarui.');
    }

    public function destroyUnit(Unit $unit)
    {
        $unit->delete();

        return back()->with('success', 'Unit kerja berhasil dihapus.');
    }

    /* ================= EDUCATION LEVELS ================= */

    public function storeEducation(Request $request)
    {
        EducationLevel::create($request->validate([
            'code' => ['required', 'max:20', 'unique:education_levels,code'],
            'name' => ['required', 'max:255'],
            'sort_order' => ['nullable', 'integer'],
        ]));

        return back()->with('success', 'Tingkat pendidikan berhasil ditambahkan.');
    }

    public function updateEducation(Request $request, EducationLevel $education_level)
    {
        $education_level->update($request->validate([
            'code' => ['required', 'max:20', Rule::unique('education_levels', 'code')->ignore($education_level)],
            'name' => ['required', 'max:255'],
            'sort_order' => ['nullable', 'integer'],
        ]));

        return back()->with('success', 'Tingkat pendidikan berhasil diperbarui.');
    }

    public function destroyEducation(EducationLevel $education_level)
    {
        $education_level->delete();

        return back()->with('success', 'Tingkat pendidikan berhasil dihapus.');
    }

    /* ================= CAMPUSES (MASTER KAMPUS) ================= */

    public function storeCampus(Request $request)
    {
        Campus::create($request->validate([
            'name' => ['required', 'max:255', 'unique:campuses,name'],
            'city' => ['nullable', 'max:100'],
            'type' => ['nullable', 'in:negeri,swasta,luar_negeri'],
            'sort_order' => ['nullable', 'integer'],
        ]));

        return back()->with('success', 'Kampus berhasil ditambahkan.');
    }

    public function updateCampus(Request $request, Campus $campus)
    {
        $campus->update($request->validate([
            'name' => ['required', 'max:255', Rule::unique('campuses', 'name')->ignore($campus)],
            'city' => ['nullable', 'max:100'],
            'type' => ['nullable', 'in:negeri,swasta,luar_negeri'],
            'sort_order' => ['nullable', 'integer'],
        ]));

        return back()->with('success', 'Kampus berhasil diperbarui.');
    }

    public function destroyCampus(Campus $campus)
    {
        $campus->delete();

        return back()->with('success', 'Kampus berhasil dihapus.');
    }

    /* ================= RANKS ================= */

    public function storeRank(Request $request)
    {
        Rank::create($request->validate([
            'code' => ['required', 'max:20', 'unique:ranks,code'],
            'name' => ['nullable', 'max:255'],
            'group_name' => ['nullable', 'max:50'],
            'is_pppk' => ['boolean'],
            'sort_order' => ['nullable', 'integer'],
        ]));

        return back()->with('success', 'Golongan berhasil ditambahkan.');
    }

    public function updateRank(Request $request, Rank $rank)
    {
        $validated = $request->validate([
            'code' => ['required', 'max:20', Rule::unique('ranks', 'code')->ignore($rank)],
            'name' => ['nullable', 'max:255'],
            'group_name' => ['nullable', 'max:50'],
            'is_pppk' => ['boolean'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        // checkbox yang tidak dicentang tidak terkirim -> paksa boolean
        $validated['is_pppk'] = $request->boolean('is_pppk');

        $rank->update($validated);

        return back()->with('success', 'Golongan berhasil diperbarui.');
    }

    public function destroyRank(Rank $rank)
    {
        $rank->delete();

        return back()->with('success', 'Golongan berhasil dihapus.');
    }

    /* ================= EMPLOYMENT STATUSES ================= */

    public function storeStatus(Request $request)
    {
        EmploymentStatus::create($request->validate([
            'code' => ['required', 'max:50', 'unique:employment_statuses,code'],
            'name' => ['required', 'max:255'],
        ]));

        return back()->with('success', 'Status kepegawaian berhasil ditambahkan.');
    }

    public function updateStatus(Request $request, EmploymentStatus $employment_status)
    {
        $employment_status->update($request->validate([
            'code' => ['required', 'max:50', Rule::unique('employment_statuses', 'code')->ignore($employment_status)],
            'name' => ['required', 'max:255'],
        ]));

        return back()->with('success', 'Status kepegawaian berhasil diperbarui.');
    }

    public function destroyStatus(EmploymentStatus $employment_status)
    {
        $employment_status->delete();

        return back()->with('success', 'Status kepegawaian berhasil dihapus.');
    }

    /* ================= JOB LEVELS ================= */

    public function storeJobLevel(Request $request)
    {
        JobLevel::create($request->validate([
            'code' => ['required', 'max:50', 'unique:job_levels,code'],
            'name' => ['required', 'max:255'],
            'sort_order' => ['nullable', 'integer'],
        ]));

        return back()->with('success', 'Level jabatan berhasil ditambahkan.');
    }

    public function updateJobLevel(Request $request, JobLevel $job_level)
    {
        $job_level->update($request->validate([
            'code' => ['required', 'max:50', Rule::unique('job_levels', 'code')->ignore($job_level)],
            'name' => ['required', 'max:255'],
            'sort_order' => ['nullable', 'integer'],
        ]));

        return back()->with('success', 'Level jabatan berhasil diperbarui.');
    }

    public function destroyJobLevel(JobLevel $job_level)
    {
        $job_level->delete();

        return back()->with('success', 'Level jabatan berhasil dihapus.');
    }

    /* ================= POSITION TYPES ================= */

    public function storePositionType(Request $request)
    {
        PositionType::create($request->validate([
            'code' => ['required', 'max:50', 'unique:position_types,code'],
            'name' => ['required', 'max:255'],
        ]));

        return back()->with('success', 'Jenis jabatan berhasil ditambahkan.');
    }

    public function updatePositionType(Request $request, PositionType $position_type)
    {
        $position_type->update($request->validate([
            'code' => ['required', 'max:50', Rule::unique('position_types', 'code')->ignore($position_type)],
            'name' => ['required', 'max:255'],
        ]));

        return back()->with('success', 'Jenis jabatan berhasil diperbarui.');
    }

    public function destroyPositionType(PositionType $position_type)
    {
        $position_type->delete();

        return back()->with('success', 'Jenis jabatan berhasil dihapus.');
    }

    /* ================= POSITIONS ================= */

    public function storePosition(Request $request)
    {
        Position::create($request->validate([
            'position_type_id' => ['required', 'exists:position_types,id'],
            'job_level_id' => ['nullable', 'exists:job_levels,id'],
            'code' => ['required', 'max:100', 'unique:positions,code'],
            'name' => ['required', 'max:255'],
            'description' => ['nullable'],
        ]));

        return back()->with('success', 'Jabatan berhasil ditambahkan.');
    }

    public function updatePosition(Request $request, Position $position)
    {
        $position->update($request->validate([
            'position_type_id' => ['required', 'exists:position_types,id'],
            'job_level_id' => ['nullable', 'exists:job_levels,id'],
            'code' => ['required', 'max:100', Rule::unique('positions', 'code')->ignore($position)],
            'name' => ['required', 'max:255'],
            'description' => ['nullable'],
        ]));

        return back()->with('success', 'Jabatan berhasil diperbarui.');
    }

    public function destroyPosition(Position $position)
    {
        $position->delete();

        return back()->with('success', 'Jabatan berhasil dihapus.');
    }
}
