<?php

namespace Tests\Feature\Architecture;

use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Filament\Pages\CreateManualOrderPage;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * **«Crear pedido» se usa en TABLET, con un cliente delante** (`DECISIONES #240`, U7).
 *
 * `[DECIDIDO owner, 2026-08-28]`: el gerente crea las reservas desde la tablet. Eso **corrige la
 * premisa de `#232`** —«la puerta tiene tablet propia, el resto del panel se usa en ordenador»— para
 * esta pantalla en concreto.
 *
 * ⚠️ **Medido en iPad horizontal (1080×810) ANTES de tocar nada**, que es de donde sale cada decisión
 * que esta guarda protege:
 *   · paso 1 «Cliente» → 762 px · **4** controles bajo 44 px
 *   · paso 2 vacío → 1.026 px, **se pasa 216** · **8** controles bajo 44
 *   · paso 2 con producto → **1.292 px, se pasa 482** · **15** bajo 44 (steppers a 28×28, un icono a 16×16)
 * ▶ Y lo que de verdad dolía no era el alto: quedaban **fuera de pantalla** el **resumen del pedido**
 * (a 1.100 px) y **el botón de avanzar** — las dos cosas que el gerente necesita ver con el cliente
 * delante—, todo apilado en una columna de 648 px dentro de un lienzo apaisado de 1080.
 *
 * ❗ **Lo que esta guarda NO puede ver**: que la pantalla «se vea bien». Eso es del navegador, y sus
 * cifras salen del sondeo headless (`specs/panel-navegacion.md` §9.4). Aquí se fijan las DECISIONES,
 * para que nadie las deshaga sin enterarse.
 */
class CreateManualOrderTabletTest extends TestCase
{
    use RefreshDatabase;

    private const THEME = 'resources/css/filament/admin/theme.css';

    private const VIEW = 'resources/views/filament/pages/create-manual-order.blade.php';

    private function css(): string
    {
        return (string) file_get_contents(base_path(self::THEME));
    }

    // ─── Las decisiones de forma, fijadas en la hoja ──────────────────────────────────────────

    /**
     * ⚠️ **El punto de ruptura cubre los DOS casos de tablet, y no es evidente cuál es más ancho**:
     * en apaisado (1080) el menú lateral se lleva ~250 px y el área principal queda en **760**; en
     * vertical (810) el menú se esconde y queda en **810**. O sea que **la tablet en vertical tiene
     * más ancho útil que en horizontal**, y con el corte en 50rem (800 px) las dos entran.
     */
    public function test_the_two_column_layout_covers_both_tablet_orientations(): void
    {
        $css = $this->css();

        $this->assertMatchesRegularExpression(
            '/@media \(min-width: 50rem\)\s*\{[^@]*?\.cmo-layout\s*\{[^}]*grid-template-columns/s',
            $css,
            "El armazón de dos columnas de «Crear pedido» ha desaparecido o cambió de punto de ruptura.\n".
            "El corte es 50rem (800 px) porque cubre la tablet en las DOS orientaciones: apaisada deja\n".
            '760 px de área principal (el menú se lleva 250) y vertical deja 810 (el menú se esconde).'
        );
    }

    /**
     * ⚠️ **La columna del resumen es PEGAJOSA, y eso es la tanda entera.** Sin ella el gerente pierde
     * de vista lo que lleva y lo que cobra en cuanto el formulario crece — que es exactamente el
     * defecto medido: con un producto elegido, el resumen quedaba a 1.100 px en una pantalla de 810.
     */
    public function test_the_summary_column_sticks(): void
    {
        $this->assertMatchesRegularExpression(
            '/\.cmo-aside\s*\{[^}]*position:\s*sticky/s',
            $this->css(),
            "La columna del resumen ha dejado de ser pegajosa.\n".
            'Verificado en navegador: sin `sticky` se va por arriba al desplazar 600 px.'
        );
    }

