<?php

namespace App\Domain\Payments\Services;

use App\Domain\Platform\Models\Setting;

/**
 * Helper defensivo para el destinatario de los AVISOS DE INCIDENCIA de cobro (recomendación C,
 * 2026-06-15). Mismo patrón que `PaymentSettings`/`DisplayTime`: lee el setting, valida y aplica
 * un fallback no destructivo.
 *
 * Cadena de resolución del email del operador:
 *   1. `incidents.alert_email` — override explícito (un buzón técnico/operaciones), editable en el
 *      panel (Ajustes → Avanzado).
 *   2. `contact.email` — el email del negocio que ya usa el formulario de contacto (#180).
 *   3. `null` — si ninguno es un email válido, NO se envía aviso (la web/handler no rompen); la
 *      incidencia queda igualmente registrada en `audit_logs` y en `laravel.log`, que son el
 *      rastro DURADERO. El email es best-effort.
 */
class IncidentSettings
{
    /**
     * Email al que avisar de una incidencia crítica de cobro, o `null` si no hay uno válido.
     */
    public static function alertEmail(): ?string
    {
        $candidates = [
            Setting::value('incidents.alert_email'),
            Setting::value('contact.email'),
        ];

        foreach ($candidates as $raw) {
            $email = trim((string) $raw);
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false) {
                return $email;
            }
        }

        return null;
    }
}
