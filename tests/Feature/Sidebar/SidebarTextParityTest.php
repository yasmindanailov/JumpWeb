<?php

namespace Tests\Feature\Sidebar;

use App\Http\Middleware\SetLocale;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Fase 4 · paso 4.3·1 — **el cajón SPA lee el diccionario como lo lee `__()`**.
 *
 * El servidor inyecta el grupo `tickets` en el montaje (§4.5) y el cliente lo consulta. Suena trivial
 * y no lo es: el helper que los pasos traían inline fallaba en tres cosas MEDIDAS, **las tres en
 * silencio y ninguna visible para el diff de árbol**, que descarta los nodos de texto.
 *
 *  1. **El payload NO es plano**: de las 121 claves de primer nivel, cuatro son subarrays (`errors`,
 *     `paused`, `statuses`, `payment_failed`). `messages['errors.choose_one']` es `undefined` y el
 *     helper devolvía `''`: el aviso de error se pintaba VACÍO.
 *  2. **`String.replace` con patrón de texto sustituye la PRIMERA aparición**, y `cart_items` lleva
 *     `:count` dos veces.
 *  3. **`cart_items` se sirve CRUDA con su barra dentro**: sin resolver la pluralización, la
 *     barra-carrito enseñaría literalmente «2 artículo|2 artículos».
 *
 * Se recorren los TRES idiomas del sitio público, leyendo la lista del propio middleware: el día que
 * crezca, este test cae y obliga a mirar el selector de plural del cliente.
 */
class SidebarTextParityTest extends TestCase
{
    /**
     * Las claves con parámetros que pinta el cajón, con los valores que les llegan de verdad.
     *
     * @return array<string, array<string, string|int>>
     */
    private function parameterised(): array
    {
        return [
            'guests_count' => ['count' => 8],
            'guests_left' => ['count' => 12],
            'seats_left' => ['count' => 5],
            'addon_included_partial' => ['count' => 1],
            'addon_requires' => ['name' => 'Tarta'],
            'addon_per_guest_qty' => ['count' => 8],
            'deposit_catalog' => ['amount' => '30,00 €'],
            'step_count' => ['n' => 2, 'total' => 3],
            // ⚠️ Dos marcadores, y el símbolo del euro va DENTRO del valor sustituido: el blade
            // inyecta `number_format(...).' €'` en cada uno. La plantilla no lo añade.
            'deposit_card_note' => ['deposit' => '30,00 €', 'rest' => '114,00 €'],
            'errors.fields_missing' => ['fields' => 'Homenajeado, Edad'],
        ];
    }

    public function test_the_client_resolves_the_same_texts_as_the_server(): void
    {
        foreach (SetLocale::SUPPORTED as $locale) {
            $this->app->setLocale($locale);

            $expected = [];
            foreach ($this->parameterised() as $key => $params) {
                $expected[$key] = __('tickets.'.$key, $params);
            }

            // Claves ANIDADAS, que son las que el helper anterior no sabía leer.
            foreach (['errors.choose_one', 'errors.cart_too_large', 'errors.field_required'] as $key) {
                $expected[$key] = __('tickets.'.$key);
            }

            $fromClient = $this->resolveInNode($locale, array_keys($expected), $this->parameterised());

            $this->assertSame(
                $expected, $fromClient,
                "Los textos del cajón SPA NO coinciden con los de `__()` en «{$locale}».\n".
                '⚠️ El diff de árbol no puede cazar esto: descarta los nodos de texto. Los subarrays '.
                '(`errors`, `paused`, `statuses`, `payment_failed`) se leen por CAMINO, no por clave literal.'
            );
        }
    }

    /**
     * ⚠️ **La pluralización de Laravel no es `n === 1`.**
     *
     * `cart_items` es la única clave del grupo con dos formas, y la pinta la barra-carrito del paso 1.
     * En francés el CERO cae en el SINGULAR, que es justo el número que más se ve en una barra de
     * carrito; un ternario ingenuo diverge ahí y en ningún otro sitio.
     */
    public function test_the_client_pluralises_like_trans_choice(): void
    {
        foreach (SetLocale::SUPPORTED as $locale) {
            $this->app->setLocale($locale);

            foreach ([0, 1, 2, 5, 21] as $count) {
                $expected = trans_choice('tickets.cart_items', $count, ['count' => $count]);
                $fromClient = $this->pluraliseInNode($locale, 'cart_items', $count);

                $this->assertSame(
                    $expected, $fromClient,
                    "La barra-carrito del cajón SPA escribiría «{$fromClient}» donde el servidor escribe ".
                    "«{$expected}» ({$locale}, {$count} artículos)."
                );
            }
        }
    }

