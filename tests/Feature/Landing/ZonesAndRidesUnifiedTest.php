<?php

namespace Tests\Feature\Landing;

use App\Domain\Booking\Models\Zone;
use App\Domain\Content\Models\Attraction;
use Database\Seeders\LandingContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ReadsSiteStylesheets;
use Tests\TestCase;

/**
 * **ZONAS Y ATRACCIONES, UNIFICADAS** (`specs/idioma-visual-heredado.md`, T3).
 *
 * Cada zona lleva sus atracciones justo debajo. Antes eran dos secciones y una barra de pestañas.
 *
 * ❗❗ **Esta guarda nace de un defecto REAL y medido, no de una precaución.** La identidad de la
 * zona en el marcado era `accent`, que **agrupa y no identifica**: `cap` y `cap2` comparten el de
 * `kids`, así que **tres carruseles emitían el mismo `x-ref` y el mismo `data-zone`**. Medido en
 * navegador: pulsar «Zona KIDS» abría **tres a la vez** —8 tarjetas y dos vacíos, 568 px— y las
 * flechas movían uno cualquiera, porque `$refs` resuelve a uno solo.
 *
 * ⚠️⚠️ **Y la misma raíz ya se había arreglado A MEDIAS**: `ThemeColorTest` tiene desde antes un
 * caso llamado *«dos zonas que comparten acento ya no comparten color»* — se corrigió el COLOR y la
 * IDENTIDAD se quedó en `accent`. *Cuando un campo demuestra que no identifica, hay que mirar todo
 * lo que lo usa para identificar, no solo el sitio donde dolió.*
 */
