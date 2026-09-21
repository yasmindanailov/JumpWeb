<?php

namespace Tests\Feature\Architecture;

use PHPUnit\Framework\Attributes\DataProvider;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * Fase 2 — **frontera de módulo EJECUTABLE** (`docs/specs/modulos-dominio.md` §4, `DECISIONES #13`).
 *
 * El namespace plano de `app/Support` + `app/Models` esconde las llamadas cruzadas: no aparecen
 * como imports, así que las fronteras entre contextos son invisibles e inejecutables. Este test
 * las hace visibles Y las impone sobre lo que ya vive en `app/Domain`.
 *
 * Tres guardas:
 *  1. **Grafo permitido** entre módulos (`ALLOWED`): quién puede mirar a quién, más dos
 *     exenciones CON NOMBRE (`SHARED_KERNEL`, `OUTBOUND`) que son reglas, no deuda.
 *  2. **Baselines explícitas** para lo que no cumple el grafo, cada una con su naturaleza:
 *     `SEAM` (costura aceptada y documentada), `DEFERRED` (decisión de diseño pendiente),
 *     `LEGACY`/`PENDING` (código sin modularizar — ambas VACÍAS desde el paso 6).
 *     **Solo pueden ENCOGER**: una entrada que deja de usarse hace fallar el test, así que
 *     borrarla es obligatorio y nadie puede colar una flecha nueva ahí dentro.
 *  3. **Puerta de entrada** desde fuera de `app/Domain`: la capa de ENTREGA es el composition
 *     root y usa la superficie pública de cualquier módulo; el dominio sin modularizar solo
 *     entraba por `Contracts`/Platform (ya no queda ninguno).
 *
 * El escaneo usa el TOKENIZADOR de PHP, no expresiones regulares sobre el texto: los docblocks de
 * los contratos citan clases legacy a propósito y una regex las contaría como dependencias reales.
 */
class ModuleBoundariesTest extends TestCase
{
    /**
     * Grafo permitido (spec §4): todos→Platform; Content/Identity→`Contracts` de Booking/Payments;
     * Booking↔Payments solo por `SEAM`. Cada módulo puede mirarse a sí mismo siempre.
     *
     * Prefijos relativos a `App\Domain\`.
     *
     * @var array<string, list<string>>
     */
    /** Marca de «lista vacía» para los data-providers: PHPUnit trata un provider sin casos como error. */
    private const EMPTY_SENTINEL = '(vacía)';

    private const ALLOWED = [
        'Platform' => [],
        'Content' => ['Platform', 'Booking\Contracts', 'Payments\Contracts'],
        'Identity' => ['Platform', 'Booking\Contracts', 'Payments\Contracts'],
        // Booking habla con Payments por su CONTRATO (`RefundGateway`, creado en el paso 1
        // justo para esto). Lo demás entre ambos sigue exigiendo entrada explícita en `SEAM`:
        // el canal sancionado es el contrato, no un permiso general de módulo.
        'Booking' => ['Platform', 'Payments\Contracts'],
        'Payments' => ['Platform', 'Booking\Contracts'],
    ];

    /**
     * **Kernel compartido.** `User` vive en Identity pero lo referencia todo el sistema (auth,
     * autoría de acciones, `belongsTo` de pedidos y reembolsos). El spec §4 ya lo decidió así:
     * «`User` es kernel compartido: vive en Identity, las relaciones cruzadas quedan exentas».
     *
     * Es una REGLA, no una deuda: por eso es una exención con nombre y no seis entradas
     * anónimas en una baseline, que esconderían el porqué y darían la falsa idea de que hay
     * que retirarlas.
     *
     * @var list<string>
     */
    private const SHARED_KERNEL = ['App\Domain\Identity\Models\User'];

    /**
     * Canal de SALIDA del dominio: notificaciones y mailables. Un servicio de dominio que avisa
     * al cliente construye un `Notification`/`Mailable` — es el idioma del framework para emitir
     * un efecto hacia fuera, no una llamada a otro contexto.
     *
     * Invertirlo (evento de dominio + listener que notifica) es un refactor de verdad y Fase 2
     * está declarada como mudanza, no reestructuración (§2). Queda anotado como candidato para
     * cuando exista un motivo real; hoy lo usan `CustomerRegistrar`, `ManualOrderFulfiller` y
     * `RedsysReturnHandler`.
     *
     * @var list<string>
     */
    private const OUTBOUND = ['App\Notifications\\', 'App\Mail\\'];

