<?php

namespace App\Http\Instancia;

use Illuminate\Foundation\Vite;

/**
 * **Lo que la compra necesita al abrirse, para adelantarlo** (`isla-y-landing-nueva.md` §4.14, `DECISIONES #783`).
 *
 * En una página declarada la compra no viaja con la página: al pulsar, el cajón trae su arranque, el motor, la compra de
 * la isla y sus pasos, uno detrás de otro. Medido con un 4G simulado (150ms de latencia, 1,6 Mbps): la compra aparecía a
 * los 2 SEGUNDOS de tocar, 1,1 de ellos bajando el motor. Estas son las direcciones de esos trozos —con los que importan de
 * forma estática, menos los que la página ya trae—, para que la isla de la página los pida en segundo plano cuando la
 * página está quieta (`modulepreload`: se bajan y se compilan, no se ejecutan; al abrir, el `import()` los encuentra).
 *
 * Solo con el manifiesto construido: con el servidor de Vite en caliente, o sin construir, no hay nada que adelantar.
 */
class PrecargaDeCompra
{
    /** Lo que se abre al pulsar: el arranque de una página ajena, el motor, la compra de la isla y sus pasos. */
    public const TROZOS = [
        'resources/js/cajon/standalone.js',
        'resources/js/sidebar/index.js',
        'resources/js/isla/SeccionCompra.vue',
        'resources/js/isla/compra/pasos-diferidos.js',
    ];

    /**
     * @param  list<string>  $entradasDeLaPagina  lo que la página ya carga (sus entradas de Vite): lo suyo no se repite
     * @return list<string> las URLs, sin repetir
     */
    public static function urls(array $entradasDeLaPagina): array
    {
        $vite = app(Vite::class);
        $fichero = public_path('build/manifest.json');

        if ($vite->isRunningHot() || ! is_file($fichero)) {
            return [];
        }
        /** @var array<string, array{file: string, imports?: list<string>}> $manifiesto */
        $manifiesto = json_decode((string) file_get_contents($fichero), true) ?: [];
        $alcance = static function (array $claves) use ($manifiesto): array {
            $vistos = [];
            $pendientes = $claves;
            while ($pendientes !== []) {
                $k = array_pop($pendientes);
                if (isset($vistos[$k]) || ! isset($manifiesto[$k])) {
                    continue;
                }
                $vistos[$k] = true;
                array_push($pendientes, ...($manifiesto[$k]['imports'] ?? []));
            }

            return array_keys($vistos);
        };
        $yaEsta = array_flip($alcance($entradasDeLaPagina));
        $faltan = array_values(array_filter($alcance(self::TROZOS), fn (string $k): bool => ! isset($yaEsta[$k])));

        return array_values(array_unique(array_map(fn (string $k): string => asset('build/'.$manifiesto[$k]['file']), $faltan)));
    }
}