    /**
     * ⚠️ **La navegación vive CON el resumen, no debajo del formulario.** Es el gesto más repetido de
     * la pantalla y el que decide el cobro: en la columna pegajosa está siempre a la misma altura.
     */
    public function test_the_wizard_navigation_lives_in_the_sticky_column(): void
    {
        $view = (string) file_get_contents(base_path(self::VIEW));

        $aside = strstr($view, '<aside class="cmo-aside">');

        $this->assertNotFalse($aside, 'No existe la columna `.cmo-aside`.');

        // ⚠️ **Se asevera el ATRIBUTO completo, no la subcadena `cmo-nav`.** La primera versión de
        // esta guarda pasaba en verde con la mutación puesta, porque `cmo-nav-fuera` **contiene**
        // `cmo-nav`. Es la QUINTA vez que este repo tropieza con lo mismo (`#234`·trampa 2).
        $this->assertStringContainsString(
            'class="cmo-nav"', $aside,
            "La navegación del asistente ha salido de la columna pegajosa.\n".
            'Ahí es donde está siempre a la vista; debajo del formulario vuelve a quedar fuera de pantalla.'
        );

        // Y que esté DENTRO del `<aside>`, no después de cerrarlo: `strstr()` devuelve todo el resto
        // del fichero, así que sin esto la navegación podría vivir al final de la página.
        $this->assertLessThan(
            strpos((string) $aside, '</aside>'),
            strpos((string) $aside, 'class="cmo-nav"'),
            'La navegación está DESPUÉS de cerrar la columna pegajosa, no dentro.'
        );
    }

    /**
     * ⚠️ **Los objetivos táctiles van SIN `@media`**, igual que en la puerta (`#232`): un ratón nunca
     * falló por un control grande, y así no depende de acertar el ancho del dispositivo — que es
     * exactamente lo que había fallado, donde no existía ninguna regla en el rango de la tablet.
     *
     * Cada selector de la lista salió de MEDIR, no de leer el DOM. Los tres últimos fueron los más
     * escurridizos: el calendario emergente son **dos** nodos (el disparador y el `<input>` de
     * dentro), la «x» que vacía un select mide **16×16**, y de un radio se pulsa **su fila**.
     */
    public function test_every_measured_touch_target_is_forty_four(): void
    {
        $css = $this->css();

        $selectores = [
            '.cmo-layout .fi-input',
            '.cmo-layout .fi-select-input-btn',
            '.cmo-layout .fi-fo-date-time-picker-trigger',
            '.cmo-layout .fi-fo-date-time-picker-display-text-input',
            '.cmo-layout .fi-select-input-value-remove-btn',
            '.cmo-layout .fi-fo-radio-label',
            '.cmo-layout .manual-addon-step',
        ];

        foreach ($selectores as $selector) {
            $this->assertStringContainsString(
                $selector, $css,
                "El selector «{$selector}» ya no lleva su mínimo táctil.\n".
                'Salió de medir la pantalla en una tablet: si se quita, vuelve a estar por debajo de 44 px.'
            );
        }

        // ⚠️ Y **fuera de todo `@media`**, que es la mitad que de verdad importa: dentro de uno
        // dejarían de aplicarse en el ancho que nadie probó — el fallo exacto que `#232` arregló en la
        // puerta. Se mide por PROFUNDIDAD DE LLAVES: en la raíz de la hoja vale 0.
        // ⚠️ La primera versión de esta comprobación buscaba «el `@media` que viene después» y salía
        // en rojo por una razón que no era la suya: el bloque de dos columnas está ANTES, así que no
        // había ninguno después. Un instrumento que mide otra cosa da un rojo tan inútil como un verde.
        foreach (['.cmo-layout .fi-input', '.cmo-layout .manual-addon-step', '.cmo-layout .fi-fo-radio-label'] as $regla) {
            $hasta = substr($css, 0, (int) strpos($css, $regla));

            $this->assertSame(
                substr_count($hasta, '{'), substr_count($hasta, '}'),
                "«{$regla}» ha quedado DENTRO de un bloque (`@media` u otro).\n".
                'Los mínimos táctiles van en la raíz: un ratón nunca falló por un control grande.'
            );
        }
    }

    // ─── La conducta: DOS puertas para elegir día, UNA regla ──────────────────────────────────

    /** Mismo andamiaje que `CreateManualOrderPageTest`, para no inventar un segundo montaje. */
    private function seedAdmin(): User
    {
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $admin = User::factory()->create();
        $admin->roles()->sync([Role::where('name', 'admin')->value('id')]);

        return $admin;
    }