    /**
     * Costura Booking↔Payments: el dinero. Explícita fichero a fichero — no es un permiso de
     * módulo, es una lista de flechas concretas que la revisión aceptó (spec §4 y §6).
     *
     * Incluye también las **relaciones Eloquent cruzadas**, que el spec §4 exime a propósito:
     * son costura de BASE DE DATOS (una FK), no una llamada de dominio, y romperlas costaría
     * más de lo que ordenan.
     *
     * La llamada de dinero real (`Order` → `RefundGateway`) sigue en `app/Models/Order.php` y
     * entra aquí cuando Booking mude en el paso 6.
     *
     * @var array<string, list<string>>
     */
    private const SEAM = [
        // `audit_logs.user_id` → quién hizo la acción. Relación Eloquent, costura de BD (§4).
        // Platform no depende de Identity: solo declara la FK que ya existe en el esquema.
        'Platform/Models/AuditLog.php' => ['App\Domain\Identity\Models\User'],
        // `User` mira a Booking por DOS motivos distintos (paso 4, 2026-08-12):
        //  · relaciones Eloquent `orders()` / `tickets()` → costura de BD del §4;
        //  · la SUPRESIÓN RGPD (art. 17) vacía `order_items.guest_data`/`event_data`, que es PII
        //    de TERCEROS (alergias de menores, art. 9). Eso no es una relación: es una operación
        //    que cruza contextos por naturaleza —el derecho de supresión alcanza a todos—. El
        //    diseño limpio sería que Identity emitiera «usuario anonimizado» y cada contexto
        //    borrase lo suyo; eso es un refactor de eventos, fuera del alcance de Fase 2 (§2).
        //    Anotado aquí para que la decisión exista y no se pierda.
        'Identity/Models/User.php' => ['App\Domain\Booking\Models\Order', 'App\Domain\Booking\Models\OrderItem', 'App\Domain\Booking\Models\Ticket'],
        // Relaciones Eloquent Content↔Booking: `attractions.zone_id` y `landing_service_products`
        // (`#588`). Son FKs del esquema, no llamadas de dominio; el spec §4 las exime a propósito.
        // Sobreviven a la mudanza de Booking (paso 6).
        // ⚠️ **La costura con `TicketType` se fue en `#668`** (F5 · T3): era `attractions.ticket_type_id`,
        // el complemento vinculado, y la pieza entera se retiró (`#632`·P3, **0 de 23** lo usaban). La
        // entrada sale de la línea base en el mismo cambio porque **esta lista solo ENCOGE**: una costura
        // declarada que ya no existe deja de medir nada y tapa a la siguiente que aparezca ahí.
        'Content/Models/Attraction.php' => ['App\Domain\Booking\Models\Zone'],
        'Content/Models/LandingService.php' => ['App\Domain\Booking\Models\TicketType'],

        // ─── PAYMENTS → BOOKING: la costura del DINERO (paso 5, 2026-08-12) ───
        // Es LA costura que el spec §4 anticipó, y ahora es visible en el código en vez de
        // esconderse en un namespace plano. Dos naturalezas:
        //  · FK y tipos: `payment_refunds.order_item_id` (reembolso parcial por item) y las
        //    firmas de las guardas de reembolso, que deciden sobre un item de Booking.
        //  · ORQUESTACIÓN: `RedsysReturnHandler` es, por `PAY-01`, el ÚNICO autorizado a pasar
        //    una Order a `paid`, y por `PAY-03` dispara `TicketIssuer`. Es decir: Payments
        //    conduce el ciclo de vida de la reserva. El diseño limpio sería que Payments
        //    emitiera «pago confirmado» y Booking reaccionara — pero eso es reestructurar el
        //    núcleo endurecido de dinero, exactamente lo que el paso 5 tiene PROHIBIDO hacer
        //    en el mismo commit que la mudanza (§2 + INVARIANTES §1). Queda anotado como el
        //    candidato nº1 a evento de dominio cuando haya un motivo real.
        'Payments/Models/PaymentRefund.php' => ['App\Domain\Booking\Models\OrderItem'],
        'Payments/Concerns/GuardsItemRefunds.php' => ['App\Domain\Booking\Models\OrderItem'],
        'Payments/Services/Redsys.php' => ['App\Domain\Booking\Models\Order'],
        'Payments/Services/RedsysReturnHandler.php' => ['App\Domain\Booking\Models\Order', 'App\Domain\Booking\Services\TicketIssuer'],
        // La IDA del pago (Fase 3 · paso 2), hermana exacta de las dos anteriores: abre el cobro de
        // un pedido, así que necesita leer su importe online y su moneda. **No es una flecha
        // nueva**: este código vivía duplicado en `Livewire\Tickets\Purchase` y en
        // `RetryPaymentController`, que son capa de ENTREGA y por eso quedaban exentos —la costura
        // existía igual, solo que fuera del dominio y sin que ninguna guarda la viera—. Traerla
        // aquí la hace visible y la reduce a un solo fichero.
        'Payments/Services/PaymentInitiator.php' => ['App\Domain\Booking\Models\Order'],

        // ─── BOOKING → PAYMENTS: el otro lado del dinero (paso 6, 2026-08-12) ───
        // `Order` es el punto de encuentro: los dos traits de reembolso se aplican SOBRE ÉL y
        // navega a sus `Payment`/`PaymentRefund` (`payments.payable` morph, `payment_refunds`).
        // Su llamada al gateway ya NO está aquí: va por `Payments\Contracts\RefundGateway`, que
        // es canal sancionado en `ALLOWED` — el contrato del paso 1 haciendo su trabajo.
        'Booking/Models/Order.php' => [
            'App\Domain\Payments\Concerns\GuardsItemRefunds',
            'App\Domain\Payments\Concerns\OrderRefundFlags',
            'App\Domain\Payments\Models\Payment',
            'App\Domain\Payments\Models\PaymentRefund',
        ],
        'Booking/Models/OrderItem.php' => ['App\Domain\Payments\Models\PaymentRefund'],
        'Booking/Services/ManualOrderFulfiller.php' => ['App\Domain\Payments\Models\Payment'],
        // `PaymentSettings` guarda la VENTANA DE RETENCIÓN del pedido: config de pago que la
        // oferta y el aforo necesitan para fijar `expires_at`. Candidata a contrato — es la
        // única de estas flechas que no es una relación ni un trait, sino una lectura de config.
        'Booking/Services/OrderCreator.php' => ['App\Domain\Payments\Services\PaymentSettings'],
        'Booking/Services/SlotGenerator.php' => ['App\Domain\Payments\Services\PaymentSettings'],
        'Booking/Services/SlotOffer.php' => ['App\Domain\Payments\Services\PaymentSettings'],
        // Cuarta lectura de la MISMA config: al admitir un reintento, la política extiende la
        // ventana de retención con `holdMinutes()` (Fase 3 · paso 2). Refuerza que la candidata a
        // contrato es `PaymentSettings`, no cada uno de sus lectores.
        'Booking/Services/ReservationAdmissionPolicy.php' => ['App\Domain\Payments\Services\PaymentSettings'],

        // ─── BOOKING → CONTENT (paso 6) ───
        // Relaciones Eloquent inversas (`landing_service_products`, `attractions.zone_id`)
        // + la costura que la revisión del spec ya había previsto en §6.7: la tarjeta de producto
        // del email pinta el color de la zona leyendo el tema. Muere cuando Content exponga el
        // color por contrato o cuando la tarjeta deje de decidir su propio color.
        'Booking/Models/TicketType.php' => ['App\Domain\Content\Models\LandingService'],
        'Booking/Models/Zone.php' => ['App\Domain\Content\Models\Attraction'],
        'Booking/Services/EmailProductCard.php' => ['App\Domain\Content\Services\ThemeSettings'],

        // ─── CONTENT → BOOKING: recibir una entidad NO es consultar otro módulo (paso 7) ───
        // `LandingAddonPresenter::rows(TicketType $product, …)` solo TIPA lo que le pasa la capa
        // de entrega; la regla de qué complementos se anuncian la aplica Booking en la relación
        // `addons()`. La línea que separa costura de acoplamiento, y que el paso 7 fija:
        // **recibir una entidad de otro módulo es costura de BD (§4); CONSULTAR sus datos o
        // repetir sus reglas exige contrato** — que es justo lo que se hizo con el calendario
        // (`OperatingCalendar`) y el color de zona (`ZonePalette`).
        'Content/Services/LandingAddonPresenter.php' => ['App\Domain\Booking\Models\TicketType'],
    ];

