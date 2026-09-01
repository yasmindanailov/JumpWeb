<?php

namespace Tests\Feature\Sidebar;

use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * **Toda clave de texto que una zona del cajón pide TIENE que llegarle** (`#332`).
 *
 * ❗❗ **Por qué existe, y es un defecto que llegó al navegador del owner.** `i18n.js::t()` devuelve
 * `''` cuando la clave no está — a propósito: en producción un texto ausente no puede tumbar el
 * cajón. El precio es que **una clave equivocada no falla, se queda muda**, y eso salió a la calle en
 * `#331`: el aviso de «Debes verificar tu correo» y el botón de «Cerrar sesión» se pidieron como
 * `account.verify.*` y `account.nav.*` cuando el prop `account` **ES YA** el grupo `account`, así que
 * la ruta buena era `verify.*` y `nav.*`. Medido: `data_get(__('account'), 'account.verify.pending_notice')`
 * devuelve **NULL**. El párrafo se pintó vacío y el botón sin rótulo.
 *
 * ⚠️⚠️ **Y lo que hace esta guarda distinta de mirar el fichero de idioma**: comprueba contra **el
 * payload que el servidor MANDA de verdad**, extraído del `data-boot` de una página con sesión. Hay
 * dos formas de quedarse mudo y solo una se ve leyendo `lang/`:
 *  1. la clave no existe (el fallo de `#331`);
 *  2. la clave existe **pero la poda del montaje la deja fuera** — el cajón recibe subgrupos
 *     recortados clave a clave (`layout.blade.php`) precisamente para no pagar bytes en cada página.
 * La segunda no la ve ningún `grep` en `lang/`, y es la que muerde al añadir un rótulo nuevo.
 *
 * ⚠️ **Alcance declarado, para que nadie lo crea más ancho de lo que es**: solo se comprueban las
 * llamadas con la clave escrita como LITERAL y sobre el prop `account`. Las claves computadas
 * (`titleKeyOf(zone)`, ternarios) quedan fuera — no se pueden resolver sin ejecutar el componente, y
 * fingir que sí las cubre sería peor que no cubrirlas.
 */
class SidebarTranslationKeysExistTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Las llamadas que esta guarda sabe leer, todas sobre el prop `account`:
     *  · `translate(account, 'x')` / `translateWith(account, 'x', …)` — la forma directa;
     *  · `a('x')` / `aw('x', …)` — el atajo que varios componentes declaran como
     *    `const a = (key) => translate(props.account, key)`.
     */
    private const PATTERNS = [
        "/\b(?:translate|translateWith)\(\s*account\s*,\s*'([^']+)'/",
        "/\ba\(\s*'([^']+)'\s*\)/",
        "/\baw\(\s*'([^']+)'\s*,/",
    ];

    public function test_every_literal_account_key_a_zone_asks_for_is_in_the_payload(): void
    {
        $payload = $this->accountMessages();
        $this->assertNotSame([], $payload, 'el montaje no ha traído el grupo `account`');

        $missing = [];

        foreach ($this->componentSources() as $relative => $source) {
            foreach ($this->keysIn($source) as $key) {
                if (! is_string(data_get($payload, $key))) {
                    $missing[] = "{$relative}: «{$key}»";
                }
            }
        }

        $this->assertSame([], $missing, implode("\n", array_merge(
            ['Estas claves no llegan al cajón, y `t()` las pinta como CADENA VACÍA sin avisar:'],
            array_map(static fn (string $m): string => "    {$m}", $missing),
            [
                '',
                '▶ Dos causas posibles, y conviene distinguirlas antes de tocar nada:',
                '  · la ruta está mal — el prop `account` ES YA el grupo `account`, así que se pide',
                '    «verify.resend», NO «account.verify.resend» (el fallo de `#331`);',
                '  · la clave existe pero la PODA del montaje la deja fuera: mírala en',
                '    `resources/views/components/layout.blade.php` y añádela ahí a propósito.',
            ],
        )));
    }

    /**
     * ⚠️ **El caso de CONTROL, y sin él esta guarda no demuestra nada**: si el localizador no
     * encontrara ninguna llamada, la lista de claves saldría vacía y el test pasaría en verde sobre
     * un cajón entero roto. Es la trampa que este proyecto ha pagado varias veces (una guarda que
     * «no encuentra» pasa igual que una que «encuentra y todo está bien»).
     */
    public function test_the_scanner_actually_finds_keys(): void
    {
        $found = 0;
        foreach ($this->componentSources() as $source) {
            $found += count($this->keysIn($source));
        }

        $this->assertGreaterThan(20, $found, 'el localizador de claves no está encontrando nada');
    }

    /** El grupo `account` tal y como el servidor lo MANDA a una página con sesión. */
    private function accountMessages(): array
    {
        $user = User::factory()->create();
        $html = (string) $this->actingAs($user)->get('/')->assertOk()->getContent();

        if (preg_match('/id="sidecart-spa" data-boot="([^"]*)"/', $html, $matches) !== 1) {
            $this->fail('no se ha encontrado el punto de montaje de la SPA en la página');
        }

        $boot = json_decode(html_entity_decode($matches[1], ENT_QUOTES), true, 512, JSON_THROW_ON_ERROR);

        return $boot['messages']['account'] ?? $boot['account'] ?? [];
    }

    /** @return array<string, string> ruta relativa → fuente */
    private function componentSources(): array
    {
        $root = base_path('resources/js/sidebar');
        $out = [];

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));
        foreach ($files as $file) {
            if (! $file->isFile() || ! in_array($file->getExtension(), ['vue', 'js'], true)) {
                continue;
            }
            if (str_contains($file->getFilename(), '.test.')) {
                continue;
            }
            $out[str_replace($root.'/', '', $file->getPathname())] = (string) file_get_contents($file->getPathname());
        }

        return $out;
    }

    /** @return list<string> */
    private function keysIn(string $source): array
    {
        $keys = [];

        foreach (self::PATTERNS as $pattern) {
            if (preg_match_all($pattern, $source, $m) > 0) {
                $keys = array_merge($keys, $m[1]);
            }
        }

        return array_values(array_unique($keys));
    }
}
