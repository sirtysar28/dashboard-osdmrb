<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Survey;
use Illuminate\Http\Request;

/**
 * Survei masukan & saran aplikasi.
 * - Semua pengguna bisa mengirim survei (form di dashboard).
 * - Admin & Administrator Utama dapat melihat hasilnya.
 */
class SurveyController extends Controller
{
    /**
     * Kirim jawaban survei (dari dashboard / beranda).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'message' => ['nullable', 'max:1000'],
        ], [
            'rating.required' => 'Silakan pilih rating bintang terlebih dahulu.',
            'rating.min' => 'Rating minimal 1 bintang.',
            'rating.max' => 'Rating maksimal 5 bintang.',
        ]);

        Survey::create([
            'user_id' => $request->user()->id,
            'user_name' => $request->user()->name,
            'rating' => $validated['rating'],
            'message' => $validated['message'] ?? null,
        ]);

        AuditLog::record(AuditLog::EVENT_CREATE, 'survei', 'Mengisi survei aplikasi ('.$validated['rating'].' bintang)');

        return back()->with('success', 'Terima kasih! Masukan Anda telah terkirim.');
    }

    /**
     * Daftar hasil survei (admin & super admin).
     */
    public function index(Request $request)
    {
        $surveys = Survey::query()
            ->when($request->search, fn ($q, $v) => $q->where(fn ($w) => $w
                ->where('user_name', 'like', "%{$v}%")
                ->orWhere('message', 'like', "%{$v}%")))
            ->when($request->rating, fn ($q, $v) => $q->where('rating', $v))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('survey.index', [
            'surveys' => $surveys,
            'stats' => Survey::stats(),
            'filters' => $request->only(['search', 'rating']),
        ]);
    }

    public function destroy(Survey $survey)
    {
        AuditLog::record(AuditLog::EVENT_DELETE, 'survei', 'Menghapus jawaban survei '.$survey->user_name);

        $survey->delete();

        return back()->with('success', 'Jawaban survei berhasil dihapus.');
    }
}
