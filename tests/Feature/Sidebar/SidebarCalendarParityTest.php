<?php

namespace Tests\Feature\Sidebar;

use Illuminate\Support\Carbon;
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
 * ### Qué queda aquí tras la retirada (`DECISIONES #79`, ejecutado en 4.7·2b·3)
 *
 * El fichero **no era homogéneo**, y por eso se operó DENTRO en vez de borrarlo entero:
 *
 * · Los **dos primeros casos comparaban ENTRE MOTORES** —su referencia era `viewData('weeks')`, que
 *   componía `Purchase` y no existe en ningún otro sitio—, así que **se fueron con el componente**.
 *   Su hueco lo había cerrado ya (B) en `#68`: el diff de árbol EJECUTA `calendar.js`, y las dos
 *   fronteras que aquellos casos declaraban medidas viven en `calendar.test.js` (18 casos).
 * · El **caso de los husos se queda, y hoy es el único**: no compara motores, compara el cliente
 *   consigo mismo con el huso del proceso forzado. Eso `calendar.test.js` **no puede hacerlo** —corre
 *   en un solo proceso y `TZ` se lee al arrancarlo—, así que es la única red de una defensa que ya se
 *   comprobó inerte con el huso del contenedor.
 *
 * ⚠️ Por eso el nombre del fichero dice «Parity» y ya casi no queda paridad: se conserva porque la
 * doc y el tracker lo citan por él. Lo que hay dentro es la defensa del huso, y nada más.
 */
class SidebarCalendarParityTest extends TestCase
{
    // ⚠️ **Sin `RefreshDatabase` y sin `setUp`, y no es descuido**: retirados los dos casos que
    // comparaban motores, el único que queda ejecuta `calendar.js` en Node y **no toca la base de
    // datos**. El andamiaje que había —zona, tarifa y un producto con franjas— solo alimentaba a
    // aquellos dos; dejarlo montaría el esquema entero por cada huso para no consultarlo nunca.

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
        // eso es el único del fichero que sobrevivió a la retirada (`DECISIONES #79`).

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
