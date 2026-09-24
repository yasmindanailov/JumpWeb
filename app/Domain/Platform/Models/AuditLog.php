<?php

namespace App\Domain\Platform\Models;

use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Registro inmutable de una acción sensible del panel (Fase 7.0).
 *
 * No tiene `updated_at` ni soft delete: las filas son append-only y NUNCA
 * se modifican. El consumo es exclusivamente lectura (informes, investigación
 * de incidentes). Crear usando `App\Domain\Platform\Services\AuditLogger::log()`.
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
        'puerta.lookup_rate_limited',       // abuso: rate-limit de la búsqueda TECLEADA que abre la ficha (subsistema A, §4.6·4)
    ];

    /**
     * **CATÁLOGO COMPLETO de acciones auditables** (`DECISIONES #145`).
     *
     * Existe porque el registro del pedido llevaba meses enseñando la CLAVE CRUDA de casi todas
     * sus acciones: medido el 2026-08-25 sobre staging, de las **9 acciones de pedido realmente
     * emitidas allí, 8 no tenían etiqueta**. Y al revés: de las 18 etiquetas que había, **4 estaban
     * archivadas bajo un grupo que el código no emite** (`order_items.item_refunded` cuando lo que
     * se escribe es `orders.item_refunded`) y **4 etiquetaban un ciclo retirado** (preparado /
     * sin preparar). El fichero de idioma describía un vocabulario que el código ya no usaba.
     *
     * ⚠️ **Un catálogo estático NO basta por sí solo, y por eso está también la validación en
     * `AuditLogger`**: tres acciones se construyen CONCATENANDO
     * (`'orders.item_'.$actionKey.'_blocked'`, con `$actionKey` ∈ `edit|cancel|refund`) y dos
     * llegan por CONSTANTE dentro de un array de incidencia (`RedsysReturnHandler`). Un escaneo
     * estático del código no las ve — se comprobó: la primera extracción de esta misma sesión se
     * dejó `orders.refund_blocked`, que se pasa a través de un helper. Lo único que las caza es
     * ejecutar el código, que es lo que hace la suite con la validación activa.
     *
     * ⚠️ **El orden no importa; la pertenencia sí.** Añadir una acción nueva sin registrarla aquí
     * lanza FUERA de producción (ver `AuditLogger::assertKnownAction`), y en producción se acepta
     * en silencio: una etiqueta que falta no puede tumbar un flujo de cobro.
     *
     * @var array<int,string>
     */
    public const ACTIONS = [
        // ── Accesos y roles ────────────────────────────────────────────────────────────────
        'access.role_permissions_updated',
        'access.user_roles_update_blocked',
        'access.user_roles_updated',

        // ── Calendario ─────────────────────────────────────────────────────────────────────
        'calendar.day_summary_printed',

        // ── Catálogo ───────────────────────────────────────────────────────────────────────
        'catalog.addon_attached',
        'catalog.addon_configured',
        'catalog.addon_detached',
        'catalog.created',
        'catalog.delete_blocked',
        'catalog.deleted',
        'catalog.prices_updated',
        'catalog.update_blocked',
        'catalog.updated',

        // ── Contenido / CMS ────────────────────────────────────────────────────────────────
        'content.attraction_created',
        'content.attraction_deleted',
        'content.attraction_updated',
        'content.faq_created',
        'content.faq_deleted',
        'content.faq_updated',
        'content.landing_service_created',
        'content.landing_service_deleted',
        'content.landing_service_updated',
        'content.offer_created',
        'content.offer_deleted',
        'content.offer_updated',
        'content.page_updated',
        'content.rule_created',
        'content.rule_deleted',
        'content.rule_updated',
        'content.zone_created',
        'content.zone_delete_blocked',
        'content.zone_deleted',
        'content.zone_updated',

        // ── Ficha de Google (`specs/google-business-profile.md` §4.2) ──────────────────────
        // ⚠️ El payload NUNCA lleva token ni código: solo el hecho y quién lo hizo.
        'google_business.connected',
        'google_business.disconnected',
        'google_business.location_chosen',
        // ⚠️⚠️ **«Ocultar» (§4.3·7, `#731`) lleva SOLO el hash y el motivo**, nunca el texto, el
        // nombre ni la foto del autor (`RGPD-02`). Y no es una cautela de más: la reseña se borra al
        // ocultarla, así que este registro **sobrevive al dato que lo causó** y dura mucho más.
        'google_business.review_hidden',
        'google_business.review_unhidden',

        // ── Mantenimiento ──────────────────────────────────────────────────────────────────
        'maintenance.updated',

        // ── Reservas (target = OrderItem) ──────────────────────────────────────────────────
        'order_items.event_data_blocked',
        'order_items.event_data_updated',

        // ── Pedidos (target = Order) ───────────────────────────────────────────────────────
        'orders.cancel_blocked',
        'orders.cancelled',
        'orders.created_manual',
        'orders.customer_registered',
        'orders.email_resent',
        'orders.email_resent_blocked',
        'orders.extra_due_applied',
        'orders.guest_form_link_rotated',
        'orders.guest_form_link_rotate_blocked',
        'orders.guest_form_submitted',
        // `#574`: un padre contestó a la invitación digital. SIN PII — ni el nombre del niño ni sus
        // datos: solo qué reserva, si viene y si ocupó plaza nueva (`RGPD-02`).
        'orders.invitation_reply_received',
        // `#576`: el operador ANULÓ el enlace de la invitación. Sin el token, que es la credencial:
        // escribirlo en el rastro sería guardarla en claro (`RGPD-02`, la lección de `#413`).
        'orders.invitation_link_rotated',
        'orders.invitation_link_rotate_blocked',
        'orders.item_cancel_blocked',   // ⚠️ construida: 'orders.item_'.$actionKey.'_blocked'
        'orders.item_cancelled',
        'orders.item_edit_blocked',     // ⚠️ construida
        'orders.item_edited',
        'orders.item_refund_blocked',   // ⚠️ construida
        'orders.item_refund_failed',
        'orders.item_refunded',
        'orders.item_slot_changed',
        'orders.manual_created_without_phone',  // `#440`: se vendió sin teléfono — se avisa, no se bloquea
        'orders.manual_customer_phone_added',   // `#440`: el operador escribió el teléfono que faltaba
        'orders.guest_count_changed',   // `#444`: el CLIENTE movió los invitados de su reserva
        'orders.mixed_party_surcharge_synced',
        'orders.payment_init_failed',
        'orders.postform_addons_changed',
        'orders.refund_blocked',
        'orders.refund_failed',
        'orders.refunded',
        'orders.slip_printed',
        'orders.value_reduction_applied',   // T1 del libro: la bajada de una gestión, como UN hecho

        // ── Panel ──────────────────────────────────────────────────────────────────────────
        'panel.locale_changed',
        // La descarga del CSV de «Analítica» (`specs/analitica.md` §4.5, T2d): informe, periodo y recuento; sin PII.
        'reports.exported',
        // T4b de la analítica: la descarga de un SEGMENTO (una lista de personas con opt-in), con el segmento y
        // el recuento en el payload, sin PII.
        'segments.exported',
        // T5b de la analítica: un experimento se crea, se cambia (encender, apagar, ventana) o se borra desde
        // «Ajustes»; el payload lleva la clave, las variantes y el estado, sin PII.
        'experiments.saved',
        'experiments.deleted',

        // ── Incidencias de cobro (llegan por CONSTANTE, no por literal) ────────────────────
        self::ACTION_DUPLICATE_CAPTURE,
        self::ACTION_OVERBOOKED_CAPTURE,

        // ── Precios y tarifas ──────────────────────────────────────────────────────────────
        'prices.rate_created',
        'prices.rate_delete_blocked',
        'prices.rate_deleted',
        'prices.rate_updated',
        'prices.special_date_created',
        'prices.special_date_deleted',
        'prices.special_date_updated',

        // ── Puerta ─────────────────────────────────────────────────────────────────────────
        'registrations.validate_rate_limited',
        'registrations.validated',

        // ── Ajustes ────────────────────────────────────────────────────────────────────────
        'settings.updated',

        // ── Franjas y horario ──────────────────────────────────────────────────────────────
        'slot_templates.generated',
        'slots.capacity_override_cleared',
        'slots.regenerated',
        'slots.season_created',
        'slots.season_deleted',
        'slots.season_updated',
        'slots.slot_updated',
        'slots.template_created',
        'slots.template_deleted',
        'slots.template_updated',
        'slots.weekly_schedule_updated',

        // ── Usuarios ───────────────────────────────────────────────────────────────────────
        'users.anonymize_blocked',
        'users.anonymized',
        'users.password_reset_sent',
        'users.send_reset_blocked',
        'users.rotate_card_blocked',        // «Rotar carné» del panel rechazada entre render y submit (`specs/identidad-qr-puerta.md` §9.6 B·5)

        // ── Menores a cargo (Fase 6 · C, `specs/menores-a-cargo.md`) — target = User; sin PII ──
        'dependents.added',                 // payload: dependent_id (nunca el nombre ni la fecha)
        'dependents.removed',               // payload: dependent_id + mode (deleted | unlinked)
        'dependents.assigned',              // payload: dependent_id + order_item_id + order_id (tanda 4; nunca el nombre)
        'dependents.unassigned',            // payload: dependent_id + order_item_id + order_id (tanda 5, el mostrador desmarca; user_id = el operador)

        // ── Identidades externas (`specs/auth-con-google.md` §6.2) — target = User; nunca el `sub` ──
        // Payload: proveedor, por qué puerta se vinculó y si la cuenta destino estaba SIN verificar y
        // se promovió (P12). El identificador de Google NO entra: no hace falta para auditar y es un
        // identificador de la persona en un tercero (`RGPD-02`).
        'identities.linked',
        // El titular quita el vínculo desde su cuenta (T3). Payload: el proveedor y nada más.
        'identities.unlinked',

        // ── Carné QR y puerta (Fase 6 · A, `specs/identidad-qr-puerta.md` §9.2 A·10) — target = User; nunca el token ──
        'cards.issued',                     // payload: card_id
        'cards.rotated',                    // payload: card_id (el revocado) + reason (`rotated`)
        'cards.revoked',                    // payload: count + reason (`revoked` | `anonymized`); lo escribe `User::revokeAllAccess()`/`anonymize()`
        'puerta.card_scanned',              // SENSIBLE: payload_hash = sha256 del token; target = el titular si el carné existe
        'puerta.profile_viewed',            // la DIVULGACIÓN de la ficha (§4.6·3), distinta de la búsqueda; payload: via (`card` | `lookup`)
        'puerta.visit_registered',          // la visita acreditada (§8.3); payload: visited_on; user_id = el operador
        // `#336` — el operador DA FE de una aceptación retenida, con la persona delante: la firma
        // queda con su nombre en `declared_by_user_id`. ⚠️ Acredita a la PERSONA, nunca al BUZÓN:
        // `email_verified_at` no se toca. Payload: signature_id, legal_document_version_id y el
        // canal por el que se aceptó.
        'puerta.waiver_declared',
        'puerta.lookup_rate_limited',       // crítica: el limitador de la búsqueda tecleada; payload: limit

        // ── Waiver probatorio y textos legales versionados (Fase 6) ────────────────────────
        'legal.version_published',          // target = LegalDocumentVersion (la fila del 1.er idioma)
        'waiver.declared',                  // firma DECLARADA por un operador (alta presencial)
        'waiver.proof_downloaded',          // PDF del registro probatorio servido; target = WaiverSignature
        'waiver.proof_viewed',              // registro probatorio abierto en la ficha; target = User
        'waiver.signed',                    // firma del titular (web/API); target = User
    ];

    /**
     * Las acciones que pueden aparecer en el REGISTRO DE UN PEDIDO, y que por tanto **deben**
     * tener etiqueta en `admin.orders.audit_modal.actions.*`.
     *
     * Se derivan por PREFIJO y no a mano: el registro del pedido consulta por `target_type`
     * (`order` / `order_item`), así que cualquier acción de esas dos familias puede salir ahí.
     * Derivarlo evita la deriva que este catálogo existe para cerrar.
     *
     * @return array<int,string>
     */
    public static function orderActions(): array
    {
        return array_values(array_filter(
            self::ACTIONS,
            static fn (string $a): bool => str_starts_with($a, 'orders.') || str_starts_with($a, 'order_items.'),
        ));
    }

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