    /**
     * Baseline LEGACY: referencias desde `app/Domain` a código SIN MODULARIZAR.
     *
     * ✅ **VACÍA desde el paso 6** (2026-08-12): `app/Models` y `app/Support` ya no existen, así
     * que no queda código legacy al que apuntar. Se conserva la constante —y su guarda de
     * «solo encoge»— porque es el sitio donde volvería a aparecer si alguien reintrodujera un
     * cajón de sastre fuera de los módulos.
     *
     * @var array<string, list<string>>
     */
    private const LEGACY = [];

    /**
     * **Flechas APLAZADAS**: las que el grafo NO permite y que el paso 7 (cierre) debe resolver.
     * No son costura aceptada —por eso no están en `SEAM`— ni deuda con código legacy —por eso
     * no están en `LEGACY`—: son una DECISIÓN DE DISEÑO pendiente, con nombre y fecha.
     *
     * Todas son Content leyendo datos de Booking para PINTARLOS (horarios en la landing y en el
     * SEO, color de zona, precio de referencia del complemento). El grafo dice que Content solo
     * entra a Booking por `Contracts`. Las dos salidas, ya con todos los datos delante:
     *   (a) **contratos de lectura en Booking** — «calendario de operación», «identidad de zona»
     *       y «precio de referencia», extraídos de estas llamadas reales (como se hizo en el
     *       paso 1 con `CustomerReservations`); o
     *   (b) **reclasificar el calendario**. Medido en el paso 6: NO es viable llevarlo a
     *       Platform, porque `SpecialDate` referencia `RateType` (tarifa) y Platform no puede
     *       depender de nadie. Haría falta un módulo «recinto» nuevo — más de lo que el spec
     *       aprobó.
     * ✅ **RESUELTA y VACÍA en el paso 7** (2026-08-12) por la vía (a): nacieron
     * `Booking\Contracts\OperatingCalendar` (+ 4 DTOs) y `Booking\Contracts\ZonePalette`,
     * extraídos de esas llamadas. De regalo murieron dos reglas duplicadas que Content
     * mantenía «para no divergir» de las reservas: cuál es la temporada vigente y cuál la
     * ventana efectiva de una fecha especial.
     *
     * Se conserva la constante —y su guarda de «solo encoge»— como el sitio donde declarar la
     * próxima flecha que el grafo no permita, en vez de colarla en `SEAM` como si fuera
     * costura aceptada.
     *
     * @var array<string, list<string>>
     */
    private const DEFERRED = [];

