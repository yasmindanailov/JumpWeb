<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

/**
 * Fase 4.2 — Verificación de email. La cuenta se activa al confirmar (Flujo 1).
 * El enlace va firmado y caduca (~60 min). No requiere sesión previa: el id+hash
 * firmados prueban la titularidad; tras verificar, se inicia sesión.
 *
 * Pay-first (decisión clienta 2026-06-14): en la COMPRA el email ya NO bloquea — el pedido se
 * cobra con Redsys y el pago auto-verifica la cuenta (un bot no paga; ver RedsysReturnHandler).
 * La verificación por correo queda como vía para entrar a «mi cuenta» sin comprar; quien se
 * registró en la compra y abandonó sin pagar puede REENVIARSE el correo desde la página de aviso.
 */
class EmailVerificationController extends Controller
{
    /** Página informativa "revisa tu correo" (pública; con botón de reenviar si hay sesión). */
    public function notice(): View
    {
        return view('auth.verify-email');
    }

    /** Verifica el email desde el enlace firmado del correo. */
    public function verify(Request $request, int $id, string $hash): RedirectResponse
    {
        // 403 (no 404) si el id no existe: la firma garantiza autenticidad del enlace; un id
        // desconocido solo puede venir de una cuenta borrada o de un atacante. Devolver el mismo
        // código que para hash inválido evita distinguir "id no existe" vs "hash inválido".
        $user = User::find($id);
        if (! $user) {
            abort(403);
        }

        if (! hash_equals($hash, sha1($user->getEmailForVerification()))) {
            abort(403);
        }

        if ($user->hasVerifiedEmail()) {
            Auth::login($user);

            return redirect()->route('account.orders')->with('status', 'email-already-verified');
        }

        $user->markEmailAsVerified();
        event(new Verified($user));
        Auth::login($user);
        Log::info('auth.email_verified', ['user_id' => $user->id, 'via' => 'link']);

        // Pay-first: el enlace YA NO confirma reservas (antes dejaba el pedido «firme pero sin pagar»,
        // estado incoherente heredado de la Capa 4 pre-Redsys; #76/#78). Solo verifica + abre «mis
        // reservas» (ya accesible: la cuenta acaba de quedar verificada).
        return redirect()->route('account.orders')->with('status', 'email-verified');
    }

    /**
     * Reenvía el correo de verificación al usuario AUTENTICADO sin verificar (desde la página de
     * aviso, p. ej. al intentar entrar a «mi cuenta» tras registrarse en la compra sin pagar).
     * Cooldown por usuario (1/min) además del throttle de ruta. Mensaje siempre genérico.
     */
    public function resend(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login');
        }

        if ($user->hasVerifiedEmail()) {
            return redirect()->route('account.orders')->with('status', 'email-already-verified');
        }

        $key = 'verify-notice-resend:'.$user->id;
        if (RateLimiter::tooManyAttempts($key, 1)) {
            return back()->with('status', 'verification-resend-throttled');
        }
        RateLimiter::hit($key, 60);

        $user->sendEmailVerificationNotification();
        Log::info('auth.verification_resent', ['user_id' => $user->id, 'via' => 'notice']);

        return back()->with('status', 'verification-link-sent');
    }
}
