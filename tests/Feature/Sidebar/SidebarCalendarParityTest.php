<?php

namespace Tests\Feature\Sidebar;

use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Livewire\Tickets\Purchase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Fase 4 · paso 4.2 — **la rejilla del calendario se compone igual en los dos motores**.
 *
 * `SidebarDomContractTest` compara el ÁRBOL, pero lo hace pasándole a Vue el view-model que compone
 * el SERVIDOR. Eso deja un hueco que importa: en la aplicación real, la rejilla la compone el
 * CLIENTE a partir de los días que devuelve `GET availability/{producto}/dates`. Si las dos
 * composiciones difieren —un mes que empieza en domingo, una semana de más, un día fuera de mes mal
 * marcado—, el diff de árbol seguiría verde y el cajón enseñaría otro calendario.
 *
 * Esto cierra ese hueco comparando los dos resultados **dato a dato** para el mismo mes.
 *
 * ⚠️ Repartir días en semanas es PRESENTACIÓN y por eso puede vivir en el cliente. Lo que no vive
 * ahí es qué días se ofrecen: eso lo dice `SlotOffer` (`AFORO-02`) y llega por la API.
 *
 * ### Clasificación para la retirada, MEDIDA el 2026-08-15 (`DECISIONES #79`)
 *
 * El fichero **no es homogéneo**, y por eso no se re-apunta ni se borra entero:
 *
 * · Los **dos primeros casos comparan ENTRE MOTORES** —la referencia es `viewData('weeks')`, que
 *   compone `Purchase` y no existe en ningún otro sitio—, así que **mueren con el componente en
 *   ·2b·3**. Su hueco ya lo cerró (B) en `#68`: el diff de árbol ejecuta `calendar.js`, y las dos
 *   fronteras que estos casos declaraban medidas viven en `calendar.test.js` (18 casos).
 * · El **caso de los husos SOBREVIVE**: no compara motores, compara el cliente consigo mismo con el
 *   huso del proceso forzado. Eso `calendar.test.js` **no puede hacerlo** —corre en un solo proceso y
 *   `TZ` se lee al arrancarlo—, así que es la única red de una defensa que ya se comprobó inerte con
 *   el huso del contenedor.
 *
 * ▶ **Lo que ·2b·3 tiene que hacer con este fichero**: borrar los dos primeros casos y quedarse con
 * el de los husos, no borrarlo entero. Es de los pocos donde hay que operar DENTRO.
 */