    /** El escaneo nunca puede pasar en vacío (un glob roto lo volvería un test decorativo). */
    public function test_the_scan_actually_sees_the_domain_modules(): void
    {
        $files = $this->domainFiles();

        $this->assertNotEmpty($files, 'no se ha escaneado ningún fichero de app/Domain');
        $this->assertNotEmpty(
            array_filter($files, fn (string $f): bool => str_contains($f, '/Contracts/')),
            'no se ha escaneado ningún contrato'
        );
    }

    /** Cada carpeta de `app/Domain` debe declarar sus flechas: módulo nuevo sin grafo = fallo. */
    public function test_every_module_declares_its_allowed_arrows(): void
    {
        foreach ($this->modules() as $module) {
            $this->assertArrayHasKey(
                $module, self::ALLOWED,
                "el módulo «{$module}» no declara su grafo en ModuleBoundariesTest::ALLOWED"
            );
        }
    }

    /** La guarda principal: ninguna flecha fuera del grafo ni de las baselines. */
    public function test_domain_modules_only_depend_on_what_the_graph_allows(): void
    {
        $violations = [];

        foreach ($this->domainFiles() as $file) {
            $relative = $this->relative($file);
            $module = explode('/', $relative)[0];

            foreach ($this->appReferences($file) as $reference) {
                if ($this->isAllowed($module, $relative, $reference)) {
                    continue;
                }

                $violations[] = "  {$relative}  →  {$reference}";
            }
        }

        $this->assertSame([], $violations, "Flechas fuera del grafo de módulos:\n".implode("\n", $violations));
    }