    /**
     * **La guarda de la guarda**: el cliente solo entiende la forma `singular|plural`.
     *
     * Si alguien añade al grupo `tickets` una clave con la sintaxis de rangos de Laravel
     * (`{0} nada|[1,*] algo`), el cajón SPA la pintaría entera, con las llaves dentro. Esto lo dice
     * el día que pase, en vez de dejarlo salir a producción.
     */
    public function test_no_ticket_key_uses_a_form_the_client_cannot_resolve(): void
    {
        foreach (SetLocale::SUPPORTED as $locale) {
            $this->app->setLocale($locale);

            foreach ($this->flatten(__('tickets')) as $key => $text) {
                $this->assertDoesNotMatchRegularExpression(
                    '/^\s*[\{\[]/',
                    $text,
                    "La clave `tickets.{$key}` ({$locale}) usa la sintaxis de RANGOS de Laravel, y el ".
                    'cajón SPA solo resuelve `singular|plural` (`resources/js/sidebar/i18n.js`). '.
                    'O se reescribe la clave, o el módulo tiene que aprender los rangos.'
                );

                if (str_contains($text, '|')) {
                    $this->assertSame(
                        'cart_items', $key,
                        "La clave `tickets.{$key}` ({$locale}) tiene DOS formas y el cajón SPA solo llama a ".
                        '`tc()` para `cart_items`. Quien la pinte con `t()` verá la barra vertical en pantalla.'
                    );
                }
            }
        }
    }

    /**
     * `['errors' => ['x' => 'y']]` → `['errors.x' => 'y']`, para poder recorrer el grupo entero.
     *
     * @param  array<string, mixed>  $group
     * @return array<string, string>
     */
    private function flatten(array $group, string $prefix = ''): array
    {
        $flat = [];

        foreach ($group as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;

            if (is_array($value)) {
                $flat += $this->flatten($value, $path);

                continue;
            }

            $flat[$path] = (string) $value;
        }

        return $flat;
    }

    /**
     * Resuelve claves con `i18n.js` en Node, con el payload REAL del montaje.
     *
     * @param  list<string>  $keys
     * @param  array<string, array<string, string|int>>  $params
     * @return array<string, string>
     */
    private function resolveInNode(string $locale, array $keys, array $params): array
    {
        return $this->runInNode(<<<'JS'
            import { tp } from 'file://__MODULE__';
            let raw = '';
            process.stdin.setEncoding('utf8');
            process.stdin.on('data', (c) => { raw += c; });
            process.stdin.on('end', () => {
                const { messages, keys, params } = JSON.parse(raw);
                const out = {};
                for (const key of keys) out[key] = tp(messages, key, params[key] ?? {});
                process.stdout.write(JSON.stringify(out));
            });
            JS,
            ['messages' => __('tickets'), 'keys' => $keys, 'params' => $params],
            'resolve-texts.mjs'
        );
    }

    private function pluraliseInNode(string $locale, string $key, int $count): string
    {
        $out = $this->runInNode(<<<'JS'
            import { tc } from 'file://__MODULE__';
            let raw = '';
            process.stdin.setEncoding('utf8');
            process.stdin.on('data', (c) => { raw += c; });
            process.stdin.on('end', () => {
                const { messages, key, count, locale } = JSON.parse(raw);
                process.stdout.write(JSON.stringify({ text: tc(messages, key, count, locale) }));
            });
            JS,
            ['messages' => __('tickets'), 'key' => $key, 'count' => $count, 'locale' => $locale],
            'pluralise-text.mjs'
        );

        return $out['text'];
    }

    /**
     * Mismo patrón que `SidebarCalendarParityTest`: script efímero que importa el módulo REAL por
     * ruta ABSOLUTA —el `import` se resuelve respecto al fichero, no al directorio de trabajo— y
     * habla por stdin/stdout con JSON.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function runInNode(string $script, array $input, string $filename): array
    {
        $module = base_path('resources/js/sidebar/i18n.js');
        $path = base_path('storage/framework/testing/'.$filename);

        @mkdir(dirname($path), 0775, true);
        file_put_contents($path, str_replace('__MODULE__', $module, $script));

        $process = new Process(['node', $path], base_path());
        $process->setInput(json_encode($input, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        $process->setTimeout(60);
        $process->run();

        $this->assertTrue($process->isSuccessful(), "El diccionario del cajón falló:\n".$process->getErrorOutput());

        return json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
    }
}
