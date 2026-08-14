<?php

namespace Tests\Feature\Sidebar;

use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Fase 4 · paso 4.3·1 — **los dos motores escriben el mismo importe**.
 *
 * ⚠️ **Esta es la divergencia que el gate de paridad NO puede ver.** `SidebarDomContractTest` compara
 * el ÁRBOL y su normalizador descarta a propósito todo nodo que no sea un elemento —«el contrato es
 * la estructura, no la copia»—, así que un motor que pinte «1000,00 €» donde el otro pinta
 * «1.000,00 €» pasa VERDE. Y no es teórico: hasta 4.3·1 el cajón SPA tenía DOS copias del formateador
 * con `(céntimos/100).toFixed(2)`, que no agrupa nunca.
 *
 * Las dos salidas «obvias» de JavaScript fallan, y las dos están medidas:
 *  - `toFixed(2)` no agrupa: 100000 → «1000,00» (PHP: «1.000,00»);
 *  - `Intl.NumberFormat('es-ES')` no agrupa ENTRE 1.000 y 9.999 (el español declara
 *    `minimumGroupingDigits: 2`), o sea que arregla los importes de cinco cifras y deja rotos los de
 *    cuatro, que son los que más aparecen en una cesta.
 *
 * Por eso esto no compara «unos cuantos casos»: barre TODOS los céntimos de 0 a 2.000 más los cruces
 * de millar, contra `number_format` de verdad.
 */
class SidebarMoneyParityTest extends TestCase
{
    /**
     * Los importes que se comparan: el barrido corto entero (donde vive el 99 % de los precios) más
     * los cruces de millar y los importes grandes de una cesta con packs.
     *
     * @return list<int>
     */
    private function amounts(): array
    {
        $sweep = range(0, 2000);

        $edges = [
            9999, 10000, 10001,
            // ⚠️ A CERO decimales el redondeo cruza el millar antes que el importe: 99950 céntimos
            // son 999,50 € pero se pintan «1.000». Un caso puesto en 100000 no cubre esta franja.
            99949, 99950, 99999,
            100000, 100001, 100050, 123450,
            999999, 1000000, 1000050,
            12345678, 99999999,
            // No son alcanzables en la compra, pero un formateador no puede perder el signo.
            -5, -990, -100000,
        ];

        return array_values(array_unique(array_merge($sweep, $edges)));
    }

    public function test_the_client_writes_the_same_amount_as_number_format(): void
    {
        $amounts = $this->amounts();
        $fromClient = $this->formatInNode($amounts);

        $expected = [];
        foreach ($amounts as $cents) {
            $expected[(string) $cents] = [
                // Espejo de `Purchase::money()` y de las 17 líneas del blade que formatean dinero.
                'money' => number_format($cents / 100, 2, ',', '.').' €',
                // Espejo del precio de celda del calendario: cero decimales y el € PEGADO.
                'dayPrice' => number_format($cents / 100, 0, ',', '.').'€',
            ];
        }

        $this->assertSame(
            $expected, $fromClient,
            "El formato de importes del cajón SPA NO coincide con el del servidor.\n".
            '⚠️ El diff de árbol no puede cazar esto: descarta los nodos de texto. Si esta prueba cae, '.
            "el cliente está escribiendo un importe distinto del que escribe la web.\n".
            $this->firstDifference($expected, $fromClient)
        );
    }

    /**
     * **La guarda de la guarda**: que el barrido no esté comparando dos cosas triviales.
     *
     * Si un día `money()` devolviera siempre lo mismo, o el barrido se quedara vacío, la aserción de
     * arriba pasaría sin probar nada.
     */
    public function test_the_sweep_actually_covers_the_grouping_boundary(): void
    {
        $amounts = $this->amounts();

        $this->assertGreaterThan(2000, count($amounts), 'el barrido tiene que ser un barrido');
        $this->assertContains(100000, $amounts, 'sin el cruce del millar no se prueba la agrupación');
        $this->assertContains(99950, $amounts, 'sin 999,50 € no se prueba el cruce a cero decimales');

        $this->assertNotSame(
            number_format(100000 / 100, 2, ',', '.'),
            number_format(99999 / 100, 2, ',', '.'),
            'los dos lados del millar tienen que escribirse distinto, o el caso no distingue'
        );
    }

    /**
     * Ejecuta `money.js` en Node sobre la lista de céntimos.
     *
     * Mismo patrón que `SidebarCalendarParityTest`: un script efímero que importa el módulo REAL por
     * ruta absoluta —el `import` se resuelve respecto al fichero, no al directorio de trabajo— y
     * habla por stdin/stdout con JSON.
     *
     * @param  list<int>  $amounts
     * @return array<string, array{money: string, dayPrice: string}>
     */
    private function formatInNode(array $amounts): array
    {
        $module = base_path('resources/js/sidebar/money.js');

        $script = <<<JS
            import { money, dayPrice } from 'file://{$module}';
            let raw = '';
            process.stdin.setEncoding('utf8');
            process.stdin.on('data', (c) => { raw += c; });
            process.stdin.on('end', () => {
                const out = {};
                for (const cents of JSON.parse(raw)) {
                    out[String(cents)] = { money: money(cents), dayPrice: dayPrice(cents) };
                }
                process.stdout.write(JSON.stringify(out));
            });
            JS;

        $path = base_path('storage/framework/testing/format-money.mjs');
        @mkdir(dirname($path), 0775, true);
        file_put_contents($path, $script);

        $process = new Process(['node', $path], base_path());
        $process->setInput(json_encode($amounts, JSON_THROW_ON_ERROR));
        $process->setTimeout(60);
        $process->run();

        $this->assertTrue($process->isSuccessful(), "El formateador del cajón falló:\n".$process->getErrorOutput());

        return json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * El primer importe en el que los dos lados se separan. Un `assertSame` de 2.000 entradas es
     * ilegible; lo que hace falta es saber QUÉ céntimo rompe.
     *
     * @param  array<string, array<string, string>>  $expected
     * @param  array<string, array<string, string>>  $actual
     */
    private function firstDifference(array $expected, array $actual): string
    {
        foreach ($expected as $cents => $formats) {
            foreach ($formats as $name => $value) {
                $got = $actual[$cents][$name] ?? '(ausente)';

                if ($got !== $value) {
                    return "\nPrimera diferencia: {$cents} céntimos, formato «{$name}»\n".
                        "  servidor: «{$value}»\n".
                        "  cliente : «{$got}»\n";
                }
            }
        }

        return '';
    }
}
