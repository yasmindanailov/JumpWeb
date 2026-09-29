<?php

namespace App\Console\Commands;

use App\Domain\Booking\Models\Zone;
use App\Domain\Content\Services\IllustrationKit;
use Illuminate\Console\Command;
use Throwable;

/**
 * **Instala el KIT DE ILUSTRACIÓN de una instalación** (`specs/hueco-ilustracion.md` §5).
 *
 * Toma el sprite que entrega el diseñador, lo valida entero y —solo si pasa— lo escribe en
 * `public/img/client-kit.svg`, que es de donde el producto lo sirve con `<use>`.
 *
 * ⚠️⚠️ **Valida, NO sanea.** Si el fichero trae un `<style>` o un `fill` propio, este comando
 * **rechaza y no escribe**, en vez de quitarlos por su cuenta. El motivo no es pereza: sanear por
 * dentro cambiaría el dibujo de un cliente sin que él lo sepa —un `fill="none"` puede ser
 * deliberado— y lo dejaría creyendo que su kit entró tal cual. Es la misma política de todo-o-nada
 * que `InlineSvg` eligió para el logotipo.
 *
 * ⚠️ **Y esto no basta**, dicho para que nadie se confíe: si alguien copia un sprite a mano en el
 * servidor, este comando no se entera. Por eso la validación se repite al desplegar (`§14·U5`).
 *
 * ▶ **`--podar`** (T6g, `specs/isla-y-landing-nueva.md` §4.25): cuando el producto RETIRA ranuras, el kit instalado
 * pasa a traer claves que se rechazan. Esto las quita —solo los `slot-*` que `SLOTS` ya no declara, dichos uno a uno—
 * y valida lo que queda antes de escribirlo; sin origen, poda el instalado. Con `--check`, el ensayo: dice qué
 * quitaría y si el resto es servible, sin escribir. No es la política de «validar, no sanear» rota: no toca ningún
 * dibujo que se quede; quita los que ya no tienen dónde pintarse.
 */
class BuildIllustrationKit extends Command
{
    protected $signature = 'kit:build
        {origen? : Ruta del SVG que entrega el diseñador. Sin ella se valida el kit YA instalado.}
        {--check : No escribe nada; solo dice si el kit es servible. Es el modo del despliegue.}
        {--podar : Quita las ranuras `slot-*` que el producto ya no declara (dice cuáles) y escribe el resto si es servible.}';

    protected $description = 'Valida el kit de ilustración de la instalación y lo instala en public/img/client-kit.svg';

    public function handle(): int
    {
        $destino = public_path(IllustrationKit::PATH);
        $origen = $this->argument('origen') ?? $destino;
        $podar = (bool) $this->option('podar');
        // Sin origen se COMPRUEBA el instalado, salvo que se pida podarlo: entonces se reescribe (si queda servible).
        $soloComprobar = (bool) $this->option('check') || ($this->argument('origen') === null && ! $podar);

        if (! is_file($origen) || ! is_readable($origen)) {
            // ⚠️ Sin kit NO es un error del producto: es el estado normal de este repo y de
            // cualquier instalación que no traiga ilustración. El hueco falla hacia invisible.
            if ($soloComprobar) {
                $this->line("Sin kit instalado en <comment>{$origen}</comment>. Nada que validar.");

                return self::SUCCESS;
            }

            $this->error("No se puede leer «{$origen}».");

            return self::FAILURE;
        }

        $svg = (string) file_get_contents($origen);

        if ($podar) {
            ['svg' => $svg, 'removed' => $quitadas] = IllustrationKit::withoutRetiredSlots($svg, $this->slots());
            $this->line($quitadas === []
                ? 'Ninguna ranura que podar: el kit solo trae las que el producto declara.'
                : ($soloComprobar ? 'Se quitarían ' : 'Se quitan ').count($quitadas).' ranura(s) que el producto ya no declara: '.implode(', ', $quitadas).'.');
        }

        $problemas = IllustrationKit::problems($svg, $this->zoneSlugs(), $this->slots());

        if ($problemas !== []) {
            $this->error('El kit NO es servible. '.count($problemas).' problema(s):');

            foreach ($problemas as $p) {
                $this->line('  · '.$p);
            }

            $this->newLine();
            $this->line('▶ No se ha escrito nada. El contrato está en `docs/specs/hueco-ilustracion.md` §4.1.');

            return self::FAILURE;
        }

        $simbolos = preg_match_all('/<symbol\b/i', $svg);

        if ($soloComprobar) {
            $this->info("Kit servible: {$simbolos} símbolo(s).");

            return self::SUCCESS;
        }

        @mkdir(dirname($destino), 0o775, true);

        if (@file_put_contents($destino, $svg) === false) {
            $this->error("No se ha podido escribir «{$destino}».");

            return self::FAILURE;
        }

        $this->info("Kit instalado: {$simbolos} símbolo(s) en {$destino}.");

        return self::SUCCESS;
    }

    /**
     * Los `slug` de las zonas VIVAS. Sin ellos no se puede saber si un `zone-*` apunta a algo.
     *
     * ⚠️ Si la base de datos no responde se devuelve `null` y la comprobación de esas claves **se
     * salta**, en vez de inventarse que todas están mal: un comando de instalación que falla porque
     * la BD aún no está levantada no ayuda a nadie.
     *
     * @return list<string>
     */
    private function zoneSlugs(): array
    {
        try {
            return Zone::query()->where('is_active', true)->pluck('slug')->filter()->values()->all();
        } catch (Throwable $e) {
            $this->warn('No se ha podido leer las zonas ('.$e->getMessage().'): las claves `zone-*` no se comprueban.');

            return [];
        }
    }

    /**
     * Las ranuras decorativas que el PRODUCTO declara.
     *
     * ⚠️ Sale de {@see IllustrationKit::SLOTS}, que es el único sitio donde se escriben. La lista
     * **solo crece, y solo con su consumidor en el mismo cambio**: declarar una ranura que ninguna
     * pantalla pinta es lo que dejó los 19 dibujos de `#257` esperando.
     *
     * @return list<string>
     */
    private function slots(): array
    {
        return IllustrationKit::SLOTS;
    }
}
