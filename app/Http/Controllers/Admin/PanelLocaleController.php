<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Platform\Services\AuditLogger;
use App\Http\Controllers\Controller;
use App\Http\Middleware\SetAdminLocale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Cambia el idioma del panel para el usuario autenticado.
 *
 * Ver `docs/PLAN-FASE-7-PANEL.md` §1.3 y `docs/DECISIONES.md` #123.
 *
 * POST /admin/lang/{locale} — staff o admin. La preferencia se persiste en
 * `users.panel_locale` (vive entre sesiones, no se reseleciona cada día).
 *
 * Defensa:
 *  - Locale valida contra `SetAdminLocale::SUPPORTED` (es/zh_CN); cualquier
 *    otro valor devuelve 422 (anti-tampering).
 *  - Solo POST (los GETs no deben mutar estado).
 *  - CSRF estándar (la sesión y la cookie SÍ están disponibles — es navegación
 *    top-level desde el panel; no es cross-site como Redsys).
 *  - Audit log de cada cambio.
 */
class PanelLocaleController extends Controller
{
    public function __invoke(Request $request, string $locale): RedirectResponse
    {
        if (! in_array($locale, SetAdminLocale::SUPPORTED, true)) {
            abort(422, 'Unsupported panel locale.');
        }

        $user = $request->user();
        $previous = $user->panel_locale;
        $user->forceFill(['panel_locale' => $locale])->save();

        AuditLogger::log(
            action: 'panel.locale_changed',
            target: $user,
            payload: ['previous' => $previous, 'new' => $locale],
        );

        return back();
    }
}