    private function seedProduct(): TicketType
    {
        RateType::firstOrCreate(['key' => RateType::KEY_NORMAL], ['label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0]);
        $zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'JUMP']]);

        $product = TicketType::create([
            'name' => ['es' => 'Entrada 1h'], 'zone_id' => $zone->id, 'duration_min' => 60,
            'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 1,
        ]);
        $product->prices()->create(['rate_type_id' => RateType::where('key', 'normal')->value('id'), 'amount_cents' => 990]);

        // ⚠️ Las franjas empiezan MAÑANA a propósito: así, si la tira dejara de leer la oferta y
        // pasara a sumar días a «hoy», el caso de abajo lo vería.
        for ($i = 1; $i <= 20; $i++) {
            Slot::firstOrCreate(
                ['zone_id' => $zone->id, 'date' => now()->addDays($i)->toDateString(), 'start_time' => '10:00:00'],
                ['end_time' => '11:00:00', 'capacity' => 20, 'online_capacity' => 20],
            );
        }

        return $product;
    }

    /**
     * ⚠️ **La tira son 14 días y no el horizonte entero**, y las dos mitades importan: 14 porque el
     * calendario sigue debajo para el salto largo y meter 182 chips serían ~180 nodos re-renderizados
     * por Livewire en cada cambio del formulario; y **solo días OFRECIBLES** porque salen de
     * `SlotOffer` (`AFORO-02`), no de sumarle días a hoy.
     */
    public function test_the_quick_strip_is_capped_and_only_offers_offerable_days(): void
    {
        $product = $this->seedProduct();

        $component = Livewire::actingAs($this->seedAdmin())
            ->test(CreateManualOrderPage::class)
            ->set('step', CreateManualOrderPage::STEP_PRODUCTS)
            ->set('data.sel_product_id', $product->id);

        $days = $component->instance()->quickDays();

        $this->assertCount(14, $days, 'La tira rápida tiene que estar acotada a 14 días.');

        // Ninguno es HOY: las franjas sembradas empiezan mañana, así que un «hoy + N» se notaría.
        $this->assertNotContains(
            Carbon::today()->toDateString(),
            array_column($days, 'date'),
            'La tira está inventando días en vez de leer la oferta de `SlotOffer`.'
        );
    }

    /**
     * ⚠️⚠️ **Las DOS puertas de elegir día tienen que hacer LO MISMO.**
     *
     * Desde `#240` hay dos —la tira y el calendario— y la hora y los menores dependen de la FECHA
     * (`D13`). Quedarse con la hora de otro día es ofrecer algo que el checkout rechazaría, y una
     * regla escrita dos veces es una regla que diverge: se arregla una y la otra se queda atrás.
     * Por eso las dos terminan en `onDateChosen()`, y esto lo comprueba **por conducta**.
     */
    public function test_both_doors_to_choosing_a_day_forget_the_time_and_the_dependents(): void
    {
        $product = $this->seedProduct();
        $admin = $this->seedAdmin();
        $primero = now()->addDay()->toDateString();
        $segundo = now()->addDays(2)->toDateString();

        foreach (['tira', 'calendario'] as $puerta) {
            // ⚠️ **El paso 2 tiene que estar VISIBLE.** Sus campos viven en un `Group` con
            // `->visible(step === STEP_PRODUCTS)`, y un campo oculto no está en el formulario: su
            // `afterStateUpdated` no se llama. La primera versión de este caso dejaba el paso en 1 y
            // daba «el calendario conserva la hora» — un defecto del arnés, no del código.
            $component = Livewire::actingAs($admin)
                ->test(CreateManualOrderPage::class)
                ->set('step', CreateManualOrderPage::STEP_PRODUCTS)
                ->set('data.sel_product_id', $product->id)
                ->set('data.sel_date', $primero)
                ->set('data.sel_time', '10:00:00')
                ->set('data.sel_dependent_ids', [7]);

            if ($puerta === 'tira') {
                $component->call('pickQuickDay', $segundo);
            } else {
                $component->set('data.sel_date', $segundo);
            }

            $this->assertSame($segundo, $component->get('data.sel_date'), "«{$puerta}» no cambió el día");
            $this->assertNull($component->get('data.sel_time'), "«{$puerta}» conservó la hora de OTRO día");
            $this->assertSame([], $component->get('data.sel_dependent_ids'), "«{$puerta}» conservó los menores de OTRO día");
        }
    }

    /**
     * ⚠️ **El navegador propone, el servidor decide.** El `wire:click` de la tira lleva una fecha, y
     * una fecha que la oferta no admite no puede entrar por ahí — da igual que la tira solo pinte
     * días buenos: quien decide qué se vende es `SlotOffer` (`AFORO-02`), no el marcado.
     */
    public function test_the_strip_refuses_a_day_that_is_not_offered(): void
    {
        $product = $this->seedProduct();

        $component = Livewire::actingAs($this->seedAdmin())
            ->test(CreateManualOrderPage::class)
            ->set('step', CreateManualOrderPage::STEP_PRODUCTS)
            ->set('data.sel_product_id', $product->id)
            ->call('pickQuickDay', now()->addYears(3)->toDateString());

        $this->assertNull(
            $component->get('data.sel_date'),
            'La tira ha aceptado un día que `SlotOffer` no ofrece.'
        );
    }

    // ─── Lo que pidió el OJO del owner (`#241`) ───────────────────────────────────────────────

    /**
     * ⚠️⚠️ **Cambiar el CONTROL no puede cambiar la REGLA, y esta es la guarda que lo sostiene.**
     *
     * En `#240` la hora era un `ToggleButtons` nativo con `disableOptionWhen`; en `#241` pasa a un
     * partial propio porque su etiqueta es texto plano y no dejaba bajarle el peso a las plazas
     * (`[OWNER]`). Al escribir el control a mano, **el deshabilitado deja de ser del framework**: una
     * franja llena tiene que seguir viajando marcada como no vendible —**se enseña deshabilitada, no
     * se esconde**, igual que en la web— y `pickTime()` tiene que rechazarla en el SERVIDOR, porque
     * un `wire:click` se puede llamar con cualquier hora.
     */
    public function test_a_full_slot_is_shown_disabled_and_cannot_be_chosen(): void
    {
        $product = $this->seedProduct();
        $dia = now()->addDay()->toDateString();

        // La franja de ese día se queda sin cupo online: sigue existiendo, pero no se vende.
        Slot::where('zone_id', $product->zone_id)->where('date', $dia)->update(['online_capacity' => 0]);

        $component = Livewire::actingAs($this->seedAdmin())
            ->test(CreateManualOrderPage::class)
            ->set('step', CreateManualOrderPage::STEP_PRODUCTS)
            ->set('data.sel_product_id', $product->id)
            ->set('data.sel_date', $dia);

        $chips = $component->instance()->timeChips();

        $this->assertNotSame([], $chips, 'La franja llena ha DESAPARECIDO: se enseña deshabilitada, no se esconde.');
        $this->assertFalse($chips[0]['sellable'], 'La franja llena no está marcada como no vendible.');

        // Y la puerta del servidor la rechaza aunque el navegador la pida.
        $component->call('pickTime', $chips[0]['time']);

        $this->assertNull(
            $component->get('data.sel_time'),
            'El servidor ha aceptado una franja que no se vende. El navegador propone, el servidor decide.'
        );
    }

    /** Una franja vendible sí entra: el control tiene que servir para lo que existe. */
    public function test_a_sellable_slot_can_be_chosen(): void
    {
        $product = $this->seedProduct();

        $component = Livewire::actingAs($this->seedAdmin())
            ->test(CreateManualOrderPage::class)
            ->set('step', CreateManualOrderPage::STEP_PRODUCTS)
            ->set('data.sel_product_id', $product->id)
            ->set('data.sel_date', now()->addDay()->toDateString());

        $component->call('pickTime', '10:00:00');

        $this->assertSame('10:00:00', $component->get('data.sel_time'));
    }

    /**
     * ⚠️ **Las plazas van en SEGUNDO plano, no al mismo nivel que la hora** (`[OWNER, 2026-08-28]`).
     * Es contexto de la decisión, no la decisión. Aquí se fija que sean **dos nodos distintos**: con
     * uno solo —que es lo que hacía el control anterior, «10:00 · 20 plazas» en una cadena— no hay
     * forma de darles pesos distintos.
     */
    public function test_the_hour_and_the_seats_are_two_separate_nodes(): void
    {
        $partial = (string) file_get_contents(base_path('resources/views/filament/pages/partials/manual-order-times.blade.php'));

        $this->assertStringContainsString('class="cmo-times__h"', $partial);
        $this->assertStringContainsString('class="cmo-times__seats"', $partial);

        $css = $this->css();
        preg_match('/\.cmo-times__h\s*\{[^}]*font-size:\s*([\d.]+)rem/', $css, $hora);
        preg_match('/\.cmo-times__seats\s*\{[^}]*font-size:\s*([\d.]+)rem/', $css, $plazas);

        $this->assertNotEmpty($hora, 'La hora ha perdido su tamaño propio.');
        $this->assertNotEmpty($plazas, 'Las plazas han perdido su tamaño propio.');
        $this->assertGreaterThan(
            (float) $plazas[1], (float) $hora[1],
            'Las plazas han vuelto a pesar lo mismo que la hora, o más. La hora es lo que se elige.'
        );
    }

    /**
     * El calendario amplio nace PLEGADO tras su CTA (`[OWNER]`: «mejor un CTA "abrir calendario"»).
     * Misma decisión y mismo motivo que en el cajón del cliente: la tira resuelve la reserva de
     * mostrador y el calendario es el atajo para el salto largo.
     */
    public function test_the_wide_calendar_is_folded_behind_its_cta(): void
    {
        $component = Livewire::actingAs($this->seedAdmin())->test(CreateManualOrderPage::class);

        $this->assertFalse($component->get('calendarOpen'), 'El calendario amplio ya no nace plegado.');

        $component->call('toggleCalendar');
        $this->assertTrue($component->get('calendarOpen'));

        $component->call('toggleCalendar');
        $this->assertFalse($component->get('calendarOpen'), 'El CTA tiene que poder cerrarlo también.');
    }

    /**
     * ⚠️ **El resumen dice DE QUIÉN es el pedido** (`[OWNER]`), y con el MISMO texto que el buscador de
     * clientes: componer aquí una segunda forma sería tener dos maneras de nombrar a la misma persona
     * en la misma página. En una tablet de mostrador el resumen es lo único que queda a la vista
     * mientras se monta la reserva; sin el titular, con una cola delante, eso es cobrarle a otro.
     */
    public function test_the_summary_names_the_customer_with_the_same_text_as_the_search(): void
    {
        $admin = $this->seedAdmin();
        $cliente = User::factory()->create(['name' => 'Vilma Probe', 'email' => 'vilma@jumpweb.test']);
        $cliente->roles()->sync([Role::where('name', 'customer')->value('id')]);

        $component = Livewire::actingAs($admin)
            ->test(CreateManualOrderPage::class)
            ->set('data.customer_id', $cliente->id);

        $this->assertSame(
            'Vilma Probe · vilma@jumpweb.test',
            $component->instance()->currentCustomerLabel(),
            'El resumen ha dejado de usar el mismo texto que el buscador de clientes.'
        );

        // Sin cliente elegido no hay cabecera que pintar.
        $vacio = Livewire::actingAs($admin)->test(CreateManualOrderPage::class);
        $this->assertNull($vacio->instance()->currentCustomerLabel());
    }

    /**
     * ⚠️ **Las flechas de la tira SOLO existen donde hay ratón.** Nacen de un defecto real
     * (`[OWNER]`: «en desktop no hay manera de deslizar»), pero en una tablet táctil dos botones
     * flotando sobre la tira tapan chips y compiten con el gesto que ya funciona. Las DOS consultas
     * juntas: `hover: hover` sola la cumple un táctil con lápiz.
     */
    public function test_the_panel_strip_arrows_only_exist_where_there_is_a_mouse(): void
    {
        $css = $this->css();

        $this->assertMatchesRegularExpression(
            '/\.cmo-daystrip__nav\s*\{\s*display:\s*none;?\s*\}/',
            $css,
            'Las flechas de la tira del panel han dejado de nacer APAGADAS.'
        );
        $this->assertStringContainsString('@media (hover: hover) and (pointer: fine)', $css);
    }
}
