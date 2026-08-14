<?php

namespace Tests\Feature\Sidebar;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Identity\Models\User;
use App\Domain\Payments\Models\Payment;
use App\Domain\Platform\Models\Setting;
use App\Http\Sidebar\SidebarEntry;
use App\Livewire\Auth\Register;
use App\Livewire\Tickets\Purchase;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    protected function setUp(): void
    {
        parent::setUp();

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

        $livewire = $this->livewireTreeForStep(1);
        $vue = $this->vueTree(1, $this->catalogProps());

        $this->assertSame(
            $livewire, $vue,
            "El árbol del catálogo DIFIERE entre los dos motores.\n".
            'El contrato visual es el árbol (§4.2): 90 de 292 selectores del cajón son estructurales '.
            'o dependen del tipo de elemento, así que esta diferencia es estilo perdido aunque las '.
            "clases coincidan.\n\n".
            $this->firstDivergence($livewire, $vue)
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

        $component = Livewire::test(Purchase::class)->call('selectType', $product->id);

        $livewire = $this->livewireTree($component, 'wiz__title', withSiblings: true);
        $vue = $this->vueTree(2, $this->dateProps($component), 'wiz__title', withSiblings: true);

        $this->assertSame(
            $livewire, $vue,
            "El árbol del calendario DIFIERE entre los dos motores.\n\n".$this->firstDivergence($livewire, $vue)
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

        $component = Livewire::test(Purchase::class)
            ->call('selectType', $product->id)
            ->call('selectDate', now()->addDay()->toDateString());

        $this->assertNotNull($component->get('date'), 'el caso tiene que llegar con día elegido');
        $this->assertStringContainsString(
            'aria-current="date"', $component->html(),
            'y el servidor tiene que marcarlo, o este caso no mira nada'
        );

        $livewire = $this->livewireTree($component, 'wiz__title', withSiblings: true);
        $vue = $this->vueTree(2, $this->dateProps($component), 'wiz__title', withSiblings: true);

        $this->assertSame(
            $livewire, $vue,
            "El día elegido NO se marca igual en los dos motores.\n".
            '⚠️ `aria-current` es lo único que le dice a un lector de pantalla cuál está seleccionado: '.
            "la clase `is-selected` no se lee.\n\n".$this->firstDivergence($livewire, $vue)
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
        $component = Livewire::test(Purchase::class)
            ->call('selectType', $pack->id)
            ->call('selectDate', $date)
            ->call('goToTime')
            ->call('selectTime', '10:00:00');

        $livewire = $this->livewireTree($component, 'wiz__title', withSiblings: true);
        $vue = $this->vueTree(3, $this->timeProps($component), 'wiz__title', withSiblings: true);

        $this->assertSame(
            $livewire, $vue,
            "El árbol del paso de hora DIFIERE entre los dos motores.\n\n".$this->firstDivergence($livewire, $vue)
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
        $component = Livewire::test(Purchase::class)
            ->call('selectType', $pack->id)
            ->call('selectDate', $date)
            ->call('goToTime')
            ->call('selectTime', '10:00:00');

        $max = $component->viewData('maxQty');
        $min = $component->viewData('minQty');

        $this->assertGreaterThan($min, $max, 'sin margen entre mínimo y máximo el caso no probaría nada');

        foreach ([$min, $max] as $quantity) {
            $component->set('qty', $quantity);

            $livewire = $this->livewireTree($component, 'qtybox');
            $vue = $this->vueTree(3, $this->timeProps($component), 'qtybox');

            $this->assertSame(
                $livewire, $vue,
                "Con cantidad {$quantity} (mínimo {$min}, máximo {$max}) el selector NO se acota igual.\n".
                '⚠️ En un pack `available` y `max_quantity` no son el mismo número, y construir el '.
                "selector sobre el primero deja pedir invitados que el checkout rechaza.\n\n".
                $this->firstDivergence($livewire, $vue)
            );
        }
    }

    // ── El paso 4: la cesta ───────────────────────────────────────────────────────────────────

    /**
     * La cesta con TODO lo que una línea puede llevar, porque cada rama solo aparece con sus datos:
     * las respuestas del pack (`cart__event`), los complementos con su etiqueta de «incluido»
     * (`cart__addons` + `cart__addon-incl`) y la nota de señal (`cart__deposit`). Un pedido simple de
     * una entrada no emite ninguna de las cuatro, así que un caso así pasaría sin mirar nada.
     */
    public function test_the_cart_step_emits_the_same_tree_in_both_engines(): void
    {
        $component = $this->componentWithFullCart();

        $livewire = $this->livewireTree($component, 'wiz__title', withSiblings: true);
        $vue = $this->vueTree(4, $this->cartProps($component), 'wiz__title', withSiblings: true);

        $this->assertSame(
            $livewire, $vue,
            "El árbol del carrito DIFIERE entre los dos motores.\n\n".$this->firstDivergence($livewire, $vue)
        );
    }

    /**
     * ⚠️ **La cesta vacía en el paso 4 es alcanzable y no es un caso teórico**: `removeLine()` y
     * `mount()` miran `$this->cart`, pero el recuento sale del PRESUPUESTO, así que un producto
     * retirado de la venta con el cajón abierto deja la cesta «no vacía» y el contador a cero. Ahí el
     * paso pinta su aviso y el pie desaparece: la pantalla queda sin CTA y sin salida. Es la conducta
     * de los dos motores y hay que transcribirla igual.
     */
    public function test_the_empty_cart_step_emits_the_same_tree_in_both_engines(): void
    {
        $component = Livewire::test(Purchase::class)->set('step', 4);

        $livewire = $this->livewireTree($component, 'wiz__title', withSiblings: true);
        $vue = $this->vueTree(4, $this->cartProps($component), 'wiz__title', withSiblings: true);

        $this->assertSame(
            $livewire, $vue,
            "El árbol del carrito VACÍO DIFIERE entre los dos motores.\n\n".$this->firstDivergence($livewire, $vue)
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
     * Y el estado es alcanzable, no teórico: `Cart::sanitize()` conserva una línea con `date: ''`
     * (una cesta antigua o manipulada) y el presupuesto **la tarifica igual** —medido: subtotal
     * correcto con la tarifa de hoy—, así que el carrito la pinta.
     */
    public function test_a_cart_line_without_a_date_still_emits_its_row_container(): void
    {
        $product = $this->product('Entrada 1h', TicketType::TYPE_ENTRY, 990);
        $this->slotsForNextDays($product, 3);

        $component = Livewire::test(Purchase::class)
            ->set('cart', [[
                'ticket_type_id' => $product->id, 'date' => '', 'time' => '10:00:00',
                'qty' => 2, 'event_data' => [], 'addons' => [],
            ]])
            ->set('step', 4);

        $this->assertSame('', $component->viewData('cartLines')[0]['date'], 'el caso exige una línea sin fecha');

        $livewire = $this->livewireTree($component, 'cart__item');
        $vue = $this->vueTree(4, $this->cartProps($component), 'cart__item');

        $this->assertSame(
            $livewire, $vue,
            "La línea SIN fecha DIFIERE entre los dos motores.\n".
            'El contenedor `.cart__lines` se emite siempre, aunque quede vacío: el condicional del '.
            "Blade está DENTRO del `<div>`.\n\n".$this->firstDivergence($livewire, $vue)
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
        $component = Livewire::test(Purchase::class)
            ->set('step', 5)
            ->call('setAuthMode', 'register')
            ->call('setAuthMode', 'login');

        $livewire = $this->livewireTree($component, 'bk-back', withSiblings: true);
        $vue = $this->vueTree(5, $this->identifyProps(), 'bk-back', withSiblings: true);

        $this->assertSame(
            $livewire, $vue,
            "El árbol de la identificación DIFIERE entre los dos motores.\n".
            'Este paso no tiene banda de progreso, así que su «Volver» es propio; y el formulario que '.
            "cuelga de las pestañas es el login embebido.\n\n".
            $this->firstDivergence($livewire, $vue)
        );
    }

    /**
     * **La guarda del caso de arriba.** Si alguien lo «simplifica» a un `set('authMode', …)`, el diff
     * volvería a comparar dos armazones sin formulario y nadie lo notaría. Aquí se fija el hecho
     * medido: por clic hay formulario, por `set` no.
     */
    public function test_reaching_the_identify_step_by_setting_the_mode_hides_the_embedded_form(): void
    {
        $bySet = Livewire::test(Purchase::class)->set('step', 5)->set('authMode', 'login')->html();
        $byClick = Livewire::test(Purchase::class)->set('step', 5)
            ->call('setAuthMode', 'register')->call('setAuthMode', 'login')->html();

        $this->assertStringNotContainsString(
            'auth__form', $bySet,
            'si `set` ya renderizara el hijo, el caso del paso 5 podría simplificarse; hasta entonces, no'
        );
        $this->assertStringContainsString(
            'auth__form', $byClick,
            'el camino por clic tiene que renderizar el formulario, o el diff del paso 5 no compara nada'
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
        $component = Livewire::test(Purchase::class)->set('step', 5)->call('setAuthMode', 'register');

        $livewire = $this->livewireTree($component, 'bk-back', withSiblings: true);
        $vue = $this->vueTree(5, $this->identifyProps('register'), 'bk-back', withSiblings: true);

        $this->assertSame(
            $livewire, $vue,
            "El árbol del ALTA DIFIERE entre los dos motores.\n".
            'Ojo al honeypot (`.hp`), a la fila de email+teléfono y al `<small>` del hint: no se ven, '.
            "y son parte del contrato.\n\n".$this->firstDivergence($livewire, $vue)
        );
    }

    /**
     * ⚠️ **El banner de errores del alta, que es un árbol distinto**: `<strong>` + `<ul>` con un `<li>`
     * por aviso, **y además** cada aviso bajo su campo. Las dos cosas, no una.
     *
     * El caso anterior no puede verlo —un formulario recién abierto no tiene errores—, y el número de
     * `<li>` depende de cuántos campos fallen: se fuerza un envío vacío, que falla en los seis.
     */
    public function test_the_register_error_banner_emits_the_same_tree_in_both_engines(): void
    {
        // Un alta VACÍA: falla la validación de todos los campos obligatorios a la vez, que es lo que
        // llena la lista. Con un solo campo en rojo, un `<li>` de más o de menos no se vería.
        $register = Livewire::test(Register::class, ['embedded' => true])->call('register');
        $errors = $register->errors()->toArray();

        $this->assertGreaterThan(3, count($errors), 'el caso necesita varios campos en rojo para probar la lista');

        $livewire = $this->treeOf($register->html(), 'auth__errors');
        $vue = $this->treeOf(
            $this->renderVue(5, $this->identifyProps('register', $this->registerErrorsFrom($errors))),
            'auth__errors'
        );

        $this->assertSame(
            $livewire, $vue,
            "El banner de errores del alta DIFIERE entre los dos motores.\n".
            "Es `<strong>` + `<ul>` con un `<li>` por aviso.\n\n".$this->firstDivergence($livewire, $vue)
        );
    }

    /**
     * El paso 7 — «revisa tu correo». Son tres nodos, pero uno lleva `role="status"`, que es contrato:
     * lo anuncia el lector de pantalla sin robar el foco.
     */
    public function test_the_verify_email_step_emits_the_same_tree_in_both_engines(): void
    {
        $component = Livewire::test(Purchase::class)->set('step', 7);

        $livewire = $this->livewireTree($component, 'purchase__confirm', withSiblings: true);
        $vue = $this->vueTree(7, ['messages' => __('tickets')], 'purchase__confirm', withSiblings: true);

        $this->assertSame(
            $livewire, $vue,
            "El árbol de «revisa tu correo» DIFIERE entre los dos motores.\n\n".$this->firstDivergence($livewire, $vue)
        );
    }

    /**
     * Los avisos del bag de Livewire → la forma que compone `register.js` desde el sobre de la API.
     *
     * @param  array<string, array<int, string>>  $errors
     * @return array<string, mixed>
     */
    private function registerErrorsFrom(array $errors): array
    {
        $fields = [];
        foreach ($errors as $field => $messages) {
            $fields[$field] = $messages[0] ?? '';
        }

        // El mismo orden que fija `register.js`: el de las reglas de validación.
        $order = ['name', 'email', 'phone', 'password', 'accept_privacy', 'accept_terms', 'marketing'];
        $summary = [];
        foreach ($order as $key) {
            if (isset($fields[$key])) {
                $summary[] = $fields[$key];
            }
        }
        foreach ($fields as $key => $message) {
            if (! in_array($key, $order, true)) {
                $summary[] = $message;
            }
        }

        return ['summary' => $summary, 'fields' => $fields];
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
                    'accept_privacy' => __('account.register.accept_privacy', ['url' => route('legal.privacidad')]),
                    'accept_terms' => __('account.register.accept_terms', ['url' => route('legal.condiciones')]),
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
        $component = $this->componentWithFullCart();
        $this->actingAs(User::factory()->create());
        $component->call('checkout');

        $this->assertSame(8, (int) $component->get('step'), 'el caso tiene que llegar al paso de pago');

        $livewire = $this->livewireTree($component, 'bk-back', withSiblings: true);
        $vue = $this->vueTree(8, $this->payProps($component), 'bk-back', withSiblings: true);

        $this->assertSame(
            $livewire, $vue,
            "El árbol de la pantalla de PAGO DIFIERE entre los dos motores.\n".
            'Se parece al carrito, pero no es el carrito: `cart--summary`, sin botón de quitar y con el '.
            "precio en un `<span>` sin clase.\n\n".$this->firstDivergence($livewire, $vue)
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
        $component = $this->componentWithFullCart();
        $this->actingAs(User::factory()->create());
        $component->call('checkout');

        $footer = $component->viewData('footer');

        $this->assertSame('band', $footer['splitMode'] ?? null, 'el paso de pago tiene que pedir la banda');
        $this->assertNotNull($footer['split'] ?? null, 'y el caso necesita señal, o no hay desglose que comparar');

        $livewire = $this->livewireTree($component, 'bk-paybreakdown', withSiblings: true);
        $vue = $this->vueTree(8, $this->payProps($component), 'bk-paybreakdown', withSiblings: true, shell: $this->shellProps($component));

        $this->assertSame(
            $livewire, $vue,
            "La banda de desglose del pago DIFIERE entre los dos motores.\n".
            "Va FUERA del scroll y pegada encima del pie: de ese orden depende su borde.\n\n".
            $this->firstDivergence($livewire, $vue)
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
        $component = $this->componentWithFullCart();
        $this->actingAs(User::factory()->create());
        $component->call('checkout')->call('confirmReservation');

        $this->assertSame(9, (int) $component->get('step'), 'el caso tiene que llegar a la redirección');

        $livewire = $this->livewireTree($component, 'purchase__redirecting', withSiblings: true);
        $vue = $this->vueTree(9, ['form' => $this->gatewayFormProps($component), 'messages' => __('tickets')], 'purchase__redirecting', withSiblings: true);

        $this->assertSame(
            $livewire, $vue,
            "El árbol de la redirección DIFIERE entre los dos motores.\n\n".$this->firstDivergence($livewire, $vue)
        );
    }

    /**
     * El view-model del paso 8: las mismas líneas que el carrito, en la forma de la API.
     *
     * @return array<string, mixed>
     */
    private function payProps(Testable $component): array
    {
        return [
            'lines' => $this->cartProps($component)['lines'],
            'error' => '',
            'messages' => __('tickets'),
            'locale' => app()->getLocale(),
        ];
    }

    /**
     * El formulario de la pasarela en la forma que publica la API (`payment.fields` es un mapa) y que
     * `pay.js` traduce a lista.
     *
     * @return array<string, mixed>
     */
    private function gatewayFormProps(Testable $component): array
    {
        $data = (array) $component->get('redsysFormData');

        return [
            'url' => $data['gatewayUrl'] ?? '',
            'method' => 'POST',
            'fields' => [
                ['name' => 'Ds_SignatureVersion', 'value' => $data['signatureVersion'] ?? ''],
                ['name' => 'Ds_MerchantParameters', 'value' => $data['params'] ?? ''],
                ['name' => 'Ds_Signature', 'value' => $data['signature'] ?? ''],
            ],
        ];
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

        $component = $this->confirmedComponent(paid: true);

        $confirmation = $component->viewData('confirmation');
        $this->assertSame('paid', $confirmation['status'], 'el caso necesita el pedido PAGADO, o no hay desglose');
        $this->assertGreaterThan(0, $confirmation['pending_at_park'], 'y algo pendiente en el parque');
        $this->assertNotNull($component->viewData('registration'), 'y el enlace de registro configurado');

        $livewire = $this->livewireTree($component, 'purchase__confirm', withSiblings: true);
        $vue = $this->vueTree(6, $this->confirmedProps($component), 'purchase__confirm', withSiblings: true);

        $this->assertSame(
            $livewire, $vue,
            "El árbol de la RESERVA CREADA difiere entre los dos motores.\n".
            "Es el más largo del cajón y casi todo en él es condicional.\n\n".$this->firstDivergence($livewire, $vue)
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
        $component = $this->confirmedComponent(paid: false, withGuestForm: true);

        $confirmation = $component->viewData('confirmation');
        $this->assertSame('pending', $confirmation['status'], 'el caso necesita el pedido PENDIENTE');
        $this->assertTrue((bool) $confirmation['has_guest_form'], 'y una reserva que pide formulario de invitados');
        $this->assertNull($component->viewData('registration'), 'y ninguna URL de registro configurada');

        $livewire = $this->livewireTree($component, 'purchase__confirm', withSiblings: true);
        $vue = $this->vueTree(6, $this->confirmedProps($component), 'purchase__confirm', withSiblings: true);

        $this->assertSame(
            $livewire, $vue,
            "El árbol de la reserva creada de un pedido PENDIENTE difiere entre los dos motores.\n\n".
            $this->firstDivergence($livewire, $vue)
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
        $component = Livewire::test(Purchase::class)->set('step', 6)->set('orderCode', 'R-ABC123');

        $this->assertNull($component->viewData('confirmation'), 'el caso tiene que llegar SIN resumen');

        $livewire = $this->livewireTree($component, 'purchase__confirm', withSiblings: true);
        $vue = $this->vueTree(6, [
            'confirmation' => null,
            'orderCode' => 'R-ABC123',
            'registration' => null,
            'messages' => __('tickets'),
            'locale' => app()->getLocale(),
        ], 'purchase__confirm', withSiblings: true);

        $this->assertSame(
            $livewire, $vue,
            "El árbol de la reserva creada SIN resumen difiere entre los dos motores.\n".
            "Es lo que ve quien perdió la sesión entre la pasarela y la vuelta.\n\n".
            $this->firstDivergence($livewire, $vue)
        );
    }

    /**
     * Un componente que ha comprado de verdad y está en la pantalla de reserva creada.
     *
     * Se llega **pagando**: `checkout()` + `confirmReservation()` crean el pedido con su hold y su
     * cobro abierto, igual que en producción. Sembrar un `Order` a mano fijaría una forma de pedido que
     * el código real no produce —y el resumen sale del pedido, no de la cesta—.
     */
    private function confirmedComponent(bool $paid, bool $withGuestForm = false): Testable
    {
        $component = $this->componentWithFullCart($withGuestForm);
        $this->actingAs(User::factory()->create());
        $component->call('checkout')->call('confirmReservation');

        $code = (string) $component->get('orderCode');
        $this->assertNotSame('', $code, 'el caso tiene que haber creado el pedido');

        if ($paid) {
            // La vuelta OK de la pasarela deja el pedido pagado; aquí solo hace falta ese HECHO, no
            // volver a ejecutar el receptor de la respuesta firmada (que tiene sus propios tests).
            Order::where('code', $code)->update(['status' => Order::STATUS_PAID, 'paid_at' => now()]);
        }

        return $component->set('step', 6);
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
    private function confirmedProps(Testable $component): array
    {
        $confirmation = $component->viewData('confirmation');

        return [
            'confirmation' => $confirmation === null ? null : [
                'code' => $confirmation['code'],
                'status' => $confirmation['status'],
                'total_cents' => $confirmation['total'],
                'online_cents' => $confirmation['online'],
                'park_cents' => $confirmation['pending_at_park'],
                'has_guest_form' => (bool) $confirmation['has_guest_form'],
                'lines' => array_map(fn (array $line): array => [
                    'product_name' => $line['name'],
                    'is_pack' => $line['is_pack'],
                    'quantity' => $line['qty'],
                    'date' => $line['date'],
                    'time' => $line['time'],
                    'subtotal_cents' => $line['subtotal'],
                    'has_deposit' => (bool) $line['has_deposit'],
                    'deposit_cents' => $line['deposit'],
                    'gate_remainder_cents' => $line['gate_remainder'],
                    'addons' => array_map(fn (array $addon): array => [
                        'product_name' => $addon['name'],
                        'quantity' => $addon['qty'],
                        'free_quantity' => $addon['free_qty'],
                        'subtotal_cents' => $addon['subtotal'],
                    ], $line['addons']),
                    'event' => $line['event'],
                ], $confirmation['lines']),
            ],
            'orderCode' => (string) $component->get('orderCode'),
            'registration' => $component->viewData('registration'),
            'messages' => __('tickets'),
            'locale' => app()->getLocale(),
        ];
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
        $component = $this->declinedComponent();

        $this->assertSame(10, (int) $component->get('step'), 'el caso tiene que llegar al pago denegado');
        $this->assertNotNull($component->get('declinedReasonText'), 'y traer el motivo del rechazo');

        $livewire = $this->livewireTree($component, 'purchase__failed', withSiblings: true);
        $vue = $this->vueTree(10, $this->declinedProps($component), 'purchase__failed', withSiblings: true);

        $this->assertSame(
            $livewire, $vue,
            "El árbol del PAGO DENEGADO difiere entre los dos motores.\n".
            "El pedido sigue vivo aquí: esta pantalla es la segunda oportunidad, no un error.\n\n".
            $this->firstDivergence($livewire, $vue)
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
        $component = $this->declinedComponent(responseCode: null);

        $this->assertSame(
            __('tickets.payment_failed.reasons.default'), $component->get('declinedReasonText'),
            'sin código, el servidor cae al motivo genérico — no a null'
        );

        $livewire = $this->livewireTree($component, 'purchase__failed', withSiblings: true);
        $vue = $this->vueTree(10, $this->declinedProps($component), 'purchase__failed', withSiblings: true);

        $this->assertSame($livewire, $vue, $this->firstDivergence($livewire, $vue));
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
        $component = $this->verifyingComponent();

        $this->assertSame(11, (int) $component->get('step'), 'el caso tiene que llegar a «verificando»');

        $livewire = $this->livewireTree($component, 'purchase__verifying', withSiblings: true);
        $vue = $this->vueTree(11, $this->verifyingProps($component), 'purchase__verifying', withSiblings: true);

        $this->assertSame(
            $livewire, $vue,
            "El árbol de «verificando el pago» difiere entre los dos motores.\n\n".
            $this->firstDivergence($livewire, $vue)
        );
    }

    /**
     * Un componente en el paso 10, con un pago REALMENTE rechazado.
     *
     * ⚠️ **Se llega por la costura, no con un `->set('step', 10)`**: `declinedReasonText` lo compone
     * `mount()` a partir del último `Payment` fallido, así que colocar el paso a mano dejaría el motivo
     * vacío y el caso compararía dos pantallas sin su bloque más frágil.
     */
    private function declinedComponent(?string $responseCode = '0101'): Testable
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

        return Livewire::test(Purchase::class);
    }

    /** Un componente en el paso 11, al que se llega por la misma costura. */
    private function verifyingComponent(): Testable
    {
        SidebarEntry::verifying($this->purchasedOrderCode());

        return Livewire::test(Purchase::class);
    }

    /** Compra REAL de punta a punta, para que el pedido lo cree el dominio y no el test. */
    private function purchasedOrderCode(): string
    {
        $component = $this->componentWithFullCart();
        $this->actingAs(User::factory()->create());
        $component->call('checkout')->call('confirmReservation');

        $code = (string) $component->get('orderCode');
        $this->assertNotSame('', $code, 'el caso tiene que haber creado el pedido');

        return $code;
    }

    /**
     * El view-model del paso 10 en la forma que recibe el cajón.
     *
     * ⚠️ **`reason` llega YA traducido en los dos motores, pero por caminos distintos**: Livewire lo
     * resuelve en servidor y el cajón lo saca del diccionario que ya viaja en el montaje, usando
     * `declined_reason` como clave. Que las dos rutas den el mismo texto lo comprueba
     * `SidebarOutcomeParityTest`, recorriendo el mapa entero del servidor.
     *
     * @return array<string, mixed>
     */
    private function declinedProps(Testable $component): array
    {
        return [
            'orderCode' => (string) $component->get('orderCode'),
            'reason' => (string) $component->get('declinedReasonText'),
            'retrying' => false,
            'contactUrl' => route('contacto'),
            'messages' => __('tickets'),
        ];
    }

    /** @return array<string, mixed> */
    private function verifyingProps(Testable $component): array
    {
        return [
            'orderCode' => (string) $component->get('orderCode'),
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

        $component = Livewire::test(Purchase::class)->call('selectType', $product->id);

        // Con las reservas abiertas, este estado lleva banda Y pie: es lo que hace significativa su
        // ausencia después.
        $this->assertSame(
            ['jj-loading', 'bk-progress', 'purchase__scroll', 'bk-foot'],
            $this->shellBlocks($component->html()),
            'sin banda y sin pie de partida, la ausencia que este caso comprueba no probaría nada'
        );

        $this->pauseReservations();
        $component->call('$refresh');

        $this->assertNotNull($component->viewData('bookingProgress'), 'el servidor sigue componiendo la banda en pausa');
        $this->assertNotNull($component->viewData('footer'), 'el servidor sigue componiendo el pie en pausa');

        $livewire = $this->livewireTree($component, 'jj-loading', withSiblings: true);
        $vue = $this->vueTree(2, $this->dateProps($component), 'jj-loading', withSiblings: true, shell: $this->shellProps($component));

        $this->assertSame(
            $livewire, $vue,
            "El cajón EN PAUSA DIFIERE entre los dos motores.\n".
            'El aviso no solo sustituye el contenido: apaga también la banda de progreso, el pie y la '.
            "banda de desglose del pago — la misma condición gobierna los cuatro sitios.\n\n".
            $this->firstDivergence($livewire, $vue)
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
            $component = Livewire::test(Purchase::class)->set('step', $step);

            $this->assertTrue(
                $component->instance()->showPausedNotice(),
                "el servidor ha dejado de tapar el paso {$step}: revisa `showPausedNotice()`"
            );
            $this->assertStringContainsString('purchase__maint', $component->html());

            $vue = $this->renderVue($step, [], $this->shellProps($component));

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
        foreach ($this->footerStates() as $label => [$step, $component, $props]) {
            $livewire = $this->livewireTree($component, 'bk-foot');
            $vue = $this->vueTree($step, $props, 'bk-foot', shell: $this->shellProps($component));

            $this->assertSame(
                $livewire, $vue,
                "El pie del estado «{$label}» DIFIERE entre los dos motores.\n".
                "Son tres árboles distintos: la barra-carrito, la barra sin desglose y la barra con él.\n\n".
                $this->firstDivergence($livewire, $vue)
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
            $component = Livewire::test(Purchase::class)->set('step', $step);

            $this->assertNull(
                $component->viewData('footer'),
                "el servidor no emite pie en el paso {$step} con la cesta vacía"
            );

            $this->assertStringNotContainsString(
                'bk-foot', $component->html(),
                "Livewire no debería emitir el pie en el paso {$step} con la cesta vacía"
            );

            $props = $step === 1 ? $this->catalogProps() : $this->cartProps($component);
            $vue = $this->renderVue($step, $props, $this->shellProps($component));

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
        $component = $this->componentWithFullCart();
        $footer = $component->viewData('footer');

        $this->assertSame('popover', $footer['splitMode'] ?? null, 'el caso necesita el pie con ⓘ');

        $this->assertStringContainsString(
            ':aria-expanded', $component->html(),
            'el Blade ha dejado de anunciar si el desglose está abierto'
        );

        $vue = $this->renderVue(4, $this->cartProps($component), $this->shellProps($component));

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

        $component = Livewire::test(Purchase::class);

        $livewire = $this->livewireTree($component, 'jj-loading', withSiblings: true);
        $vue = $this->vueTree(1, $this->catalogProps(), 'jj-loading', withSiblings: true, shell: $this->shellProps($component));

        $this->assertSame(
            $livewire, $vue,
            "El ARMAZÓN del cajón DIFIERE entre los dos motores.\n".
            'Son el velo de carga, la banda de progreso y la zona scrollable: los nodos que sostienen '.
            "la cadena flex del panel (scroll que recorta + pie anclado al fondo).\n\n".
            $this->firstDivergence($livewire, $vue)
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

        $component = Livewire::test(Purchase::class)->call('selectType', $product->id);

        $livewire = $this->shellBlocks($component->html());
        $vue = $this->shellBlocks($this->renderVue(2, $this->dateProps($component), $this->shellProps($component)));

        $this->assertContains('bk-progress', $livewire, 'el paso 2 tiene que llevar banda, o el caso no prueba el orden');
        $this->assertContains('bk-foot', $livewire, 'el paso 2 tiene que llevar pie, o el orden que se compara es trivial');

        $expected = array_values(array_diff($livewire, self::SHELL_BLOCKS_NOT_YET_IN_SPA));

        $this->assertSame(
            $expected, $vue,
            "Los bloques del armazón NO salen en el mismo orden en los dos motores.\n".
            'La banda va pegada arriba, el pie anclado abajo y el scroll en medio; de ese orden dependen '.
            "la cadena flex del panel y los selectores de adyacencia.\n".
            '  Livewire: '.implode(' → ', $livewire)."\n".
            '  Vue     : '.implode(' → ', $vue)."\n".
            '  (descontando lo aún no transcrito: '.implode(', ', self::SHELL_BLOCKS_NOT_YET_IN_SPA).')'
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

        $component = Livewire::test(Purchase::class)->call('selectType', $product->id);

        $livewire = $this->livewireTree($component, 'bk-progress');
        $vue = $this->vueTree(2, $this->dateProps($component), 'bk-progress', shell: $this->shellProps($component));

        $this->assertSame(
            $livewire, $vue,
            "La banda de progreso DIFIERE entre los dos motores.\n\n".$this->firstDivergence($livewire, $vue)
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

        $component = Livewire::test(Purchase::class)
            ->call('selectType', $product->id)
            ->call('selectDate', now()->addDay()->toDateString())
            ->call('goToTime')
            ->call('selectTime', '10:00:00');

        $livewire = $this->livewireTree($component, 'bk-progress');
        $vue = $this->vueTree(3, $this->timeProps($component), 'bk-progress', shell: $this->shellProps($component));

        $this->assertSame(
            $livewire, $vue,
            "La banda de progreso del paso 3 DIFIERE entre los dos motores.\n\n".$this->firstDivergence($livewire, $vue)
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

    // ── Herramientas ──────────────────────────────────────────────────────────────────────────

    /**
     * Un componente con la cesta sembrada con TODO lo que una línea puede llevar: un pack con señal,
     * con respuestas del evento y con un complemento que trae unidades gratis.
     *
     * Se siembra pasando por `addToCart()` en vez de escribiendo `$cart` a mano: así la línea la
     * compone el propio dominio —con su saneado y su selección de complementos resuelta— y el test no
     * fija una forma de cesta que el código real nunca produciría.
     */
    private function componentWithFullCart(bool $withGuestForm = false): Testable
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

        return Livewire::test(Purchase::class)
            ->call('selectType', $pack->id)
            ->call('selectDate', now()->addDay()->toDateString())
            ->call('goToTime')
            ->call('selectTime', '10:00:00')
            ->set('eventData', ['celebrant' => 'Mara'])
            ->call('incAddon', TicketType::where('name->es', 'Tarta')->value('id'))
            ->call('addToCart');
    }

    /**
     * Los CUATRO estados del pie, cada uno con su paso y sus props.
     *
     * @return array<string, array{0: int, 1: Testable, 2: array<string, mixed>}>
     */
    private function footerStates(): array
    {
        $withCart = $this->componentWithFullCart();

        $entry = $this->product('Entrada 1h', TicketType::TYPE_ENTRY, 990);
        $this->slotsForNextDays($entry, 5);
        $onCalendar = Livewire::test(Purchase::class)->call('selectType', $entry->id);

        $onTime = Livewire::test(Purchase::class)
            ->call('selectType', $entry->id)
            ->call('selectDate', now()->addDay()->toDateString())
            ->call('goToTime')
            ->call('selectTime', '10:00:00');

        return [
            // Rama `cart`: un único hijo, sin nota de IVA.
            'catálogo con cesta' => [1, (clone $withCart)->set('step', 1), $this->catalogProps()],
            // Rama `bar` sin desglose y con el CTA INACTIVO: el importe es «—» hasta elegir día.
            'calendario sin día' => [2, $onCalendar, $this->dateProps($onCalendar)],
            // Rama `bar` sin desglose, con importe: una entrada se paga entera.
            'hora de una entrada' => [3, $onTime, $this->timeProps($onTime)],
            // Rama `bar` CON desglose: seis nodos más, y el disparador dentro del rótulo.
            'cesta con señal' => [4, $withCart, $this->cartProps($withCart)],
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
    private function cartProps(Testable $component): array
    {
        $lines = array_map(fn (array $line): array => [
            'index' => $line['index'],
            'product_id' => 0,
            'product_name' => $line['name'],
            'is_pack' => $line['is_pack'],
            'date' => $line['date'],
            'time' => $line['time'],
            'quantity' => $line['qty'],
            'subtotal_cents' => $line['subtotal'],
            'has_deposit' => $line['has_deposit'],
            'deposit_cents' => $line['deposit'],
            'gate_remainder_cents' => $line['gate_remainder'],
            'addons' => array_map(fn (array $addon): array => [
                'product_id' => 0,
                'product_name' => $addon['name'],
                'quantity' => $addon['qty'],
                'free_quantity' => $addon['free_qty'],
                'subtotal_cents' => $addon['subtotal'],
            ], $line['addons']),
            'event' => $line['event'],
        ], $component->viewData('cartLines'));

        // El carrito solo pinta las líneas TARIFICADAS. Con la cesta vacía enseña su aviso, y con el
        // recuento a cero también: el contador sale del presupuesto, no del array (fallo P8).
        return [
            'lines' => $component->viewData('cartCount') > 0 ? $lines : [],
            'confirmed' => (bool) $component->get('confirmed'),
            'error' => '',
            'messages' => __('tickets'),
            'locale' => app()->getLocale(),
        ];
    }

    /**
     * Las props del ARMAZÓN, tomadas del propio componente Livewire.
     *
     * `busy` va a `false` a propósito: en Livewire el velo está SIEMPRE en el HTML servido y lo tapa
     * `wire:loading`, y en Vue lo tapa `v-show`. El normalizador descarta `style`, así que los dos
     * árboles coinciden — lo que se compara es que el NODO esté, no si se ve.
     *
     * @return array<string, mixed>
     */
    private function shellProps(Testable $component): array
    {
        return [
            'busy' => false,
            'notice' => $this->noticeProps($component),
            'progress' => $component->viewData('bookingProgress'),
            // El pie se toma del SERVIDOR, igual que la banda: aquí se compara el marcado. Que el
            // cliente componga el mismo view-model lo comprueba `SidebarCartParityTest`.
            'footer' => $component->viewData('footer'),
            'messages' => __('tickets'),
            'ui' => __('ui'),
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
    private function noticeProps(Testable $component): ?array
    {
        if (! $component->instance()->showPausedNotice()) {
            return null;
        }

        return $this->buildNoticeInNode([
            'status' => $this->getJson('/api/v1/booking/status')->assertOk()->json(),
            'step' => (int) $component->get('step'),
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
        $process->setTimeout(60);
        $process->run();

        $this->assertTrue($process->isSuccessful(), "El módulo del aviso falló:\n".$process->getErrorOutput());

        return json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR)['notice'];
    }

    /** @return array<string, mixed> */
    private function catalogProps(): array
    {
        // El mismo view-model que el componente Livewire compone para su vista. Se toma de ÉL y no
        // se escribe a mano: si se escribiera, el test compararía el árbol de Vue contra una idea
        // del catálogo, no contra el catálogo.
        $component = Livewire::test(Purchase::class);

        return [
            'sections' => $component->viewData('catalog'),
            'searchEnabled' => $component->viewData('catalogSearchEnabled'),
            'messages' => __('tickets'),
        ];
    }

    /**
     * Franjas para los próximos N días, para que el calendario tenga algo que ofrecer.
     *
     * Sin ellas el paso 2 se pinta vacío y el diff compararía dos calendarios sin días
     * seleccionables — es decir, pasaría sin mirar lo que de verdad importa: la celda con precio,
     * la seleccionada y la deshabilitada.
     */
    private function slotsForNextDays(TicketType $product, int $days): void
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
                ['end_time' => '11:00:00', 'capacity' => 20, 'online_capacity' => 20],
            );
        }
    }

    /**
     * El view-model del calendario, tomado del propio componente Livewire.
     *
     * @return array<string, mixed>
     */
    private function dateProps(Testable $component): array
    {
        return [
            'progress' => $component->viewData('bookingProgress'),
            'weeks' => $component->viewData('weeks'),
            'weekdayHeaders' => $component->viewData('weekdayHeaders'),
            'monthLabel' => $component->viewData('monthLabel'),
            'canPrev' => $component->viewData('canPrev'),
            'canNext' => $component->viewData('canNext'),
            'selectedDate' => $component->get('date'),
            'messages' => __('tickets'),
        ];
    }

    /**
     * El view-model del paso 3, tomado del propio componente Livewire.
     *
     * @return array<string, mixed>
     */
    private function timeProps(Testable $component): array
    {
        return [
            'times' => $component->viewData('times'),
            'selectedTime' => $component->get('time'),
            'quantity' => $component->get('qty'),
            'minQuantity' => $component->viewData('minQty'),
            'maxQuantity' => $component->viewData('maxQty'),
            'isPack' => $component->viewData('selectedIsPack'),
            'dayPriceCents' => $component->viewData('dayPriceCents'),
            'periodLabel' => $component->viewData('selectedPeriodLabel'),
            'eventFields' => $this->eventFieldsFor($component),
            // ⚠️ **Traducido a la forma de la API, que es la que el cajón recibe de verdad.** El
            // view-model de Livewire usa otros nombres (`id`, `qty`, `can_inc`…), y alimentar al
            // componente con ellos dejaría el diff verde mientras el cajón real pinta filas vacías.
            // `SidebarAddonsParityTest` comprueba aparte que las dos fuentes dicen lo mismo.
            'addons' => $this->addonsAsApi($component->viewData('addonModel')),
            'messages' => __('tickets'),
        ];
    }

    /**
     * El view-model de complementos de Livewire → la forma que publica
     * `POST catalog/products/{id}/addons`.
     *
     * @param  array<string, mixed>  $model
     * @return array<string, mixed>
     */
    private function addonsAsApi(array $model): array
    {
        $row = fn (array $opt): array => [
            'product_id' => $opt['id'],
            'product_name' => $opt['name'],
            'price_cents' => $opt['price'],
            'note' => $opt['note'],
            'is_included' => $opt['is_included'],
            'is_mandatory' => $opt['is_mandatory'],
            'per_guest' => $opt['per_guest'],
            'allow_extra' => $opt['allow_extra'],
            'badge' => $opt['badge'],
            'features' => $opt['features'],
            'selected' => $opt['selected'],
            'available' => $opt['available'],
            'requires_name' => $opt['requires_name'],
            'quantity' => $opt['qty'],
            'free_quantity' => $opt['free'],
            'charged_cents' => $opt['charged'],
            'min_quantity' => $opt['min'],
            'max_quantity' => $opt['max'],
            'can_toggle' => $opt['can_toggle'],
            'can_increase' => $opt['can_inc'],
            'can_decrease' => $opt['can_dec'],
        ];

        return [
            'groups' => array_map(fn (array $group): array => [
                'key' => $group['key'],
                'label' => $group['label'],
                'options' => array_map($row, $group['options']),
            ], $model['groups']),
            'singles' => array_map($row, $model['singles']),
        ];
    }

    /**
     * Los campos del evento con su etiqueta ya resuelta.
     *
     * El Blade la resuelve al pintar (`$selectedType->eventFieldLabel($field)`); la API la publica ya
     * resuelta en `catalog/products/{id}`. Aquí se replica lo segundo, que es lo que el cajón SPA
     * recibirá de verdad.
     *
     * @return array<int, array<string, mixed>>
     */
    private function eventFieldsFor(Testable $component): array
    {
        $type = TicketType::find($component->get('typeId'));

        return array_map(fn (array $field): array => [
            'key' => $field['key'],
            'label' => $type->eventFieldLabel($field),
            'type' => $field['type'],
            'required' => $field['required'],
        ], $component->viewData('eventFields'));
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

    /** El árbol del componente Livewire en un paso, ya normalizado. */
    private function livewireTreeForStep(int $step): string
    {
        $html = Livewire::test(Purchase::class)->set('step', $step)->html();

        return $this->treeOf($html, 'catalog-acc');
    }

    /** Igual, pero para un componente ya colocado por el test en el paso que quiere comparar. */
    private function livewireTree(Testable $component, string $anchor, bool $withSiblings = false): string
    {
        return $this->treeOf($component->html(), $anchor, $withSiblings);
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
    private function vueTree(int $step, array $props, string $anchor = 'catalog-acc', bool $withSiblings = false, ?array $shell = null): string
    {
        return $this->treeOf($this->renderVue($step, $props, $shell), $anchor, $withSiblings);
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
    private function renderVue(int $step, array $props, ?array $shell = null): string
    {
        $bundle = base_path('storage/ssr/render-sidebar.js');

        $this->assertFileExists(
            $bundle,
            'Falta el bundle SSR del cajón: corre `npm run build:ssr`. Node no puede cargar un `.vue` '.
            'sin compilar, así que el renderizador se construye con Vite. El `pre-push` ya lo hace.'
        );

        $process = new Process(['node', $bundle], base_path());
        $process->setInput(json_encode(
            array_filter(['step' => $step, 'props' => $props, 'shell' => $shell], fn ($value) => $value !== null),
            JSON_THROW_ON_ERROR
        ));
        $process->setTimeout(60);
        $process->run();

        $this->assertTrue(
            $process->isSuccessful(),
            "El renderizador de Vue falló:\n".$process->getErrorOutput()
        );

        return $process->getOutput();
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
