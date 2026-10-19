<?php

namespace App\Http\Controllers\Admin;

use App\Models\User;
use App\Services\StaffSpecialtyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Spesialisasi Staff (/admin/staff/{staff}/specialties) — US-13, F-ADM-03.
 */
class StaffSpecialtyController extends AdminController
{
    public function __construct(private readonly StaffSpecialtyService $specialties) {}

    public function edit(Request $request, User $staff): View
    {
        Gate::authorize('manageStaff', $staff);
        $faculty = $request->user()->faculty;

        return view('admin.staff.specialties', [
            'staff' => $staff,
            'faculty' => $faculty,
            'options' => $this->specialties->groupedOptions($faculty->id, $faculty->code),
            'selected' => old('category_ids', $staff->specialties()->pluck('categories.id')->all()),
            'coverage' => $this->specialties->coverage($faculty->id),
        ]);
    }

    public function update(Request $request, User $staff): RedirectResponse|JsonResponse
    {
        Gate::authorize('manageStaff', $staff);

        $validated = $request->validate([
            // Tidak ada checkbox yang dicentang = field tidak terkirim = staff tanpa spesialisasi.
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'distinct'],
        ]);

        $this->specialties->sync($staff, $validated['category_ids'] ?? []);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'data' => $staff->specialties()->pluck('categories.id')]);
        }

        return to_route('admin.staff.index')->with('status', "Spesialisasi {$staff->name} disimpan dan langsung berlaku.");
    }
}
