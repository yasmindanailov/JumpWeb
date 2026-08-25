<?php

namespace Tests\Feature\Architecture;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * **LA SUITE NO PUEDE DECIDIR SOLA SI UN `Cache::tags()` FUNCIONARÁ EN PRODUCCIÓN**
 * (`DECISIONES #137`, `ENTORNOS.md` §4).
 *
 * ⚠️⚠️ El motivo es incómodo y está medido: **el store que usa la suite (`array`) SOPORTA tags, y los
 * que ha usado producción (`database`, `file`) LANZAN**. O sea que un test escrito de buena fe sale
 * VERDE aquí y revienta con `BadMethodCallException` en la primera petición del servidor. Es la misma
 * forma de fallo que `DECISIONES #129` —«la guarda de conducta salía VERDE en SQLite»— y la razón por
 * la que el `compose.yaml` trae un Redis: sin uno REAL, esto no se puede comprobar.
 *
 * Este fichero cubre las dos mitades:
 *
 *  1. **que la asimetría siga siendo la que creemos** — si Laravel cambiara y `database` empezara a
 *     soportar tags, o `array` dejara de hacerlo, lo que hay debajo dejaría de tener sentido y hay
 *     que enterarse;
 *  2. **que los tags funcionen contra un Redis de verdad**, que es lo que producción va a ejecutar.
 *
 * ▶ Y el tercer caso es el que de verdad protege: **se activa solo el día que alguien use tags**.
 * Hoy nadie lo hace, así que duerme; en cuanto aparezca el primer `Cache::tags()`, exige que el canal
 * de despliegue garantice un store que los soporte. Una guarda que hay que acordarse de escribir
 * *después* es una guarda que no se escribe.
 */
class CacheTaggingContractTest extends TestCase
{
    /**
     * **La asimetría, MEDIDA y no recordada.**
     *
     * ⚠️ Es el hecho del que cuelga todo lo demás, y es contraintuitivo: el store de los tests es
     * **más capaz** que el de producción, así que la suite es optimista justo donde no debe.
     */
    public function test_the_store_the_suite_uses_is_more_capable_than_the_one_production_used(): void
    {
        $this->assertTrue(
            $this->supportsTagging('array'),
            'el store de la suite ha dejado de soportar tags: los casos de abajo ya no miden lo que dicen',
        );

        foreach (['database', 'file'] as $store) {
            $this->assertFalse(
                $this->supportsTagging($store),
                "`$store` ha empezado a soportar tags. Es una buena noticia, pero invalida el porqué de ".
                'este fichero y el de `DECISIONES #137`: revísalos antes de borrar nada.',
            );
        }
    }

    /**
     * **Los tags, contra un REDIS REAL** — el del `compose.yaml`, con la misma versión que staging.
     *
     * ⚠️ **Si Redis no está, este caso FALLA en vez de saltarse**, y es deliberado: Redis es requisito
     * DURO de instalación (`#137`), así que un `markTestSkipped` dejaría la guarda verde para siempre
     * en la máquina que no lo tenga — que es justo la máquina donde importa.
     */
    public function test_tags_actually_work_against_a_real_redis(): void
    {
        // ⚠️ Sufijo único: `--parallel` levanta varios procesos contra el MISMO Redis y comparten
        // prefijo, así que un nombre fijo haría que un worker invalidara la clave de otro. El fallo
        // se vería como intermitente, que es la peor forma de verse.
        $n = Str::random(8);
        $redis = Cache::store('redis');

        $redis->tags(["contenido-$n", "zonas-$n"])->put("probe-$n", 'vivo', 60);

        $this->assertSame(
            'vivo', $redis->tags(["contenido-$n", "zonas-$n"])->get("probe-$n"),
            'el Redis del stack no guarda una entrada etiquetada: ¿está levantado? `docker compose up -d`',
        );

        // Lo que hace útiles los tags: invalidar por UNO de ellos, sin conocer las claves.
        $redis->tags(["zonas-$n"])->flush();

        $this->assertNull(
            $redis->tags(["contenido-$n", "zonas-$n"])->get("probe-$n"),
            'vaciar un tag no invalidó la entrada: la invalidación por evento no serviría',
        );

        // Y la guarda de la guarda: que el flush de un tag NO se lleve por delante lo que no lleva
        // ese tag. Sin esto, un `flush()` global pasaría este caso fingiendo precisión.
        $redis->tags(["otro-$n"])->put("intacto-$n", 'sigo', 60);
        $redis->tags(["zonas-$n"])->flush();

        $this->assertSame(
            'sigo', $redis->tags(["otro-$n"])->get("intacto-$n"),
            'vaciar un tag se llevó entradas de otro: la invalidación sería un martillo, no un bisturí',
        );

        $redis->tags(["otro-$n"])->flush();
    }

