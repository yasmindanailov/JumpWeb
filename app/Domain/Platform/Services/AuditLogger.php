<?php

namespace App\Domain\Platform\Services;

use App\Domain\Platform\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Servicio de auditoría inmutable. Punto único de creación de `AuditLog`.
 *
 * Ver `docs/PLAN-FASE-7-PANEL.md` §1.4 y `docs/DECISIONES.md` #118 / #119.
 *
 * Dos modos:
 *  - `log(action, target, payload)` — payload visible (acciones SIN dato personal:
 *    cambios de estado de ticket, settings, asignación de roles, etc.).
 *  - `logSensitive(action, identifier, target)` — solo guarda el sha256 del
 *    identificador (acciones CON dato personal: validar registro por email/teléfono).
 *
 * Diseño defensivo:
 *  - `request()` se resuelve perezosamente (puede no existir en CLI/jobs).
 *  - Falla silenciosamente con log de WARNING si la BD está caída: el panel debe
 *    seguir funcionando aunque la auditoría hipo (mismo patrón que `OrderConfirmation`
 *    en #98).
 */
class AuditLogger
{
    /**
     * Registrar una acción con payload visible (sin dato personal).
     *
     * @param  array<string,mixed>|null  $payload  Datos del cambio (estado anterior/nuevo, ids, etc.).
     */
    public static function log(string $action, ?Model $target = null, ?array $payload = null): ?AuditLog
    {
        return self::write(
            action: $action,
            target: $target,
            payload: $payload,
            payloadHash: hash('sha256', json_encode($payload ?? [], JSON_THROW_ON_ERROR)),
        );
    }

    /**
     * Registrar una acción con dato personal: el identificador NO se guarda
     * en claro, solo su sha256.
     */
    public static function logSensitive(string $action, string $sensitiveIdentifier, ?Model $target = null): ?AuditLog
    {
        return self::write(
            action: $action,
            target: $target,
            payload: null,
            payloadHash: hash('sha256', $sensitiveIdentifier),
        );
    }

    /**
     * Registrar una acción del SISTEMA, no atribuible a un usuario: NO guarda `user_id`, `ip` ni
     * `user_agent`. Para eventos disparados por un callback server-a-servidor o por el
     * procesamiento interno —p. ej. una incidencia de cobro Redsys—, donde el «actor» de la
     * petición en curso (la IP del banco, o la del navegador del cliente que volvió) NO es
     * significativo y además es dato personal innecesario (minimización RGPD). El payload sigue
     * siendo visible: pásalo SIN PII (igual que `log()`).
     *
     * @param  array<string,mixed>|null  $payload
     */
    public static function logSystem(string $action, ?Model $target = null, ?array $payload = null): ?AuditLog
    {
        return self::write(
            action: $action,
            target: $target,
            payload: $payload,
            payloadHash: hash('sha256', json_encode($payload ?? [], JSON_THROW_ON_ERROR)),
            system: true,
        );
    }

    private static function write(string $action, ?Model $target, ?array $payload, string $payloadHash, bool $system = false): ?AuditLog
    {
        try {
            // En modo sistema NO se mira la petición: ni actor ni IP/UA (minimización RGPD).
            $request = (! $system && app()->bound('request')) ? app(Request::class) : null;

            return AuditLog::create([
                'user_id' => $system ? null : Auth::id(),
                'action' => $action,
                'target_type' => $target?->getMorphClass(),
                'target_id' => $target?->getKey(),
                'payload' => $payload,
                'payload_hash' => $payloadHash,
                'ip' => $request?->ip(),
                'user_agent' => Str::limit((string) ($request?->userAgent() ?? ''), 250, ''),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // No bloquear la acción del panel por un fallo de auditoría: log y continuar.
            \Log::warning('audit_logger.write_failed', [
                'action' => $action,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
