<?php

use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Alias: di routes cukup tulis 'role:staff' alih-alih nama class lengkap.
        $middleware->alias([
            'role' => EnsureUserHasRole::class,
        ]);

        // Dicek di setiap request web: user yang dinonaktifkan langsung di-logout.
        $middleware->web(append: [
            EnsureUserIsActive::class,
        ]);

        // Tamu yang membuka halaman ber-middleware 'auth' -> ke halaman login.
        $middleware->redirectGuestsTo(fn () => route('login'));

        // User yang SUDAH login membuka halaman 'guest' (mis. /login) -> ke dashboard perannya.
        $middleware->redirectUsersTo(fn (Request $request) => route($request->user()->role->homeRoute()));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