    /**
     * Las baselines SOLO ENCOGEN: una entrada que ya no se usa debe borrarse en el mismo commit
     * que la deja de usar. Sin esta guarda la lista se convierte en un cajón de sastre que
     * legitima flechas nuevas escondidas entre las viejas.
     */
    #[DataProvider('baselineProvider')]
    public function test_baseline_entries_are_still_in_use(string $baseline, string $relative, string $reference): void
    {
        if ($relative === self::EMPTY_SENTINEL) {
            $this->assertSame([[], [], []], [self::SEAM, self::LEGACY, self::DEFERRED]);

            return;
        }

        $path = app_path('Domain/'.$relative);

        $this->assertFileExists($path, "{$baseline}: «{$relative}» ya no existe — quita su entrada");
        $this->assertContains(
            $reference,
            $this->appReferences($path),
            "{$baseline}: «{$relative}» ya no referencia «{$reference}» — quita la entrada (la baseline solo encoge)"
        );
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function baselineProvider(): iterable
    {
        foreach (['SEAM' => self::SEAM, 'LEGACY' => self::LEGACY, 'DEFERRED' => self::DEFERRED] as $name => $entries) {
            foreach ($entries as $relative => $references) {
                foreach ($references as $reference) {
                    yield "{$name}: {$relative} → {$reference}" => [$name, $relative, $reference];
                }
            }
        }

        // Con las dos baselines vacías el proveedor no puede quedarse sin casos (PHPUnit lo
        // trataría como error): un caso trivial mantiene el test verde y honesto.
        if (self::SEAM === [] && self::LEGACY === [] && self::DEFERRED === []) {
            yield 'baselines vacías' => ['-', self::EMPTY_SENTINEL, self::EMPTY_SENTINEL];
        }
    }

    /**
     * Capa de ENTREGA: los directorios que componen la aplicación a partir de varios contextos.
     * Es el *composition root* del sistema — por definición toca varios módulos y el spec §4 la
     * deja quieta en Fase 2 («solo actualizan imports»).
     *
     * @var list<string>
     */
    private const DELIVERY = [
        'Console', 'Exceptions', 'Filament', 'Http', 'Livewire', 'Mail', 'Notifications', 'Providers',
    ];

    /**
     * Código de DOMINIO aún sin modularizar que se salta la puerta de entrada.
     *
     * ✅ **VACÍA desde el paso 6** (2026-08-12): ya no hay dominio fuera de `app/Domain`
     * —`app/Models` y `app/Support` dejaron de existir—, así que no queda nadie que pueda
     * saltársela. Sus 11 entradas de Booking→Payments no se «perdonaron»: pasaron a `SEAM`
     * como costura entre dos módulos, que es lo que son desde que Booking es un módulo.
     *
     * @var array<string, list<string>>
     */
    private const PENDING = [];

    /**
     * Puerta de entrada a los módulos desde fuera de `app/Domain`.
     *
     * La regla se afinó en el paso 3 CON DATOS (antes decía «solo Contracts», que era cierto
     * cuando los módulos solo tenían contratos). Al mudar Content aparecieron ~40 referencias
     * desde Filament, controladores y providers a sus modelos Y a sus servicios: es la capa de
     * entrega haciendo su trabajo. Prohibírselo habría significado reescribir el panel entero,
     * que es justo lo que Fase 2 declara fuera de alcance. Así que:
     *
     *  - **capa de entrega** (`self::DELIVERY`) → puede usar la superficie pública de cualquier
     *    módulo: es quien compone los contextos;
     *  - **código de dominio aún sin mudar** (`app/Support`, `app/Models`) → solo `Contracts` y
     *    Platform. Cualquier otra cosa es acoplamiento entre contextos y necesita entrada
     *    explícita en `PENDING`, que **solo puede encoger** (se vacía en el paso 7).
     *
     * **Platform queda EXENTO entero**, no por comodidad: el grafo del spec §4 dice
     * «todos→Platform». Es la base compartida (settings, audit, formateo, i18n), no una costura
     * sustituible; meterle una interfaz a `Money::format()` sería ceremonia sin lector.
     */
    public function test_unmodularised_domain_code_enters_modules_only_through_contracts(): void
    {
        $violations = [];

        foreach ($this->phpFiles(app_path()) as $file) {
            if (str_starts_with($file, app_path('Domain'))) {
                continue;
            }

            $relative = mb_substr($file, mb_strlen(app_path()) + 1);
            if (in_array(explode('/', $relative)[0], self::DELIVERY, true)) {
                continue;   // composition root
            }

            foreach ($this->appReferences($file) as $reference) {
                if (! str_starts_with($reference, 'App\\Domain\\')) {
                    continue;
                }
                // Platform entero: base compartida (spec §4, «todos→Platform»).
                if (str_starts_with($reference, 'App\\Domain\\Platform\\')) {
                    continue;
                }
                // `User`: kernel compartido (spec §4). Regla, no deuda — ver SHARED_KERNEL.
                if (in_array($reference, self::SHARED_KERNEL, true)) {
                    continue;
                }
                // App\Domain\<Módulo>\Contracts\<Símbolo>
                if (preg_match('/^App\\\\Domain\\\\[A-Za-z]+\\\\Contracts\\\\/', $reference) === 1) {
                    continue;
                }
                if (in_array($reference, self::PENDING[$relative] ?? [], true)) {
                    continue;
                }

                $violations[] = "  app/{$relative}  →  {$reference}";
            }
        }

        $this->assertSame(
            [], $violations,
            "El código de dominio aún sin mudar solo puede entrar a un módulo por sus Contracts\n"
            ."(o por Platform). Si es acoplamiento real, decláralo en PENDING con su porqué:\n"
            .implode("\n", $violations)
        );
    }

    /** `PENDING` también solo encoge: una entrada que ya no se usa hay que borrarla. */
    #[DataProvider('pendingProvider')]
    public function test_pending_entries_are_still_in_use(string $relative, string $reference): void
    {
        if ($relative === self::EMPTY_SENTINEL) {
            $this->assertSame([], self::PENDING);

            return;
        }

        $path = app_path($relative);

        $this->assertFileExists($path, "PENDING: «{$relative}» ya no existe — quita su entrada");
        $this->assertContains(
            $reference,
            $this->appReferences($path),
            "PENDING: «{$relative}» ya no referencia «{$reference}» — quita la entrada"
        );
    }

    /** @return iterable<string, array{string, string}> */
    public static function pendingProvider(): iterable
    {
        foreach (self::PENDING as $relative => $references) {
            foreach ($references as $reference) {
                yield "{$relative} → {$reference}" => [$relative, $reference];
            }
        }

        if (self::PENDING === []) {
            yield 'PENDING vacía' => [self::EMPTY_SENTINEL, self::EMPTY_SENTINEL];
        }
    }

    private function isAllowed(string $module, string $relative, string $reference): bool
    {
        if (in_array($reference, self::SEAM[$relative] ?? [], true)) {
            return true;
        }
        if (in_array($reference, self::LEGACY[$relative] ?? [], true)) {
            return true;
        }
        if (in_array($reference, self::DEFERRED[$relative] ?? [], true)) {
            return true;
        }
        if (in_array($reference, self::SHARED_KERNEL, true)) {
            return true;
        }
        foreach (self::OUTBOUND as $prefix) {
            if (str_starts_with($reference, $prefix)) {
                return true;
            }
        }
        if (! str_starts_with($reference, 'App\\Domain\\')) {
            return false;   // legacy sin baseline explícita
        }

        $target = mb_substr($reference, mb_strlen('App\\Domain\\'));

        // El propio módulo, siempre.
        if (str_starts_with($target, $module.'\\') || $target === $module) {
            return true;
        }

        foreach (self::ALLOWED[$module] ?? [] as $prefix) {
            if (str_starts_with($target, $prefix.'\\')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Símbolos `App\…` REALMENTE referenciados por el fichero (imports y FQCN en línea),
     * ignorando comentarios y docblocks. Se excluye la propia declaración `namespace`.
     *
     * @return list<string>
     */
    private function appReferences(string $file): array
    {
        $tokens = token_get_all((string) file_get_contents($file));
        $references = [];
        $skipNext = false;

        foreach ($tokens as $token) {
            if (! is_array($token)) {
                continue;
            }

            if ($token[0] === T_NAMESPACE) {
                $skipNext = true;

                continue;
            }

            if ($token[0] !== T_NAME_QUALIFIED && $token[0] !== T_NAME_FULLY_QUALIFIED) {
                continue;
            }

            if ($skipNext) {
                $skipNext = false;   // el nombre que sigue a `namespace` no es una dependencia

                continue;
            }

            $name = ltrim($token[1], '\\');
            if (str_starts_with($name, 'App\\')) {
                $references[$name] = true;
            }
        }

        return array_keys($references);
    }

    /** @return list<string> */
    private function domainFiles(): array
    {
        return is_dir(app_path('Domain')) ? $this->phpFiles(app_path('Domain')) : [];
    }

    /** @return list<string> */
    private function modules(): array
    {
        $modules = [];
        foreach ($this->domainFiles() as $file) {
            $modules[explode('/', $this->relative($file))[0]] = true;
        }

        return array_keys($modules);
    }

    /** Ruta relativa a `app/Domain`, con `/` — la clave de las baselines. */
    private function relative(string $file): string
    {
        return mb_substr($file, mb_strlen(app_path('Domain')) + 1);
    }

    /** @return list<string> */
    private function phpFiles(string $directory): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }
}
