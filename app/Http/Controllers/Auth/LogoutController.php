<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Fase 4.2 — Cierre de sesión (el inicio de sesión llega en la 4.3).
 * Se añade ahora porque, al verificar el email, el usuario queda autenticado y
 * necesita poder salir. Invalida la sesión y regenera el token (ASVS V3).
 */
class LogoutController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $userId = Auth::id();

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        Log::info('auth.logout', ['user_id' => $userId]);

        return redirect('/');
    }
}
