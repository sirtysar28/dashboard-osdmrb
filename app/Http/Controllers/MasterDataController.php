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
    public function index()
    {
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

            'units' => Unit::with('parent', 'children')
                ->orderBy('level')->orderBy('name')
                ->paginate(10, ['*'], 'pageUnits'),
            'educationLevels' => EducationLevel::orderBy('sort_order')
                ->paginate(10, ['*'], 'pageEdu'),
            'campuses' => Campus::orderBy('sort_order')->orderBy('name')
                ->paginate(10, ['*'], 'pageCampus'),
            'ranks' => Rank::orderBy('sort_order')
                ->paginate(10, ['*'], 'pageRank'),
            'employmentStatuses' => EmploymentStatus::orderBy('name')
                ->paginate(10, ['*'], 'pageStatus'),
            'jobLevels' => JobLevel::orderBy('sort_order')
                ->paginate(10, ['*'], 'pageJobLevel'),
            'positionTypes' => PositionType::orderBy('name')
                ->paginate(10, ['*'], 'pagePosType'),
            'positions' => Position::with(['positionType', 'jobLevel'])->orderBy('name')
                ->paginate(10, ['*'], 'pagePos'),
            'archiveCategories' => ArchiveCategory::withCount('archives')->orderBy('code')
                ->paginate(10, ['*'], 'pageArsip'),
        ]);
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
