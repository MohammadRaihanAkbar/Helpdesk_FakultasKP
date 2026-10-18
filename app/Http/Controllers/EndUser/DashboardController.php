<?php

namespace App\Http\Controllers\EndUser;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Dashboard EndUser (placeholder M1). Isi ringkasan & grafik menyusul di milestone berikutnya.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        return view('enduser.dashboard', [
            'user' => $request->user()->load('faculty', 'originFaculty'),
        ]);
    }
}
