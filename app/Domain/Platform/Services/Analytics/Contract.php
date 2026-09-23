<?php

namespace App\Domain\Platform\Services\Analytics;

/**
 * **EL CONTRATO DE EVENTOS** (`docs/specs/analitica.md` §4.2; `DECISIONES #678`): la lista cerrada de hechos
 * que el libro admite, quién puede emitir cada uno y qué propiedades acepta. **Es la verdad**: el `enum` de
 * `openapi/v1.yaml` y la lista que emite `track.js` se comparan contra esto (`AnalyticsContractTest`).
 *
 * ⚠️⚠️ **`source` no es documentación: es una regla de la ingesta.** Un hecho de SERVIDOR (`order_paid`,
 * `user_registered`…) que llegue por `POST /events` se rechaza: si no, cualquier `curl` fabricaría compras
 * con importe y campaña, y el embudo, el CPA y el ROAS del panel saldrían falsos (spec §7.1, seguridad-1).
 * Los hechos de servidor solo los escribe el `Recorder` desde sus fuentes reales.
 *
 * ⚠️ **Las `props` se cierran por evento y en PHP**, no en el yaml: la guarda de rigor del contrato exige un
 * esquema estricto por objeto, y declarar cuarenta objetos distintos allí sería o inflarlo o no declarar
 * nada (producto-6). Una clave fuera de la lista se descarta; una clave o un valor con pinta de dato
 * personal vacía el evento entero (`PII_KEYS`, `PII_VALUE_RE`).
 *
 * ⚠️ El nombre sigue `objeto_verbo` en pasado y va en `snake_case` ASCII: es una clave, no un rótulo.
 */
final class Contract
{
    public const CLIENT = 'client';

    public const SERVER = 'server';

    /** Las propiedades de atribución que viajan en la primera vista y en ninguna otra. */
    public const ATTRIBUTION_PROPS = ['entry', 'referrer_host', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'ref', 'gclid', 'fbclid', 'ttclid'];

    /** @var array<string, array{source: string, props: list<string>}> */
    public const EVENTS = [
        // ── Entrada y landing (cliente) ──────────────────────────────────────────────────────────
        'page_viewed' => ['source' => self::CLIENT, 'props' => [...self::ATTRIBUTION_PROPS, 'device', 'locale']],
        'section_viewed' => ['source' => self::CLIENT, 'props' => ['section']],
        'call_clicked' => ['source' => self::CLIENT, 'props' => []],
        'whatsapp_clicked' => ['source' => self::CLIENT, 'props' => []],
        'map_clicked' => ['source' => self::CLIENT, 'props' => []],
        'contact_form_started' => ['source' => self::CLIENT, 'props' => []],
        // ── El cajón (cliente) ───────────────────────────────────────────────────────────────────
        'drawer_opened' => ['source' => self::CLIENT, 'props' => ['product', 'reason']],
        'step_entered' => ['source' => self::CLIENT, 'props' => ['from', 'to']],
        'product_chosen' => ['source' => self::CLIENT, 'props' => ['product']],
        'date_chosen' => ['source' => self::CLIENT, 'props' => ['product', 'date']],
        'availability_missing' => ['source' => self::CLIENT, 'props' => ['product', 'month']],
        'time_chosen' => ['source' => self::CLIENT, 'props' => ['product']],
        'line_added' => ['source' => self::CLIENT, 'props' => ['product', 'qty']],
        'line_removed' => ['source' => self::CLIENT, 'props' => ['product', 'qty']],
        'identify_started' => ['source' => self::CLIENT, 'props' => ['method']],
        'identified' => ['source' => self::CLIENT, 'props' => ['method']],
        'email_verification_pending' => ['source' => self::CLIENT, 'props' => []],
        'pay_started' => ['source' => self::CLIENT, 'props' => ['amount_cents']],
        'drawer_closed' => ['source' => self::CLIENT, 'props' => ['step', 'outcome', 'reloading']],
        'request_failed' => ['source' => self::CLIENT, 'props' => ['route', 'status', 'offline']],
        'client_error' => ['source' => self::CLIENT, 'props' => ['hash']],
        // ── Consentimiento y sistema (cliente) ───────────────────────────────────────────────────
        'consent_shown' => ['source' => self::CLIENT, 'props' => []],
        'consent_updated' => ['source' => self::CLIENT, 'props' => ['categories']],
        'batch_dropped' => ['source' => self::CLIENT, 'props' => ['count', 'status']],
        'experiment_exposed' => ['source' => self::CLIENT, 'props' => ['key', 'variant']],
        // ── Los hechos del servidor: solo el `Recorder`, desde su fuente real (spec §4.1) ─────────
        'order_created' => ['source' => self::SERVER, 'props' => ['total_cents', 'channel']],
        'order_paid' => ['source' => self::SERVER, 'props' => ['paid_cents', 'total_cents', 'channel']],
        'order_paid_incident' => ['source' => self::SERVER, 'props' => ['kind', 'paid_cents', 'channel']],
        'order_declined' => ['source' => self::SERVER, 'props' => ['channel']],
        'order_expired' => ['source' => self::SERVER, 'props' => ['channel']],
        'order_payment_init_failed' => ['source' => self::SERVER, 'props' => ['channel']],
        'order_cancelled' => ['source' => self::SERVER, 'props' => ['channel']],
        'order_refunded' => ['source' => self::SERVER, 'props' => ['refunded_cents', 'channel']],
        'user_registered' => ['source' => self::SERVER, 'props' => ['method']],
        'user_logged_in' => ['source' => self::SERVER, 'props' => ['method']],
        'contact_received' => ['source' => self::SERVER, 'props' => ['topic']],
        'guest_form_opened' => ['source' => self::SERVER, 'props' => []],
        'guest_form_submitted' => ['source' => self::SERVER, 'props' => []],
        'invitation_replied' => ['source' => self::SERVER, 'props' => []],
        'email_sent' => ['source' => self::SERVER, 'props' => ['key']],
        'email_clicked' => ['source' => self::SERVER, 'props' => ['key']],
        'visit_checked_in' => ['source' => self::SERVER, 'props' => []],
    ];

