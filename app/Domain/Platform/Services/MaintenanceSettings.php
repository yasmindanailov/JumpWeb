<?php

namespace App\Domain\Platform\Services;

use App\Domain\Platform\Models\Setting;

/**
 * Helpers defensivos del subsistema de DISPONIBILIDAD / MANTENIMIENTO (fuera de roadmap, #218).
 *
 * Mismo patrón que `PuertaSettings`/`ThemeSettings`/`DisplayTime`: lee de `settings`, valida con
 * fallback NO destructivo y NUNCA lanza. La **dirección fail-safe es deliberada y crítica**: el
 * mantenimiento exige **opt-in explícito**. Un valor ausente, corrupto o no reconocido deja TODO
 * disponible (la web arriba, las reservas abiertas) — un setting roto jamás puede tirar la web ni
 * cortar ventas por accidente. Es lo CONTRARIO del waiver (#216), que por seguridad cae a ON.
 *
 * Tres ámbitos independientes (precedencia sitio > página > reservas):
 *  - Sitio entero    → `maintenance.site` == '1'           (item 2 — esta iteración)
 *  - Página concreta → `maintenance.page.{clave}` == '1'   (item 1 — iteración posterior)
 *  - Reservas        → `reservations.enabled` == '0'        (item 3 — iteración posterior)
 *
 * El enforcement vive fuera (middleware `EnsureSiteAvailable`, CTAs y guards de servidor); aquí
 * solo está la LECTURA del estado. Ver `docs/DECISIONES.md` #218.
 */
class MaintenanceSettings
{
    /**
     * Páginas públicas que pueden ponerse en mantenimiento individual (lista FIJA — son rutas
     * conocidas con nombre propio). El middleware mapea la ruta actual a una de estas claves.
     * `entradas` (deep-link de compra) comparte el estado de `home` (renderiza la misma página).
     *
     * @var list<string>
     */
    public const PAGE_KEYS = ['home', 'precios', 'cumpleanos', 'servicios', 'normas', 'contacto', 'bar'];

    /**
     * ¿Toda la web pública está en mantenimiento? (item 2)
     *
     * Solo si el literal '1' está fijado. Ausente/`null`/cualquier otro valor → `false` (la web
     * sigue arriba): fail-safe deliberado, un setting corrupto no puede cerrar la web entera.
     */
    public static function siteInMaintenance(): bool
    {
        return (string) Setting::value('maintenance.site', '0') === '1';
    }

    /**
     * ¿El sistema de RESERVAS está en pausa? (item 3)
     *
     * Solo si el literal '1' está fijado en `reservations.paused` (misma polaridad que
     * `maintenance.site`: default '0', estado de mantenimiento = '1'). Ausente/`null`/cualquier otro
     * valor → reservas ABIERTAS (fail-safe: un setting corrupto no corta las ventas por accidente).
     *
     * Pausa SOLO la reserva ONLINE pública (CTAs → teléfono + guards de servidor en `Purchase` y los
     * reintentos de pago). El **pedido manual del panel** (`OrderCreator` vía `ManualOrderFulfiller`)
     * NO se ve afectado: el cliente llama y el personal reserva a mano — ese es el propósito del
     * fallback telefónico. Las callbacks de Redsys tampoco: un pago YA iniciado puede finalizar.
     */
    public static function reservationsPaused(): bool
    {
        return (string) Setting::value('reservations.paused', '0') === '1';
    }

    /**
     * ¿Una página concreta está en mantenimiento? (item 1)
     *
     * Solo claves conocidas (`PAGE_KEYS`) y solo si el literal '1' está fijado en
     * `maintenance.page.{clave}`. Clave desconocida o cualquier otro valor → `false` (página
     * disponible): mismo fail-safe que el resto del subsistema.
     */
    public static function pageInMaintenance(string $key): bool
    {
        if (! in_array($key, self::PAGE_KEYS, true)) {
            return false;
        }

        return (string) Setting::value('maintenance.page.'.$key, '0') === '1';
    }

