<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Dashboard Admin (placeholder M1). Isi ringkasan & grafik menyusul di milestone berikutnya.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        return view('admin.dashboard', [
            'user' => $request->user()->load('faculty', 'originFaculty'),
        ]);
    }
}
