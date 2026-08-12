<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Fase 4.4 — Página para fijar la nueva contraseña (se llega desde el enlace del
 * correo). El formulario es el componente Livewire `Auth\ResetPassword`.
 * La solicitud del enlace ("¿olvidaste tu contraseña?") es un modal.
 */
class PasswordResetController extends Controller
{
    public function edit(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }
}