    /**
     * Mensaje de mantenimiento de sitio para el `$locale` dado, con fallback al texto i18n por
     * defecto si el admin no fijó un override (patrón #215: setting-por-idioma con respaldo i18n).
     * Devuelve SIEMPRE un texto válido — la página de mantenimiento nunca queda muda.
     */
    public static function siteMessage(?string $locale = null): string
    {
        $locale = $locale ?: app()->getLocale();
        $override = trim((string) Setting::value('maintenance.message.'.$locale, ''));

        // El fallback i18n se resuelve en EL MISMO `$locale` (no en el locale global de la app),
        // para que pedir el mensaje de un idioma concreto sea coherente aunque la app esté en otro.
        return $override !== '' ? $override : (string) __('site.maintenance.body', [], $locale);
    }

    /**
     * Mensaje de «reservas en pausa» para el `$locale` dado, con fallback al texto i18n por defecto si
     * el admin no fijó override (mismo patrón #215 que `siteMessage()`). Lo muestra el sidecart de
     * compra (`Purchase::pausedMessage()`). Devuelve SIEMPRE un texto válido — el aviso nunca queda mudo.
     */
    public static function reservationMessage(?string $locale = null): string
    {
        $locale = $locale ?: app()->getLocale();
        $override = trim((string) Setting::value('reservations.message.'.$locale, ''));

        return $override !== '' ? $override : (string) __('tickets.paused.body', [], $locale);
    }

    /**
     * Título del aviso de «reservas en pausa» para el `$locale` dado, con fallback al texto i18n por
     * defecto si el admin no fijó override (igual que `reservationMessage()`). Lo muestra el sidecart
     * (`Purchase::pausedTitle()`). Devuelve SIEMPRE un texto válido.
     */
    public static function reservationTitle(?string $locale = null): string
    {
        $locale = $locale ?: app()->getLocale();
        $override = trim((string) Setting::value('reservations.title.'.$locale, ''));

        return $override !== '' ? $override : (string) __('tickets.paused.title', [], $locale);
    }

    /**
     * El aviso COMPLETO de «reservas en pausa»: título, mensaje y canales de contacto (Fase 4 ·
     * paso 4.0b).
     *
     * Existe porque hay dos consumidores —el sidebar y `GET /api/v1/booking/status`— y la parte que
     * faltaba por centralizar eran los canales: el título y el mensaje ya salían de aquí, pero el
     * teléfono y el WhatsApp los normalizaba a mano la clase de UI.
     *
     * ⚠️ **La normalización del teléfono se conserva EXACTAMENTE como estaba** (`\s+`), y no se
     * unifica con la otra que existe en el sistema (`[^0-9+]`, en el composer global de vistas)
     * aunque las dos lean el mismo ajuste. Unificarlas **cambiaría el `tel:` renderizado** en
     * cualquier instalación cuyo teléfono lleve puntuación, y eso es una decisión de producto, no
     * un refactor. La divergencia queda anotada en `DEUDA.md` para que sea visible en vez de vivir
     * escondida en dos ficheros.
     */
    public static function reservationNotice(?string $locale = null): ReservationNotice
    {
        $locale = $locale ?: app()->getLocale();

        $phone = trim((string) Setting::value('contact.phone', ''));
        $phoneTel = (string) preg_replace('/\s+/', '', $phone);
        $whatsapp = (string) preg_replace('/\D/', '', (string) Setting::value('contact.whatsapp', ''));

        return new ReservationNotice(
            title: self::reservationTitle($locale),
            message: self::reservationMessage($locale),
            // `null` y no cadena vacía: «no hay canal» es un estado, no un texto vacío, y es lo que
            // los dos consumidores preguntan para decidir si lo pintan.
            phone: $phoneTel !== '' ? $phone : null,
            phoneTel: $phoneTel !== '' ? $phoneTel : null,
            whatsapp: $whatsapp !== '' ? $whatsapp : null,
        );
    }
}
