<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Letter;
use App\Models\LetterType;
use App\Models\Setting;
use Illuminate\Http\Request;

/**
 * Halaman utama user pegawai.
 */
class HomeController extends Controller
{
    public function index(Request $request)
    {
        $employee = $request->user()->load('employee.rank', 'employee.employmentStatus', 'employee.unit', 'employee.education')->employee;

        $letters = Letter::with('letterType')
            ->where('employee_id', $employee?->id)
            ->latest()
            ->limit(5)
            ->get();

        return view('home', [
            'employee' => $employee,
            'letters' => $letters,
            'letterStats' => [
                'total' => Letter::where('employee_id', $employee?->id)->count(),
                'pending' => Letter::where('employee_id', $employee?->id)->where('status', Letter::STATUS_PENDING)->count(),
                'approved' => Letter::where('employee_id', $employee?->id)->where('status', Letter::STATUS_APPROVED)->count(),
                'rejected' => Letter::where('employee_id', $employee?->id)->where('status', Letter::STATUS_REJECTED)->count(),
            ],
            'letterTypes' => LetterType::where('is_active', true)->orderBy('name')->get(),
            'announcement' => Announcement::active(),
            'announcements' => Announcement::activeAll(),
            'menuLetters' => Setting::menuVisible('letters'),
            'menuArchives' => Setting::menuVisible('archives'),
        ]);
    }
}
