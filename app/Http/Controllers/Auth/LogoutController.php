<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Identity\Services\RememberedDevice;
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

        // ⚠️ ESTE dispositivo y no `logout()` (A2a del acceso con código, `#855`): salir cierra ESTE dispositivo
        // (`#848`·3, como `auth/logout`). `logout()` rota el `remember_token` —uno por cuenta, al que van atadas todas las
        // sesiones y cookies de recuerdo— y echaría también al móvil al salir en el portátil. Y su cookie de recuerdo sale
        // también de esta petición (A3, `#857`): lo que preguntara después por el usuario volvía a entrar con ella.
        RememberedDevice::logOutHere($request);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        Log::info('auth.logout', ['user_id' => $userId]);

        return redirect('/');
    }
}
