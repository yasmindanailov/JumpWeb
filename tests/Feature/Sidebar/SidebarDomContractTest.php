<?php

namespace Tests\Feature\Sidebar;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\WaiverSignature;
use App\Domain\Identity\Services\DependentRegistry;
use App\Domain\Identity\Services\LegalDocumentPublisher;
use App\Domain\Identity\Services\WaiverSignatureRequest;
use App\Domain\Identity\Services\WaiverSigner;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Services\RedsysResponseCode;
use App\Domain\Platform\Models\Setting;
use App\Http\Sidebar\AccountContextSeed;
use App\Http\Sidebar\RegistrationLink;
use App\Http\Sidebar\SidebarEntry;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Fase 4 · paso 4.2 — **el contrato visual es el ÁRBOL, y aquí se compara**
 * (`docs/specs/sidebar-spa.md` §4.2 y §6, criterio CE-2).
 *
 * La v1 del spec daba por hecho que el contrato eran los nombres de clase y que bastaría con
 * extraerlos del código. Se midió y es **falso**: de los 292 selectores que estilan el cajón, **90
 * no se satisfacen emitiendo la clase correcta** —89 son estructurales (descendencia, `+`,
 * `:last-of-type`) y 37 dependen del TIPO DE ELEMENTO sin clase en el nodo, como `.catalog__go svg`
 * o `.purchase__total strong`—. Un `<div>` donde había un `<button>` pierde el estilo con el
 * contrato de clases cumplido al 100%.
 *
 * Por eso esto compara **árboles renderizados**, no código fuente: Livewire por el lado de PHP y Vue
 * con `@vue/server-renderer` por el lado de Node. Sin navegador headless — Vue ya trae su
 * renderizador de servidor, y una dependencia de navegador en el gate es lenta y tiene su propio
 * modo de fallo.
 *
 * ⚠️ **Lo que se normaliza y lo que NO**, porque ahí está el valor del test:
 *  - **fuera** los atributos de cada motor (`wire:*`, `x-*`, `@*`, `:*`, `data-v-*`, `id` de
 *    Livewire): son andamiaje, no contrato, y compararlos haría el test imposible de pasar;
 *  - **dentro** la etiqueta, las clases, el anidamiento y los atributos de accesibilidad
 *    (`role`, `aria-*`, `disabled`, `type`) — que es exactamente lo que el CSS y el lector de
 *    pantalla miran;
 *  - **no se desciende dentro de un `<svg>`**: su interior es geometría, no estructura estilable, y
 *    exigir que dos motores emitan los mismos `<path>` convertiría un contrato visual en una copia
 *    literal de los iconos. Que HAYA un `<svg>` donde toca sí se comprueba, porque de eso sí
 *    dependen selectores como `.catalog__go svg`.
 */
class SidebarDomContractTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zone;

    private int $rateId;

    /**
     * ⚠️ **El reloj se CONGELA, y sin esto el manifiesto de 4.7·1 caduca al día siguiente.**
     *
     * Medido el 2026-08-15, un día después de congelarlo: los dos casos del calendario cayeron contra
     * el manifiesto —no entre motores— porque la rejilla del mes depende de HOY (cada día que pasa
     * añade una casilla deshabilitada, y cada mes cambia la forma entera). Una foto de un árbol que se
     * mueve solo no es una red: es una alarma diaria que el siguiente agente aprende a ignorar.
     *
     * La fecha elegida no es arbitraria: **miércoles 12 de agosto de 2026**, mes que empieza en SÁBADO
     * —así la rejilla conserva sus casillas de relleno inicial, que son estructura— y día 12 para que
     * `slotsForNextDays(5)` (13–17) no cruce a septiembre. Cambiarla obliga a regenerar el manifiesto.
     */
    private const FROZEN_NOW = '2026-08-12 09:00:00';

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(self::FROZEN_NOW);

        $this->rateId = (int) RateType::create([
            'key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0,
        ])->id;

        $this->zone = Zone::create([
            'slug' => 'jump', 'name' => ['es' => 'Jump'], 'accent' => 'jump', 'color' => '#FF5B22',
            'position' => 1, 'is_active' => true,
        ]);
    }

    private function product(string $name, string $type, ?int $priceCents = 990): TicketType
    {
        $product = TicketType::create([
            'name' => ['es' => $name], 'type' => $type, 'zone_id' => $this->zone->id,
            'duration_min' => 60, 'min_qty' => $type === TicketType::TYPE_PACK ? 6 : 1,
            'max_qty' => $type === TicketType::TYPE_PACK ? 20 : null,
            'seats_per_unit' => 1, 'is_sellable' => true, 'is_active' => true,
            'position' => (int) TicketType::max('position') + 1,
        ]);

        if ($priceCents !== null) {
            $product->prices()->create(['rate_type_id' => $this->rateId, 'amount_cents' => $priceCents]);
        }

        return $product;
    }

    // ── El paso 1: catálogo ───────────────────────────────────────────────────────────────────

    public function test_the_catalog_step_emits_the_same_tree_in_both_engines(): void
    {
        $this->product('Entrada 1h', TicketType::TYPE_ENTRY, 990);
        $this->product('Cumpleaños', TicketType::TYPE_PACK, 5000);

        $vue = $this->vueTree(1, [], api: $this->catalogApiPayload());

        $this->assertTree(__FUNCTION__, $vue,
            "El árbol del catálogo DIFIERE entre los dos motores.\n".
            'El contrato visual es el árbol (§4.2): 90 de 292 selectores del cajón son estructurales '.
            'o dependen del tipo de elemento, así que esta diferencia es estilo perdido aunque las '.
            "clases coincidan.\n\n".
            ''
        );
    }

    // ── El paso 2: calendario ─────────────────────────────────────────────────────────────────

    /**
     * ⚠️ El contenido del paso 2 son CUATRO nodos hermanos —título, calendario, leyenda y el aviso de
     * «sin fechas»—, no un contenedor. Se comparan todos: dejar fuera los hermanos es como se cuela
     * una leyenda que no se pinta o un título con otra etiqueta.
     */
    public function test_the_date_step_emits_the_same_tree_in_both_engines(): void
    {
        $product = $this->product('Entrada 1h', TicketType::TYPE_ENTRY, 990);
        $this->slotsForNextDays($product, 5);

        $vue = $this->vueTree(2, [], 'wiz__title', withSiblings: true,
            api: $this->dateApiPayload($product->id), state: $this->clientState());

        $this->assertTree(__FUNCTION__, $vue,
            "El árbol del calendario DIFIERE entre los dos motores.\n\n"
        );
    }

    /**
     * ⚠️ **El mismo calendario CON el día elegido, y el caso existe por un fallo de red medido.**
     *
     * `aria-current="date"` entró en la lista de atributos de contrato al cerrar el último ítem de
     * accesibilidad de §6, y se comprobó por mutación que **no servía de nada**: el caso de arriba no
     * elige día, así que ningún motor emite el atributo y borrarlo del componente pasaba en verde. Un
     * atributo solo está cubierto por el caso que lo hace aparecer.
     *
     * Es la misma lección que la acotación del selector de cantidad en 4.2: hay que llevar el estado a
     * donde el atributo existe.
     */
    public function test_the_selected_day_is_marked_the_same_in_both_engines(): void
    {
        $product = $this->product('Entrada 1h', TicketType::TYPE_ENTRY, 990);
        $this->slotsForNextDays($product, 5);
        $vue = $this->vueTree(2, [], 'wiz__title', withSiblings: true,
            api: $this->dateApiPayload($product->id), state: $this->clientState(now()->addDay()->toDateString()));

        $this->assertTree(__FUNCTION__, $vue,
            "El día elegido NO se marca igual en los dos motores.\n".
            '⚠️ `aria-current` es lo único que le dice a un lector de pantalla cuál está seleccionado: '.
            "la clase `is-selected` no se lee.\n\n"
        );
    }

    /**
     * ⚠️ **El CALENDARIO del paso 2, desplegado — y este caso no es un extra: sin él el calendario
     * entero deja de estar cubierto** (`#239`).
     *
     * Desde el rediseño el paso abre con la TIRA y el calendario nace plegado, así que los dos casos
     * de arriba ya **no emiten ni una celda de la rejilla**. Si nadie lo abre, `.cal__grid`,
     * `.cal__day`, las flechas del mes y la leyenda salen del gate sin que nada avise — y son
     * exactamente los selectores estructurales que este test existe para vigilar (un `<span>` donde
     * había un `<button>` pierde el estilo con el contrato de clases cumplido al 100 %).
     *
     * Es la misma lección que el `aria-current` de aquí arriba, aplicada a un bloque entero: **hay que
     * llevar el estado a donde el árbol existe.**
     */
    public function test_the_month_calendar_is_the_same_tree_when_unfolded(): void
    {
        $product = $this->product('Entrada 1h', TicketType::TYPE_ENTRY, 990);
        $this->slotsForNextDays($product, 5);

        $vue = $this->vueTree(2, [], 'wiz__title', withSiblings: true,
            api: $this->dateApiPayload($product->id),
            state: $this->clientState(calendarOpen: true));

        $this->assertTree(__FUNCTION__, $vue,
            "El árbol del calendario desplegado ha CAMBIADO.\n".
            "⚠️ Una celda no reservable es un `<span>` y una reservable un `<button>`: el CSS los\n".
            "distingue por el TIPO DE ELEMENTO, no por la clase.\n\n"
        );
    }

    // ── El paso 3: hora, cantidad y complementos ──────────────────────────────────────────────

    /**
     * El paso más denso del embudo: chips de hora, caja de cantidad, campos del pack y complementos.
     *
     * Se prueba con un PACK y con complementos de las tres formas que existen —grupo excluyente,
     * incluido y dependiente— porque cada una emite un árbol distinto: un dependiente bloqueado
     * enseña un stepper INERTE, un per-invitado un interruptor, y el resto un stepper normal. Un
     * caso con un solo complemento suelto no probaría ninguna de las tres ramas.
     */
    public function test_the_time_step_emits_the_same_tree_in_both_engines(): void
    {
        $pack = $this->product('Cumpleaños', TicketType::TYPE_PACK, 5000);
        $pack->update(['event_fields' => [
            ['key' => 'celebrant', 'type' => 'text', 'required' => true, 'stage' => 'booking', 'label' => ['es' => 'Homenajeado']],
            ['key' => 'notes', 'type' => 'textarea', 'required' => false, 'stage' => 'booking', 'label' => ['es' => 'Notas']],
        ]]);

        $this->addon($pack, 'Hamburguesa', 800, ['choice_group' => 'menu']);
        $this->addon($pack, 'Pizza', 900, ['choice_group' => 'menu']);
        $tarta = $this->addon($pack, 'Tarta', 1000, ['is_included' => true, 'included_quantity' => 1, 'allow_extra' => true]);
        $this->addon($pack, 'Velas', 200, ['requires_addon_id' => $tarta->id]);
        $this->addon($pack, 'Comida', 700, ['quantity_mode' => 'per_guest']);

        $this->slotsForNextDays($pack, 3);

        $date = now()->addDay()->toDateString();

        $vue = $this->vueTree(3, [], 'wiz__title', withSiblings: true,
            api: $this->timeApiPayload($pack->id, $date, '10:00:00', (int) $pack->min_qty),
            state: $this->clientState($date, '10:00:00', (int) $pack->min_qty));

        $this->assertTree(__FUNCTION__, $vue,
            "El árbol del paso de hora DIFIERE entre los dos motores.\n\n"
        );
    }

    /**
     * ⚠️ **Una hora casi llena LO DICE, y este caso es el único que hace aparecer ese nodo** (`#239`).
     *
     * `[DECIDIDO owner]`: sin número — dice que queda poco, no cuánto. El umbral lo publica
     * `GET /config` (`available <= low_availability_max`, por defecto 8) y lo edita el operador.
     *
     * ⚠️ **La franja se siembra con capacidad 6 a propósito.** Con las 20 de siempre `available` vale
     * 20, el rótulo no se pinta y el caso saldría verde sin cubrir nada: es la trampa que este fichero
     * ya documenta con `aria-current`. Y el aforo se mira sobre `available`, NO sobre `max_quantity`
     * —en un pack no son el mismo número—, así que se prueba con una ENTRADA, donde el número que se
     * lee es inequívocamente el de la franja.
     */
    public function test_a_nearly_full_hour_says_so(): void
    {
        $entry = $this->product('Entrada 1h', TicketType::TYPE_ENTRY, 990);
        $this->slotsForNextDays($entry, 3, capacity: 6);

        $date = now()->addDay()->toDateString();

        $vue = $this->vueTree(3, [], 'timestrip', withSiblings: false,
            api: $this->timeApiPayload($entry->id, $date, '10:00:00', 1),
            state: $this->clientState($date, '10:00:00', 1));

        $this->assertStringContainsString(
            'purchase__chip-full', $vue,
            "El caso no está haciendo aparecer el rótulo de «casi llena», así que no cubre nada.\n".
            'Comprueba la capacidad de la franja contra `low_availability_max` de `GET /config`.'
        );

        $this->assertTree(__FUNCTION__, $vue,
            "El árbol de la tira de horas con el aviso de «casi llena» ha CAMBIADO.\n\n"
        );
    }

    /**
     * ❗❗ **UNA HORA COMPLETA SE ENSEÑA DESHABILITADA, y hasta `#277` no se enseñaba de ninguna forma.**
     *
     * `SlotOffer` manda las franjas llenas con `sellable: false` **a propósito** —su docblock dice
     * «se muestran deshabilitadas, no se ocultan»— y el cajón **no leía el campo**: la pintaba como
     * un chip normal y clicable, y sólo al pulsarlo aparecía «agotado» en el contador de cantidad.
     *
     * ⚠️⚠️ **Y el contrato de árbol no lo cazaba, porque NINGUNA fixture tenía una franja llena.**
     * `disabled` es atributo de contrato para el normalizador, así que el nodo se habría comparado…
     * si alguna vez hubiera aparecido. *Un atributo solo está cubierto por el caso que lo hace
     * aparecer* — la misma lección que el `aria-current` del día elegido y que el umbral de `/config`.
     */
    public function test_a_full_hour_is_offered_disabled(): void
    {
        $entry = $this->product('Entrada 1h', TicketType::TYPE_ENTRY, 990);
        // Sin una sola plaza online: `SlotOffer` la ofrece igual, marcada como no vendible.
        $this->slotsForNextDays($entry, 3, capacity: 0);

        $date = now()->addDay()->toDateString();

        $vue = $this->vueTree(3, [], 'timestrip', withSiblings: false,
            api: $this->timeApiPayload($entry->id, $date, '10:00:00', 1),
            state: $this->clientState($date, '10:00:00', 1));

        $this->assertStringContainsString(
            'disabled', $vue,
            "El caso no está haciendo aparecer una hora COMPLETA, así que no cubre nada.\n".
            'Comprueba que `SlotOffer` sigue ofreciendo las llenas con `sellable: false` en vez de ocultarlas.'
        );

        $this->assertTree(__FUNCTION__, $vue,
            "El árbol de la tira de horas con una franja COMPLETA ha CAMBIADO.\n\n"
        );
    }

    /**
     * ⚠️ **El selector se acota con `max_quantity`, y esto es lo que lo comprueba.**
     *
     * El caso anterior no bastaba: con la cantidad lejos de sus topes, los dos botones salen
     * habilitados en cualquier motor y el diff pasa aunque la regla de acotación sea otra —se
     * verificó por mutación que cambiar el techo NO lo ponía en rojo—. Aquí la cantidad se lleva a
     * los dos extremos, que es donde el `disabled` deja de ser el mismo si alguien confunde
     * `available` con `max_quantity` (`AFORO-02`): en un pack **no son el mismo número**.
     */
    public function test_the_quantity_stepper_is_bounded_the_same_in_both_engines(): void
    {
        $pack = $this->product('Cumpleaños', TicketType::TYPE_PACK, 5000);
        $this->slotsForNextDays($pack, 3);

        $date = now()->addDay()->toDateString();

        // Los topes salen de la FICHA del producto, no del view-model del motor que este fichero
        // compara (4.7·2b·3, paso 1). Medido antes de sustituirlos: el componente devolvía exactamente
        // `min_qty` y `max_qty` del pack (6 y 20).
        // ⚠️ Estos DOS sí son estructurales, a diferencia de otras cantidades: el selector deshabilita
        // sus botones en los extremos y `disabled` es atributo de CONTRATO para el normalizador.
        // Verificado mutando: mover el suelo o el techo pone el caso en rojo. (La cantidad del paso de
        // HORA, en cambio, no muerde: ahí es texto. Y el día del paso de FECHA sí, porque
        // `aria-current="date"` marca la celda del calendario. No todos los valores del mismo tipo
        // pesan igual: hay que mutarlos uno a uno en vez de deducirlo.)
        $min = (int) $pack->min_qty;
        $max = (int) $pack->max_qty;

        $this->assertGreaterThan($min, $max, 'sin margen entre mínimo y máximo el caso no probaría nada');

        foreach ([$min, $max] as $quantity) {

            $vue = $this->vueTree(3, [], 'qtybox',
                api: $this->timeApiPayload($pack->id, $date, '10:00:00', $quantity),
                state: $this->clientState($date, '10:00:00', $quantity));

            $this->assertTree(__FUNCTION__, $vue,
                "Con cantidad {$quantity} (mínimo {$min}, máximo {$max}) el selector NO se acota igual.\n".
                '⚠️ En un pack `available` y `max_quantity` no son el mismo número, y construir el '.
                "selector sobre el primero deja pedir invitados que el checkout rechaza.\n\n".
                ''
            );
        }
    }

    /**
     * ⚠️ **Y con una ENTRADA y menores a cargo (Fase 6 · tanda 4, `DECISIONES #202`).** Los dos casos
     * anteriores usan un PACK, donde el selector «¿Para quién son estas entradas?» no existe —un pack
     * ya pide a sus invitados—, y el caso de la entrada que ya había ancla en `bk-progress`: el bloque
     * de casillas del paso 3 quedaba sin contrato de árbol. Dos menores a propósito: uno con la exención
     * VIGENTE, marcado; otro sin firmar, que se pinta DESHABILITADO con su motivo — son dos ramas.
     */
    public function test_the_time_step_with_dependents_emits_the_same_tree(): void
    {
        $entry = $this->product('Entrada 1h', TicketType::TYPE_ENTRY, 990);
        $this->slotsForNextDays($entry, 3);
        $dependents = $this->holderWithDependents();

        $date = now()->addDay()->toDateString();

        $vue = $this->vueTree(3, [], 'wiz__title', withSiblings: true,
            api: [...$this->timeApiPayload($entry->id, $date, '10:00:00', 2), 'dependents' => $dependents],
            state: [...$this->clientState($date, '10:00:00', 2), 'dependentIds' => [$dependents['data'][0]['id']]]);

        $this->assertTree(__FUNCTION__, $vue,
            "El paso de hora con menores a cargo DIFIERE del árbol congelado.\n\n"
        );
    }

    /**
     * Un titular con DOS menores: Lucas con la exención vigente (asignable) y Vera sin firmar.
     * Modo interno, porque fuera de él no hay firma que comprobar y la rama deshabilitada no existiría.
     *
     * @return array<string, mixed> la respuesta REAL de `GET /me/dependents`
     */
    private function holderWithDependents(): array
    {
        Setting::updateOrCreate(['key' => 'waiver.mode'], ['value' => 'interno', 'group' => 'waiver']);
        Setting::flushMemo();

        $user = User::factory()->create(['email_verified_at' => now()]);
        $registry = app(DependentRegistry::class);
        $lucas = $registry->add($user, 'Lucas', '2017-03-12');
        $registry->add($user, 'Vera', '2019-11-02');

        $version = app(LegalDocumentPublisher::class)->publish('waiver', [
            'es' => ['title' => 'Exención', 'body' => [['h' => 'Riesgo', 'p' => 'Saltar implica riesgos.']]],
        ])->first();
        app(WaiverSigner::class)->sign($user, $version, new WaiverSignatureRequest(
            channel: WaiverSignature::CHANNEL_WEB, ip: '10.0.0.7', userAgent: 'test',
            subjectType: WaiverSignature::SUBJECT_DEPENDENT, subjectId: (int) $lucas->getKey(),
        ));

        return $this->actingAs($user)->getJson('/api/v1/me/dependents')->assertOk()->json();
    }

    // ── El paso 4: la cesta ───────────────────────────────────────────────────────────────────

    /**
     * El carrito con una ENTRADA asignada a un menor y el aviso de la puerta 2 (Fase 6 · tanda 4):
     * el selector por línea —ya en la cesta y persistida— y el bloque de aviso que comparte clase con
     * «carrito listo». Ninguno de los tres casos del paso 4 los pintaba.
     */
    public function test_the_cart_step_with_dependents_emits_the_same_tree(): void
    {
        $entry = $this->product('Entrada 1h', TicketType::TYPE_ENTRY, 990);
        $this->slotsForNextDays($entry, 3);
        $dependents = $this->holderWithDependents();

        $api = $this->cartApiPayload([[
            'ticket_type_id' => $entry->id, 'date' => now()->addDay()->toDateString(), 'time' => '10:00:00',
            'qty' => 2, 'event_data' => [], 'addons' => [],
        ]]);
        $api['cart'][0]['dependent_ids'] = [$dependents['data'][0]['id']];
        $api['dependents'] = $dependents;

        // ⚠️⚠️ **Vuelve a anclar en `wiz__title` en `#555`, y NO es una relajación.** Estuvo anclado en
        // `bk-back` desde `#210` porque el paso 4 tenía «Volver» propio, el botón iba ANTES del título
        // y `treeOf()` solo recorre hermanos SIGUIENTES: anclar en el título habría dejado el botón
        // fuera del contrato. Hoy ese botón **ya no está en el paso** —se mudó a la banda, que lo
        // trae para las cinco pantallas—, así que el título vuelve a ser el primer nodo y anclar ahí
        // cubre el paso ENTERO. El «Volver» no queda sin vigilar: lo fijan los dos casos de la banda.
        // ▶ *Un ancla se elige por dónde empieza el sujeto, y el sujeto cambió.*
        $vue = $this->vueTree(4, [], 'wiz__title', withSiblings: true, api: $api,
            state: [...$this->clientState(), 'cartNotice' => 'assign']);

        $this->assertTree(__FUNCTION__, $vue,
            "El carrito con menores a cargo DIFIERE del árbol congelado.\n\n"
        );
    }

    /**
     * La cesta con TODO lo que una línea puede llevar, porque cada rama solo aparece con sus datos:
     * las respuestas del pack (`cart__event`), los complementos con su etiqueta de «incluido»
     * (`cart__addons` + `cart__addon-incl`) y la nota de señal (`cart__deposit`). Un pedido simple de
     * una entrada no emite ninguna de las cuatro, así que un caso así pasaría sin mirar nada.
     */
    public function test_the_cart_step_emits_the_same_tree_in_both_engines(): void
    {
        $this->setUpFullCart();

        $vue = $this->vueTree(4, [], 'wiz__title', withSiblings: true,
            api: $this->cartApiPayload($this->fullCartItems()), state: $this->clientState());

        $this->assertTree(__FUNCTION__, $vue,
            "El árbol del carrito DIFIERE entre los dos motores.\n\n"
        );
    }

    /**
     * ⚠️ **La cesta vacía en el paso 4 es alcanzable y no es un caso teórico**: `removeLine()` y
     * `mount()` miran `$this->cart`, pero el recuento sale del PRESUPUESTO, así que un producto
     * retirado de la venta con el cajón abierto deja la cesta «no vacía» y el contador a cero. Ahí el
     * paso pinta su aviso y el pie desaparece: la pantalla queda sin CTA. Era la conducta de los dos
     * motores y se transcribió igual; ▶ **desde `#210` (2026-08-28) SÍ tiene salida**: el «Volver»
     * del paso 4 se pinta también con la cesta vacía, y este árbol lo fija.
     */
    public function test_the_empty_cart_step_emits_the_same_tree_in_both_engines(): void
    {

        $vue = $this->vueTree(4, [], 'wiz__title', withSiblings: true,
            api: $this->cartApiPayload([]), state: $this->clientState());

        $this->assertTree(__FUNCTION__, $vue,
            "El árbol del carrito VACÍO DIFIERE entre los dos motores.\n\n"
        );
    }

    /**
     * ⚠️ **Una línea SIN fecha, que es la trampa que el caso anterior no cubría.**
     *
     * `.cart__lines` se emite SIEMPRE: el condicional del Blade está DENTRO del `<div>`, no fuera. Lo
     * natural al transcribir a Vue es poner el `v-if` en el div, y eso deja un nodo de menos y se
     * lleva la separación que da la cabecera. **Se verificó por mutación que el caso de la cesta
     * completa NO lo detectaba**, porque todas sus líneas tienen fecha.
     *
     * ⚠️ **[CORREGIDO 2026-08-21, medido] El presupuesto NO la tarifica: la RECHAZA.** Este docblock
     * decía lo contrario («la tarifica igual —medido: subtotal correcto—») y era falso:
     * `POST /api/v1/orders/quote` con `date: ''` devuelve **422** con
     * `items.0.date: El campo items.0.date es obligatorio`. Sondeado contra la API real antes de
     * reescribir esto. Quien creyera la versión vieja intentaría alimentar el caso por la API y
     * perdería el tiempo: **no hay camino real que alimentar**, y por eso este es el único caso del
     * paso 4 cuyo fixture se declara a mano — lo que comprueba es que el marcado no se desmorona si
     * un estado así llegara (cesta antigua o manipulada que el saneador dejara pasar).
     */
    public function test_a_cart_line_without_a_date_still_emits_its_row_container(): void
    {
        $product = $this->product('Entrada 1h', TicketType::TYPE_ENTRY, 990);
        $this->slotsForNextDays($product, 3);

        // ⚠️ **El ÚNICO caso del paso 4 que sigue con props cocinadas, y a propósito** (4.7·2b·2·B):
        // su fixture es una línea con `date: ''`, que `POST /orders/quote` RECHAZA con 422 y que el
        // saneador del cajón **descarta** antes de guardarla (`cart.js` espeja `CartPayload`). O sea:
        // el estado que este caso ejerce **no es alcanzable por el cliente**, así que no hay camino
        // real que alimentar — lo que se comprueba aquí es que el marcado no se desmorona si llegara.
        $vue = $this->vueTree(4, $this->datelessCartProps($product), 'cart__item');

        $this->assertTree(__FUNCTION__, $vue,
            "La línea SIN fecha DIFIERE entre los dos motores.\n".
            'El contenedor `.cart__lines` se emite siempre, aunque quede vacío: el condicional del '.
            "Blade está DENTRO del `<div>`.\n\n"
        );
    }

    // ── El paso 5: la identificación ──────────────────────────────────────────────────────────

    /**
     * ⚠️ **El estado se alcanza PULSANDO la pestaña, y no es ceremonia: es lo único que hace que
     * Livewire renderice el formulario.**
     *
     * Medido: `->set('authMode', 'login')` sobre un componente recién montado deja el hijo como
     * `<div wire:id=… wire:name="auth.login"></div>` **VACÍO** —los componentes hijos se hidratan en
     * una petición posterior—, así que el árbol del paso 5 tendría **9 nodos** en vez de 31 y este
     * diff compararía el armazón contra el armazón. Un motor SPA que no emitiera el formulario
     * pasaría en verde. Llegando por `setAuthMode` el hijo se renderiza entero.
     *
     * `embedded` es parte del caso: quita el enlace de «¿olvidaste tu contraseña?» y el pie de «¿no
     * tienes cuenta?», que dentro del cajón llevarían fuera de la compra.
     */
    public function test_the_identify_step_emits_the_same_tree_in_both_engines(): void
    {

        $vue = $this->vueTree(5, $this->identifyProps(), 'wiz__title', withSiblings: true);

        $this->assertTree(__FUNCTION__, $vue,
            "El árbol de la identificación DIFIERE entre los dos motores.\n".
            'Este paso no tiene banda de progreso, así que su «Volver» es propio; y el formulario que '.
            "cuelga de las pestañas es el login embebido.\n\n".
            ''
        );
    }

    /**
     * ⚠️ **El formulario más largo del cajón, y el que más nodos invisibles tiene.**
     *
     * Tres de ellos no se ven nunca y el diff es lo ÚNICO que los vigila: el **honeypot** (`.hp`, que
     * el CSS oculta y que es el señuelo del servidor), la **fila** `.form__row` que agrupa email y
     * teléfono, y el `<small class="form__hint">` de la contraseña —que es un `<small>`, no un `<span>`,
     * y el tipo de elemento es contrato—. Un motor sin honeypot deja al servidor sin su defensa y no se
     * nota mirando la pantalla.
     */
    public function test_the_register_form_emits_the_same_tree_in_both_engines(): void
    {

        $vue = $this->vueTree(5, $this->identifyProps('register'), 'wiz__title', withSiblings: true);

        $this->assertTree(__FUNCTION__, $vue,
            "El árbol del ALTA DIFIERE entre los dos motores.\n".
            'Ojo al honeypot (`.hp`), a la fila de email+teléfono y al `<small>` del hint: no se ven, '.
            "y son parte del contrato.\n\n"
        );
    }

    /**
     * ⚠️ **El banner de errores del alta, que es un árbol distinto**: `<strong>` + `<ul>` con un `<li>`
     * por aviso, **y además** cada aviso bajo su campo. Las dos cosas, no una.
     *
     * El caso anterior no puede verlo —un formulario recién abierto no tiene errores—, y el número de
     * `<li>` depende de cuántos campos fallen: se fuerza un envío vacío, que falla en los CUATRO que
     * quedan (nombre, correo, teléfono y contraseña). ⚠️ Eran seis hasta la T8·c (`#350`), cuando las
     * dos casillas legales salieron del alta.
     */
    public function test_the_register_error_banner_emits_the_same_tree_in_both_engines(): void
    {
        // Un alta VACÍA: falla la validación de todos los campos obligatorios a la vez, que es lo que
        // llena la lista. Con un solo campo en rojo, un `<li>` de más o de menos no se vería.
        // ⚠️ La precondición de abajo pide MÁS DE TRES y hoy son exactamente cuatro: si alguien quita
        // otro campo obligatorio del alta, este caso se pondrá rojo por su precondición y no por el
        // árbol — que es lo correcto, porque con tres avisos deja de probar lo que dice probar.
        // ⚠️ El lado SPA ya no recibe los errores cocinados por el test: recibe el **422 crudo** de
        // `POST /auth/register` y los compone `register.js`. El orden de los avisos —el de las reglas de
        // validación— y el reparto entre banner y campo son SUYOS, y el test los reimplementaba en PHP
        // («el mismo orden que fija `register.js`», decía su comentario): eso es un punto ciego, no una
        // comodidad.
        $api = ['register' => [
            'status' => 422,
            'body' => $this->postJson('/api/v1/auth/register', [])->assertStatus(422)->json(),
        ]];

        // La precondición sigue viva y ahora se lee del MISMO 422 que consume el cajón: con un solo
        // campo en rojo, un `<li>` de más o de menos no se vería.
        $this->assertGreaterThan(
            3, count($api['register']['body']['error']['fields'] ?? []),
            'el caso necesita varios campos en rojo para probar la lista'
        );

        $vue = $this->treeOf(
            $this->renderVue(5, [], null, $api, $this->identifyState('register')),
            'auth__errors'
        );

        $this->assertTree(__FUNCTION__, $vue,
            "El banner de errores del alta DIFIERE entre los dos motores.\n".
            "Es `<strong>` + `<ul>` con un `<li>` por aviso.\n\n"
        );
    }

    /**
     * El paso 7 — «revisa tu correo». Son tres nodos, pero uno lleva `role="status"`, que es contrato:
     * lo anuncia el lector de pantalla sin robar el foco.
     */
    public function test_the_verify_email_step_emits_the_same_tree_in_both_engines(): void
    {

        $vue = $this->vueTree(7, ['messages' => __('tickets')], 'purchase__confirm', withSiblings: true);

        $this->assertTree(__FUNCTION__, $vue,
            "El árbol de «revisa tu correo» DIFIERE entre los dos motores.\n\n"
        );
    }

    /**
     * El estado de cliente del paso 5: la pestaña activa y los DOS grupos de diccionario que el
     * montaje inyecta.
     *
     * ⚠️ `account` va con los dos textos legales **ya interpolados** —llevan un `<a href>` que compone
     * `route()` y el cajón los pinta con `v-html`—, y `auth` es el grupo de Laravel del que sale el
     * aviso del limitador. Los dos son payload del SERVIDOR, no derivaciones: se pasan tal cual.
     *
     * @return array<string, mixed>
     */
    private function identifyState(string $mode = 'login'): array
    {
        return $this->clientState(step: 5) + [
            'mode' => $mode,
            'account' => [
                'login' => __('account.login'),
                'register' => array_replace(__('account.register'), [
                    'privacy_notice' => __('account.register.privacy_notice', ['url' => route('legal.privacidad')]),
                ]),
            ],
            'auth' => __('auth'),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $registerErrors
     * @return array<string, mixed>
     */
    private function identifyProps(string $mode = 'login', ?array $registerErrors = null): array
    {
        return [
            'mode' => $mode,
            'loginErrors' => ['global' => '', 'fields' => []],
            'registerErrors' => $registerErrors ?? ['summary' => [], 'fields' => []],
            'submitting' => false,
            'form' => [],
            'messages' => __('tickets'),
            // El montaje inyecta `account` PODADO, con los dos textos legales YA interpolados: llevan
            // un `<a href>` dentro que compone `route()`, y el cajón los pinta con `v-html`.
            'account' => [
                'login' => __('account.login'),
                'register' => array_replace(__('account.register'), [
                    'privacy_notice' => __('account.register.privacy_notice', ['url' => route('legal.privacidad')]),
                ]),
            ],
        ];
    }

    // ── Los pasos 8 y 9: pagar ────────────────────────────────────────────────────────────────

    /**
     * El paso 8 — la pantalla de PAGO. Es el carrito otra vez, y ahí está la trampa: **se parece tanto
     * al paso 4 que lo natural es reutilizarlo**, y tiene cuatro diferencias que el diff sí ve —la
     * clase `cart--summary`, la ausencia del botón de quitar, el precio SIN clase y el pie de aviso
     * sin «añadir otra reserva»—.
     */
    public function test_the_pay_step_emits_the_same_tree_in_both_engines(): void
    {
        $this->setUpFullCart();
        $this->actingAs(User::factory()->create());

        $vue = $this->vueTree(8, [], 'wiz__title', withSiblings: true,
            api: $this->cartApiPayload($this->fullCartItems()), state: $this->clientState(step: 8));

        $this->assertTree(__FUNCTION__, $vue,
            "El árbol de la pantalla de PAGO DIFIERE entre los dos motores.\n".
            'Se parece al carrito, pero no es el carrito: `cart--summary`, sin botón de quitar y con el '.
            "precio en un `<span>` sin clase.\n\n"
        );
    }

    /**
     * El paso 8 **con lo que falta antes de pagar** (`#562`).
     *
     * ⚠️⚠️ **El caso de arriba NO ve este bloque, y estuvo así desde `#349`.** Su comprador tiene
     * teléfono y esta instalación no publica condiciones, así que `need.phone` y `need.terms` salen
     * los dos `false` y el `.paydue` entero **no se emite**: en el manifiesto congelado solo aparece
     * el `.paydue__legal` del otro camino. O sea que el teléfono, la casilla legal y la fila de las
     * condiciones —lo único de esta pantalla que el cliente **rellena**— no lo vigilaba ningún diff.
     *
     * ▶ Lo que lo hace aparecer es el estado REAL, no un doble: un comprador **sin teléfono** y una
     * instalación **con las condiciones publicadas**. Así el bloque llega por su camino entero
     * —`CheckoutDuties` → contexto de cuenta → montaje → `buyer-due.js` → la pantalla—, que es lo que
     * un doble de `need` no probaría.
     */
    public function test_the_pay_step_emits_what_is_missing_before_paying(): void
    {
        $this->setUpFullCart();
        app(LegalDocumentPublisher::class)->publish('condiciones', [
            'es' => ['title' => 'Condiciones', 'body' => [['h' => 'Reserva', 'p' => 'Texto.']]],
        ]);
        $this->actingAs(User::factory()->create(['phone' => '', 'email_verified_at' => now()]));

        $vue = $this->vueTree(8, [], 'paydue', withSiblings: true,
            api: $this->cartApiPayload($this->fullCartItems()) + [
                // El MISMO contexto que siembra el montaje: el renderizador le pasa esto a
                // `buyerNeeds()`, que es quien decide qué se pide.
                'accountContext' => AccountContextSeed::forCurrentRequest(),
            ],
            state: $this->clientState(step: 8) + ['termsUrl' => route('legal.condiciones')]);

        $this->assertTree(__FUNCTION__, $vue,
            "El árbol de LO QUE FALTA ANTES DE PAGAR ha cambiado.\n".
            "Aquí viven las tres piezas que el cliente rellena: el teléfono con su pista, la casilla de\n".
            "condiciones —que desde `#562` se lee ENTERA, sin el enlace dentro— y la FILA que las abre,\n".
            "con sus 48 px de alto. Si la fila desaparece, el único enlace a las condiciones de quien\n".
            "todavía no las ha aceptado se va con ella.\n\n"
        );
    }

    /**
     * ⚠️ **La BANDA de desglose del pago**, que llevaba declarada en `SHELL_BLOCKS_NOT_YET_IN_SPA`
     * desde 4.3·1 y ahora se retira de esa lista.
     *
     * Se ancla en `.bk-paybreakdown` **con hermanos** porque lo que importa no es solo su contenido: es
     * que vaya pegada ENCIMA del pie. `.bk-paybreakdown + .bk-foot` es un selector de hermano
     * adyacente, así que un nodo entre las dos —o meterla dentro del scroll— le quita el borde que las
     * une sin que falte ninguna clase.
     */
    public function test_the_payment_breakdown_band_emits_the_same_tree_in_both_engines(): void
    {
        $this->setUpFullCart();
        $this->actingAs(User::factory()->create());

        // La precondición sigue viva, pero su sujeto ya no es el view-model del motor: es el
        // PRESUPUESTO. Sin señal no hay desglose que comparar y el caso no probaría nada.
        $quote = $this->cartApiPayload($this->fullCartItems())['quote'];

        // «Hay señal» en el CONTRATO es que lo que se paga online sea menor que el total: el
        // presupuesto no publica un `deposit_cents` (medido: sus claves son `lines`, `total_cents` y
        // `online_amount_cents`). Sin esa diferencia no hay desglose que comparar.
        $this->assertLessThan(
            (int) $quote['total_cents'], (int) $quote['online_amount_cents'],
            'el caso necesita señal, o no hay desglose de pago que comparar'
        );

        $vue = $this->vueTree(8, [], 'bk-paybreakdown', withSiblings: true,
            api: $this->cartApiPayload($this->fullCartItems()), state: $this->clientState(step: 8), shellFromServer: false);

        $this->assertTree(__FUNCTION__, $vue,
            "La banda de desglose del pago DIFIERE entre los dos motores.\n".
            "Va FUERA del scroll y pegada encima del pie: de ese orden depende su borde.\n\n".
            ''
        );
    }

    /**
     * El paso 9 — el auto-POST hacia la pasarela.
     *
     * ⚠️ **Este árbol es el que MENOS dice de los once**, y conviene saberlo: `action`, `method` y los
     * `name` de los campos **no son atributos de contrato**, así que este diff da por bueno un
     * formulario con los campos vacíos, mal nombrados o apuntando a otro sitio — y el pago fallaría con
     * SIS0042 con la suite en verde. Lo que de verdad lo verifica es `SidebarPayParityTest`, campo a
     * campo. Aquí solo se comprueba la ESTRUCTURA: el `<noscript>` con su botón, y que los tres campos
     * ocultos existen.
     */
    public function test_the_redirect_step_emits_the_same_tree_in_both_engines(): void
    {
        $this->setUpFullCart();
        $this->actingAs(User::factory()->create());

        // ⚠️ El sobre de pago se pide ANTES de confirmar, y no es un detalle de orden: a partir del 201
        // el pedido EXISTE y retiene aforo, así que `confirmReservation()` vacía la cesta —y sin cesta
        // no hay con qué crear por la API el pedido equivalente (la primera versión de esto se llevó un
        // 422 por ahí). Los dos motores acaban con un pedido cada uno, que es lo que se compara.
        $api = $this->redirectApiPayload();

        $vue = $this->vueTree(9, [], 'purchase__redirecting', withSiblings: true,
            api: $api, state: $this->clientState(step: 9));

        $this->assertTree(__FUNCTION__, $vue,
            "El árbol de la redirección DIFIERE entre los dos motores.\n\n"
        );
    }

    /**
     * El sobre `payment` REAL de `POST /api/v1/orders` (Fase 4 · paso 4.7·2b·2·B).
     *
     * ⚠️ **Aquí desaparece la cuarta traducción a mano del test.** `gatewayFormProps()` convertía el
     * mapa `payment.fields` del contrato en la LISTA de `{name, value}` que pinta `RedirectStep`, que
     * es exactamente lo que hace `pay.js::gatewayForm()`. El gate nunca la ejecutaba — y es el sitio
     * donde un campo renombrado rompe el cobro con SIS0042 **con el pedido ya creado y el aforo
     * retenido**.
     *
     * Se crea un pedido de verdad por la API con la misma cesta: los importes y las firmas serán otros
     * —son dos pedidos distintos— pero lo que este diff compara es la ESTRUCTURA, y los valores los
     * compara campo a campo `SidebarPayParityTest`.
     *
     * @return array<string, mixed>
     */
    private function redirectApiPayload(): array
    {
        $cart = $this->cartApiPayload($this->fullCartItems());

        $response = $this->postJson('/api/v1/orders', ['items' => $cart['cart']])->assertCreated();

        return ['payment' => $response->json('payment')];
    }

    // ── El paso 6: la reserva creada ──────────────────────────────────────────────────────────

    /**
     * El paso 6 — **la reserva creada**, y el primero al que se llega DESDE FUERA del cajón.
     *
     * Es el árbol más largo de los once y **cinco de sus bloques son condicionales** —el desglose de la
     * señal, la nota del pago, el aviso del post-form, el enlace de registro y el resumen entero—, así
     * que un solo caso no lo cubre: éste enciende todo lo que se puede encender, y los dos de abajo
     * apagan lo que aquí está encendido.
     *
     * ⚠️ La fila del resumen la comparten desde 4.6·1 este paso y el de pagar (`SummaryLine.vue`). Que
     * los dos casos sigan pasando **por separado** es lo que demuestra que la extracción no movió nada.
     */
    public function test_the_confirmed_step_emits_the_same_tree_in_both_engines(): void
    {
        Setting::updateOrCreate(['key' => 'registration.url'], ['value' => 'https://registro.example.test/alta', 'group' => 'business']);
        Setting::flushMemo();

        $orderCode = $this->setUpConfirmedOrder(paid: true);

        // Las tres precondiciones siguen vivas y ahora se leen del DOMINIO, no del view-model.
        $order = Order::where('code', $orderCode)->firstOrFail();

        $this->assertSame(Order::STATUS_PAID, $order->status, 'el caso necesita el pedido PAGADO, o no hay desglose');
        $this->assertGreaterThan(
            0, (int) ($this->confirmedApiPayload()['order']['ledger']['balance']['cents'] ?? 0),
            'y algo pendiente en el parque, o no hay desglose que comparar'
        );
        $this->assertNotNull(RegistrationLink::current(), 'y el enlace de registro configurado');

        $vue = $this->vueTree(6, [], 'purchase__confirm', withSiblings: true,
            api: $this->confirmedApiPayload(),
            state: $this->clientState(step: 6) + [
                'orderCode' => $this->confirmedOrderCode(),
                'registration' => RegistrationLink::current()?->toArray(),
                // ⚠️ **CON sesión, que es el camino de la vuelta del banco** (`#563`): es lo que hace
                // aparecer la puerta al carné. Sin esto el manifiesto congelaría la pantalla del otro
                // camino —el enlace del correo, sin titular— y el botón nuevo no lo vigilaría nadie.
                'hasSession' => true,
            ]);

        $this->assertTree(__FUNCTION__, $vue,
            "El árbol de la RESERVA CREADA difiere entre los dos motores.\n".
            "Es el más largo del cajón y casi todo en él es condicional.\n\n".
            "⚠️ Sus DOS salidas son condicionales: con sesión, «Ver Mi QR» en tinta y «hacer otra\n".
            "reserva» de fantasma; sin ella, solo la segunda y recuperando el peso primario.\n\n"
        );
    }

    /**
     * El mismo paso con el pedido **PENDIENTE**: sin desglose de señal, con la nota de «pendiente de
     * pago» en vez de la de pago confirmado, con el aviso del post-form y **sin** enlace de registro.
     *
     * ⚠️ No es un caso rebuscado: es el camino del enlace de verificación de correo, que también
     * aterriza en el paso 6. Hasta #224 esta pantalla decía «pendiente de pago» SIEMPRE —también a
     * quien acababa de pagar—, así que la rama importa.
     */
    public function test_the_confirmed_step_of_a_pending_order_emits_the_same_tree_in_both_engines(): void
    {
        $orderCode = $this->setUpConfirmedOrder(paid: false, withGuestForm: true);
        // Las dos precondiciones siguen vivas; su sujeto pasa del view-model al DOMINIO y a la API.
        $this->assertSame(
            Order::STATUS_PENDING, Order::where('code', $orderCode)->firstOrFail()->status,
            'el caso necesita el pedido PENDIENTE'
        );
        $this->assertTrue(
            (bool) ($this->confirmedApiPayload()['order']['guest_form_pending'] ?? false),
            'y una reserva que pide formulario de invitados'
        );

        $vue = $this->vueTree(6, [], 'purchase__confirm', withSiblings: true,
            api: $this->confirmedApiPayload(),
            state: $this->clientState(step: 6) + [
                'orderCode' => $this->confirmedOrderCode(),
                'registration' => RegistrationLink::current()?->toArray(),
            ]);

        $this->assertTree(__FUNCTION__, $vue,
            "El árbol de la reserva creada de un pedido PENDIENTE difiere entre los dos motores.\n\n".
            ''
        );
    }

    /**
     * ⚠️ **Y el caso que de verdad se olvida: SIN resumen.**
     *
     * El Blade pinta esta pantalla aunque `$confirmation` sea `null` —queda el código del pedido, el
     * aviso del correo y el CTA—, y es lo que ve quien perdió la sesión entre la ida a la pasarela y la
     * vuelta. Un motor que tratara ese caso como un error dejaría a quien acaba de pagar mirando un
     * aviso de fallo, y **el gate no lo vería** si nadie compara este árbol.
     */
    public function test_the_confirmed_step_without_a_summary_emits_the_same_tree_in_both_engines(): void
    {
        // Sin sesión, `confirmationSummary()` devuelve null: es el mismo `null` que produce un pedido
        // que ya no es de quien pregunta, y el que este caso existe para fijar.

        $vue = $this->vueTree(6, [
            'confirmation' => null,
            'orderCode' => 'R-ABC123',
            'registration' => null,
            'messages' => __('tickets'),
            'locale' => app()->getLocale(),
        ], 'purchase__confirm', withSiblings: true);

        $this->assertTree(__FUNCTION__, $vue,
            "El árbol de la reserva creada SIN resumen difiere entre los dos motores.\n".
            "Es lo que ve quien perdió la sesión entre la pasarela y la vuelta.\n\n".
            ''
        );
    }

    /**
     * Un componente que ha comprado de verdad y está en la pantalla de reserva creada.
     *
     * Se llega **pagando**: `checkout()` + `confirmReservation()` crean el pedido con su hold y su
     * cobro abierto, igual que en producción. Sembrar un `Order` a mano fijaría una forma de pedido que
     * el código real no produce —y el resumen sale del pedido, no de la cesta—.
     */
    /**
     * Deja creado el pedido del desenlace y devuelve su código.
     *
     * ⚠️ Antes conducía el componente Livewire hasta el paso 6. Ahora crea el pedido por la API y,
     * si el caso lo pide, marca el HECHO de que la pasarela lo dejó pagado — que es lo único que hace
     * falta aquí; volver a ejecutar el receptor de la respuesta firmada tiene sus propios tests.
     */
    private function setUpConfirmedOrder(bool $paid, bool $withGuestForm = false): string
    {
        $this->setUpFullCart($withGuestForm);
        $this->actingAs(User::factory()->create());

        $cart = $this->cartApiPayload($this->fullCartItems());
        $this->postJson('/api/v1/orders', ['items' => $cart['cart']])->assertCreated();

        $code = $this->confirmedOrderCode();

        if ($paid) {
            // Cobrado COMO lo cobra el canal real: estado + `paid_at` + el cobro de lo que las líneas
            // aportan online. Un pedido `paid` sin `Payment` no lo produce nadie, y desde la T2 del
            // libro el saldo lo sabe (identidad I2) y contestaría «en revisión», sin desglose.
            $order = Order::where('code', $code)->firstOrFail();
            Payment::create([
                'payable_type' => $order->getMorphClass(), 'payable_id' => $order->id,
                'amount' => $order->onlineDueCents(), 'currency' => 'EUR', 'provider' => 'redsys',
                'status' => Payment::STATUS_PAID, 'paid_at' => now(), 'gateway_order' => sprintf('%010d', $order->id),
            ]);
            $order->forceFill(['status' => Order::STATUS_PAID, 'paid_at' => now()])->save();
        }

        return $code;
    }

    /**
     * El view-model del paso 6, traducido a la forma en la que el cajón lo recibe de VERDAD.
     *
     * ⚠️ **La traducción no es cosmética** (mismo motivo que en el carrito, y ya mordió una vez con los
     * complementos): el view-model de Livewire dice `name`/`qty`/`subtotal` y el pedido de la API dice
     * `product_name`/`quantity`/`charged_subtotal_cents`. Alimentar al componente con los primeros
     * dejaría este diff verde mientras el cajón real pinta filas vacías. Que las DOS fuentes digan lo
     * mismo se comprueba aparte, en `SidebarOutcomeParityTest`.
     *
     * @return array<string, mixed>
     */
    /**
     * El desenlace del paso 6 desde los DOS endpoints reales (Fase 4 · paso 4.7·2b·2·B).
     *
     * ⚠️ El resumen y las respuestas del pack llegan **por separado** y no por capricho: el pedido no
     * lleva los datos de un menor (`#39`), así que el cliente los pide aparte y los empareja. Ese
     * emparejado ya mordió una vez —`#56`: el caso pasaba con el cliente emparejando por POSICIÓN,
     * porque llave y posición coinciden por casualidad cuando el orden natural es el mismo—, y hasta
     * ahora el gate no lo ejecutaba: el test traducía el view-model de Livewire a mano.
     *
     * @return array<string, mixed>
     */
    /**
     * El pedido que el caso acaba de crear, leído de la BD y NO del view-model del motor.
     *
     * ⚠️ Es la misma corrección de método que `fullCartItems()` (4.7·2b·3, paso 1): el payload que se
     * le da a Vue no puede salir del motor que este fichero compara. Medido antes de cambiarlo: en los
     * dos casos del desenlace la BD tiene **un solo pedido** y su código coincide exactamente con el
     * `orderCode` del componente, así que la fuente nueva es equivalente y además sobrevive al borrado
     * —tras él, ese pedido lo crea la API, que es como lo crea un cliente de verdad—.
     */
    private function confirmedOrderCode(): string
    {
        $code = (string) Order::query()->latest('id')->value('code');

        $this->assertNotSame('', $code, 'el caso tiene que haber creado el pedido antes de pedir su código');

        return $code;
    }

    private function confirmedApiPayload(): array
    {
        $code = $this->confirmedOrderCode();

        return [
            'order' => $this->getJson('/api/v1/orders/'.$code)->assertOk()->json(),
            'eventData' => $this->getJson('/api/v1/orders/'.$code.'/event-data')->assertOk()->json(),
        ];
    }

    /**
     * El desenlace del paso 10: el estado del cobro tal y como lo publica el contrato.
     *
     * ⚠️ Se pide `payment-status` y NO se traduce el motivo: `declined_reason` **es** la clave de
     * `tickets.payment_failed.reasons.*`, y quien la resuelve —con su caída a `default`, que es lo que
     * impide pintar «Motivo:» sin nada detrás— es `outcome.js`.
     *
     * @return array<string, mixed>
     */
    private function declinedApiPayload(): array
    {
        $code = $this->confirmedOrderCode();

        return ['paymentStatus' => $this->getJson('/api/v1/orders/'.$code.'/payment-status')->assertOk()->json()];
    }

    // ── Los pasos 10 y 11: los otros dos desenlaces ───────────────────────────────────────────

    /**
     * El paso 10 — **el pago denegado, con sus tres CTA**.
     *
     * ⚠️ **Los dos `<span>` del botón principal están SIEMPRE en el árbol**, y es lo que hace que este
     * caso valga: en Livewire `wire:loading` es un ATRIBUTO, no un condicional de servidor, así que el
     * rótulo y el `.btn__loading` con su spinner viajan los dos en el HTML. Un motor que emitiera solo
     * uno —lo natural con un `v-if`— dejaría el botón sin su estado de carga y el gate lo ve.
     *
     * ⚠️ Y la jerarquía de los tres: principal `<button>` con `btn--zone btn--lg`, secundario
     * `<button>` fantasma y terciario **`<a>`**. El normalizador compara el tipo de elemento, así que
     * emitir un `<button>` donde hay un enlace rompe con las clases correctas al 100%.
     */
    public function test_the_declined_step_emits_the_same_tree_in_both_engines(): void
    {
        $this->setUpDeclinedOrder();

        $vue = $this->vueTree(10, [], 'purchase__failed', withSiblings: true,
            api: $this->declinedApiPayload(),
            state: $this->clientState(step: 10) + [
                'orderCode' => $this->confirmedOrderCode(),
                'contactUrl' => route('contacto'),
            ]);

        $this->assertTree(__FUNCTION__, $vue,
            "El árbol del PAGO DENEGADO difiere entre los dos motores.\n".
            "El pedido sigue vivo aquí: esta pantalla es la segunda oportunidad, no un error.\n\n".
            ''
        );
    }

    /**
     * ⚠️ **Y el bloque del motivo se emite SIEMPRE que hay sesión, aunque el rechazo no traiga código.**
     *
     * Medido: `RedsysResponseCode::reasonText()` **nunca devuelve null** —cae a `default`—, así que el
     * `@if ($declinedReasonText)` del Blade es verdadero también sin `Ds_Response`. Un motor que
     * condicionara el bloque a «hay motivo conocido» emitiría un nodo de menos justo en el caso más
     * frecuente: el de un rechazo del que la pasarela no dijo el porqué.
     */
    public function test_the_declined_step_still_emits_the_reason_block_without_a_known_code(): void
    {
        $this->setUpDeclinedOrder(responseCode: null);

        // El sujeto sobrevive al motor: quien decide el motivo es el servicio del dominio.
        $this->assertSame(
            __('tickets.payment_failed.reasons.default'), RedsysResponseCode::reasonText(null),
            'sin código, el servidor cae al motivo genérico — no a null'
        );

        $vue = $this->vueTree(10, [], 'purchase__failed', withSiblings: true,
            api: $this->declinedApiPayload(),
            state: $this->clientState(step: 10) + [
                'orderCode' => $this->confirmedOrderCode(),
                'contactUrl' => route('contacto'),
            ]);

        $this->assertTree(__FUNCTION__, $vue, '');
    }

    /**
     * El paso 11 — **verificando**, la pantalla de los terminales que vuelven sin datos firmados.
     *
     * ⚠️ `role="status"` y `aria-live="polite"` son atributos de contrato y aquí no son decoración: la
     * pantalla cambia SOLA cuando llega la notificación de la pasarela, y sin ellos un lector de
     * pantalla no anunciaría nada. Lo que el diff no ve es el sondeo en sí — de eso responden
     * `outcome.test.js` y `SidebarOutcomeParityTest`.
     */
    public function test_the_verifying_step_emits_the_same_tree_in_both_engines(): void
    {
        $this->setUpVerifyingOrder();

        $vue = $this->vueTree(11, $this->verifyingProps(), 'purchase__verifying', withSiblings: true);

        $this->assertTree(__FUNCTION__, $vue,
            "El árbol de «verificando el pago» difiere entre los dos motores.\n\n".
            ''
        );
    }

    /**
     * Un componente en el paso 10, con un pago REALMENTE rechazado.
     *
     * ⚠️ **Se llega por la costura, no con un `->set('step', 10)`**: `declinedReasonText` lo compone
     * `mount()` a partir del último `Payment` fallido, así que colocar el paso a mano dejaría el motivo
     * vacío y el caso compararía dos pantallas sin su bloque más frágil.
     */
    private function setUpDeclinedOrder(?string $responseCode = '0101'): void
    {
        $code = $this->purchasedOrderCode();

        Payment::whereHas('payable', fn ($q) => $q->where('code', $code))
            ->latest('id')
            ->first()
            ?->update([
                'status' => Payment::STATUS_FAILED,
                'raw_response' => $responseCode === null ? [] : ['Ds_Response' => $responseCode],
            ]);

        SidebarEntry::failed($code);

    }

    /** Un componente en el paso 11, al que se llega por la misma costura. */
    private function setUpVerifyingOrder(): void
    {
        SidebarEntry::verifying($this->purchasedOrderCode());

    }

    /** Compra REAL de punta a punta, para que el pedido lo cree el dominio y no el test. */
    /**
     * Un pedido creado de verdad, POR LA API — que es como lo crea un cliente.
     *
     * ⚠️ Antes lo creaba conduciendo el componente Livewire (`checkout` + `confirmReservation`).
     * Con el motor retirado el camino es el mismo que usa el cajón SPA: `POST /api/v1/orders` con la
     * cesta declarada. El código se lee de la BD, no de la respuesta, por la misma razón que en
     * {@see self::confirmedOrderCode()}: es el resultado, no la vista de nadie.
     */
    private function purchasedOrderCode(): string
    {
        $this->setUpFullCart();
        $this->actingAs(User::factory()->create());

        $cart = $this->cartApiPayload($this->fullCartItems());
        $this->postJson('/api/v1/orders', ['items' => $cart['cart']])->assertCreated();

        return $this->confirmedOrderCode();
    }

    /** @return array<string, mixed> */
    private function verifyingProps(): array
    {
        return [
            'orderCode' => $this->confirmedOrderCode(),
            'ordersUrl' => route('account.orders'),
            'messages' => __('tickets'),
        ];
    }

    // ── Reservas EN PAUSA: el flujo entero se sustituye ───────────────────────────────────────

    /**
     * ⚠️ **La divergencia que 4.3·2 dejó abierta, cerrada aquí.** Con el interruptor puesto, el cajón
     * SPA seguía vendiendo —catálogo, cesta y «Ir a pagar»— mientras Livewire enseñaba el aviso de
     * mantenimiento. Y **ningún test podía cazarlo**: hasta este paso, ni un solo caso de
     * `tests/Feature/Sidebar` sembraba la pausa.
     *
     * Se ancla en `.jj-loading` con hermanos y en el paso 2, que es donde el servidor SÍ compone banda
     * y pie: así el mismo `assertSame` demuestra las cuatro cosas a la vez —que el aviso está DENTRO
     * de la zona scrollable, que la banda NO está, que el pie NO está y que el paso no se pinta—, sin
     * necesitar un idioma aparte para las ausencias.
     *
     * ⚠️ Y las props del armazón siguen viniendo del SERVIDOR, que durante la pausa **sigue componiendo
     * `bookingProgress` y `footer` no nulos** —lo que los oculta es la vista—. Anularlos aquí «para que
     * el caso pase» lo dejaría probando nada.
     */
    public function test_the_paused_notice_replaces_the_whole_flow_in_both_engines(): void
    {
        $product = $this->product('Entrada 1h', TicketType::TYPE_ENTRY, 990);
        $this->slotsForNextDays($product, 5);

        // Con las reservas abiertas, este estado lleva banda Y pie: es lo que hace significativa su
        // ausencia después.
        $this->pauseReservations();

        $vue = $this->vueTree(2, [], 'jj-loading', withSiblings: true,
            api: $this->dateApiPayload($product->id),
            state: $this->clientState(step: 2, productId: $product->id, notice: $this->noticeProps(2)),
            shellFromServer: false);

        $this->assertTree(__FUNCTION__, $vue,
            "El cajón EN PAUSA DIFIERE entre los dos motores.\n".
            'El aviso no solo sustituye el contenido: apaga también la banda de progreso, el pie y la '.
            "banda de desglose del pago — la misma condición gobierna los cuatro sitios.\n\n".
            ''
        );
    }

    /**
     * ⚠️ **El aviso tapa SEIS pasos**, y aquí se comprueba que el motor SPA los tapa todos —incluidos
     * el 5 y el 8, que todavía no están transcritos—.
     *
     * Que esos dos se puedan comparar no es casualidad: con el aviso puesto **no hay paso que
     * renderizar**, porque sustituye el contenido entero. El renderizador SSR lo admite desde este
     * paso, y es lo fiel al Blade.
     *
     * ⚠️ La otra mitad —que los pasos de RESULTADO (6, 7, 9, 10, 11) **no** se tapen— no puede
     * comprobarse aquí: sin aviso hace falta el componente del paso, y esos cinco no existen todavía.
     * Vive en `SidebarPausedParityTest`, que compara el mapa ENTERO contra `showPausedNotice()`.
     */
    public function test_the_paused_notice_covers_the_six_steps_the_server_covers(): void
    {
        $this->product('Entrada 1h', TicketType::TYPE_ENTRY, 990);
        $this->pauseReservations();

        foreach ([1, 2, 3, 4, 5, 8] as $step) {

            // ⚠️ Con el aviso puesto, el armazón TAPA el paso entero, así que no hace falta cargar la
            // API de cada uno: lo que se compara es que el aviso sustituya el flujo. El armazón lo
            // compone igualmente el cliente (`shellFromServer: false`) — es el último montaje que lo
            // tomaba del servidor, y dejarlo así habría escondido el único que faltaba.
            $vue = $this->renderVue($step, [], null, [],
                $this->clientState(step: $step, notice: $this->noticeProps($step)), shellFromServer: false);

            $this->assertStringContainsString(
                'purchase__maint', $vue,
                "El motor SPA NO tapa el paso {$step} y el servidor sí."
            );

            foreach (['bk-progress', 'bk-foot', 'bk-paybreakdown'] as $block) {
                $this->assertStringNotContainsString(
                    $block, $vue,
                    "El motor SPA emite «{$block}» en el paso {$step} con las reservas pausadas. ".
                    'La misma condición que enseña el aviso apaga los tres bloques del armazón: dejar '.
                    'uno vivo es un CTA de compra sobre un cartel que dice que no se puede comprar.'
                );
            }
        }
    }

    // ── El PIE: la navegación del embudo ──────────────────────────────────────────────────────

    /**
     * ⚠️ **El pie son TRES árboles, no uno**, y cada uno se emite en un estado distinto:
     *  - rama `cart` (catálogo con cesta): un ÚNICO hijo y **sin nota de IVA**;
     *  - rama `bar` sin desglose: el calendario, con el CTA inactivo y el importe en «—»;
     *  - rama `bar` con desglose: seis nodos más, y `.bk-foot__info` cuelga DENTRO de `.bk-foot__l`.
     *
     * Se recorren los cuatro estados en un solo caso porque lo que se compara es siempre lo mismo, y
     * separarlos multiplicaría el andamiaje sin añadir una sola aserción.
     */
    public function test_the_footer_emits_the_same_tree_in_every_state(): void
    {
        foreach ($this->footerStates() as $label => [$step, $props, $api, $state]) {
            $vue = $this->vueTree($step, $props, 'bk-foot', api: $api, state: $state, shellFromServer: false);

            $this->assertTree(__FUNCTION__, $vue,
                "El pie del estado «{$label}» DIFIERE entre los dos motores.\n".
                "Son tres árboles distintos: la barra-carrito, la barra sin desglose y la barra con él.\n\n".
                ''
            );
        }
    }

    /**
     * **La guarda del pie**: donde el servidor NO emite barra, el motor SPA tampoco.
     *
     * Con la cesta vacía, `footer()` devuelve `null` en el catálogo y en el carrito. Un motor que
     * pintara la barra igual enseñaría «0,00 €» y un «Ir a pagar» donde la web no enseña nada — y el
     * diff no lo vería, porque su ancla `bk-foot` simplemente no existiría en el lado de Livewire.
     */
    public function test_the_footer_is_absent_in_both_engines_when_the_cart_is_empty(): void
    {
        foreach ([1, 4] as $step) {

            $vue = $step === 1
                ? $this->renderVue($step, [], null, $this->catalogApiPayload(),
                    $this->clientState(step: 1), shellFromServer: false)
                : $this->renderVue($step, [], null, $this->cartApiPayload([]),
                    $this->clientState(step: $step), shellFromServer: false);

            $this->assertStringNotContainsString(
                'bk-foot', $vue,
                "El motor SPA emite el pie en el paso {$step} con la cesta vacía y el servidor no. ".
                'Enseñaría un total de 0,00 € y un CTA donde la web no enseña nada.'
            );
        }
    }

    /**
     * ⚠️ **Los dos motores anuncian si el desglose de la señal está abierto, y esto NO lo ve el diff.**
     *
     * Es el último apartado de accesibilidad de §6 que quedaba sin comprobar, y al medirlo apareció una
     * divergencia real: el botón del popover del pie llevaba `:aria-expanded` en el Blade y **nada** en
     * el cajón SPA, así que un lector de pantalla no decía si el desglose estaba desplegado. El diff de
     * árbol no podía verlo por partida doble — el binding de Alpine se descarta como andamiaje y la
     * ausencia en Vue no deja rastro.
     *
     * Se comprueba sobre el marcado de cada motor, en su propia forma: el atributo es de RUNTIME en los
     * dos, así que lo que se exige es que ambos lo DECLAREN.
     */
    public function test_both_engines_announce_the_deposit_popover_state(): void
    {
        $this->setUpFullCart();

        // La precondición —«hay señal, luego el pie lleva el ⓘ del desglose»— sale del PRESUPUESTO,
        // que es lo que compone el pie en el cajón (`foot.js`), no del view-model del motor retirado.
        $quote = $this->cartApiPayload($this->fullCartItems())['quote'];

        $this->assertLessThan(
            (int) $quote['total_cents'], (int) $quote['online_amount_cents'],
            'el caso necesita señal, o el pie no lleva el ⓘ que se comprueba abajo'
        );

        $vue = $this->renderVue(4, [], null, $this->cartApiPayload($this->fullCartItems()),
            $this->clientState(step: 4), shellFromServer: false);

        $this->assertStringContainsString(
            'aria-expanded', $vue,
            'El cajón SPA no anuncia si el desglose de la señal está abierto.
'.
            '⚠️ El diff de árbol NO puede verlo: en Livewire es un binding de Alpine —que el '.
            'normalizador descarta— y aquí sería, simplemente, un atributo que falta.'
        );
    }

    // ── El ARMAZÓN: lo que no pertenece a ningún paso ─────────────────────────────────────────

    /**
     * El armazón del cajón: velo de carga, banda y zona scrollable.
     *
     * ⚠️ **Este caso nace en 4.3·1 porque hasta entonces NADIE miraba ahí.** Todos los casos anclan
     * DENTRO (`catalog-acc`, `wiz__title`), así que el motor SPA podía —y lo hacía— no emitir ni el
     * velo, ni la zona scrollable, ni la banda, y el gate seguía verde. No es decoración: esos nodos
     * son la cadena flex que recorta el panel y ancla el pie.
     *
     * Se ancla en `.jj-loading`, el PRIMER hijo de `.purchase`, con hermanos: es la única forma de
     * comparar el orden entre ellos, del que dependen selectores de adyacencia
     * (`.bk-paybreakdown + .bk-foot`, que llega en 4.5).
     *
     * Con el catálogo y la cesta vacía, Livewire emite exactamente dos hijos —velo y scroll—: el pie
     * es `null` sin cesta. Los pasos con pie entran con 4.3·2.
     */
    public function test_the_shell_emits_the_same_tree_in_both_engines(): void
    {
        $this->product('Entrada 1h', TicketType::TYPE_ENTRY, 990);

        $vue = $this->vueTree(1, [], 'jj-loading', withSiblings: true, api: $this->catalogApiPayload(),
            state: $this->clientState(step: 1), shellFromServer: false);

        $this->assertTree(__FUNCTION__, $vue,
            "El ARMAZÓN del cajón DIFIERE entre los dos motores.\n".
            'Son el velo de carga, la banda de progreso y la zona scrollable: los nodos que sostienen '.
            "la cadena flex del panel (scroll que recorta + pie anclado al fondo).\n\n".
            ''
        );
    }

    /**
     * Los bloques del armazón que aún NO emite el motor SPA. **Solo puede ENCOGER.**
     *
     * Existe porque el caso de arriba compara el armazón del paso 1 —donde Livewire emite solo dos
     * hijos— y el ORDEN entre la banda y la zona scrollable se quedaba sin verificar. Aquí se compara
     * la secuencia completa de bloques descontando lo que todavía no está transcrito, de modo que:
     *  - el orden de lo que SÍ está queda fijado hoy;
     *  - lo que falta está DECLARADO y es ejecutable, no una nota en un documento.
     *
     * `bk-foot` salió en 4.3·2 y `bk-paybreakdown` sale en 4.5 —la banda de desglose es EXCLUSIVA del
     * paso de pago y solo cuando hay señal—. Añadir una entrada aquí es señal de que algo se ha
     * desmontado.
     *
     * @var list<string>
     */
    private const SHELL_BLOCKS_NOT_YET_IN_SPA = [];

    /**
     * ⚠️ **El ORDEN de los bloques del armazón, que es de lo que dependen los selectores de
     * adyacencia** (`.bk-paybreakdown + .bk-foot`) y la cadena flex del panel: la banda va pegada
     * ARRIBA, el pie anclado ABAJO y el scroll en medio.
     *
     * Se comprueba en el paso 2, que es el único donde el armazón lleva banda **y** pie a la vez. El
     * caso del armazón completo no puede cubrirlo todavía porque el motor SPA aún no emite el pie;
     * hasta que lo emita, la comparación se hace descontando la lista declarada de arriba.
     */
    public function test_the_shell_blocks_appear_in_the_same_order_in_both_engines(): void
    {
        $product = $this->product('Entrada 1h', TicketType::TYPE_ENTRY, 990);
        $this->slotsForNextDays($product, 5);

        $vue = $this->shellBlocks($this->renderVue(
            2, [], null, $this->dateApiPayload($product->id),
            $this->clientState(step: 2, productId: $product->id), shellFromServer: false
        ));

        // ⚠️ **Antes este caso comparaba el orden de los DOS motores; con uno solo, el orden se
        // DECLARA** (4.7·2b·3). Lo que protege sigue siendo lo mismo y no es cosmético: la banda va
        // pegada arriba, el pie anclado abajo y el scroll en medio, y de ese orden dependen la cadena
        // flex del panel y los selectores de adyacencia (`.bk-paybreakdown + .bk-foot`).
        $expected = ['jj-loading', 'bk-progress', 'purchase__scroll', 'bk-foot'];

        $this->assertSame(
            $expected, $vue,
            "Los bloques del armazón NO salen en el orden que fija el contrato.\n".
            'La banda va pegada arriba, el pie anclado abajo y el scroll en medio; de ese orden '.
            "dependen la cadena flex del panel y los selectores de adyacencia.\n".
            '  esperado: '.implode(' → ', $expected)."\n".
            '  emitido : '.implode(' → ', $vue)
        );
    }

    /**
     * La BANDA de progreso tiene su propio caso porque **no es del paso 2**: la comparten los pasos 2
     * y 3, vive fuera del bloque de cada uno en el Blade y trae el «volver» del flujo. Un diff que
     * empezara en el título del paso no la vería, y un motor que no la emitiera dejaría al cliente
     * sin salida y sin contador de fases.
     *
     * ⚠️ **Se renderiza a través del ARMAZÓN desde 4.3·1, y ese cambio es el que da valor al caso.**
     * Antes se renderizaba `DateStep` suelto, que montaba la banda él mismo; el gate salía verde
     * mientras `Sidebar.vue` le pasaba `progress: null` y `TimeStep` ni la importaba — o sea, el
     * cajón vivo no tenía «Volver» en ningún paso. Ahora la emite quien la emite de verdad.
     */
    public function test_the_booking_progress_band_emits_the_same_tree_in_both_engines(): void
    {
        $product = $this->product('Entrada 1h', TicketType::TYPE_ENTRY, 990);
        $this->slotsForNextDays($product, 5);

        $vue = $this->vueTree(2, [], 'bk-progress', api: $this->dateApiPayload($product->id),
            state: $this->clientState(step: 2, productId: $product->id), shellFromServer: false);

        $this->assertTree(__FUNCTION__, $vue,
            "La banda de progreso DIFIERE entre los dos motores.\n\n"
        );
    }

    /**
     * ⚠️ **Y también en el paso 3, que es donde faltaba.** La banda la pintan los DOS pasos del modo
     * «booking», pero el caso anterior solo cubría el 2 y `TimeStep.vue` ni siquiera importaba el
     * componente: el paso más denso del embudo iba sin «Volver» y sin contador. Un caso por paso, no
     * uno por componente.
     */
    public function test_the_booking_progress_band_is_also_emitted_on_the_time_step(): void
    {
        $product = $this->product('Entrada 1h', TicketType::TYPE_ENTRY, 990);
        $this->slotsForNextDays($product, 3);

        $vue = $this->vueTree(3, [], 'bk-progress',
            api: $this->timeApiPayload($product->id, now()->addDay()->toDateString(), '10:00:00', (int) $product->min_qty),
            state: $this->clientState(now()->addDay()->toDateString(), '10:00:00', (int) $product->min_qty, step: 3, productId: $product->id),
            shellFromServer: false);

        $this->assertTree(__FUNCTION__, $vue,
            "La banda de progreso del paso 3 DIFIERE entre los dos motores.\n\n"
        );
    }

    /**
     * **La guarda de la guarda.** Un diff de árboles que normaliza de más acaba comparando dos
     * cadenas vacías y pasando siempre. Aquí se comprueba que el normalizador CONSERVA lo que el
     * contrato necesita —las clases y el tipo de elemento— y que por tanto sabe distinguir.
     */
    public function test_the_normaliser_still_tells_two_different_trees_apart(): void
    {
        $button = $this->normalise('<div class="catalog"><button type="button" class="catalog__item"><span class="catalog__go"><svg viewBox="0 0 1 1"></svg></span></button></div>');
        $div = $this->normalise('<div class="catalog"><div class="catalog__item"><span class="catalog__go"><svg viewBox="0 0 1 1"></svg></span></div></div>');
        $noClass = $this->normalise('<div class="catalog"><button type="button"><span class="catalog__go"><svg viewBox="0 0 1 1"></svg></span></button></div>');

        $this->assertNotSame($button, $div, 'un `div` donde había un `button` TIENE que verse');
        $this->assertNotSame($button, $noClass, 'una clase que falta TIENE que verse');
        $this->assertNotEmpty($button, 'el normalizador no puede dejar el árbol vacío');
    }

    /** Y que el andamiaje de cada motor SÍ se ignora, o el test sería imposible de pasar. */
    public function test_the_normaliser_ignores_each_engines_scaffolding(): void
    {
        $withLivewire = $this->normalise('<button type="button" class="catalog__item" wire:click="selectType(1)" x-show="hit" data-search="x">a</button>');
        $withVue = $this->normalise('<button type="button" class="catalog__item" data-v-7ba5bd90 data-search="x">a</button>');

        $this->assertSame($withLivewire, $withVue);
    }

    /**
     * **El manifiesto no acumula fantasmas.**
     *
     * ⚠️ La foto congelada solo vale si sigue describiendo casos que existen: una entrada de un test
     * borrado o renombrado se queda ahí para siempre, engordando el fichero y dando una falsa
     * sensación de cobertura. Aquí se comprueba que cada clave apunta a un método REAL de esta clase.
     *
     * La dirección contraria —que todo caso esté en el manifiesto— la cubre `assertTree()` en el acto,
     * con su `assertArrayHasKey`.
     */
    public function test_the_frozen_manifest_has_no_orphan_entries(): void
    {
        $methods = array_map(
            fn (\ReflectionMethod $m): string => $m->getName(),
            (new \ReflectionClass(self::class))->getMethods(\ReflectionMethod::IS_PUBLIC)
        );

        $orphans = [];

        foreach (array_keys($this->manifest()) as $key) {
            $method = explode('#', $key)[0];

            if (! in_array($method, $methods, true)) {
                $orphans[] = $key;
            }
        }

        $this->assertSame(
            [], $orphans,
            "El manifiesto congelado tiene entradas de casos que ya no existen:\n  ".implode("\n  ", $orphans)."\n\n".
            'Regenéralo con `MANIFEST_REFRESH=1` tras borrar el fichero, o quítalas a mano.'
        );
    }

    // ── Herramientas ──────────────────────────────────────────────────────────────────────────

    /**
     * Un componente con la cesta sembrada con TODO lo que una línea puede llevar: un pack con señal,
     * con respuestas del evento y con un complemento que trae unidades gratis.
     *
     * Se siembra pasando por `addToCart()` en vez de escribiendo `$cart` a mano: así la línea la
     * compone el propio dominio —con su saneado y su selección de complementos resuelta— y el test no
     * fija una forma de cesta que el código real nunca produciría.
     */
    /**
     * Deja creado el pack con señal, su complemento incluido y sus franjas.
     *
     * ⚠️ Antes devolvía el componente Livewire recorrido hasta la cesta; con el motor retirado
     * (4.7·2b·3) solo monta el ESTADO DE DOMINIO, que es lo que los payloads de la API necesitan.
     * La cesta en sí la declara {@see self::fullCartItems()}.
     */
    private function setUpFullCart(bool $withGuestForm = false): void
    {
        $pack = $this->product('Cumpleaños', TicketType::TYPE_PACK, 5000);
        $pack->update([
            'deposit_type' => 'fixed',
            'deposit_value' => 3000,
            'event_fields' => [
                ['key' => 'celebrant', 'type' => 'text', 'required' => true, 'stage' => 'booking', 'label' => ['es' => 'Homenajeado']],
            ],
            // El post-form por invitado (#217) es lo que hace que la reserva confirmada prometa un
            // formulario. Va bajo bandera porque el resto de casos NO deben prometerlo: un pack sin
            // `guest_fields` que enseñara el aviso sería la incoherencia que aquel cambio cerró.
            ...($withGuestForm ? ['guest_fields' => [
                ['key' => 'guest_name', 'type' => 'text', 'required' => true, 'label' => ['es' => 'Nombre del invitado']],
            ]] : []),
        ]);
        $this->addon($pack, 'Tarta', 1000, ['is_included' => true, 'included_quantity' => 1, 'allow_extra' => true]);
        $this->slotsForNextDays($pack, 3);

    }

    /**
     * Los CUATRO estados del pie, cada uno con su paso y sus props.
     *
     * @return array<string, array{0: int, 1: Testable, 2: array<string, mixed>}>
     */
    private function footerStates(): array
    {
        $withCart = $this->setUpFullCart();

        $entry = $this->product('Entrada 1h', TicketType::TYPE_ENTRY, 990);
        $this->slotsForNextDays($entry, 5);

        // ⚠️ **Los CUATRO estados se alimentan del servidor, armazón incluido** (4.7·2b·2·B): el pie lo
        // compone `foot.js` aquí, no se toma del view-model de Livewire. Cada entrada lleva su carga de
        // API y el estado de cliente que el armazón necesita.
        //
        // ⚠️ La rama `cart` del paso 1 necesita **las dos cargas**: el catálogo para el paso y el
        // presupuesto para el pie. Es el único estado en el que el pie habla de algo que no está en la
        // pantalla que se pinta.
        // ⚠️ El día y la cantidad se DECLARAN, no se leen del view-model del motor que este fichero
        // compara (4.7·2b·3, paso 1). Los dos son lo que el propio montaje de arriba fijó: el día es
        // el que se le pasó a `selectDate()`, y la cantidad es el suelo del selector para una entrada.
        // Medido antes de sustituirlo: el componente devolvía exactamente estos dos valores.
        // ⚠️ Y medido también lo que ESTE test NO protege: mutar los dos deja los 34 casos en verde,
        // porque el diff compara ESTRUCTURA y el normalizador da el texto por bueno a propósito. No es
        // una pérdida —antes salían del componente y tampoco los protegía nadie aquí—: quien los
        // vigila son `SidebarMoneyParityTest` y `SidebarTextParityTest`, que es la división de trabajo
        // declarada. Lo único que este fixture necesita es un día CON franjas, no un día concreto.
        $onTimeDate = now()->addDay()->toDateString();
        $onTimeQty = (int) $entry->min_qty;

        return [
            // Rama `cart`: un único hijo, sin nota de IVA.
            'catálogo con cesta' => [
                1, [],
                array_merge($this->catalogApiPayload(), $this->cartApiPayload($this->fullCartItems())),
                $this->clientState(step: 1),
            ],
            // Rama `bar` sin desglose y con el CTA INACTIVO: el importe es «—» hasta elegir día.
            'calendario sin día' => [
                2, [], $this->dateApiPayload($entry->id),
                $this->clientState(step: 2, productId: $entry->id),
            ],
            // Rama `bar` sin desglose, con importe: una entrada se paga entera.
            'hora de una entrada' => [
                3, [],
                $this->timeApiPayload($entry->id, $onTimeDate, '10:00:00', $onTimeQty),
                $this->clientState($onTimeDate, '10:00:00', $onTimeQty, step: 3, productId: $entry->id),
            ],
            // Rama `bar` CON desglose: seis nodos más, y el disparador dentro del rótulo.
            'cesta con señal' => [
                4, [], $this->cartApiPayload($this->fullCartItems()), $this->clientState(step: 4),
            ],
        ];
    }

    /**
     * El view-model del carrito, traducido a la forma que el cajón recibe de verdad.
     *
     * ⚠️ **La traducción no es cosmética y ya mordió una vez** (con los complementos, en 4.2): el
     * view-model de Livewire usa `qty`, `name`, `subtotal`, y el presupuesto de la API publica
     * `quantity`, `product_name`, `subtotal_cents`. Alimentar al componente con los primeros dejaría
     * el diff verde mientras el cajón real pinta filas vacías. La paridad entre las dos fuentes se
     * comprueba aparte, en `SidebarCartParityTest`.
     *
     * @return array<string, mixed>
     */
    /**
     * La cesta del cliente y su PRESUPUESTO real (Fase 4 · paso 4.7·2b·2·B).
     *
     * ⚠️ **Aquí desaparece la tercera —y la más grande— de las traducciones que hacía el test.**
     * `cartProps()` renombraba a mano el view-model de Livewire a la forma de la API (`qty`→`quantity`,
     * `name`→`product_name`, `subtotal`→`subtotal_cents`… y **`product_id` inventado a 0**), así que el
     * gate nunca ejecutaba `cartRows()`, que es quien de verdad empareja cada línea tarificada con las
     * respuestas del pack. Y ese emparejamiento **es por `index`, no por posición**: una línea cuyo
     * producto dejó de venderse desaparece del presupuesto y el hueco es la única señal. Recorrer las
     * dos listas en paralelo pinta los precios de una línea sobre otra — el fallo que `#56` ya midió.
     *
     * ⚠️ **Lo que sí se traduce aquí, y es legítimo, es la CESTA**: no es una respuesta del servidor,
     * es estado del cliente. Livewire la guarda en sesión con `ticket_type_id`/`qty` y el cajón la
     * guarda en `localStorage` con `product_id`/`quantity` — la misma equivalencia que declara
     * `Http\Api\CartPayload`. Traducir ESTADO para que los dos motores partan de la misma cesta no es
     * lo mismo que traducir la RESPUESTA que el cliente tiene que saber leer.
     *
     * @return array<string, mixed>
     */
    /**
     * La cesta que un cliente tendría tras recorrer el flujo de {@see self::componentWithFullCart()},
     * **DECLARADA en vez de derivada del motor**.
     *
     * ⚠️ **Esto no es una comodidad, es un arreglo de método** (4.7·2b·3, paso 1). Hasta hoy el
     * payload que se le daba a Vue salía de `$component->get('cart')`, o sea **del motor Livewire que
     * este mismo fichero está comparando**: un fixture derivado del sujeto bajo prueba. Mientras los
     * dos motores existían el `assertSame($livewire, $vue)` lo tapaba —los dos lados nacían del
     * mismo sitio—, pero al quedar uno la circularidad se vuelve invisible y el manifiesto congelaría
     * lo que emitiera Vue ese día, sin nada que lo contradijera.
     *
     * Los valores están MEDIDOS, no supuestos: se volcó `$component->get('cart')` en las nueve
     * llamadas del fichero y las ocho no vacías resultaron **idénticas**. `qty` sale de `min_qty` del
     * pack —el suelo del selector— y se lee del DOMINIO, que es de donde lo lee también el cliente.
     *
     * @return list<array<string, mixed>>
     */
    private function fullCartItems(): array
    {
        $pack = TicketType::where('name->es', 'Cumpleaños')->firstOrFail();
        $tarta = TicketType::where('name->es', 'Tarta')->firstOrFail();

        return [[
            'ticket_type_id' => $pack->id,
            'date' => now()->addDay()->toDateString(),
            'time' => '10:00:00',
            'qty' => (int) $pack->min_qty,
            'event_data' => ['celebrant' => 'Mara'],
            'addons' => [['ticket_type_id' => $tarta->id, 'qty' => 1]],
        ]];
    }

    /** @param list<array<string, mixed>> $cart */
    private function cartApiPayload(array $cart): array
    {
        $items = array_map(fn (array $line): array => [
            'product_id' => (int) $line['ticket_type_id'],
            'date' => $line['date'],
            'time' => $line['time'],
            'quantity' => (int) $line['qty'],
            'event_data' => (array) ($line['event_data'] ?? []),
            'addons' => array_map(fn (array $addon): array => [
                'product_id' => (int) $addon['ticket_type_id'],
                'quantity' => (int) $addon['qty'],
            ], (array) ($line['addons'] ?? [])),
        ], $cart);

        if ($items === []) {
            return ['quote' => null, 'cart' => [], 'fieldsByProduct' => []];
        }

        // Las etiquetas del esquema del evento viven en la ficha del producto: el presupuesto NO
        // devuelve las respuestas del pack (RGPD, §4.4.6) y sin ellas no hay con qué emparejarlas.
        $fields = [];
        foreach ($items as $item) {
            $fields[$item['product_id']] = $this->getJson('/api/v1/catalog/products/'.$item['product_id'])
                ->assertOk()->json('event_fields');
        }

        return [
            'quote' => $this->postJson('/api/v1/orders/quote', ['items' => $items])->assertOk()->json(),
            'cart' => $items,
            'fieldsByProduct' => $fields,
        ];
    }

    /**
     * El view-model de Livewire traducido a mano a la forma de la API.
     *
     * ⚠️ **En retirada**: solo la usan el caso de la línea sin fecha —estado inalcanzable por el
     * cliente, ver ahí— y el paso 8, que se migra en el siguiente tramo. Cuando esos dos caigan, se va.
     */
    /**
     * Las props del ÚNICO caso que no puede alimentarse por la API (la línea sin fecha), DECLARADAS.
     *
     * ⚠️ Antes se renombraban a mano desde `$component->viewData('cartLines')`, o sea **desde el motor
     * que este fichero compara** (4.7·2b·3, paso 1). Los valores están MEDIDOS volcando ese view-model
     * antes de sustituirlo: una línea, `date: ''`, `qty: 2`, subtotal 1.980 = 2 × 990. Los que se
     * pueden derivar del dominio se derivan (nombre y precio del producto); los demás describen el
     * estado que el caso ejerce a propósito.
     *
     * @return array<string, mixed>
     */
    private function datelessCartProps(TicketType $product): array
    {
        return [
            'lines' => [[
                'index' => 0,
                'product_id' => 0,
                'product_name' => $product->tr('name'),
                'is_pack' => false,
                'date' => '',
                'time' => '10:00:00',
                'quantity' => 2,
                'subtotal_cents' => 2 * 990,
                'has_deposit' => false,
                'deposit_cents' => 2 * 990,
                'gate_remainder_cents' => 0,
                'addons' => [],
                'event' => [],
            ]],
            'confirmed' => false,
            'error' => '',
            'messages' => __('tickets'),
            'locale' => app()->getLocale(),
        ];
    }

    /**
     * Pausa las reservas y siembra los DOS canales de contacto.
     *
     * ⚠️ Los canales se siembran a propósito: sin ellos el aviso pinta un solo enlace —el de
     * `/contacto`— que para el normalizador es **indistinguible** del de WhatsApp (los dos son
     * `<a class="btn btn--lg">`, y `href`/`target`/`rel` no son atributos de contrato). El caso pasaría
     * sin probar ni que los dos canales conviven ni que solo el del teléfono lleva `btn--zone`.
     */
    private function pauseReservations(): void
    {
        Setting::updateOrCreate(['key' => 'reservations.paused'], ['value' => '1']);
        Setting::updateOrCreate(['key' => 'contact.phone'], ['value' => '+34 968 12 34 56']);
        Setting::updateOrCreate(['key' => 'contact.whatsapp'], ['value' => '+34 600-11-22-33']);
        Setting::flushMemo();
    }

    /**
     * El aviso que recibiría el cliente, compuesto **por el módulo del cliente ejecutado en Node**.
     *
     * ⚠️ **La primera versión lo recomponía en PHP y eso vaciaba el caso**: el diff habría comparado
     * el Blade contra un Vue alimentado por una réplica de la regla del cliente, así que la regla del
     * cliente —la de los canales, que `DECISIONES #38(g)` señala como «regresión funcional
     * silenciosa»— no la tocaba nadie. Se verificó: con la réplica, poner los canales en cascada
     * dejaba la suite entera en verde.
     *
     * @return array<string, mixed>|null
     */
    private function noticeProps(int $step): ?array
    {
        // ⚠️ Antes preguntaba `$component->instance()->showPausedNotice()`, o sea al motor retirado.
        // Ahora la decisión la toma quien de verdad manda: el SERVIDOR, por `GET /booking/status`,
        // que es exactamente lo que consulta el cajón. Y el paso lo declara el propio caso.
        $status = $this->getJson('/api/v1/booking/status')->assertOk()->json();

        if (($status['reservations_paused'] ?? false) !== true) {
            return null;
        }

        return $this->buildNoticeInNode([
            'status' => $status,
            'step' => $step,
            'messages' => __('tickets'),
        ]);
    }

    /**
     * Ejecuta `paused.js` en Node, que es lo que hace el cajón de verdad.
     *
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>|null
     */
    private function buildNoticeInNode(array $state): ?array
    {
        $script = <<<'JS'
            import { buildNotice } from 'file://__MODULE__';
            let raw = '';
            process.stdin.setEncoding('utf8');
            process.stdin.on('data', (c) => { raw += c; });
            process.stdin.on('end', () => {
                process.stdout.write(JSON.stringify({ notice: buildNotice(JSON.parse(raw)) }));
            });
            JS;

        $path = base_path('storage/framework/testing/dom-build-notice.mjs');
        @mkdir(dirname($path), 0775, true);
        file_put_contents($path, str_replace('__MODULE__', base_path('resources/js/sidebar/paused.js'), $script));

        $process = new Process(['node', $path], base_path());
        $process->setInput(json_encode($state, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        // ⚠️⚠️ **300 y no 60, y el número sale de una MEDICIÓN, no de un susto** (2026-09-02). Este
        // fichero arranca un `node` POR CASO —unos 38— y el `pre-push` los corre después de `build`,
        // `build:ssr` y `test:js`: bajo esa contención el proceso no es lento, **se queda sin CPU el
        // minuto entero** y salta `ProcessTimedOutException`. Medido: un render normal tarda
        // **~140 ms** (146/134/137 en tres pasadas), así que 60 s ya eran **430×** de margen y aun así
        // no bastaban. Con 300 el margen es ~2.000× y **la guarda sigue haciendo su trabajo**: un
        // proceso de verdad colgado sigue fallando.
        // ▶ **Esto es lo que estaba detrás del rojo transitorio del pre-push** que `DECISIONES #97`
        // dejó sin explicar el 2026-08-16 y que `#164` no pudo atribuir al reloj ni al orden: lo
        // capturó la trampa que aquel incidente motivó (el hook preserva el log y dice su nombre).
        // *Un rojo transitorio sin nombre no se puede arreglar; con nombre, se arregla en diez minutos.*
        // ⚠️ El arreglo de fondo sigue fichado en `DEUDA.md`: reutilizar UN proceso en vez de arrancar
        // uno por caso. Subir el tope quita el síntoma, no el coste.
        // ⚠️ **Son DOS los sitios que arrancan el renderizador** y los dos suben: una aserción de
        // conteo lo cazó al intentar cambiar solo uno.
        $process->setTimeout(300);
        $process->run();

        $this->assertTrue($process->isSuccessful(), "El módulo del aviso falló:\n".$process->getErrorOutput());

        return json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR)['notice'];
    }

    /**
     * El paso 1 se alimenta de las respuestas **REALES** del servidor (Fase 4 · paso 4.7·2b·2·B).
     *
     * ⚠️ **Antes esto devolvía el view-model del componente Livewire, y esa era su debilidad.** Al
     * pasarle a Vue un catálogo ya cocinado por el motor que se va, el gate no ejecutaba nunca la
     * traducción que de verdad corre en el navegador (`catalog.js`: agrupar por tipo y renombrar los
     * campos de la API). Un renombre ahí salía VERDE con el cajón real pintando filas vacías — y ya
     * pasó una vez con los complementos (`#46(a)`).
     *
     * Ahora se piden los dos endpoints que pide el cajón al abrirse y se entregan **crudos**: quien
     * los traduce es el propio cliente, dentro del renderizador. Es también lo que hace que este test
     * sobreviva a la retirada del componente: ya no depende de él para el paso 1.
     *
     * @return array<string, mixed>
     */
    private function catalogApiPayload(): array
    {
        return [
            'catalog' => $this->getJson('/api/v1/catalog/products')->assertOk()->json(),
            'config' => $this->getJson('/api/v1/config')->assertOk()->json(),
        ];
    }

    /**
     * Franjas para los próximos N días, para que el calendario tenga algo que ofrecer.
     *
     * Sin ellas el paso 2 se pinta vacío y el diff compararía dos calendarios sin días
     * seleccionables — es decir, pasaría sin mirar lo que de verdad importa: la celda con precio,
     * la seleccionada y la deshabilitada.
     */
    private function slotsForNextDays(TicketType $product, int $days, int $capacity = 20): void
    {
        for ($i = 1; $i <= $days; $i++) {
            // ⚠️ Las franjas son de la ZONA, no del producto, y su clave única es (zona, día, hora).
            // Un caso que siembre dos productos de la misma zona las pide dos veces: `firstOrCreate`
            // hace que compartirlas sea lo natural, que es justo lo que son.
            Slot::firstOrCreate(
                [
                    'zone_id' => $product->zone_id,
                    'date' => now()->addDays($i)->toDateString(),
                    'start_time' => '10:00:00',
                ],
                ['end_time' => '11:00:00', 'capacity' => $capacity, 'online_capacity' => $capacity],
            );
        }
    }

    /**
     * El paso 2 se alimenta de la oferta REAL de días (Fase 4 · paso 4.7·2b·2·B).
     *
     * ⚠️ **Lo que cambia no es de dónde salen los datos: es QUIÉN COMPONE LA REJILLA.** Antes se le
     * pasaba a Vue el `weeks` que ya había repartido el servidor, así que `buildWeeks()` —el reparto
     * que de verdad corre en el navegador— **no se ejecutaba nunca en el gate**. Es el hueco que
     * `SidebarCalendarParityTest` declara en su propio docblock. Ahora se entrega la respuesta cruda
     * de `GET availability/{producto}/dates` y compone el cliente.
     *
     * ⚠️ **El MES no se pasa: se deriva de la oferta dentro del renderizador**, igual que hace
     * `Sidebar.vue` al elegir producto (abre en el primero con oferta). Pasarlo dejaría esa regla
     * fuera del gate otra vez, que es el error que este paso corrige.
     *
     * @return array<string, mixed>
     */
    private function dateApiPayload(int $productId): array
    {
        return [
            'dates' => $this->getJson('/api/v1/availability/'.$productId.'/dates')->assertOk()->json(),
            // El ARMAZÓN lo necesita: la banda de progreso enseña el nombre del producto elegido, y el
            // cajón lo saca del CATÁLOGO que ya tiene en memoria, no de la ficha que está pidiendo.
            'catalog' => $this->getJson('/api/v1/catalog/products')->assertOk()->json(),
        ];
    }

    /**
     * Lo que NO viene del servidor: el día elegido y el idioma del documento.
     *
     * @return array<string, mixed>
     */
    private function clientState(
        ?string $selectedDate = null,
        ?string $selectedTime = null,
        ?int $quantity = null,
        ?int $step = null,
        ?int $productId = null,
        ?array $notice = null,
        bool $calendarOpen = false,
    ): array {
        return array_filter([
            'selectedDate' => $selectedDate,
            'selectedTime' => $selectedTime,
            'quantity' => $quantity,
            // Lo que el ARMAZÓN necesita para componer la banda y el pie con el código del cliente.
            'step' => $step,
            'productId' => $productId,
            'notice' => $notice,
            'locale' => app()->getLocale(),
            // ⚠️ **El calendario del paso 2 nace PLEGADO** (`#239`), así que su árbol solo existe en el
            // caso que lo abre. `false` se cae con el `array_filter` de abajo y el renderizador aplica
            // su propio respaldo, que es el mismo.
            'calendarOpen' => $calendarOpen ?: null,
        ], fn ($value) => $value !== null);
    }

    /**
     * El paso 3 se alimenta de las CUATRO respuestas que pide el cajón para pintarlo
     * (Fase 4 · paso 4.7·2b·2·B).
     *
     * ⚠️ **Aquí desaparece la traducción que el test hacía por su cuenta.** Para los complementos había
     * un `addonsAsApi()` que vivía en este mismo fichero y renombraba el view-model de Livewire a la forma de
     * la API —`id`→`product_id`, `qty`→`quantity`, `can_inc`→`can_increase`—, y esa traducción **es
     * justo el punto ciego**: el cajón real recibe la respuesta del endpoint, no una traducción del
     * test. Es el fallo que `#46(a)` documenta —diff verde, cajón con filas vacías— y el motivo de que
     * `SidebarAddonsParityTest` tuviera que cubrirlo por el otro lado.
     *
     * @return array<string, mixed>
     */
    private function timeApiPayload(int $productId, string $date, string $time, int $quantity): array
    {
        $root = '/api/v1';

        return [
            'times' => $this->postJson($root.'/availability/'.$productId.'/times', ['date' => $date])->assertOk()->json(),
            'dates' => $this->getJson($root.'/availability/'.$productId.'/dates')->assertOk()->json(),
            'product' => $this->getJson($root.'/catalog/products/'.$productId)->assertOk()->json(),
            'addons' => $this->postJson($root.'/catalog/products/'.$productId.'/addons', [
                'quantity' => $quantity, 'date' => $date, 'time' => $time,
            ])->assertOk()->json(),
            'catalog' => $this->getJson($root.'/catalog/products')->assertOk()->json(),
            // ⚠️ **`/config` entra el 2026-08-28** (`#239`): de ahí sale el umbral del aviso «casi
            // llena», y sin él el chip nunca lo pinta — el caso saldría verde sin cubrir nada del
            // rótulo. Es la misma lección que el `aria-current` del día elegido: un nodo solo está
            // cubierto por el caso que lo hace aparecer.
            'config' => $this->getJson($root.'/config')->assertOk()->json(),
        ];
    }

    /**
     * Un complemento enganchado al producto, con la config del pivote que el caso necesite.
     *
     * @param  array<string, mixed>  $pivot
     */
    private function addon(TicketType $product, string $name, ?int $priceCents, array $pivot = []): TicketType
    {
        $addon = TicketType::create([
            'name' => ['es' => $name], 'type' => TicketType::TYPE_ADDON,
            'seats_per_unit' => 0, 'is_sellable' => true, 'is_active' => true,
            'position' => (int) TicketType::max('position') + 1,
        ]);

        if ($priceCents !== null) {
            $addon->prices()->create(['rate_type_id' => $this->rateId, 'amount_cents' => $priceCents]);
        }

        $product->configurableAddons()->attach($addon->id, array_merge([
            'quantity_mode' => 'fixed',
            'position' => (int) $product->configurableAddons()->count() + 1,
        ], $pivot));

        return $addon;
    }

    /**
     * **El MANIFIESTO congelado** (Fase 4 · paso 4.7·1, `sidebar-spa.md` §4.2).
     *
     * ⚠️ **Toda la red de esta fase comparaba contra Livewire, y Livewire ya se fue** (4.7·2b·3). Un
     * diff «los dos motores emiten lo mismo» se habría quedado sin uno de los dos y habría pasado en
     * verde para siempre. El manifiesto es la foto del árbol VERIFICADO, tomada **mientras los dos
     * motores convivían**, justamente para que el contrato visual sobreviviera a la retirada.
     *
     * Mientras Livewire vivió se comprobaban las DOS cosas —motor contra motor y motor contra
     * manifiesto—, que es lo único que impedía que la foto envejeciera en silencio. Hoy queda solo la
     * segunda, y fue correcta el día que se tomó.
     */
    private const MANIFEST = 'tests/Fixtures/sidebar-dom-manifest.json';

    /*
     * ⚠️⚠️ **CÓMO QUEDÓ ESTE FICHERO TRAS ·2b·3** (`DECISIONES #111`; la medición previa, en `#83`).
     *
     * Este test nunca se auditó con la pregunta de `#75`: su sujeto ERA la comparación entre motores.
     * Tampoco se pudo convertir en «Vue contra el manifiesto» hasta el final, porque el manifiesto
     * estaba anclado en `$livewire` y Vue solo quedaba cubierta por transitividad, a través del
     * `assertSame($livewire, $vue)` de la primera línea — y el motor anclado era, además, el que
     * servía. Por eso murió DENTRO y no entero, en tres cambios exactos, todos hechos:
     *   1. `assertTree()`: fuera el `assertSame($livewire, $vue)` y **anclaje cambiado a `$vue`** en
     *      sus dos apariciones —la comparación contra el manifiesto y la rama de `MANIFEST_REFRESH`—.
     *      Borrar solo la primera habría dejado el manifiesto vigilando un motor inexistente.
     *   2. Retirados los renderizadores del motor viejo (`livewireTree*`) y la firma de `$livewire`.
     *   3. `assertBundleIsNotStale()` **se quedó**: es la única guarda de que el artefacto contra el
     *      que se renderiza no está rancio (`#69`).
     *
     * ⚠️ **Y el riesgo asumido, ahora ya real**: mientras hubo dos motores, regenerar el manifiesto
     * era seguro porque la igualdad entre ellos lo respaldaba. Sin el segundo, `MANIFEST_REFRESH=1`
     * acepta CUALQUIER deriva sin que nada la contradiga. Es el precio del árbol congelado que `#60`
     * aceptó; desde ·2b·3 la única guarda es la disciplina de decir POR QUÉ en el commit.
     *
     * ⚠️ Y una restricción que no se puede perder: **el NOMBRE de cada caso es la CLAVE del
     * manifiesto**. Los `…_in_both_engines` conservan su nombre histórico a propósito — renombrarlos
     * obligaría a regenerarlo, y regenerarlo sin segundo motor congelaría lo que Vue emitiera ese día.
     */

    /** Trazas capturadas en esta ejecución, por caso. Se usan para detectar entradas huérfanas. */
    private array $seen = [];

    /**
     * Compara los dos motores y, además, contra el árbol congelado.
     *
     * `$case` es el nombre del método; con varios árboles en un mismo caso se numeran por orden de
     * llamada, que dentro de un test es determinista.
     *
     * Para REGENERAR el manifiesto —tras un cambio de interfaz deliberado—:
     * `MANIFEST_REFRESH=1 php artisan test --filter=SidebarDomContractTest`
     */
    private function assertTree(string $case, string $vue, string $message): void
    {
        // ⚠️ **Desde 4.7·2b·3 el ancla es `$vue`; antes era `$livewire`** (`DECISIONES #111`). Al
        // quedar UN solo motor desaparece el `assertSame($livewire, $vue)` que los comparaba, y el
        // manifiesto congelado pasa a ser la ÚNICA referencia.
        // Re-anclar no cambió ni un byte del JSON, y se comprobó antes de tocarlo: mientras los dos
        // motores vivían, los 30 casos pasaban las DOS aserciones a la vez, luego `$manifest === $vue`.
        // ⚠️ Lo que sí cambia es lo que significa: ya no se compara motor contra motor, se compara **el
        // motor de hoy contra el árbol congelado el día que había dos**. Y `MANIFEST_REFRESH=1` ya no
        // tiene un segundo motor que lo contradiga, así que aceptaría cualquier deriva: regenerarlo
        // exige decir POR QUÉ en el commit.
        $manifest = $this->manifest();
        $key = $case.'#'.(count(array_filter(array_keys($this->seen), fn (string $k): bool => str_starts_with($k, $case.'#'))) + 1);
        $this->seen[$key] = $vue;

        if (getenv('MANIFEST_REFRESH') === '1') {
            $manifest[$key] = $vue;
            ksort($manifest);
            file_put_contents(base_path(self::MANIFEST), json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");

            return;
        }

        $this->assertArrayHasKey(
            $key, $manifest,
            "El manifiesto congelado no cubre «{$key}».\n".
            'Si el caso es nuevo, regenéralo: `MANIFEST_REFRESH=1 php artisan test --filter=SidebarDomContractTest`.'
        );

        $this->assertSame(
            $manifest[$key], $vue,
            "El árbol de «{$key}» ha CAMBIADO respecto al manifiesto congelado.\n".
            "⚠️ Eso es un cambio del CONTRATO VISUAL, no un detalle: 90 de los 292 selectores que\n".
            "estilan el cajón son estructurales. Si el cambio es deliberado, regenera el manifiesto\n".
            "(`MANIFEST_REFRESH=1`) y dilo en el commit; si no lo es, acabas de romper el estilo.\n\n".
            $this->firstDivergence($manifest[$key], $vue)
        );
    }

    /** @return array<string, string> */
    private function manifest(): array
    {
        $path = base_path(self::MANIFEST);

        if (! is_file($path)) {
            if (getenv('MANIFEST_REFRESH') === '1') {
                @mkdir(dirname($path), 0775, true);

                return [];
            }

            $this->fail(
                'Falta el manifiesto congelado del cajón ('.self::MANIFEST.").\n".
                'Genéralo con `MANIFEST_REFRESH=1 php artisan test --filter=SidebarDomContractTest`.'
            );
        }

        return json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * El árbol que emite Vue, renderizado en Node.
     *
     * Con `$shell` el paso se renderiza DENTRO del armazón (`Shell.vue`), que es la única forma de
     * comparar los nodos que no pertenecen a ningún paso —el velo, la banda y la zona scrollable—:
     * en el Blade viven fuera del bloque de cada paso.
     *
     * @param  array<string, mixed>  $props
     * @param  array<string, mixed>|null  $shell
     */
    /**
     * @param  array<string, mixed>|null  $api  respuestas CRUDAS del servidor (ver `renderVue`)
     */
    private function vueTree(int $step, array $props, string $anchor = 'catalog-acc', bool $withSiblings = false, ?array $shell = null, ?array $api = null, array $state = [], bool $shellFromServer = true): string
    {
        return $this->treeOf($this->renderVue($step, $props, $shell, $api, $state, $shellFromServer), $anchor, $withSiblings);
    }

    /**
     * Los bloques que cuelgan DIRECTAMENTE de `.purchase`, en orden de documento y por su primera
     * clase. Es lo que hace comparable la ESTRUCTURA del armazón sin entrar en el contenido de cada
     * bloque, que ya tiene sus propios casos.
     *
     * @return list<string>
     */
    private function shellBlocks(string $html): array
    {
        $xpath = new DOMXPath($this->parse($html));
        $root = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' purchase ')]")?->item(0);

        if (! $root instanceof DOMElement) {
            $this->fail('no se ha encontrado la raíz «purchase» en el HTML renderizado');
        }

        $blocks = [];

        foreach ($root->childNodes as $child) {
            if ($child instanceof DOMElement) {
                $blocks[] = preg_split('/\s+/', trim($child->getAttribute('class')))[0] ?? '';
            }
        }

        return $blocks;
    }

    /**
     * El HTML que emite Vue, renderizado en Node.
     *
     * @param  array<string, mixed>  $props
     * @param  array<string, mixed>|null  $shell
     */
    /**
     * @param  array<string, mixed>|null  $api  respuestas CRUDAS del servidor; si viene, manda sobre `$props`
     */
    private function renderVue(int $step, array $props, ?array $shell = null, ?array $api = null, array $state = [], bool $shellFromServer = true): string
    {
        $bundle = base_path('storage/ssr/render-sidebar.js');

        $this->assertFileExists(
            $bundle,
            'Falta el bundle SSR del cajón: corre `npm run build:ssr`. Node no puede cargar un `.vue` '.
            'sin compilar, así que el renderizador se construye con Vite. El `pre-push` ya lo hace.'
        );

        $this->assertBundleIsNotStale($bundle);

        // ⚠️ Con `api` las props NO viajan: las construye el renderizador con los módulos del cliente.
        // Mandar las dos cosas sería dejar abierta la puerta a que un paso «migrado» siguiera pintando
        // las props cocinadas por el test sin que nadie lo notara.
        $payload = $api === null
            ? ['step' => $step, 'props' => $props, 'shell' => $shell]
            : [
                'step' => $step, 'api' => $api, 'messages' => __('tickets'), 'state' => $state,
                'shell' => $shell, 'ui' => __('ui'), 'shellFromServer' => $shellFromServer,
            ];

        $process = new Process(['node', $bundle], base_path());
        $process->setInput(json_encode(
            array_filter($payload, fn ($value) => $value !== null),
            JSON_THROW_ON_ERROR
        ));
        // 300 y no 60: ver la medición en el otro arranque del renderizador de este mismo fichero.
        $process->setTimeout(300);
        $process->run();

        $this->assertTrue(
            $process->isSuccessful(),
            "El renderizador de Vue falló:\n".$process->getErrorOutput()
        );

        return $process->getOutput();
    }

    /**
     * ⚠️ **El bundle SSR es un ARTEFACTO, y uno rancio da VERDE FALSO.**
     *
     * Este test no renderiza las fuentes: renderiza `storage/ssr/render-sidebar.js`, que compila Vite.
     * Si alguien toca un módulo del cajón y no reconstruye, el gate compara **código viejo** — y eso no
     * es un rojo molesto, es un **verde que miente**. Medido el 2026-08-15: con `catalog.js` roto a
     * propósito (leyendo un campo que la API no publica) y el bundle sin reconstruir, el caso del
     * catálogo pasó en verde con 7 aserciones.
     *
     * En el `pre-push` no ocurre —el hook construye antes de la suite y `PrePushGateTest` lo vigila—,
     * pero al iterar en local sí, que es justo cuando más se cambian estos módulos.
     *
     * La comprobación es de fecha de modificación, que es lo que distingue «compilado después» de
     * «compilado antes» sin volver a compilar dentro del test (medio minuto por ejecución).
     */
    private function assertBundleIsNotStale(string $bundle): void
    {
        $built = (int) filemtime($bundle);
        $newer = [];

        $sources = array_merge(
            [base_path('scripts/render-sidebar.mjs')],
            glob(resource_path('js/sidebar/*.js')) ?: [],
            glob(resource_path('js/sidebar/*.vue')) ?: [],
            glob(resource_path('js/sidebar/steps/*.vue')) ?: [],
        );

        foreach ($sources as $source) {
            // Los `*.test.js` no entran en el bundle: cambiarlos no lo deja rancio.
            if (str_ends_with($source, '.test.js')) {
                continue;
            }

            if ((int) filemtime($source) > $built) {
                $newer[] = str_replace(base_path().'/', '', $source);
            }
        }

        $this->assertSame(
            [], $newer,
            "El bundle SSR del cajón está RANCIO: hay fuentes más nuevas que él.\n  ".
            implode("\n  ", $newer)."\n\n".
            "⚠️ Este test renderiza el bundle, no las fuentes, así que seguir sin reconstruir compara\n".
            "código VIEJO — y eso da verde aunque lo que acabas de escribir esté roto.\n".
            'Corre `npm run build:ssr` (el `pre-push` ya lo hace por ti antes de la suite).'
        );
    }

    /**
     * El árbol normalizado del nodo con esa clase, dentro del HTML dado.
     *
     * ⚠️ **Se localiza con el DOM y no con una expresión regular**, y la primera versión lo hacía
     * mal: el `x-on` de Alpine contiene una flecha `=>`, así que un `[^>]*` para saltar los
     * atributos corta a media etiqueta y el recorte empezaba en el nodo equivocado. Los dos motores
     * envuelven el paso en algo propio —Livewire su `wire:id`, Vue su raíz—, así que comparar desde
     * fuera mediría el andamiaje.
     */
    private function treeOf(string $html, string $class, bool $withSiblings = false): string
    {
        $dom = $this->parse($html);
        $xpath = new DOMXPath($dom);
        $node = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' {$class} ')]")?->item(0);

        if (! $node instanceof DOMElement) {
            $this->fail("no se ha encontrado el nodo «{$class}» en el HTML renderizado");
        }

        $lines = [];
        $this->describe($node, 0, $lines);

        // Hay pasos cuyo contenido son varios nodos HERMANOS —el paso 2 son título, calendario,
        // leyenda y un aviso— y no un solo contenedor. Comparar solo el primero dejaría fuera todo
        // lo demás, que es justo donde es fácil equivocarse.
        if ($withSiblings) {
            for ($sibling = $node->nextSibling; $sibling !== null; $sibling = $sibling->nextSibling) {
                $this->describe($sibling, 0, $lines);
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Árbol normalizado, una línea por nodo con su profundidad.
     *
     * El formato es legible a propósito: cuando el test falla, lo primero que hace falta es ver EN
     * QUÉ nodo divergen, y un volcado de HTML minificado no lo enseña.
     */
    private function normalise(string $html): string
    {
        $root = $this->parse('<div id="__root">'.$html.'</div>')->getElementById('__root');

        $lines = [];
        foreach ($root?->childNodes ?? [] as $child) {
            $this->describe($child, 0, $lines);
        }

        return implode("\n", $lines);
    }

    private function parse(string $html): DOMDocument
    {
        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();

        return $dom;
    }

    /**
     * Atributos que SÍ son contrato. Todo lo demás —el andamiaje de cada motor y los `id`
     * generados— se ignora.
     *
     * @var list<string>
     */
    /**
     * ⚠️ **`aria-current` entró aquí en el cierre de §6; `aria-expanded` NO puede entrar, y se midió.**
     *
     * Los dos motores marcan el estado de expansión del desglose de la señal, pero por caminos que el
     * HTML SERVIDO no hace comparables: Livewire lo declara como binding de Alpine
     * (`:aria-expanded="…"`), que este normalizador descarta junto al resto del andamiaje, y Vue lo
     * RENDERIZA en el SSR (`aria-expanded="false"`). Compararlo dejaba el pie en rojo por una
     * diferencia que no existe en el navegador. Que los dos lo declaren lo comprueba
     * `test_both_engines_announce_the_deposit_popover_state`, sobre el marcado de cada uno.
     */
    private const CONTRACT_ATTRIBUTES = ['role', 'type', 'disabled', 'aria-hidden', 'aria-label', 'aria-labelledby', 'aria-live', 'aria-current'];

    /** @param list<string> $lines */
    private function describe(DOMNode $node, int $depth, array &$lines): void
    {
        if (! $node instanceof DOMElement) {
            return;     // texto y comentarios: el contrato es la estructura, no la copia
        }

        $parts = [str_repeat('  ', $depth).'<'.$node->tagName];

        $classes = preg_split('/\s+/', trim($node->getAttribute('class'))) ?: [];
        $classes = array_values(array_filter($classes));
        sort($classes);     // el ORDEN de las clases no lo mira ningún selector

        if ($classes !== []) {
            $parts[] = 'class='.implode('.', $classes);
        }

        foreach (self::CONTRACT_ATTRIBUTES as $attribute) {
            if ($node->hasAttribute($attribute)) {
                // Los `aria-labelledby`/`id` apuntan a identificadores que cada motor puede generar
                // distintos; lo que importa es que el atributo ESTÉ, no su valor exacto.
                $value = in_array($attribute, ['aria-labelledby'], true) ? '…' : $node->getAttribute($attribute);
                $parts[] = $attribute.'='.$value;
            }
        }

        $lines[] = implode(' ', $parts).'>';

        // Dentro de un `<svg>` no se desciende: su interior es geometría, no estructura estilable.
        if (strtolower($node->tagName) === 'svg') {
            return;
        }

        foreach ($node->childNodes as $child) {
            $this->describe($child, $depth + 1, $lines);
        }
    }

    /** La primera línea en la que los dos árboles se separan, con su contexto. */
    private function firstDivergence(string $a, string $b): string
    {
        $left = explode("\n", $a);
        $right = explode("\n", $b);

        foreach ($left as $i => $line) {
            if (($right[$i] ?? null) === $line) {
                continue;
            }

            return "Primera diferencia (línea {$i}):\n".
                '  Livewire: '.$line."\n".
                '  Vue     : '.($right[$i] ?? '(no hay más nodos)')."\n";
        }

        return count($right) > count($left)
            ? 'Vue emite '.(count($right) - count($left))." nodos de MÁS.\n"
            : "Los árboles coinciden hasta donde llega el más corto.\n";
    }
}
