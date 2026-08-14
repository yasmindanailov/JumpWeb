<?php

namespace Tests\Feature\Sidebar;

use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Http\Middleware\SetLocale;
use App\Livewire\Tickets\Purchase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Fase 4 · paso 4.3·1 — **la banda de progreso dice lo mismo en los dos motores**.
 *
 * ⚠️ **Este test nace de un fallo que el gate de árbol daba por bueno.** `BookingProgress.vue` existe
 * desde 4.2 y su caso salía verde, pero en ejecución real `Sidebar.vue` le pasaba `progress: null` y
 * `TimeStep` ni lo importaba: **el cajón SPA vivo no tenía «Volver» ni contador de fases en ningún
 * paso**. Es el límite estructural del diff de árbol dicho otra vez —alimenta a Vue con el view-model
 * del SERVIDOR—, y la contramedida es la de siempre: comparar las dos COMPOSICIONES dato a dato.
 *
 * Y de paso se fija aquí la otra señal que el cajón publica hacia fuera y que ningún árbol puede ver,
 * porque su clase se pinta FUERA del cajón: el «modo» (`is-{modo}` en `.sidecart__panel`).
 */
class SidebarProgressParityTest extends TestCase
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

        // Las franjas son de la ZONA, no del producto: sembrarlas una vez por producto violaría su
        // clave única (zona + día + hora), que es justo lo que las hace compartidas.
        for ($i = 1; $i <= 5; $i++) {
            Slot::create([
                'zone_id' => $this->zone->id, 'date' => now()->addDays($i)->toDateString(),
                'start_time' => '10:00:00', 'end_time' => '11:00:00',
                'capacity' => 30, 'online_capacity' => 30,
            ]);
        }
    }

    private function product(string $type): TicketType
    {
        $product = TicketType::create([
            'name' => ['es' => 'Entrada 1 hora', 'en' => 'One hour ticket', 'fr' => 'Entrée 1 heure'],
            'type' => $type, 'zone_id' => $this->zone->id, 'duration_min' => 60,
            'min_qty' => $type === TicketType::TYPE_PACK ? 6 : 1,
            'max_qty' => $type === TicketType::TYPE_PACK ? 20 : null,
            'seats_per_unit' => 1, 'is_sellable' => true, 'is_active' => true, 'position' => 1,
        ]);
        $product->prices()->create(['rate_type_id' => $this->rateId, 'amount_cents' => 1190]);

        return $product;
    }

    /**
     * Los tres estados que la banda tiene: el calendario, la hora sin elegir y la hora elegida.
     *
     * Se prueban los tres porque el progreso avanza DENTRO del paso 3, que es donde es fácil dejarlo
     * quieto: un caso con solo el paso 2 pasaría con la fase activa clavada en 1.
     */
    public function test_the_client_builds_the_same_progress_as_the_server(): void
    {
        foreach ([TicketType::TYPE_ENTRY, TicketType::TYPE_PACK] as $type) {
            $product = $this->product($type);
            $date = now()->addDay()->toDateString();

            $states = [
                'calendario' => Livewire::test(Purchase::class)->call('selectType', $product->id),
                'hora sin elegir' => Livewire::test(Purchase::class)
                    ->call('selectType', $product->id)->call('selectDate', $date)->call('goToTime'),
                'hora elegida' => Livewire::test(Purchase::class)
                    ->call('selectType', $product->id)->call('selectDate', $date)->call('goToTime')
                    ->call('selectTime', '10:00:00'),
            ];

            foreach ($states as $label => $component) {
                $server = $component->viewData('bookingProgress');

                $this->assertNotNull($server, "el estado «{$label}» tendría que llevar banda");

                $client = $this->buildInNode([
                    'step' => (int) $component->get('step'),
                    'isPack' => $type === TicketType::TYPE_PACK,
                    'productName' => (string) $product->tr('name'),
                    'date' => $component->get('date'),
                    'time' => $component->get('time'),
                    'messages' => __('tickets'),
                    'locale' => app()->getLocale(),
                ]);

                $this->assertSame(
                    ['active' => $server['active'], 'total' => $server['total'], 'steps' => $server['steps']],
                    ['active' => $client['active'], 'total' => $client['total'], 'steps' => $client['steps']],
                    "La banda del estado «{$label}» ({$type}) NO coincide con la del servidor.\n".
                    'El diff de árbol no lo ve: allí a Vue se le pasa el view-model del servidor.'
                );

                $this->assertSame(
                    $this->comparableContext($server['context']),
                    $this->comparableContext($client['context']),
                    "La línea de contexto del estado «{$label}» ({$type}) NO dice lo mismo.\n".
                    'Se comparan los tramos y sus abreviaturas por sus tres primeras letras, porque la '.
                    'ortografía de la fecha es una divergencia DECLARADA (§4.5); el producto, el día y '.
                    'la hora sí tienen que coincidir exactamente.'
                );
            }

            $product->delete();
        }
    }

    /**
     * La fecha del contexto es el ÚNICO texto del cajón que los dos motores no sacan de la misma
     * fuente: Carbon en el servidor, `Intl` en el cliente (§4.5).
     *
     * ⚠️ Lo que este caso fija es **hasta dónde llega esa divergencia**, que hasta ahora estaba
     * declarada pero sin medir: con el patrón fijado por nosotros —y NO por un preajuste de `Intl`,
     * que en inglés invierte día y mes— **inglés y francés coinciden EXACTAMENTE**. Solo el español
     * difiere, y solo en los puntos de abreviatura y en `sept`/`sep`.
     *
     * Si alguien «limpia» los puntos para acercar el español, este caso avisa de que ha roto el
     * francés, que hoy está bien.
     */
    public function test_the_context_date_matches_carbon_except_in_spanish(): void
    {
        $date = '2026-09-05';

        foreach (SetLocale::SUPPORTED as $locale) {
            $this->app->setLocale($locale);

            $server = Str::ucfirst(
                Carbon::parse($date)->locale($locale)->isoFormat('ddd D MMM')
            );
            $client = $this->shortDateInNode($date, $locale);

            if ($locale === 'es') {
                $this->assertNotSame(
                    $server, $client,
                    'La divergencia del español está DECLARADA y medida. Si ha desaparecido —porque '.
                    'ICU cambió o porque alguien la cerró—, hay que quitar esta excepción y el apunte '.
                    'de `DEUDA.md`, no dejar el test mintiendo.'
                );

                continue;
            }

            $this->assertSame(
                $server, $client,
                "En «{$locale}» la fecha del contexto SÍ coincide hoy, y este caso existe para que siga ".
                'coincidiendo: el patrón lo fijamos nosotros, no un preajuste de `Intl`.'
            );
        }
    }

    /**
     * ⚠️ **El «modo» es la otra señal que ningún diff de árbol puede ver**, porque su clase se pinta
     * FUERA del cajón (`is-{modo}` en `.sidecart__panel`, más los botones de login de
     * `account-context`).
     *
     * Se recorre el mapa ENTERO del servidor, no una muestra: así se destapó que el paso de PAGO
     * publicaba `result` en el cliente y `cart` en el servidor.
     */
    public function test_the_client_publishes_the_same_mode_as_the_server_for_every_step(): void
    {
        $server = Livewire::test(Purchase::class)->instance()->stepModeMap();
        $client = $this->modesInNode(array_keys($server));

        // Es un MAPA: lo que se compara es paso→modo, no el orden en que cada lenguaje los enumera
        // (el literal de PHP declara el 8 antes que el 6, y JavaScript ordena las claves numéricas).
        ksort($server);
        ksort($client);

        $this->assertSame(
            $server, $client,
            "El «modo» que publica el cajón SPA NO coincide con el del motor Livewire.\n".
            'No es cosmético: `layout.blade.php` pinta `is-{modo}` en el panel (minimiza el bloque de '.
            'cuenta y recoloca el pie) y `account-context` bloquea sus botones con `identifying`. '.
            'Ninguna de esas clases aparece en el marcado del cajón, así que ningún diff de árbol lo ve.'
        );
    }

    /**
     * Deja el contexto comparable entre motores: los tramos, con las abreviaturas recortadas a tres
     * letras y sin puntos. El producto, el número del día y la hora se comparan ENTEROS.
     */
    private function comparableContext(string $context): string
    {
        $parts = array_map(function (string $part): string {
            return implode(' ', array_map(
                fn (string $token): string => mb_substr(str_replace('.', '', mb_strtolower($token)), 0, 3),
                explode(' ', trim($part))
            ));
        }, explode('·', $context));

        return implode('·', $parts);
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function buildInNode(array $state): array
    {
        return $this->runInNode(<<<'JS'
            import { buildProgress } from 'file://__MODULE__';
            let raw = '';
            process.stdin.setEncoding('utf8');
            process.stdin.on('data', (c) => { raw += c; });
            process.stdin.on('end', () => {
                process.stdout.write(JSON.stringify(buildProgress(JSON.parse(raw))));
            });
            JS,
            $state,
            'build-progress.mjs',
            'progress.js'
        );
    }

    private function shortDateInNode(string $date, string $locale): string
    {
        $out = $this->runInNode(<<<'JS'
            import { shortDate } from 'file://__MODULE__';
            let raw = '';
            process.stdin.setEncoding('utf8');
            process.stdin.on('data', (c) => { raw += c; });
            process.stdin.on('end', () => {
                const { date, locale } = JSON.parse(raw);
                const text = shortDate(date, locale);
                process.stdout.write(JSON.stringify({ text: text.charAt(0).toUpperCase() + text.slice(1) }));
            });
            JS,
            ['date' => $date, 'locale' => $locale],
            'short-date.mjs',
            'progress.js'
        );

        return $out['text'];
    }

    /**
     * @param  list<int>  $steps
     * @return array<int, string>
     */
    private function modesInNode(array $steps): array
    {
        return $this->runInNode(<<<'JS'
            import { modeOf } from 'file://__MODULE__';
            let raw = '';
            process.stdin.setEncoding('utf8');
            process.stdin.on('data', (c) => { raw += c; });
            process.stdin.on('end', () => {
                const out = {};
                for (const step of JSON.parse(raw)) out[step] = modeOf(step);
                process.stdout.write(JSON.stringify(out));
            });
            JS,
            $steps,
            'modes.mjs',
            'machine.js'
        );
    }

    /**
     * Mismo patrón que `SidebarCalendarParityTest`: script efímero que importa el módulo REAL por
     * ruta ABSOLUTA y habla por stdin/stdout con JSON.
     *
     * @param  array<string, mixed>|list<mixed>  $input
     * @return array<mixed>
     */
    private function runInNode(string $script, array $input, string $filename, string $module): array
    {
        $path = base_path('storage/framework/testing/'.$filename);

        @mkdir(dirname($path), 0775, true);
        file_put_contents($path, str_replace('__MODULE__', base_path('resources/js/sidebar/'.$module), $script));

        $process = new Process(['node', $path], base_path());
        $process->setInput(json_encode($input, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        $process->setTimeout(60);
        $process->run();

        $this->assertTrue($process->isSuccessful(), "El módulo «{$module}» falló:\n".$process->getErrorOutput());

        return json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
    }
}
