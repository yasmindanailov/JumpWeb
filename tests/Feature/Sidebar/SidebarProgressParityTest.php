<?php

namespace Tests\Feature\Sidebar;

use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\Zone;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Fase 4 · paso 4.3·1, reducido en 4.7·2b·2 — **las dos señales que el cajón publica y que nadie más
 * mira** (`DECISIONES #82`).
 *
 * ⚠️ **Nació de un fallo que el gate de árbol daba por bueno**: `BookingProgress.vue` existía desde
 * 4.2 con su caso en verde, pero en ejecución real `Sidebar.vue` le pasaba `progress: null` y
 * `TimeStep` ni lo importaba —el cajón vivo no tenía «Volver» ni contador de fases—. Aquel hueco lo
 * cerró (B) en `#71`: el diff de árbol **ejecuta** `progress.js` desde entonces, y `progress.test.js`
 * cubre la composición. **Medido**: las dos mutaciones que la comparación con Livewire cazaba —clavar
 * la fase activa y vaciar el producto del contexto— dejan rojo también `progress.test.js`. Así que esa
 * comparación se fue con el motor, por redundante y no por descuido.
 *
 * Lo que queda son las dos cosas que ningún árbol y ningún test de módulo pueden ver:
 *
 *  · **la fecha del contexto**, único texto del cajón cuya fuente NO comparten los dos lados —Carbon
 *    en el servidor, `Intl` en el cliente (§4.5)—, con la divergencia del español medida y acotada;
 *  · **el «modo»**, cuya clase se pinta FUERA del cajón (`is-{modo}` en `.sidecart__panel`), en un
 *    `layout.blade.php` que **sobrevive** a la retirada.
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
     * Se recorre el mapa ENTERO, no una muestra: así se destapó que el paso de PAGO publicaba
     * `result` en el cliente y `cart` en el servidor.
     *
     * ⚠️ **El mapa se VOLCÓ del motor vivo antes de retirarlo** (`DECISIONES #82`) en vez de
     * reconstruirlo de memoria: `stepModeMap()` era la única declaración que existía, y una vez
     * borrado el componente no habría contra qué contrastarlo. Mismo criterio que `#60` y `#81`.
     * Su consumidor, en cambio, **sobrevive**: `layout.blade.php` sigue pintando `is-{modo}`.
     */
    public function test_the_sidebar_publishes_the_declared_mode_for_every_step(): void
    {
        $expected = [
            1 => 'catalog',
            2 => 'booking', 3 => 'booking',
            // ⚠️ El 8 es PAGO y su modo es `cart`, no `result`: es la divergencia que este caso
            // destapó, y la razón de recorrer el mapa entero en vez de una muestra.
            4 => 'cart', 5 => 'cart', 8 => 'cart',
            6 => 'result', 7 => 'result', 9 => 'result', 10 => 'result', 11 => 'result',
        ];

        $client = $this->modesInNode(array_keys($expected));

        // Es un MAPA: lo que se compara es paso→modo, no el orden en que cada lenguaje los enumera
        // (el literal de PHP declara el 8 antes que el 6, y JavaScript ordena las claves numéricas).
        ksort($expected);
        ksort($client);

        $this->assertSame(
            $expected, $client,
            "El «modo» que publica el cajón NO es el declarado.\n".
            'No es cosmético: `layout.blade.php` —que SOBREVIVE a la retirada— pinta `is-{modo}` en el '.
            'panel (minimiza el bloque de cuenta y recoloca el pie) y `account-context` bloquea sus '.
            'botones con `identifying`. Ninguna de esas clases aparece en el marcado del cajón, así que '.
            'ningún diff de árbol lo ve.'
        );
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
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