class SidebarCalendarParityTest extends TestCase
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
            'slug' => 'jump', 'name' => ['es' => 'Jump'], 'position' => 1, 'is_active' => true,
        ]);
    }

    private function productWithSlots(int $days): TicketType
    {
        $product = TicketType::create([
            'name' => ['es' => 'Entrada 1h'], 'type' => TicketType::TYPE_ENTRY, 'zone_id' => $this->zone->id,
            'duration_min' => 60, 'seats_per_unit' => 1, 'is_sellable' => true, 'is_active' => true,
            'position' => 1,
        ]);
        $product->prices()->create(['rate_type_id' => $this->rateId, 'amount_cents' => 990]);

        for ($i = 1; $i <= $days; $i++) {
            Slot::create([
                'zone_id' => $this->zone->id, 'date' => now()->addDays($i)->toDateString(),
                'start_time' => '10:00:00', 'end_time' => '11:00:00',
                'capacity' => 20, 'online_capacity' => 20,
            ]);
        }

        return $product;
    }

    public function test_the_client_builds_the_same_grid_as_the_server(): void
    {
        $product = $this->productWithSlots(20);

        $component = Livewire::test(Purchase::class)->call('selectType', $product->id);

        $serverWeeks = $component->viewData('weeks');
        $month = $component->get('month');

        // Los días OFRECIDOS, tal y como se los daría la API al cliente. Se derivan del mismo
        // view-model del servidor para que lo único que se compare sea la COMPOSICIÓN, no de dónde
        // salen los días.
        $offered = [];
        foreach ($serverWeeks as $week) {
            foreach ($week as $cell) {
                if ($cell['selectable']) {
                    $offered[] = ['date' => $cell['date'], 'price_cents' => $cell['price_cents'], 'rate_key' => $cell['type']];
                }
            }
        }

        $this->assertNotEmpty($offered, 'sin días ofrecidos el test compararía dos rejillas vacías');

        $clientWeeks = $this->buildWeeksInNode($month, $offered, $component->get('date'));

        $this->assertSame(
            $this->normalise($serverWeeks),
            $this->normalise($clientWeeks),
            "La rejilla del cliente NO coincide con la del servidor para {$month}.\n".
            'El diff de árbol no ve esto: allí a Vue se le pasa el view-model del servidor. Aquí se '.
            'compara lo que el cajón compondría de verdad.'
        );
    }

    /**
     * ⚠️ **Un mes que empieza en DOMINGO es el caso que rompe una rejilla que empiece en lunes**:
     * `getDay()` devuelve 0 para el domingo, así que un cálculo ingenuo no retrocede seis días y el
     * mes entero se desplaza una casilla. Se busca uno de verdad en vez de darlo por supuesto.
     */
    public function test_a_month_starting_on_sunday_lines_up_in_both_engines(): void
    {
        $product = $this->productWithSlots(1);

        // ISO: el domingo es 7, no 0. `Carbon::SUNDAY` vale 0 y no sirve para comparar con `dayOfWeekIso`.
        $month = $this->nextMonthStartingOn(7);

        $component = Livewire::test(Purchase::class)
            ->call('selectType', $product->id)
            ->set('month', $month);

        $serverWeeks = $component->viewData('weeks');
        $clientWeeks = $this->buildWeeksInNode($month, [], null);

        $this->assertSame(
            $this->normalise($serverWeeks),
            $this->normalise($clientWeeks),
            "La rejilla de {$month} —un mes que empieza en domingo— no coincide."
        );
    }

    /**
     * ⚠️ **El navegador del cliente puede estar en CUALQUIER huso, y el calendario no puede moverse
     * por eso.**
     *
     * `new Date('2026-08-01')` se interpreta como medianoche **UTC**: en un huso al oeste eso es el
     * 31 de julio, y el mes entero se desplaza una casilla. El fallo no se ve desde España ni desde
     * un contenedor en UTC —donde local y UTC coinciden—, así que se fuerza el huso del proceso que
     * compone la rejilla. Sin este caso, la defensa del módulo estaría escrita pero no verificada:
     * se comprobó que quitarla NO rompía nada con el huso del contenedor.
     *
     * @return array<int, array<string>>
     */
    public static function timezones(): array
    {
        return [
            'oeste (UTC-5)' => ['America/New_York'],
            'lejos al oeste (UTC-10)' => ['Pacific/Honolulu'],
            'este (UTC+13)' => ['Pacific/Auckland'],
        ];
    }

    #[DataProvider('timezones')]
    public function test_the_grid_does_not_shift_with_the_browsers_timezone(string $timezone): void
    {
        // ⚠️ **Este caso NO compara motores: compara el cliente consigo mismo en dos husos**, y por
        // eso es el único del fichero que sobrevive a la retirada (`DECISIONES #79`). Conducía el
        // componente y sembraba un producto que no usaba: los dos eran vestigiales —medido, quitarlos
        // deja los cinco casos verdes— y se retiran para que la clasificación de ·2b·3 se lea sola.

        // ⚠️ **Un mes que empieza en LUNES es el único caso que delata el desfase**, y esto se
        // descubrió midiendo: con la fecha parseada en UTC el día se corre a la víspera, pero el
        // lunes de esa semana **sigue siendo el mismo** salvo que el día 1 ya fuera lunes. Con
        // cualquier otro mes el test pasaba con el bug dentro — es decir, no probaba nada.
        $month = $this->nextMonthStartingOn(1);

        $inUtc = $this->buildWeeksInNode($month, [], null);
        $elsewhere = $this->buildWeeksInNode($month, [], null, $timezone);

        $this->assertSame(
            $this->normalise($inUtc),
            $this->normalise($elsewhere),
            "La rejilla de {$month} CAMBIA en «{$timezone}». El calendario no puede depender del huso ".
            'del navegador: `new Date(\'YYYY-MM-DD\')` se interpreta en UTC y desplaza el mes entero.'
        );
    }

    /** El primer mes futuro cuyo día 1 cae en el día de la semana pedido. */
    private function nextMonthStartingOn(int $weekday): string
    {
        $cursor = Carbon::now()->startOfMonth();

        for ($i = 0; $i < 24; $i++) {
            if ($cursor->dayOfWeekIso === $weekday) {
                return $cursor->format('Y-m');
            }
            $cursor->addMonth();
        }

        $this->fail('no se ha encontrado un mes que empiece en ese día en los próximos dos años');
    }

    /**
     * Ejecuta `buildWeeks()` en Node y devuelve su resultado.
     *
     * @param  array<int, array<string, mixed>>  $offered
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function buildWeeksInNode(string $month, array $offered, ?string $selected, ?string $timezone = null): array
    {
        // ⚠️ El `import` se resuelve respecto al FICHERO, no al directorio de trabajo, así que la
        // ruta va absoluta: un script en `storage/` con una ruta relativa a la raíz no encuentra
        // nada, y el fallo se lee como «el compositor falló» sin decir por qué.
        $module = base_path('resources/js/sidebar/calendar.js');

        $script = <<<JS
            import { buildWeeks } from 'file://{$module}';
            let raw = '';
            process.stdin.setEncoding('utf8');
            process.stdin.on('data', (c) => { raw += c; });
            process.stdin.on('end', () => {
                const { month, offered, selected } = JSON.parse(raw);
                process.stdout.write(JSON.stringify(buildWeeks(month, offered, selected)));
            });
            JS;

        $path = base_path('storage/framework/testing/build-weeks.mjs');
        @mkdir(dirname($path), 0775, true);
        file_put_contents($path, $script);

        $process = new Process(['node', $path], base_path(), $timezone === null ? null : ['TZ' => $timezone]);
        $process->setInput(json_encode(['month' => $month, 'offered' => $offered, 'selected' => $selected], JSON_THROW_ON_ERROR));
        $process->setTimeout(60);
        $process->run();

        $this->assertTrue($process->isSuccessful(), "El compositor del calendario falló:\n".$process->getErrorOutput());

        return json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Deja las celdas comparables: solo los campos que deciden lo que se pinta.
     *
     * `selected` se excluye a propósito — el servidor lo trae en la celda y el cliente lo resuelve
     * al pintar; lo que este test compara es la REJILLA, no qué día está elegido.
     *
     * @param  array<int, array<int, array<string, mixed>>>  $weeks
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function normalise(array $weeks): array
    {
        return array_map(
            fn (array $week): array => array_map(fn (array $cell): array => [
                'date' => $cell['date'],
                'day' => (int) $cell['day'],
                'in_month' => (bool) $cell['in_month'],
                'selectable' => (bool) $cell['selectable'],
                'type' => $cell['type'],
                'price_cents' => $cell['price_cents'],
            ], $week),
            $weeks
        );
    }
}
