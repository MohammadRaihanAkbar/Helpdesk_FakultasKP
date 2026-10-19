<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserType;
use App\Http\Requests\Admin\CategoryRequest;
use App\Models\Category;
use App\Services\CategoryManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Pengaturan Layanan (/admin/services) — US-11.
 *
 * Route (lihat routes/web.php, prefix admin.services.):
 *   GET    /admin/services                    index    pohon kategori 2 tingkat
 *   GET    /admin/services/create             create   form kategori tambahan (?parent_id= untuk Jenis Masalah)
 *   POST   /admin/services                    store
 *   GET    /admin/services/{category}/edit    edit
 *   PUT    /admin/services/{category}         update
 *   PATCH  /admin/services/{category}/status  status   tombol On/Off
 *   DELETE /admin/services/{category}         destroy  hanya kategori tambahan yang belum dipakai tiket
 *
 * Semua aksi pada {category} melewati CategoryPolicy::manage -> kategori fakultas lain = 403.
 */
class ServiceController extends AdminController
{
    public function __construct(private readonly CategoryManager $categories) {}

    public function index(Request $request): View
    {
        $facultyId = $this->facultyId($request);

        return view('admin.services.index', [
            'faculty' => $request->user()->faculty,
            'groups' => $this->categories->tree($facultyId),
        ]);
    }

    public function create(Request $request): View
    {
        $facultyId = $this->facultyId($request);
        $parents = $this->categories->parentOptions($facultyId);

        return view('admin.services.form', [
            'category' => new Category(['is_active' => true]),
            'parents' => $parents,
            // ?parent_id=12 dari tombol "+ Jenis Masalah" di baris kelompok
            'selectedParent' => $parents->firstWhere('id', (int) $request->query('parent_id')),
            'userTypes' => UserType::cases(),
        ]);
    }

    public function store(CategoryRequest $request): RedirectResponse|JsonResponse
    {
        $category = $this->categories->createExtra($this->facultyId($request), $request->validated());

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'data' => $category->load('audiences')], 201);
        }

        return to_route('admin.services.index')->with('status', "Kategori \"{$category->name}\" berhasil ditambahkan.");
    }

    public function edit(Request $request, Category $category): View
    {
        Gate::authorize('manage', $category);

        return view('admin.services.form', [
            'category' => $category->load('audiences', 'parent', 'children'),
            'parents' => $this->categories->parentOptions($category->faculty_id, $category),
            'selectedParent' => $category->parent,
            'userTypes' => UserType::cases(),
        ]);
    }

    public function update(CategoryRequest $request, Category $category): RedirectResponse|JsonResponse
    {
        Gate::authorize('manage', $category);

        $category = $this->categories->update($category, $request->validated());

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'data' => $category->load('audiences')]);
        }

        return to_route('admin.services.index')->with('status', "Kategori \"{$category->name}\" berhasil disimpan.");
    }

    public function status(Request $request, Category $category): RedirectResponse|JsonResponse
    {
        Gate::authorize('manage', $category);

        $validated = $request->validate(['is_active' => ['required', 'boolean']]);
        $this->categories->setActive($category, (bool) $validated['is_active']);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'data' => $category]);
        }

        $message = $category->is_active
            ? "\"{$category->name}\" diaktifkan kembali."
            : "\"{$category->name}\" dinonaktifkan".($category->isGroup() && $category->children()->exists() ? ' beserta Jenis Masalah di bawahnya.' : '.');

        return back()->with('status', $message);
    }

    public function destroy(Request $request, Category $category): RedirectResponse|JsonResponse
    {
        Gate::authorize('manage', $category);

        $name = $category->name;
        $this->categories->delete($category); // standar / sudah dipakai -> BusinessRuleException (409)

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return to_route('admin.services.index')->with('status', "Kategori \"{$name}\" berhasil dihapus.");
    }
}
