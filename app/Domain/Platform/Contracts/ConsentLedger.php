<?php

namespace App\Domain\Platform\Contracts;

/**
 * **El consentimiento VIVO de un visitante** (`docs/specs/analitica.md` §4.3, T3b·2): lo que decidió por
 * última vez, no la foto que se selló en su pedido. Lo pide la cola antes de comunicar una compra a un
 * anunciante, y lo responde Identity (`cookie_consent_logs`, que gana `visitor_id`), porque la prueba del
 * consentimiento es suya y Platform no ve a nadie (`ModuleBoundariesTest`).
 *
 * Es un contrato y no una lectura directa por la misma razón que `Booking\Contracts\CustomerReservations`:
 * la costura entre módulos se declara, se implementa en UN sitio y se enchufa en el proveedor.
 */
interface ConsentLedger
{
    /**
     * ¿Consintió este visitante la categoría en su ÚLTIMA decisión? `null` si nunca decidió con esta cookie
     * del visitante: sin decisión viva no hay conversión de servidor, que es lo correcto.
     */
    public function consentedNow(string $visitorId, string $category): ?bool;
}
