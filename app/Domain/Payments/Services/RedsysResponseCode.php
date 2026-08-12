<?php

namespace App\Domain\Payments\Services;

/**
 * Traduce el código `Ds_Response` de Redsys (Anexo 2 del manual oficial, verificado en
 * `docs/PLAN-REDSYS.md §4`) a una **categoría** y a una **clave i18n** para mostrar al
 * cliente un mensaje útil cuando un pago es denegado.
 *
 * Diseño:
 *  - El mapeo está en código (no en BD) porque son códigos del PROTOCOLO Redsys, no datos
 *    de negocio: el comerciante no los cambia. Si Redsys publica nuevos códigos, se añaden
 *    aquí en un patch.
 *  - Códigos NO mapeados caen a `default` ("denegado por tu banco, contacta con ellos") —
 *    nunca exponemos un código numérico crudo al cliente (sería ruido).
 *  - Las traducciones reales viven en `lang/{es,en,fr}/tickets.php` bajo
 *    `payment_failed.reasons.*` (audit #114).
 *
 * Categorías:
 *  - `card`: problema de la tarjeta (caducidad, CVV erróneo, ajena al servicio) — el
 *    cliente debe revisar/cambiar tarjeta.
 *  - `auth`: problema de autenticación 3DS (PIN, SCA, etc.) — reintentar con misma tarjeta.
 *  - `bank`: denegación del emisor (fondos, antifraude del banco) — contactar con su banco.
 *  - `cancelled`: el propio cliente canceló en la pasarela.
 *  - `system`: error técnico de Redsys / del comercio — reintentar más tarde.
 *  - `default`: cualquier otro caso, mensaje genérico.
 */
class RedsysResponseCode
{
    /**
     * Mapeo `Ds_Response` → clave i18n bajo `tickets.payment_failed.reasons`.
     * Una sola fuente de verdad para mostrar el motivo al cliente (paso 10 y email).
     */
    private const REASON_MAP = [
        // Tarjeta
        '0101' => 'card_expired',
        '0125' => 'card_invalid',
        '0129' => 'cvv_wrong',
        '0180' => 'card_unsupported',
        '0191' => 'card_expired',         // fecha de caducidad errónea — mismo mensaje
        '0102' => 'fraud_suspicion',
        '0106' => 'pin_attempts_exceeded',
        '0202' => 'fraud_suspicion',
        // Autenticación / 3DS
        '0184' => 'auth_failed',
        // Banco emisor
        '0190' => 'bank_denied',
        // Cliente canceló en la pasarela
        '9915' => 'user_cancelled',
        // Sistema (no debería verse el cliente, pero por si acaso)
        '0904' => 'system_error',
        '0909' => 'system_error',
        '0913' => 'system_error',         // pedido repetido — bug nuestro, no del cliente
        '0944' => 'system_error',
    ];

    /**
     * Devuelve la clave i18n del motivo. Si el código es null/desconocido/transitorio,
     * cae al `default`. Si es `9998`/`9999` (en proceso, transitorio: NO debería terminar
     * aquí, indicaría bug nuestro) → también default + log fuera de este helper.
     *
     * @param  string|null  $dsResponse  el `Ds_Response` tal como llega de Redsys (string).
     */
    public static function reasonKey(?string $dsResponse): string
    {
        if ($dsResponse === null || $dsResponse === '') {
            return 'default';
        }

        return self::REASON_MAP[$dsResponse] ?? 'default';
    }

    /**
     * Texto traducido del motivo. Conveniencia para usar directo desde Blade y notifications.
     * Lee de `tickets.payment_failed.reasons.{key}` (audit #114).
     */
    public static function reasonText(?string $dsResponse): string
    {
        return __('tickets.payment_failed.reasons.'.self::reasonKey($dsResponse));
    }
}