    /**
     * Claves que NUNCA entran en `props`, se llamen como se llamen sus dueños: una `prop` con uno de
     * estos nombres (o que lo contenga) vacía el evento. `age` cubre `age`, `guest_age`, `ages`…
     *
     * @var list<string>
     */
    public const PII_KEYS = ['name', 'email', 'phone', 'age', 'dni', 'nif', 'address', 'birth', 'password', 'token'];

    /**
     * Valores con pinta de correo o de teléfono, en `props`, `route` o `referrer`: se vacían. Una expresión
     * no es una garantía —por eso la lista de claves va delante—, pero es la red que caza el correo que
     * viaja en la query de un enlace de recuperación (spec §7.1, seguridad-2).
     *
     * ⚠️ Un teléfono son **nueve cifras o más** (con separadores entre medias), y se cuenta por CIFRAS: la
     * primera versión contaba caracteres y tomaba una fecha ISO —`2026-09-23`, ocho cifras y dos guiones— por
     * un teléfono, con lo que **todo `date_chosen` y toda ruta con fecha se habrían rechazado como PII** (lo
     * cazó el primer test de `request_failed` en la T1b, no la revisión).
     */
    public const PII_VALUE_RE = '/[\w.+-]+@[\w-]+\.[\w.-]{2,}|(?<!\d)(?:\+|00)?(?:\d[ .\-()]*){9,}(?!\d)/u';

    /** Longitud máxima de un valor escalar de `props` o de `route`. */
    public const MAX_VALUE_LENGTH = 255;

    /** Peso máximo de `props` una vez codificado, en bytes (spec §4.1). */
    public const MAX_PROPS_BYTES = 2048;

    public static function exists(string $name): bool
    {
        return array_key_exists($name, self::EVENTS);
    }

    public static function isServer(string $name): bool
    {
        return (self::EVENTS[$name]['source'] ?? null) === self::SERVER;
    }

    /** @return list<string> */
    public static function allowedProps(string $name): array
    {
        return self::EVENTS[$name]['props'] ?? [];
    }

    /** @return list<string> */
    public static function names(?string $source = null): array
    {
        $names = [];

        foreach (self::EVENTS as $name => $definition) {
            if ($source === null || $definition['source'] === $source) {
                $names[] = $name;
            }
        }

        return $names;
    }

    /** ¿Tiene esta clave pinta de dato personal? Se compara por CONTENIDO, no por igualdad. */
    public static function isPiiKey(string $key): bool
    {
        $key = strtolower($key);

        foreach (self::PII_KEYS as $pii) {
            if (str_contains($key, $pii)) {
                return true;
            }
        }

        return false;
    }

    public static function looksLikePii(string $value): bool
    {
        return preg_match(self::PII_VALUE_RE, $value) === 1;
    }
}
