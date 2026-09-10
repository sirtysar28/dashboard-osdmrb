<?php

namespace App\Http\Controllers;

use App\Models\LetterType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LetterTypeController extends Controller
{
    public function index()
    {
        return view('letters.types', [
            'letterTypes' => LetterType::withCount('letters')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        LetterType::create($request->validate([
            'code' => ['required', 'max:50', 'unique:letter_types,code'],
            'name' => ['required', 'max:255'],
            'code_format' => ['nullable', 'max:100'],
            'template_body' => ['nullable'],
            'needs_verification' => ['boolean'],
        ]) + ['is_active' => true]);

        return back()->with('success', 'Jenis surat berhasil ditambahkan.');
    }

    public function update(Request $request, LetterType $letter_type)
    {
        $validated = $request->validate([
            'code' => ['required', 'max:50', Rule::unique('letter_types', 'code')->ignore($letter_type)],
            'name' => ['required', 'max:255'],
            'code_format' => ['nullable', 'max:100'],
            'template_body' => ['nullable'],
            'needs_verification' => ['boolean'],
            'is_active' => ['boolean'],
        ]);

        // checkbox yang tidak dicentang tidak terkirim -> paksa boolean
        $validated['is_active'] = $request->boolean('is_active');
        $validated['needs_verification'] = $request->boolean('needs_verification');

        $letter_type->update($validated);

        return back()->with('success', 'Jenis surat berhasil diperbarui.');
    }

    public function destroy(LetterType $letter_type)
    {
        $letter_type->delete();

        return back()->with('success', 'Jenis surat berhasil dihapus.');
    }
}
