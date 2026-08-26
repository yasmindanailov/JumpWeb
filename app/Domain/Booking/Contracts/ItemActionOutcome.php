<?php

namespace App\Domain\Booking\Contracts;

/**
 * El resultado de una acción del panel sobre un ÍTEM ya comprado (editar,
 * mover de franja, guardar los datos del evento, cancelar, reembolsar) —
 * extracción 4b del desmontaje de `ViewOrder`, spec §9.6·5.
 *
 * El dominio devuelve QUÉ pasó; la capa de entrega decide cómo contarlo:
 * el audit del RECHAZO (`orders.item_*_blocked`, `order_items.event_data_blocked`)
 * y la `Notification` al operador son suyos. Los audits de ÉXITO y el email
 * al cliente viajan con la operación, dentro del servicio, con la topología
 * transaccional de §4.3 intacta.
 *
 *  - `reason` es la clave ESTRUCTURADA de bloqueo — la misma que la entrega
 *    ya auditaba y traducía (`stale_item_version`, `required_missing`,
 *    `insufficient_capacity_at_save`…), para que ni un texto ni un rastro
 *    cambien con la mudanza.
 *  - `extra` lleva lo que el aviso necesita además de la clave (las claves
 *    obligatorias ausentes, los complementos huérfanos, los importes que el
 *    toast de éxito enseña).
 *  - `changed` distingue «hecho» de «no había nada que cambiar» (el diff
 *    vacío de `event_data`, el ítem que ya estaba en esa franja).
 */
final readonly class ItemActionOutcome
{
    /** @param array<string,mixed> $extra */
    private function __construct(
        public bool $ok,
        public bool $changed,
        public ?string $reason,
        public array $extra,
    ) {}

    /** @param array<string,mixed> $extra */
    public static function done(array $extra = []): self
    {
        return new self(ok: true, changed: true, reason: null, extra: $extra);
    }

    public static function unchanged(): self
    {
        return new self(ok: true, changed: false, reason: null, extra: []);
    }

    /** @param array<string,mixed> $extra */
    public static function blocked(string $reason, array $extra = []): self
    {
        return new self(ok: false, changed: false, reason: $reason, extra: $extra);
    }

    public function isBlocked(): bool
    {
        return ! $this->ok;
    }
}
