<?php

namespace Tests\Feature\Site;

use App\Domain\Booking\Models\Price;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\SpecialDate;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use Database\Seeders\LandingContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * **`/precios`: la CONDUCTA del producto** (`DECISIONES #531`; partida por lo que afirma en F5 · T2b, `#658`).
 *
 * ❗❗ **Aquí no se lee el HTML** (`#649`): el contrato con la landing de una instancia son los DATOS que
 * recibe la vista —`rateTable`, `week`, las dos etiquetas de columna, `specialLabel`, `plainDays` y
 * `holidays`—, y sobre ellos se afirma. Hasta la mudanza estos casos leían el marcado de la página de
 * PlayJump, que ya no está en el producto; lo que ese marcado garantiza vive en la doc de la instancia
 * (`paginas/precios.md`) y el anfitrión mínimo tiene su guarda propia (`AnfitrionPreciosTest`).
 *
 * Lo que se rompería EN SILENCIO y esto sostiene:
 *  1. **Una entrada que no se vende un día dice que no se vende** (`normal`/`special` en `null`, y la nota
 *     «solo …»), y una que sí se vende al mismo precio **NO lo dice**. Es el defecto que `#531` reprodujo
 *     con control: *existir un precio y ser distinto son dos preguntas*.
 *  2. **Ningún complemento se publica aquí, tampoco la hora extra** (`#583`): solo en el cajón, al reservar.
 *  3. **Los bloques que alimenta el panel se quedan VACÍOS cuando no hay dato** (la semana sin tarifa
 *     especial, los festivos sin fechas): la vista no puede inventarse lo que el producto no le da.
 */
class PricingPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LandingContentSeeder::class);
        $this->withSession(['locale' => 'es']);
        app()->setLocale('es');
    }

    /** @return array<string, mixed> Lo que el controlador le pasa a la vista: el sujeto de esta guarda. */
    private function datos(): array
    {
        return $this->get('/precios')->assertOk()->original->getData();
    }

    /** @return list<array<string, mixed>> Todas las filas de todas las zonas, en orden. */
    private function filas(): array
    {
        return collect($this->datos()['rateTable'])->flatMap(fn (array $z): array => $z['rows'])->all();
    }

    /** Reescribe el precio de un producto para una tarifa (null = sin precio ese día). */
    private function reprice(TicketType $product, string $rateKey, ?int $cents): void
    {
        $rate = RateType::where('key', $rateKey)->firstOrFail();

        Price::where('priceable_type', $product->getMorphClass())
            ->where('priceable_id', $product->id)
            ->where('rate_type_id', $rate->id)
            ->delete();

        if ($cents !== null) {
            $product->prices()->create(['rate_type_id' => $rate->id, 'amount_cents' => $cents]);
        }
    }

    private function anEntry(): TicketType
    {
        return TicketType::ofType(TicketType::TYPE_ENTRY)->where('is_active', true)
            ->orderBy('position')->firstOrFail();
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  1 · La tabla
    // ─────────────────────────────────────────────────────────────────────────────────

    /** **Una sección por zona de la landing que tenga entradas**, en el orden del panel y con las suyas dentro. */
    public function test_every_landing_zone_with_entries_travels_with_its_own_rows(): void
    {
        $rateTable = $this->datos()['rateTable'];

        $esperadas = Zone::where('show_in_landing', true)->orderBy('position')->pluck('slug')
            ->filter(fn (string $slug): bool => collect($rateTable)->contains('slug', $slug))
            ->values()->all();

        $this->assertGreaterThan(0, count($esperadas), 'sin zonas de landing este caso miraría el vacío');
        $this->assertSame($esperadas, array_column($rateTable, 'slug'), 'las zonas no viajan en el orden del panel');

        foreach ($rateTable as $zona) {
            $this->assertNotEmpty($zona['rows'], "la zona «{$zona['slug']}» viaja sin filas");
            $this->assertSame(
                TicketType::ofType(TicketType::TYPE_ENTRY)->where('is_active', true)
                    ->whereHas('zone', fn ($q) => $q->where('slug', $zona['slug']))->count(),
                count($zona['rows']),
                "la zona «{$zona['slug']}» no trae una fila por entrada activa",
            );
        }

        // ⚠️ Una zona SIN entradas no viaja: una tabla vacía no informa de nada.
        $vacia = Zone::create(['slug' => 'sin-entradas', 'name' => ['es' => 'Sin entradas'], 'accent' => 'jump', 'position' => 9, 'show_in_landing' => true]);
        $this->assertNotContains($vacia->slug, array_column($this->datos()['rateTable'], 'slug'));
    }

    /**
     * ❗❗❗ **EL PRECIO DE CADA COLUMNA ES EL DE SU TARIFA, y `null` dice «ese día no se vende».**
     *
     * ⚠️ Es la propiedad que hace honesta la tabla: escribir el precio de referencia en las dos columnas
     * publicaría una cifra para un día en el que no se puede comprar.
     */
    public function test_each_column_carries_the_price_of_its_own_rate(): void
    {
        $entrada = $this->anEntry();
        $this->reprice($entrada, RateType::KEY_NORMAL, 1234);
        $this->reprice($entrada, RateType::KEY_SPECIAL, 4321);

        $fila = collect($this->filas())->first(fn (array $f): bool => str_contains((string) $f['normal'], '12,34'));

        $this->assertNotNull($fila, 'la fila no lleva el precio de la tarifa normal');
        $this->assertStringContainsString('43,21', (string) $fila['special'], 'la fila no lleva el precio de la tarifa especial');
    }

    /**
     * ❗❗❗ **UNA ENTRADA SIN PRECIO ESPECIAL DICE «SOLO», Y UNA CON EL MISMO PRECIO NO.**
     *
     * ⚠️⚠️ **El control es la mitad que importa** (`#531`): hasta aquella tanda la frase colgaba de «¿tiene
     * recargo?», así que una entrada con el MISMO precio los siete días —que se vende el sábado— se
     * anunciaba como «solo de lunes a jueves». *Existir un precio y ser distinto son dos preguntas.*
     */
    public function test_only_an_entry_that_is_not_sold_on_the_special_rate_carries_the_only_note(): void
    {
        $entrada = $this->anEntry();

        // (a) SIN precio especial → la fila lo dice y su columna especial viaja en `null`.
        $this->reprice($entrada, RateType::KEY_NORMAL, 1500);
        $this->reprice($entrada, RateType::KEY_SPECIAL, null);

        $fila = collect($this->filas())->first(fn (array $f): bool => str_contains((string) $f['normal'], '15 €'));
        $this->assertNotNull($fila);
        $this->assertNull($fila['special'], 'la entrada que no se vende el finde trae precio especial');
        $this->assertStringContainsString('solo', (string) $fila['note'], 'la entrada que no se vende el finde no lo dice');

        // (b) CONTROL: con el MISMO precio los dos días se vende los siete, y NO puede decir «solo».
        $this->reprice($entrada, RateType::KEY_SPECIAL, 1500);

        $fila = collect($this->filas())->first(fn (array $f): bool => str_contains((string) $f['normal'], '15 €'));
        $this->assertNotNull($fila);
        $this->assertNotNull($fila['special']);
        $this->assertNull($fila['note'], 'una entrada que SÍ se vende el finde viaja como si no');
    }

    /**
     * ❗❗ **NI LA HORA EXTRA NI NINGÚN COMPLEMENTO VIAJAN A `/precios`** (`[DECIDIDO owner, 2026-09-13]`,
     * `#583`): solo se ofrecen en el cajón, al reservar. Revierte `#531`, que había puesto la hora extra
     * como fila de la tabla y el resto en un bloque de filas.
     */
    public function test_neither_the_extra_hour_nor_any_addon_travels(): void
    {
        // ⚠️ El complemento de TIEMPO se siembra aquí: el catálogo de la suite no trae ninguno, y sin él
        // que no esté en la tabla no demostraría nada.
        $extra = TicketType::create([
            'name' => ['es' => 'Hora extra'], 'type' => TicketType::TYPE_ADDON,
            'is_active' => true, 'is_sellable' => true, 'seats_per_unit' => 1,
            'occupies_after_parent' => true, 'duration_min' => 60, 'position' => 90,
        ]);
        $extra->prices()->create([
            'rate_type_id' => RateType::where('key', RateType::KEY_SPECIAL)->value('id'),
            'amount_cents' => 500,
        ]);
        $this->anEntry()->configurableAddons()->attach($extra->id, [
            'quantity_mode' => 'fixed', 'max_qty' => 2, 'position' => 1,
        ]);

        $filas = $this->filas();

        $this->assertNotEmpty($filas, 'la tabla viaja vacía: el caso miraría el vacío');
        $this->assertNotContains('Hora extra', array_column($filas, 'name'), 'la hora extra ha vuelto a la tabla de precios');
    }

    /**
     * **La etiqueta destacada viaja en toda fila que la tenga, lidere o no** (`#585`, `[DECIDIDO owner,
     * 2026-09-13]`; revierte `#480`). Y sin `badge` no hay etiqueta, ni siquiera en la destacada: no se
     * inventa aquí una palabra que el dueño no ha escrito.
     */
    public function test_every_entry_with_a_badge_carries_it(): void
    {
        TicketType::ofType(TicketType::TYPE_ENTRY)->update(['featured' => false, 'badge' => null]);
        $this->assertEmpty(array_filter(array_column($this->filas(), 'badge')), 'sin etiqueta viaja una etiqueta');

        $entrada = $this->anEntry();
        $entrada->forceFill(['featured' => true])->save();
        $this->assertEmpty(array_filter(array_column($this->filas(), 'badge')), 'una destacada sin etiqueta se inventa una');

        // Con etiqueta y SIN destacar: viaja igual.
        $entrada->forceFill(['featured' => false, 'badge' => ['es' => 'La favorita']])->save();
        $this->assertSame(['La favorita'], array_values(array_filter(array_column($this->filas(), 'badge'))));
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  2 · La semana y la tarifa especial
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * **La semana sale de los días que declara la tarifa especial**, no de una lista escrita.
     *
     * ⚠️ Y el nombre completo viaja con cada día para quien no ve la inicial: «L» no es un nombre
     * accesible, y en francés la del martes y la del miércoles son la misma.
     */
    public function test_the_week_marks_the_days_the_special_rate_declares(): void
    {
        RateType::query()->where('is_special', true)->update(['weekdays' => [6]]); // solo el sábado
        RateType::forgetSpecialMemo();

        $week = $this->datos()['week'];

        $this->assertCount(7, $week, 'la semana no trae los siete días');
        $this->assertSame(1, count(array_filter(array_column($week, 'special'))), 'solo un día es de tarifa especial');

        $sabado = collect($week)->firstWhere('special', true);
        $this->assertSame('sábado', $sabado['name'], 'el día especial no es el que declara la tarifa');
        $this->assertNotSame('', trim((string) $sabado['initial']), 'el día viaja sin inicial');
    }

    /** Sin tarifa especial **no hay semana ni término que definir**: la vista no puede dibujar un diagrama sin dato. */
    public function test_without_a_special_rate_there_is_neither_week_nor_label(): void
    {
        RateType::query()->where('is_special', true)->update(['is_active' => false]);
        RateType::forgetSpecialMemo();

        $datos = $this->datos();

        $this->assertNull($datos['week']);
        $this->assertNull($datos['specialLabel'], 'sin tarifa especial viaja un rótulo que no existe');
        $this->assertNull($datos['colSpecial'], 'sin tarifa especial viaja una segunda columna');
    }

    /** El rótulo de la tarifa lo escribe **el panel**, no una lista escrita en la vista. */
    public function test_the_special_label_comes_from_the_panel(): void
    {
        RateType::query()->where('is_special', true)->update(['label' => ['es' => 'Viernes, findes, festivos y vísperas']]);
        RateType::forgetSpecialMemo();

        $this->assertSame('Viernes, findes, festivos y vísperas', $this->datos()['specialLabel']);
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  3 · Los festivos
    // ─────────────────────────────────────────────────────────────────────────────────

    /** Con cero fechas especiales **no viaja ninguna**: el bloque se apaga solo, que es regla dura del sistema. */
    public function test_with_no_special_dates_no_holiday_travels(): void
    {
        SpecialDate::query()->delete();

        $this->assertSame([], $this->datos()['holidays']);
    }

    /**
     * ❗❗ **Cada fecha trae SU hecho**: cerrada dice «Cerrado», con tarifa dice el rótulo de esa tarifa
     * —que es lo que se viene a saber en una página de precios— y si no, su horario.
     *
     * ⚠️⚠️ **La fecha cerrada lleva TARIFA a propósito, y lo obligó el arnés**: sin ella la mutación que
     * ignora `is_closed` sobrevivía —un día cerrado no tiene horario que enseñar, así que las dos ramas
     * daban lo mismo—. Con tarifa se comprueba la PRECEDENCIA, que es el caso real de un festivo de cierre.
     */
    public function test_each_special_date_states_its_own_fact(): void
    {
        SpecialDate::query()->delete();
        $especial = RateType::firstSpecial();

        SpecialDate::create([
            'date' => Carbon::now()->addDays(3)->toDateString(),
            'is_closed' => true, 'note' => ['es' => 'Navidad'],
            'rate_type_id' => $especial->id,
        ]);
        SpecialDate::create([
            'date' => Carbon::now()->addDays(5)->toDateString(),
            'is_closed' => false, 'note' => ['es' => 'Víspera de Reyes'],
            'rate_type_id' => $especial->id,
        ]);

        $holidays = collect($this->datos()['holidays']);

        $cerrada = $holidays->firstWhere('name', 'Navidad');
        $abierta = $holidays->firstWhere('name', 'Víspera de Reyes');

        $this->assertNotNull($cerrada, 'la fecha cerrada no viaja: el caso miraría el vacío');
        $this->assertNotNull($abierta);
        $this->assertTrue($cerrada['is_closed']);
        $this->assertStringContainsString(__('landing.info.closed'), (string) $cerrada['fact']);
        $this->assertStringNotContainsString((string) $especial->tr('label'), (string) $cerrada['fact'],
            'un día CERRADO anuncia su tarifa: lo que hay que decir es que no se abre');
        $this->assertStringContainsString((string) $especial->tr('label'), (string) $abierta['fact'],
            'la fecha con tarifa no dice cuál');
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  4 · Lo que el producto promete, y nada más
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * ❗❗ **El contrato son las variables que la vista USA** (`#658`): hasta la mudanza el controlador
     * pasaba `tickets`, `zones` y `registrationUrl`, y la vista **no leía ninguna de las tres** —medido—.
     * Declararlas en el contrato de instancia habría sido prometer a cada landing algo que nadie pidió, y
     * retirarlas después habría subido el MAYOR sin motivo. Se retiran ahora, antes de prometerlas.
     */
    public function test_the_controller_passes_exactly_what_the_view_uses(): void
    {
        $recibidas = array_keys($this->datos());

        foreach (['tickets', 'zones', 'registrationUrl'] as $muerta) {
            $this->assertNotContains($muerta, $recibidas, "«{$muerta}» volvió al contrato sin que la vista la use");
        }

        // Y lo que sí usa sigue llegando: si esto encoge, se rompen TODAS las instancias a la vez.
        foreach (['rateTable', 'week', 'colNormal', 'colSpecial', 'specialLabel', 'plainDays', 'holidays', 'registrationSvg'] as $viva) {
            $this->assertContains($viva, $recibidas);
        }
    }
}