    /**
     * ⚠️⚠️ **LA GUARDA QUE SE ACTIVA SOLA.**
     *
     * Hoy nadie usa tags, así que este caso pasa sin mirar nada — y ése es el punto. En cuanto
     * aparezca el primer `Cache::tags()` sobre el store por DEFECTO, exige que `deploy.sh` garantice
     * un store que los soporte, porque a partir de ese commit una instalación con `CACHE_STORE=database`
     * **sirve un 500** en cuanto se toque esa ruta.
     *
     * ⚠️ Se excluye el uso con store EXPLÍCITO (`Cache::store('redis')->tags(...)`), que es lo que
     * hace este propio fichero: ahí el store no depende de la configuración del servidor.
     */
    public function test_the_day_someone_uses_tags_the_deploy_must_guarantee_a_store_that_has_them(): void
    {
        $usos = [];

        foreach ($this->phpFilesIn(base_path('app')) as $ruta) {
            $codigo = (string) file_get_contents($ruta);

            // `Cache::tags(` o `cache()->tags(` — el uso que cuelga del store por defecto.
            if (preg_match('/(?:Cache::|cache\(\)->)tags\s*\(/', $codigo)) {
                $usos[] = str_replace(base_path().'/', '', $ruta);
            }
        }

        if ($usos === []) {
            $this->assertSame([], $usos, 'nadie usa tags todavía: la guarda sigue dormida');

            return;
        }

        $deploy = (string) file_get_contents(base_path('scripts/deploy.sh'));

        $porque = implode("\n", array_merge(
            ['Alguien usa `Cache::tags()` sobre el store por DEFECTO:'],
            array_map(fn (string $u): string => "  · $u", $usos),
            ['',
                '▶ A partir de aquí, una instalación con `CACHE_STORE=database` sirve un 500 en cuanto',
                '  se toque esa ruta: `database` LANZA al usar tags (`DECISIONES #137`).',
                '▶ `scripts/deploy.sh` tiene que EXIGIRLO, igual que ya exige `QUEUE_CONNECTION=database`',
                '  (GUARDA 6) y `APP_DEBUG=false`.'],
        ));

        // ⚠️ Se comprueba la GUARDA, no la palabra: `deploy.sh` ya nombra `CACHE_STORE` en su
        // plantilla de `.env`, así que buscar la cadena a secas habría pasado siempre sin mirar nada
        // —el modo de fallo de toda guarda por `grep`, y el motivo de que `LedgerSingleSourceTest`
        // lleve la suya—.
        $this->assertMatchesRegularExpression(
            '/guard_errors\+=\([^\n]*CACHE_STORE/', $deploy,
            $porque."\n▶ Falta la línea `guard_errors+=(… CACHE_STORE …)` que lo hace fallar.",
        );

        $this->assertStringNotContainsString(
            'CACHE_STORE=database', $deploy,
            $porque."\n▶ Y la plantilla de `.env` sigue proponiendo `CACHE_STORE=database`, que es".
            ' exactamente el valor que ahora revienta.',
        );
    }

    /** ¿Este store admite `->tags()`? Se pregunta EJECUTÁNDOLO, no leyendo la clase. */
    private function supportsTagging(string $store): bool
    {
        try {
            Cache::store($store)->tags(['contract-probe'])->put('k', 1, 5);

            return true;
        } catch (\BadMethodCallException) {
            return false;
        }
    }

    /**
     * @return list<string>
     */
    private function phpFilesIn(string $dir): array
    {
        $rutas = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));

        foreach ($it as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $rutas[] = $file->getPathname();
            }
        }

        return $rutas;
    }
}