class ZonesAndRidesUnifiedTest extends TestCase
{
    use ReadsSiteStylesheets;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LandingContentSeeder::class);
        app()->setLocale('es');
    }

    /**
     * **La etiqueta de edad no se corta a media letra.**
     *
     * ⚠️⚠️ La primera versión componía la etiqueta con `trim($a.' · '.$b, ' ·')` para no dejar el
     * separador suelto cuando falta una mitad. **Pero `trim` recorta BYTES, no caracteres**, y el
     * `·` es multibyte (`C2 B7`): con la lista `' ·'` se le pide a PHP que quite bytes `C2` y `B7`
     * sueltos, así que una etiqueta que empiece por `¡`, `¿`, `«`, `±` o `°` —todos empiezan por
     * `C2`— **pierde su primer byte y sale como mojibake**. Control ejecutado: `trim('¡Desde 3! ·
     * 1,30 m', ' ·')` devuelve `\xA1Desde 3!…`.
     * ▶ Se compone con `implode` sobre las partes no vacías: sin recorte, sin bytes.
     */
    public function test_the_age_tag_does_not_break_a_multibyte_character(): void
    {
        Zone::where('slug', 'jump')->update(['age_label' => ['es' => '¡Desde 3!']]);

        $html = (string) $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString(
            '¡Desde 3!', $html,
            'la etiqueta de edad ha perdido un byte de su primer carácter: se está recortando por '.
            'BYTES una cadena UTF-8.',
        );
        // ⚠️ La aserción negativa tiene que ser PRECISA, y la primera versión de este caso no lo
        // era: buscaba la cadena `\xA1Desde` y **eso aparece también dentro del UTF-8 correcto**,
        // porque `¡` son los dos bytes `C2 A1`. El defecto es un `A1` **sin su `C2` delante**.
        $this->assertDoesNotMatchRegularExpression(
            '/(?<!\\xC2)\\xA1Desde/', $html,
            'hay mojibake en la etiqueta de edad: sale el segundo byte de `¡` sin el primero, o '.
            'sea que algo ha recortado por BYTES una cadena UTF-8.',
        );
    }

    /** El cuerpo de la primera regla con ese selector exacto, o `null`. */
    private function firstRule(string $selector): ?string
    {
        foreach ($this->siteRules() as $regla) {
            if ($regla['selector'] === $selector) {
                return $regla['body'];
            }
        }

        return null;
    }

    /** Los `data-zone` que emite la portada, en orden. @return list<string> */
    private function carouselZones(): array
    {
        $html = $this->get('/')->assertOk()->getContent();
        preg_match_all('/<div class="slider"[^>]*data-zone="([^"]+)"/', (string) $html, $m);

        return $m[1];
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  Guarda de la guarda
    // ─────────────────────────────────────────────────────────────────────────────────

    /** **El localizador encuentra carruseles**: si devolviera vacío, todo lo de abajo sería un adorno. */
    public function test_the_probe_finds_the_carousels(): void
    {
        $this->assertNotEmpty(
            $this->carouselZones(),
            'el localizador no encuentra ningún carrusel en la portada: o el marcado cambió de forma, '.
            'o esta guarda lleva tiempo pasando sin mirar nada.',
        );
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  Lo que se vigila
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * **Un carrusel por zona CON atracciones, y ni uno más.**
     *
     * ⚠️⚠️ **La zona sin atracciones se CREA aquí, y ese detalle ES el caso.** La primera versión
     * comparaba lo emitido contra `whereHas('attractions')` y salía verde **pasando en vacío**: en
     * la BD de test solo hay tres zonas y la única sin atracciones —`cumpleanos`— no se muestra en
     * la landing, así que las dos listas coincidían hiciera lo que hiciera la vista. Lo demostró la
     * mutación: poniendo la condición a `@if (true)` el caso seguía en VERDE.
     * ▶ *Un caso sin sujeto no vigila nada, y no se nota hasta que se muta.*
     */
    public function test_only_zones_with_rides_emit_a_carousel(): void
    {
        $vacia = Zone::create([
            'slug' => 'zona-vacia', 'name' => ['es' => 'Zona vacía'], 'accent' => 'jump',
            'color' => '#00FF00', 'position' => 97, 'is_active' => true, 'show_in_landing' => true,
        ]);

        $html = (string) $this->get('/')->assertOk()->getContent();
        $emitidos = $this->carouselZones();

        $this->assertStringContainsString(
            'Zona vacía', $html,
            'la zona sin atracciones no llega a la portada: el caso ha perdido su sujeto y a partir '.
            'de aquí no comprueba nada.',
        );

        $this->assertNotContains(
            $vacia->slug, $emitidos,
            'una zona SIN atracciones emite carrusel: queda un carril vacío bajo su tarjeta.',
        );

        $this->assertSame(
            ['jump', 'kids'], $emitidos,
            'los carruseles emitidos no son los de las zonas con atracciones.',
        );
    }

    /**
     * **❗ EL CASO QUE MOTIVÓ LA GUARDA: dos zonas con el MISMO acento son dos carruseles distintos.**
     *
     * Con la identidad en `accent` este caso emitía dos veces el mismo `data-zone`, los dos `x-show`
     * se abrían juntos y `$refs` resolvía a uno solo.
     */
    public function test_two_zones_sharing_an_accent_do_not_share_a_carousel(): void
    {
        $kids = Zone::where('slug', 'kids')->firstOrFail();

        $gemela = Zone::create([
            'slug' => 'kids-gemela', 'name' => ['es' => 'Kids gemela'], 'accent' => $kids->accent,
            'color' => '#0000FF', 'position' => 98, 'is_active' => true, 'show_in_landing' => true,
        ]);

        Attraction::create([
            'zone_id' => $gemela->id, 'name' => ['es' => 'Atracción gemela'],
            'position' => 1, 'is_active' => true,
        ]);

        $emitidos = $this->carouselZones();

        $this->assertContains('kids', $emitidos, 'la zona original ha perdido su carrusel');
        $this->assertContains('kids-gemela', $emitidos, 'la zona nueva no emite el suyo');
        $this->assertSame(
            count($emitidos), count(array_unique($emitidos)),
            'hay `data-zone` repetidos: la identidad ha vuelto a un campo que AGRUPA en vez de '.
            'identificar, y con eso dos carruseles se pisan — `$refs` resuelve a uno solo.',
        );
    }

    /**
     * **Cada carrusel se puede recorrer con el teclado.**
     *
     * ⚠️ Las flechas NO bastan: se esconden con puntero grueso, así que en una tablet con teclado no
     * quedaría ninguna vía. Una región que se desplaza y no recibe foco incumple WCAG 2.1.1.
     */
    public function test_every_carousel_is_reachable_by_keyboard_and_has_a_name(): void
    {
        $html = (string) $this->get('/')->assertOk()->getContent();

        preg_match_all('/<div class="slider"[^>]*>/', $html, $m);

        $this->assertNotEmpty($m[0], 'no hay carruseles que mirar');

        foreach ($m[0] as $etiqueta) {
            $this->assertStringContainsString(
                'tabindex="0"', $etiqueta,
                'un carrusel no recibe foco: sin él no se puede recorrer con el teclado, y las '.
                'flechas se esconden con puntero grueso.',
            );
            $this->assertMatchesRegularExpression(
                '/aria-label="[^"]+"/', $etiqueta,
                'un carrusel no tiene nombre accesible: un lector de pantalla anuncia «grupo» y ya.',
            );
        }
    }

    /**
     * **❗ El carrusel enfocable TIENE anillo de foco.**
     *
     * ⚠️⚠️ **Este caso nace de una regresión que introdujo el propio arreglo de teclado.** Al darle
     * `tabindex="0"` al carrusel quedó **enfocable y SIN anillo**: la hoja tiene un reset
     * `*:focus { outline: none }` y la lista que lo devuelve es **CERRADA** (`a`, `button`,
     * `[role="menuitem"]`, checkbox, radio…). Un `<div tabindex="0">` no casa con ninguna, así que
     * el foco desaparecía de la vista — medido con tabulación real: `outline-style: none` en el
     * carrusel contra `solid` en un botón de la misma página.
     * ▶ *Hacer algo enfocable no es hacerlo accesible.* Y duele el doble aquí: con puntero grueso
     * las flechas se esconden, así que el teclado es la ÚNICA vía.
     */
    public function test_the_focusable_carousel_is_in_the_focus_ring_whitelist(): void
    {
        $lista = null;

        foreach ($this->siteRules() as $regla) {
            if (str_contains($regla['selector'], ':focus-visible') && str_contains($regla['body'], 'outline')) {
                $lista = ($lista ?? '').' '.$regla['selector'];
            }
        }

        $this->assertNotNull($lista, 'no hay ninguna regla de anillo de foco: esta guarda mira otra hoja');

        $this->assertStringContainsString(
            '.slider:focus-visible', (string) $lista,
            'el carrusel es enfocable (`tabindex="0"`) y NO está en la lista del anillo de foco: al '.
            'tabular, el foco entra y no se dibuja nada (WCAG 2.4.7). El reset `*:focus{outline:none}` '.
            'se lo come, y la lista que lo devuelve es cerrada.',
        );
    }

    /**
     * **Las tarjetas de zona, ya inertes, no fingen que se pueden pulsar.**
     *
     * Eran `<a href="#rides">`; con las atracciones debajo volvieron a ser `<div>`. El CSS que las
     * vestía de enlace —`cursor: pointer` y el levantamiento al hover— sobrevivió al consumidor y
     * prometía un clic que ya no existe: salía la manita, la tarjeta se levantaba, y no pasaba nada.
     */
    public function test_the_inert_zone_cards_do_not_pretend_to_be_clickable(): void
    {
        foreach (['.zone-intro__card', '.zone-photo-card'] as $selector) {
            foreach ($this->siteRules() as $regla) {
                if ($regla['selector'] !== $selector) {
                    continue;
                }

                $this->assertStringNotContainsString(
                    'cursor: pointer', $regla['body'],
                    "`{$selector}` declara `cursor: pointer` y ya no es un enlace: sale la manita y ".
                    'al pulsar no pasa nada.',
                );
            }

            $this->assertNull(
                $this->firstRule($selector.':hover'),
                "`{$selector}:hover` sigue declarado y la tarjeta ya no es interactiva: el ".
                'levantamiento al pasar el ratón promete una interacción retirada.',
            );
        }
    }

    /**
     * **La etiqueta de edad no se emite VACÍA.**
     *
     * Medido en la portada: las zonas de relleno no tienen `age_label` ni `age_range`, y la etiqueta
     * salía igualmente — una cápsula punteada con nada dentro. **No falla nada: solo se ve mal.**
     */
    public function test_the_age_tag_is_never_emitted_empty(): void
    {
        // ⚠️⚠️ **La zona SIN datos de edad se crea aquí por el mismo motivo que arriba**: en la BD
        // de test las dos zonas de la landing tienen `age_label` y `age_range`, así que sin este
        // sujeto el caso pasaba en vacío — y la mutación de emitir la etiqueta SIEMPRE salía verde.
        Zone::create([
            'slug' => 'zona-sin-edad', 'name' => ['es' => 'Zona sin edad'], 'accent' => 'jump',
            'color' => '#00FF00', 'position' => 96, 'is_active' => true, 'show_in_landing' => true,
        ]);

        $html = (string) $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString(
            'Zona sin edad', $html,
            'la zona sin datos de edad no llega a la portada: el caso se ha quedado sin sujeto.',
        );

        preg_match_all('/<span class="tag[^"]*zone-(?:photo-card|intro)__tag">(.*?)<\/span>/s', $html, $m);

        foreach ($m[1] as $contenido) {
            $this->assertNotSame(
                '', trim(strip_tags($contenido), " \t\n\r\0\x0B·"),
                'hay una etiqueta de edad vacía: una cápsula punteada sin texto dentro.',
            );
        }
    }
}
