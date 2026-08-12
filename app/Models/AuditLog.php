<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Registro inmutable de una acción sensible del panel (Fase 7.0).
 *
 * No tiene `updated_at` ni soft delete: las filas son append-only y NUNCA
 * se modifican. El consumo es exclusivamente lectura (informes, investigación
 * de incidentes). Crear usando `App\Support\AuditLogger::log()`.
 */
class AuditLog extends Model
{
    /**
     * Acciones de INCIDENCIA introducidas por la visibilidad de incidencias (recomendación C,
     * 2026-06-15): los dos peores casos del retorno Redsys, que antes solo iban a `Log::error`
     * (`RedsysReturnHandler`). Constantes para que el handler que las ESCRIBE y la página que
     * las LEE compartan el mismo string sin drift.
     */
    public const ACTION_DUPLICATE_CAPTURE = 'payments.duplicate_capture';

    public const ACTION_OVERBOOKED_CAPTURE = 'payments.overbooked_capture';

    /**
     * Acciones consideradas INCIDENCIA CRÍTICA (dinero + seguridad/RGPD): las que la página
     * «Incidencias» del panel destaca y filtra por defecto, y por las que se avisa al operador.
     * No incluye los `*_blocked` de integridad de datos (catálogo/contenido/precios): esos son
     * guardas operativas rutinarias, no incidencias que requieran escalado.
     *
     * @var array<int,string>
     */
    public const CRITICAL_ACTIONS = [
        self::ACTION_DUPLICATE_CAPTURE,     // cobro duplicado/huérfano: requiere devolución manual
        self::ACTION_OVERBOOKED_CAPTURE,    // cobro tras caducar: plaza pudo cederse
        'orders.refund_failed',             // fallo del reembolso REST contra Redsys
        'orders.item_refund_failed',        // fallo del reembolso parcial por línea
        'orders.payment_init_failed',       // no se pudo iniciar el pago
        'users.anonymize_blocked',          // RGPD: anonimización bloqueada
        'access.user_roles_update_blocked', // seguridad: cambio de roles bloqueado
        'registrations.validate_rate_limited', // abuso: rate-limit en la puerta
    ];

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'action',
        'target_type',
        'target_id',
        'payload',
        'payload_hash',
        'ip',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function target(): MorphTo
    {
        return $this->morphTo();
    }
}
