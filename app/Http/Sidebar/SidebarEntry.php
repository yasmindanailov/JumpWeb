<?php

namespace App\Http\Sidebar;

/**
 * El DESENLACE del pago que espera a que el cajón lo enseñe (Fase 4 · paso 4.0a,
 * `docs/specs/sidebar-spa.md` §4.1).
 *
 * Cuando el cliente vuelve de la pasarela, el navegador aterriza en una ruta WEB —no en el cajón y
 * no en la API—, y lo que hay que enseñarle (reserva confirmada · pago denegado · verificando)
 * viaja hasta el cajón por la SESIÓN. Tres claves, escritas por dos controladores y leídas por el
 * layout y por el motor del cajón.
 *
 * **Por qué esta clase existe.** Hasta el paso 4.0a esas tres claves se nombraban a mano en cinco
 * ficheros, y el ÚNICO que las olvidaba era `Livewire\Tickets\Purchase::mount()`. Con otro motor
 * —el de Fase 4— nadie las olvidaría: el cajón se auto-abriría en **cada página** hasta que
 * caducase la sesión, y la SPA además no tendría forma de leerlas. Ahora las claves, su
 * precedencia y su ciclo de vida viven aquí y en ningún otro sitio, y `SidebarEntryTest` lo impone.
 * Es el mismo trato que `User::revokeAllAccess()` da a las credenciales (`RGPD-06`).
 *
 * **Quién lee, y por qué son dos llamadas distintas.** Con el motor Livewire eran dos peticiones y
 * dos lectores; desde 4.7·2b·3 el cajón SPA vive en el MISMO documento y las dos corren juntas:
 *  - **`peek()`** lo usa el LAYOUT para decidir si el cajón se abre solo (`data-purchase-open`).
 *    No consume. Cuando el motor era Livewire NO PODÍA consumir: el componente era `lazy` y su
 *    `mount()` corría en una petición POSTERIOR (medido el 2026-08-13).
 *  - **`consume()`** lo llama el MOTOR, y hoy el motor **es el propio layout**: se lo lleva al
 *    componer `data-boot` del punto de montaje. Por eso `consume()` está memoizado por petición —el
 *    `peek()` del `<body>` y este corren en la MISMA— y por eso la SESIÓN queda vacía al terminar
 *    la respuesta, que es lo que comprueban `SidebarMountTest` y `RedsysReturnControllerTest`.
 */
final readonly class SidebarEntry
{
    /** Reserva confirmada: la pasarela autorizó (o el enlace de verificación cerró el alta). */
    public const OUTCOME_CONFIRMED = 'confirmed';

    /** Pago denegado por el banco. La reserva sigue viva hasta que caduque: se puede reintentar. */
    public const OUTCOME_FAILED = 'failed';

    /** Vuelta SIN datos firmados: no se puede confirmar aquí, se espera la notificación S2S. */
    public const OUTCOME_VERIFYING = 'verifying';

    /**
     * Las claves de sesión. **Viven aquí y en ningún otro sitio del sistema.**
     *
     * El orden es la PRECEDENCIA cuando hay más de una pendiente, y reproduce la que tenía
     * `Purchase::mount()` por el orden en que aplicaba sus tres bloques: la última ganaba. Puede
     * pasar si una vuelta no llegó a consumirse antes de que ocurriera la siguiente.
     *
     * @var array<string, string>
     */
    private const KEYS = [
        self::OUTCOME_VERIFYING => 'purchase.verifying_code',
        self::OUTCOME_FAILED => 'purchase.failed_code',
        self::OUTCOME_CONFIRMED => 'purchase.confirmed_code',
    ];

    /** Memo por PETICIÓN del consumo, para que dos lectores no se roben el valor entre sí. */
    private const MEMO = 'sidebar.entry.consumed';

    private function __construct(
        /** @var ?self::OUTCOME_* */
        public ?string $outcome,
        public ?string $orderCode,
    ) {}

    public static function none(): self
    {
        return new self(null, null);
    }

    /** ¿Hay un desenlace esperando a que el cajón lo enseñe? */
    public function pending(): bool
    {
        return $this->outcome !== null;
    }

    // ── Escritura: los tres desenlaces ────────────────────────────────────────────────────────

    /** Reserva confirmada (vuelta OK de la pasarela, o verificación de correo). */
    public static function confirmed(string $orderCode): void
    {
        self::put(self::OUTCOME_CONFIRMED, $orderCode);
    }

    /** Pago denegado por el banco (vuelta KO firmada). */
    public static function failed(string $orderCode): void
    {
        self::put(self::OUTCOME_FAILED, $orderCode);
    }

    /** Vuelta sin datos firmados: el desenlace lo dirá la notificación server-to-server. */
    public static function verifying(string $orderCode): void
    {
        self::put(self::OUTCOME_VERIFYING, $orderCode);
    }

    // ── Lectura ───────────────────────────────────────────────────────────────────────────────

    /**
     * Mira si hay desenlace pendiente **sin consumirlo**. Es lo que necesita el layout para decidir
     * si el cajón se abre solo: quien lo enseña es el motor, en una petición que puede ser otra.
     */
    public static function peek(): self
    {
        foreach (self::KEYS as $outcome => $key) {
            $code = session($key);
            if (is_string($code) && $code !== '') {
                return new self($outcome, $code);
            }
        }

        return self::none();
    }

    /**
     * Toma el desenlace y **lo olvida** — todas las claves, no solo la ganadora: una que sobreviva
     * volvería a abrir el cajón en la página siguiente.
     *
     * Memoizado por petición: si dos lectores llaman, el segundo recibe lo mismo que el primero en
     * vez de nada. Sin esto, añadir un lector nuevo rompería al que ya estaba, en silencio.
     */
    public static function consume(): self
    {
        $request = request();

        if ($request->attributes->has(self::MEMO)) {
            /** @var self $memo */
            $memo = $request->attributes->get(self::MEMO);

            return $memo;
        }

        $entry = self::peek();
        self::clear();
        $request->attributes->set(self::MEMO, $entry);

        return $entry;
    }

    /**
     * Descarta cualquier desenlace pendiente.
     *
     * Lo usa el login cuando cambia el titular de la sesión: en un dispositivo compartido, el
     * desenlace de la compra de Alice no puede aparecerle a Bob (misma defensa que la de la cesta).
     */
    public static function clear(): void
    {
        session()->forget(array_values(self::KEYS));
    }

    private static function put(string $outcome, string $orderCode): void
    {
        session([self::KEYS[$outcome] => $orderCode]);
    }
}
