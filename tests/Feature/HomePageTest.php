<?php

namespace Tests\Feature;

use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\Setting;
use App\Providers\AppServiceProvider;
use Database\Seeders\LandingContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HomePageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LandingContentSeeder::class);
        // Cache del precio mínimo de los CTAs (jerarquía nueva) limpio por test:
        // el driver `array` en pruebas comparte memoria entre tests del mismo run.
        Cache::flush();
    }

    public function test_home_loads_in_spanish(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Saltos libres');          // atracción real desde BD (ES)
        $response->assertSee('El parque');              // trigger del 1.º desplegable del nav (ES)
    }

    public function test_language_can_switch_to_english(): void
    {
        $this->get('/lang/en')->assertRedirect();

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Free jump');              // atracción real desde BD (EN)
        $response->assertSee('The park');               // trigger del 1.º desplegable del nav (EN)
    }

    public function test_unsupported_locale_is_ignored(): void
    {
        $this->get('/lang/de'); // alemán no soportado → se ignora

        $this->get('/')->assertOk()->assertSee('Saltos libres');
    }

    public function test_home_exposes_seeded_content(): void
    {
        $response = $this->get('/');

        $response->assertSee('Mesa reservada para el grupo'); // feature del producto pack (ES, #87/3c)
        $response->assertSee('Jump · 2 horas');          // entrada destacada (ES)
        $response->assertSee('hola@saltopark.example');    // setting de contacto
    }

    public function test_landing_hides_a_non_sellable_pack_from_its_cta_section(): void
    {
        // Coherencia CTA⟺catálogo (#210): el CTA «Reservar ahora» de la landing dispara el
        // deep-link al catálogo del sidebar, que SOLO vende packs `sellable()->inOperationalZone()`.
        // La landing usa el MISMO predicado → no anuncia un pack que el flujo no puede vender (si lo
        // hiciera, el deep-link `show-packs` apuntaría a una sección «Servicios» inexistente). Un
        // pack `is_active=true` pero NO vendible queda fuera de la landing.
        TicketType::ofType(TicketType::TYPE_PACK)
            ->where('position', 10)            // «Cumpleaños Kids» (seed)
            ->update(['is_sellable' => false]);

        foreach (['/', '/cumpleanos'] as $url) {
            $this->get($url)
                ->assertSee('Acceso exclusivo a la zona Jump')       // pack vendible → se anuncia
                ->assertDontSee('Acceso exclusivo a la zona Kids');  // pack no vendible → fuera
        }
    }

    public function test_landing_hides_packs_whose_operational_zone_is_disabled(): void
    {
        // `inOperationalZone()` también en la landing: si la zona operativa de los packs se
        // desactiva, dejan de anunciarse (coherente con el catálogo, que tampoco los vende).
        Zone::where('slug', 'cumpleanos')->update(['is_active' => false]);

        $this->get('/')->assertDontSee('Acceso exclusivo a la zona Jump');
        $this->get('/cumpleanos')->assertDontSee('Acceso exclusivo a la zona Kids');
    }

    public function test_entry_cards_show_a_book_cta_and_call_fallback_when_not_sellable(): void
    {
        // #228 punto 5: cada card de entrada tiene un CTA. Las vendibles → «Reservar» (abre el
        // sidebar); las que NO tienen venta online (is_sellable=false) → fallback «Llamar» (tel:
        // del contacto), porque la entrada sigue apareciendo en la landing (filtro is_active).
        Setting::updateOrCreate(['key' => 'contact.phone'], ['value' => '968 22 22 22', 'group' => 'contact']);
        TicketType::ofType(TicketType::TYPE_ENTRY)->where('position', 1)->update(['is_sellable' => false]);

        $this->get('/')
            ->assertSee(__('landing.pricing.book'))   // CTA «Reservar» (entradas vendibles)
            ->assertSee(__('landing.pricing.call'))   // fallback «Llamar» (la no vendible)
            ->assertSee('tel:968222222', false);      // enlace de llamada con los dígitos del teléfono
    }

    public function test_socks_note_renders_under_the_price_grid(): void
    {
        // Nota general «calcetines antideslizantes obligatorios» bajo el grid de precios, con el
        // icono de marca S1. Vive en `ticket-prices` → aparece en la landing Y en /precios.
        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('Calcetines antideslizantes obligatorios')          // título (ES)
            ->assertSee('Puedes traerlos de casa', false)                    // el cliente los puede traer de casa
            ->assertSee('ic-s1', false)                                      // icono de marca S1 (animado, white-label)
            ->assertSee('socks-note__title', false);                        // callout reutilizable

        // La misma nota acompaña al catálogo en la página de tarifas dedicada (/precios).
        $this->get('/precios')->assertOk()->assertSee('Calcetines antideslizantes obligatorios');
    }

    public function test_socks_note_is_translated(): void
    {
        $this->get('/lang/en');
        $this->get('/')->assertSee('Non-slip socks required')->assertSee('Bring your own from home', false);

        $this->get('/lang/fr');
        $this->get('/')->assertSee('Chaussettes antidérapantes obligatoires')->assertSee('Apporte les tiennes', false);
    }

    public function test_home_includes_the_purchase_sidebar(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('sidecart', false)            // el sidebar de compra está en el layout
            ->assertSee('Reservas');                  // título del sidebar (ES, #216)
    }

    public function test_entradas_route_opens_the_sidebar(): void
    {
        // /entradas renderiza la home y marca el sidebar para abrirse (enlace profundo).
        $this->get('/entradas')
            ->assertOk()
            ->assertSee('data-purchase-open="1"', false);
    }

    public function test_home_does_not_open_sidebar_by_default(): void
    {
        // Regresión: sin enlace profundo ni outcome de Redsys, el sidebar arranca cerrado.
        $this->get('/')
            ->assertOk()
            ->assertSee('data-purchase-open=""', false);
    }

    public function test_home_opens_sidebar_when_redsys_confirmed_code_in_session(): void
    {
        // #106 (refinado tras validación visual 2026-05-26): tras la vuelta OK firmada de
        // Redsys, HomeController escribe `purchase.confirmed_code` en sesión y redirige a
        // la home. El layout debe abrir el sidebar (Alpine lee `data-purchase-open`); el
        // Purchase Livewire consume la flag en mount() y muestra el paso 6.
        $this->withSession(['purchase.confirmed_code' => 'JJ-ABC123'])
            ->get('/')
            ->assertOk()
            ->assertSee('data-purchase-open="1"', false);
    }

    public function test_home_opens_sidebar_when_redsys_failed_code_in_session(): void
    {
        // Mismo mecanismo para el outcome KO (paso 10 — pago denegado).
        $this->withSession(['purchase.failed_code' => 'JJ-DEF456'])
            ->get('/')
            ->assertOk()
            ->assertSee('data-purchase-open="1"', false);
    }

    public function test_home_opens_sidebar_when_redsys_verifying_code_in_session(): void
    {
        // Y para el outcome "data-less" del fallback #106 (paso 11 — verificando pago).
        $this->withSession(['purchase.verifying_code' => 'JJ-GHI789'])
            ->get('/')
            ->assertOk()
            ->assertSee('data-purchase-open="1"', false);
    }

    /* ====================================================================
       Jerarquía de CTAs (mockup `design_mockup/jerarquia-ctas.html`):
       blindamos el contrato visual + de comportamiento de los 3 pesos.
       ==================================================================== */

    public function test_guest_nav_renders_register_ghost_cta(): void
    {
        // Ghost CTA: solo guests (#46, Fase 4.2). Selector `nav-cta-ghost` + su cableado son contrato.
        //
        // ⚠️ **Abría el modal de registro y desde el 2026-08-23 abre el CAJÓN** (`DECISIONES #122`):
        // el alta es una zona más, así que el CTA lleva a ella sin sacar al cliente de la página. El
        // sujeto de este caso —que el invitado reciba el CTA con su cableado— no ha cambiado; sí lo
        // que ese cableado invoca (`CONVENCIONES §3.quater`).
        $response = $this->get('/')->assertOk();

        $response->assertSee('nav-cta-ghost', false);
        $response->assertSee('cta-ghost__t', false);
        $response->assertSee("openAccount(\$event, 'register')", false);
        // ⚠️ Y su `href`: la ruta existe como PUERTA, así que el clic central, «abrir en pestaña
        // nueva» y un navegador sin JS acaban en la misma pantalla. Con el `<button>` de antes esos
        // tres casos no hacían nada.
        $response->assertSee('href="'.route('registro').'"', false);
        $response->assertSee('Registrarse'); // copy ES de `nav.reserve`
    }

    public function test_authenticated_nav_hides_ghost_and_shows_account_menu(): void
    {
        // Usuario con sesión: el ghost de "Registrarse" desaparece y emerge el
        // dropdown `nav__acct` (#79). El filled "Comprar entradas" permanece.
        $user = User::factory()->create(['email_verified_at' => now()]);

        $response = $this->actingAs($user)->get('/')->assertOk();

        // ⚠️ **RE-APUNTADO en la 2c·7 y el sujeto NO cambia.** `nav-cta-ghost` ya no distingue
        // nada: desde que la cuenta es una mitad del PAR lleva esa misma clase —su caja es la del
        // botón fantasma—. Lo que había que comprobar sigue siendo que **con sesión no se ofrece
        // el ALTA**, y eso se comprueba por el DESTINO.
        $response->assertDontSee(route('registro'), false);
        $response->assertSee('nav__acct', false);
        $response->assertSee('nav-cta-med', false); // filled SÍ visible auth
    }

    public function test_nav_renders_buy_filled_cta_for_everyone(): void
    {
        // Filled CTA del header: visible para guest y auth. Abre el sidebar de
        // compra (#66). Copy "Comprar entradas" + anclaje "desde X,XX €" en el
        // subtítulo `.cta-med__s` (data-driven con el catálogo activo) — replica
        // el `.cta-med` del mockup `design_mockup/jerarquia-ctas.html`.
        $expected = AppServiceProvider::formatPriceLabel($this->cheapestEntryCents());

        $response = $this->get('/')->assertOk();

        $response->assertSee('nav-cta-med', false);
        $response->assertSee('cta-med__t', false);
        $response->assertSee('cta-med__s', false);
        $response->assertSee('cta-med__body', false);
        $response->assertSee('$store.purchase.open()', false);
        $response->assertSee('Reservas aquí');   // #216 «Reservar ahora» → #222 «Reservas aquí»
        $response->assertSeeText("desde {$expected}");
    }

    public function test_nav_cta_omits_price_subtitle_when_catalog_empty(): void
    {
        // Sin catálogo vendible, el view composer global pasa `ctaMinPriceLabel`
        // = null y la vista omite el subtítulo del nav (botón sigue clicable,
        // sin línea vacía de subtítulo). Mismo contrato que el hero (`cta_buy_no_price`).
        TicketType::query()->update(['is_sellable' => false]);
        Cache::flush();

        $html = $this->get('/')->assertOk()->getContent();

        // ⚠️⚠️ **ACOTADO AL NAV, y la falta de acotación la destapó `#225`.** Este caso miraba la
        // PÁGINA ENTERA. Funcionaba de casualidad: la barra de móvil usaba otra clase
        // (`cta-prime__s`) para su subtítulo, así que «no hay `cta-med__s` en ninguna parte»
        // equivalía a «el nav no lo pinta». Al pasar la barra a usar el MISMO componente, la
        // aserción empezó a fallar por un elemento que no es su sujeto — y habría dado un falso
        // verde igual de fácil si la coincidencia hubiera ido en el otro sentido.
        // ▶ Y las dos conductas son DISTINTAS a propósito: sin catálogo el nav **omite** el
        // subtítulo (no deja una línea vacía en una fila apretada) y la barra de abajo lo
        // **sustituye** por «Cumpleaños online», que es lo honesto en la pieza que sí tiene sitio.
        // Sin acotar, este caso no podía expresar eso.
        $nav = $this->slice($html, 'nav-cta-med');

        $this->assertStringContainsString('cta-med__t', $nav, 'el CTA del nav perdió su rótulo');
        $this->assertStringNotContainsString(
            'cta-med__s', $nav,
            'sin catálogo vendible el CTA del NAV no debe pintar subtítulo (dejaría una línea vacía)',
        );

        // Guarda de la guarda: si el recorte no trajera el botón, la ausencia de arriba se
        // cumpliría sola. Es el modo de fallo que este fichero ya documenta dos veces.
        $this->assertStringContainsString('nav-cta-med', $nav, 'el recorte no trae el CTA del nav');
    }

    /**
     * **El HERO no lleva CTA — y el acceso a comprar no se pierde.**
     *
     * `[DECIDIDO owner, 2026-08-27]` (`specs/tema-por-instalacion.md` §12): la primera pantalla
     * es solo eslogan, titular, estado y vídeo. El botón de comprar aparece DESPUÉS, al bajar.
     *
     * ⚠️ Aquí vivía `test_hero_uses_cta_prime_structure_with_subtitle`, que aseveraba lo
     * contrario. **No se borró: se re-apuntó** (`CONVENCIONES §3.quater`). Su SUJETO —el CTA del
     * hero— se va, pero la regla que protegía —«el visitante ve un botón de comprar con el precio
     * anclado»— sigue viva, y ahora la cumplen el CTA del nav y la barra flotante de móvil. Esa
     * mitad es el test de abajo.
     */
    public function test_the_hero_carries_no_cta_and_buying_is_still_reachable(): void
    {
        $response = $this->get('/')->assertOk();
        $html = $response->getContent();

        // El hero, acotado: del `id="top"` al cierre de su `</header>`.
        $start = strpos($html, 'id="top"');
        $this->assertNotFalse($start, 'no se encuentra el hero en la home');
        $hero = substr($html, $start, strpos($html, '</header>', $start) - $start);

        // Guarda de la guarda: si el recorte fuera vacío o mínimo, las tres aserciones de abajo
        // pasarían sin mirar nada.
        $this->assertStringContainsString('hero__stage', $hero, 'el recorte del hero no trae el escenario');
        $this->assertStringContainsString('hero__video', $hero, 'el recorte del hero no trae el vídeo');

        // ⚠️⚠️ **Esta aserción decía `cta-prime` y desde `#225` NO FIJABA NADA**: esa clase ya no
        // existe en ninguna parte del producto, así que la ausencia se cumplía sola. Y el motivo
        // que daba —«el hero no lleva CTA»— también había caducado: `#216` le devolvió sus dos
        // botones (`hero__act--buy` / `--alt`), que es lo que hace el mockup.
        // ▶ Lo que SÍ sigue siendo cierto, y es lo que se asevera ahora: el hero tiene sus PROPIOS
        // botones y **no reutiliza el par del armazón**. Son dos piezas con coreografías
        // distintas —el par intercambia mitades, el del hero no— y mezclarlas volvería a crear el
        // problema que `#225` acaba de cerrar.
        // ⚠️⚠️ **CAMBIA DE SIGNO EN `#227`, y el motivo es el encargo.** Aquí se aseveraba que el
        // hero **no** reutilizaba el par del armazón, porque entonces tenía botones propios con
        // otra coreografía. `[DECIDIDO owner, 2026-08-28]`: la primera pantalla lleva **el mismo
        // CTA**, abajo a la derecha, y al bajar se apaga justo cuando el de la cabecera se
        // enciende. O sea que reutilizarlo dejó de ser un error y pasó a ser el mecanismo.
        // ▶ Lo que sí sigue prohibido —y es lo que se asevera ahora— es que se cuele la BARRA DE
        // MÓVIL dentro del hero: ésa vive fija abajo, tiene su propia visibilidad y dentro del
        // hero se pintaría dos veces.
        $this->assertStringContainsString(
            'hero__pair-slot', $hero,
            'el hero se ha quedado sin su CTA. La primera pantalla vuelve a no ofrecer comprar: es '.
            'el agujero que `#226` abrió y `#227` cerró.',
        );
        $this->assertStringContainsString(
            'cta-pair', $hero,
            'el CTA del hero ha dejado de ser el componente compartido. Si deja de serlo, el relevo '.
            'con el de la cabecera enseña dos botones distintos a mitad del cruce.',
        );
        $this->assertStringNotContainsString(
            'book-bar', $hero,
            'el hero ha metido dentro la barra flotante de móvil, que vive fija abajo y tiene su '.
            'propia visibilidad: ahí dentro se pintaría dos veces.',
        );
        // ⚠️⚠️ **EL HERO SE VUELVE A LLENAR EN `#253`, y el paréntesis de `#226` se cierra.**
        // Aquí se aseveraba la AUSENCIA de eslogan, botones y chip —«por ahora sin texto y sin
        // botones», con la palabra «por ahora» dentro de la propia decisión—.
        // `[DECIDIDO owner, 2026-08-29]`: «en el hero pondremos un titular, un texto "Activa tu
        // modo diversión" en el centro, al vídeo le ponemos la cortina, y el CTA más grande».
        // ▶ **Y con eso se cierra la ficha Alta de `DEUDA.md`**: el motivo por el que estaba
        // abierta era que el armazón nace oculto bajo el hero y la primera pantalla se quedaba sin
        // comprar. Vuelven los dos botones propios del hero, que es lo que `#216` razonó.
        // ⚠️ El chip de estado NO vuelve: no se pidió. Se sigue aseverando su ausencia para que
        // este caso no dé por buena una vuelta que nadie decidió.
        foreach (['hero__kicker', 'hero__acts', 'hero__act--buy'] as $vuelto) {
            $this->assertStringContainsString(
                $vuelto, $hero,
                "el hero ha perdido `{$vuelto}`.\n".
                '▶ Con el armazón naciendo OCULTO bajo el hero, sin estos la primera pantalla no '.
                'ofrece ni comprar ni navegar: es el agujero que `#216` cerró y `#226` reabrió.',
            );
        }
        $this->assertStringNotContainsString(
            'hero__chip', $hero,
            'ha vuelto el chip de estado al hero y nadie lo pidió: `#226` lo retiró y `#253` solo '.
            'devolvió el titular, el eslogan y los botones.',
        );
        $this->assertStringContainsString('hero__strip', $hero, 'el hero se ha quedado sin la tira de marca');

        // ⚠️ **La CORTINA vuelve con su sujeto** (`#253`). `#227` la retiró porque el hero estaba
        // vacío: era un velo que oscurecía el vídeo para hacer legible un texto que ya no estaba.
        // Con el titular de vuelta, sin ella se lee en unos fotogramas y en otros no.
        $this->assertStringContainsString(
            'hero__stage-scrim', $hero,
            "el hero sirve un titular sobre el vídeo SIN cortina.\n".
            '▶ Un texto claro sobre un fotograma claro es ilegible, y como depende del fotograma '.
            'falla de forma intermitente — que es peor que fallar siempre.',
        );

        // ⚠️ **El `<h1>` sigue siendo el único de la portada.** Deja de ser `sr-only` porque el
        // titular vuelve a verse, pero lo que se asevera es lo mismo: que existe y trae texto.
        $this->assertMatchesRegularExpression(
            '/<h1[^>]*>\s*\S/', $hero,
            "el hero no sirve un `<h1>` con texto.\n".
            '▶ Es el único encabezado principal de la portada: sin él, el documento se queda sin '.
            'título para lectores de pantalla y buscadores.',
        );

        // ⚠️ El chip de estado NO se asevera aquí: es data-driven y **no se pinta sin horario**
        // configurado (`HeroStatus`), que es justo el caso del entorno de test. Aseverarlo haría
        // que este caso fallara por un motivo que no tiene nada que ver con el CTA. Su presencia
        // la cubren los casos de `HeroStatus`, que sí siembran horario.
        $this->assertStringContainsString('hero__sentinel', $hero, 'falta el sentinel de los CTAs de compra');

        // El acceso a comprar sigue en la página, FUERA del hero: el CTA del nav y la barra móvil.
        $this->assertStringContainsString('cta-med', $html, 'el CTA de compra del nav ha desaparecido');
        $this->assertStringContainsString('book-bar__cta', $html, 'la barra de compra de móvil ha desaparecido');
    }

    /**
     * **El botón de comprar lleva el precio anclado — donde quiera que viva.**
     *
     * Es la mitad SUPERVIVIENTE del test del CTA del hero. El sujeto cambió (ahora es la barra
     * flotante de móvil, que desde `#225` usa la misma estructura que el nav, `cta-med`); la regla
     * es la misma:
     * icono + cuerpo (título + subtítulo) + flecha, con «desde X €» cuando hay catálogo vendible.
     */
    public function test_the_buy_cta_carries_the_price_anchor(): void
    {
        $expected = AppServiceProvider::formatPriceLabel($this->cheapestEntryCents());

        $html = $this->get('/')->assertOk()->getContent();

        // ⚠️ **Acotado a la barra, y no es un refinamiento: sin acotar el test NO fijaba nada.**
        // Medido por mutación: `assertSee('cta-med')` casa con `cta-med__ico`, así que
        // retirar la clase del botón lo dejaba verde; y `assertSeeText('desde X')` lo satisfacía
        // el CTA del NAV, que dice el mismo texto con otra clave de idioma. Las dos mutaciones
        // pasaban. Es literalmente el aviso de `CONVENCIONES §3.quater`: «si un dato viaja al
        // cliente y su único test conduce la superficie vieja, el contrato NO lo está fijando».
        $bar = $this->slice($html, 'book-bar__cta');

        // ⚠️ **La estructura cambió de componente en `#225`**, no de regla: la barra dejó de tener
        // pieza propia (`.cta-prime`) y usa la MISMA que el racimo de la cabecera. La flecha ya no
        // se asevera porque `.cta-med` no la tiene — `#213` la retiró del CTA del armazón: el
        // botón ya dice a dónde va con su rótulo y su icono.
        $this->assertStringContainsString('cta-med ', $bar, 'el botón perdió la estructura `cta-med`');
        $this->assertStringContainsString('cta-med__t', $bar, 'falta el título del botón');
        $this->assertStringContainsString('cta-med__s', $bar, 'falta el subtítulo del botón');
        $this->assertStringContainsString(
            "desde {$expected}", $bar,
            "el botón de compra ya no ancla el precio («desde {$expected}»). Ojo: el CTA del nav ".
            'dice el mismo texto, así que sin acotar a la barra esto pasaría igual.',
        );
    }

    /**
     * Recorta el elemento `<a>`/`<button>` que contiene `$needle`, de su `<` de apertura al `</$tag>`
     * que lo cierra. Sirve para aseverar DENTRO de un componente y no en toda la página.
     *
     * ⚠️ El corte va al cierre REAL, no a un número de caracteres. La primera versión usaba una
     * ventana fija de 900 y el botón mide 1.032 —el SVG del icono se lleva la mayor parte—, así que
     * la flecha del final quedaba fuera y el caso fallaba por el recorte, no por el marcado.
     */
    private function slice(string $html, string $needle, string $tag = 'a'): string
    {
        $at = strpos($html, $needle);
        $this->assertNotFalse($at, "no se encuentra `{$needle}` en la página");

        $open = strrpos(substr($html, 0, $at), '<');
        $open = $open === false ? $at : $open;
        $close = strpos($html, "</{$tag}>", $at);
        $this->assertNotFalse($close, "no se encuentra el cierre `</{$tag}>` de `{$needle}`");

        return substr($html, $open, $close - $open);
    }

    public function test_buy_cta_falls_back_to_no_price_subtitle_when_catalog_empty(): void
    {
        // Sin entradas vendibles: el botón SIGUE mostrando subtítulo (la clase `cta-med__s`
        // permanece) con el fallback "Cumpleaños online" (copy `hero.cta_buy_no_price`) —
        // honesto: lo único reservable online hoy. El botón nunca queda mudo.
        // ⚠️ Se re-apuntó del hero a la barra de móvil (`#195`): el sujeto cambió de sitio, la
        // regla no. La clave de idioma sigue llamándose `hero.*` por compatibilidad.
        TicketType::query()->update(['is_sellable' => false]);
        Cache::flush();

        $response = $this->get('/')->assertOk();

        $response->assertSee('cta-med', false);
        $response->assertSee('cta-med__s', false);
        $response->assertSeeText('Cumpleaños online');
    }

    /**
     * **El CTA del armazón lleva UN rótulo, y es el del mockup.**
     *
     * ⚠️ **RE-APUNTADO en la 2c·8** (`#216`). Este caso aseveraba DOS rótulos —`__t--desktop` y
     * `__t--mobile`— «controlados por @media». Al medirlo salió que **ninguna media query mostraba
     * el segundo**: su única declaración de CSS era `display: none` y nadie la levantaba nunca. O
     * sea que el caso aseveraba la presencia en el HTML de un elemento que no se veía jamás.
     * ▶ El sujeto —«el CTA del armazón dice qué hace»— sigue vivo, así que el caso se queda; lo
     * que cambia es que ahora hay un rótulo y se comprueba SU TEXTO, que es el del mockup.
     */
    public function test_the_frame_cta_carries_one_label(): void
    {
        $html = (string) $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('cta-med__t', $html);
        $this->assertStringNotContainsString(
            'cta-med__t--mobile', $html,
            'vuelve el rótulo alterno que ninguna regla enseñaba.',
        );
        // El copy ES de `landing.nav.cta_buy`, que es literalmente el del mockup.
        $this->assertStringContainsString('>Reservar<', $html);
    }

    /* ====================================================================
       Reorganización del nav (2026-05-27): dos desplegables temáticos +
       dos atajos directos. Blindamos copy + URLs + estado Alpine.
       ==================================================================== */

    // ⚠️ **`test_nav_renders_park_dropdown_with_anchor_items` y
    // `test_nav_renders_services_dropdown_with_section_links` se MUDARON**, no se retiraron
    // (2026-08-27, armazón · tanda 2c·1, `DECISIONES #200`). Su sujeto —los dos desplegables de
    // la barra— dejó de existir, pero lo que comprobaban de verdad sigue vivo: las etiquetas de
    // los destinos, **su orden** y sus anclas. Eso se comprueba ahora contra el portador nuevo y
    // **acotado al elemento**, en `Tests\Feature\Site\ArmazonContractTest`:
    // `the_menu_keeps_the_park_items_in_order` y `the_menu_keeps_the_services_in_order`.
    // ▶ Retirarlas sin mudarlas habría perdido la cobertura del ORDEN, que ninguna otra fijaba.

    public function test_nav_renders_direct_links_for_birthdays_and_tickets(): void
    {
        // Atajos directos en el nav: "Cumpleaños" (también aparece dentro de
        // Servicios, por destacado intencional) y "Entradas" → /precios.
        $response = $this->get('/')->assertOk();

        $response->assertSee('Entradas');
        // El href "Cumpleaños" del atajo va a /cumpleanos (no a un anchor).
        $response->assertSee('href="'.route('cumpleanos').'"', false);
        $response->assertSee('href="'.route('precios').'"', false);
    }

    public function test_home_marks_body_with_data_has_hero(): void
    {
        // El marker `data-has-hero` es lo que engancha la COREOGRAFÍA del armazón (2c·8, `#216`):
        // le dice al CSS que en esta vista —y solo en ésta— el logotipo, el CTA y la hamburguesa
        // nacen ocultos y entran con el scroll. Sin él, el armazón sale desde el primer píxel,
        // que es lo correcto en las otras once.
        $this->get('/')->assertOk()->assertSee('data-has-hero="1"', false);
    }

    /**
     * **El armazón nace bajo el hero, y la portada sigue ofreciendo la compra.**
     *
     * ⚠️⚠️ **DOS CORRECCIONES EN DOS DÍAS, y van DELANTE del texto que corrigen.**
     *
     * **(`#226`, 2026-08-28)** el hero se vació —«por ahora sin texto y sin botones»— y la regla
     * que este caso defiende quedó **suspendida a propósito**: la primera pantalla se quedó sin
     * ningún sitio donde comprar. El caso pasó a aseverar ese estado, y la ficha se abrió en
     * `DEUDA.md` con severidad Alta.
     *
     * **(`#227`, mismo día)** el owner cierra el agujero por la puerta buena: la primera pantalla
     * recupera **el mismo CTA del armazón**, abajo a la derecha, y al bajar se apaga justo cuando
     * el de la cabecera se enciende. La ficha se cierra y este caso vuelve a su signo original.
     *
     * ▶ **La REGLA no ha cambiado ninguna de las dos veces**: quien llega a la portada y no hace
     * scroll tiene que poder comprar. Lo que ha cambiado tres veces es QUIÉN la cumple —los
     * botones propios del hero (`#216`), nadie (`#226`), el par del armazón (`#227`)—. Por eso el
     * caso se re-apunta y no se borra: el sujeto es volátil, la regla no.
     *
     * ⚠️ **RE-APUNTADO, no retirado** (2c·8, `#216`): este caso aseveraba `x-data="navCtaReveal"`,
     * el componente que ocultaba SOLO el botón de comprar del armazón. Ese componente se retira —su
     * trabajo lo hace ahora la coreografía entera, con una sola señal— pero **el hecho que el caso
     * protegía sigue vivo y es más importante que antes**: con el armazón oculto en la primera
     * pantalla, si el hero no ofreciera la compra la portada se quedaría sin ella.
     * ▶ Por eso ahora se asevera el hecho de PRODUCTO —hay un botón de comprar dentro del hero— y
     * no el nombre de un componente de JavaScript.
     */
    public function test_the_hero_offers_the_purchase_because_the_frame_is_hidden_under_it(): void
    {
        $html = (string) $this->get('/')->assertOk()->getContent();
        $css = (string) file_get_contents(public_path('css/site.css'));

        // ── 1 · La primera pantalla OFRECE COMPRAR ────────────────────────────────────────────
        // ⚠️ El sujeto cambió dos veces en dos días y por eso conviene decirlo: `#216` lo cumplía
        // con botones propios del hero, `#226` los retiró y dejó el agujero abierto a sabiendas,
        // y `#227` lo cierra con **el mismo CTA del armazón**, colocado abajo a la derecha. La
        // REGLA no ha cambiado nunca: quien llega y no hace scroll tiene que poder comprar.
        $inicio = strpos($html, 'id="top"');
        $this->assertNotFalse($inicio, 'no se encuentra el hero en la home');
        $hero = substr($html, $inicio, strpos($html, '</header>', $inicio) - $inicio);

        $this->assertStringContainsString(
            'hero__pair-slot', $hero,
            "La primera pantalla no ofrece comprar.\n".
            "▶ El armazón nace OCULTO bajo el hero (`--nav-p`), así que sin este CTA quien llega a\n".
            "  la portada y no hace scroll no tiene ningún sitio donde comprar ni forma de navegar.\n".
            '▶ Es el agujero que `#226` abrió a propósito y `#227` cerró. Si vuelve, hay que reabrir '.
            'la ficha de `DEUDA.md`.',
        );

        // ── 2 · Y es EL MISMO botón, no uno parecido ──────────────────────────────────────────
        $this->assertStringContainsString(
            'cta-pair', $hero,
            'el CTA de la primera pantalla ha dejado de ser el componente compartido: a mitad del '.
            'relevo se leerían dos botones distintos.',
        );

        // ── 3 · El relevo se apaga CUANDO el otro se enciende, y va atado al mismo token ──────
        // ⚠️ Sin esto, alguien podría escribir `opacity: calc(1 - var(--nav-p))` —que parece lo
        // mismo— y el CTA del hero seguiría valiendo 0,15 al llegar `.nav--live`, o sea que
        // desaparecería de golpe. Los dos hechos tienen que ocurrir en el mismo fotograma.
        $this->assertMatchesRegularExpression(
            '/\.hero__pair-slot\s*\{[^}]*opacity:\s*calc\(\(var\(--nav-reveal-live\)\s*-\s*var\(--nav-p\)\)\s*\/\s*var\(--nav-reveal-live\)\)/s',
            $css,
            'el CTA de la primera pantalla ya no se apaga atado a `--nav-reveal-live`, que es el '.
            'umbral en el que entra `.nav--live`. Si los dos se separan, el botón desaparece de '.
            'golpe a mitad de fundido o se queda visible cuando ya no se puede pulsar.',
        );

        // ── 4 · `opacity: 0` NO saca del tabulador: el apagado son TRES cosas ─────────────────
        foreach (['pointer-events:\s*none' => 'el ratón', 'visibility:\s*hidden' => 'el teclado y el lector de pantalla'] as $decl => $quien) {
            $this->assertMatchesRegularExpression(
                '/\.nav--live \.hero__pair-slot\s*\{[^}]*'.$decl.'/s', $css,
                "el CTA apagado de la primera pantalla sigue alcanzable para {$quien}: `opacity: 0` ".
                'no saca del orden de tabulación ni del árbol de accesibilidad. Quien navegue con '.
                'tabulador se encontraría dos «Reservar» invisibles antes del que sí se ve.',
            );
        }

        // ── 5 · En MÓVIL no se duplica ────────────────────────────────────────────────────────
        // Ahí el CTA ya vive abajo, en la barra flotante. Dos botones idénticos a diez píxeles uno
        // de otro no son redundancia útil.
        $this->assertMatchesRegularExpression(
            '/@media \(max-width: 720px\)\s*\{[^}]*\.hero__pair-slot\s*\{[^}]*display:\s*none/s',
            $css,
            'el CTA del hero se pinta también en móvil, donde ya está la barra flotante: serían dos '.
            'botones iguales pegados.',
        );

        // ── 6 · La otra mitad del acoplamiento sigue en su sitio ──────────────────────────────
        $this->assertStringContainsString(
            'heroChoreo', $html,
            'ha desaparecido la coreografía que oculta el armazón bajo el hero. Si el armazón ya es '.
            'visible desde el primer píxel, este relevo sobra: revísalo entero, no lo dejes a medias.',
        );

        $this->assertStringNotContainsString(
            'navCtaReveal', $html,
            'sigue montándose `navCtaReveal`: son dos mecanismos ocultando el mismo botón.',
        );
    }

    /* ====================================================================
       Auditoría Fase 1 · Sistema 6 (Landing/CMS público) — W1/W2/W4.
       ==================================================================== */

    public function test_anonymous_home_get_stays_within_query_budget(): void
    {
        // W1 (HIGH): el composer global `View::composer('*')` corre una vez por CADA subvista de la
        // home (~79) y antes reconstruía `$site` con ~20 `Setting::value` sin cachear → ~1.900 queries
        // por GET anónimo (amplificación DoS). Ahora memoiza el payload en `request()->attributes` y lee
        // toda la tabla `settings` UNA vez. Regresión: presupuesto holgado pero muy por debajo de lo roto.
        DB::enableQueryLog();
        $this->get('/')->assertOk();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $count = count($queries);
        $this->assertLessThan(
            120,
            $count,
            "GET / disparó {$count} queries — regresión de la amplificación del composer (esperado « 120; estaba en ~1.900)."
        );

        // El corazón del fix: la tabla `settings` se lee un puñado de veces por request (el pluck único
        // del composer + algunos helpers acotados: ThemeSettings, PuertaSettings, CookieConsent…), NO
        // ~20 por cada una de las ~79 subvistas (que daba ~1.600). Umbral holgado que distingue sin
        // ambigüedad el estado memoizado (~11) de la regresión de amplificación (cientos).
        $settingsReads = collect($queries)
            ->filter(fn (array $q): bool => str_contains(strtolower((string) $q['query']), 'settings'))
            ->count();
        $this->assertLessThan(
            50,
            $settingsReads,
            "El composer leyó la tabla `settings` {$settingsReads} veces — debe memoizar por petición (esperado « 50, estaba en ~1.600)."
        );
    }

    public function test_cta_min_price_excludes_entries_of_a_disabled_zone(): void
    {
        // W2 (medium): el «desde X €» del nav/hero usa la MISMA comprabilidad que el catálogo de compra
        // (`inOperationalZone()`). Con el seed: kids · 1h = 7,90 € (la más barata, zona kids); jump · 1h =
        // 9,90 € (zona jump). Si se desactiva la zona de la más barata, el ancla salta a la siguiente
        // entrada realmente comprable, no anuncia un precio que el flujo no puede vender.
        $this->get('/')->assertOk()->assertSeeText('desde 7,90 €'); // estado por defecto: kids manda

        Zone::where('slug', 'kids')->update(['is_active' => false]);
        Cache::flush(); // el update directo del modelo no pasa por EditZone::afterSave (eso es W3)

        $response = $this->get('/')->assertOk();
        $response->assertSeeText('desde 9,90 €');  // ahora manda jump (zona operativa)
        $response->assertDontSee('desde 7,90 €');  // ya NO se ancla al precio de la zona desactivada
    }

    public function test_landing_hides_entries_of_a_disabled_zone(): void
    {
        // W4 (low): las entradas de una zona desactivada no se pintan con CTA «Reservar» que el flujo de
        // compra no puede vender (espejo de packs e `inOperationalZone()`). La zona kids sigue en la
        // landing (`show_in_landing`), pero sus ENTRADAS desaparecen de la rejilla de precios.
        $this->get('/')->assertSee('Kids · 1 hora'); // estado por defecto: la entrada se anuncia

        Zone::where('slug', 'kids')->update(['is_active' => false]);

        foreach (['/', '/precios'] as $url) {
            $this->get($url)->assertOk()
                ->assertSee('Jump · 1 hora')       // entrada de zona operativa: sigue
                ->assertDontSee('Kids · 1 hora');  // entrada de zona desactivada: fuera
        }
    }

    /* ====================================================================
       Las MÉTRICAS de una zona — `DECISIONES #144`
       ==================================================================== */

    /**
     * ⚠️⚠️ **Una métrica sin dato NO se pinta, y la home servía «0 m²».**
     *
     * `zones.area_sqm` y `zones.rides_count` son NULLABLE y opcionales en el panel. Las dos
     * superficies que las pintan hacían `number_format($zone->area_sqm, …)` a pelo, y
     * `number_format(null)` devuelve **«0»**: medido sobre la BD de desarrollo el 2026-08-25, dos
     * zonas visibles en la landing (`cap`, `cap2`) tenían las dos columnas a `null` y la portada
     * anunciaba **«0 m²»** y la etiqueta «Atracciones» sin número.
     *
     * ▶ **Un cero no es un hueco: afirma algo, y es falso.** Un cliente que aún no ha rellenado los
     * metros de una zona estaba publicando que mide cero.
     * ▶ Y no era solo cosmético: `number_format(null)` es `DEPRECATED` en PHP 8 y **TypeError en
     * PHP 9** — hoy un aviso en el log, mañana un 500 en la portada.
     */
    public function test_a_zone_without_measurements_does_not_advertise_zero(): void
    {
        Zone::where('slug', 'jump')->update(['area_sqm' => null, 'rides_count' => null]);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringNotContainsString(
            '<span class="v">0 m²</span>', (string) $html,
            'La home vuelve a anunciar «0 m²» para una zona sin metros configurados.',
        );

        // Y la métrica desaparece ENTERA, etiqueta incluida: media fila es peor que ninguna.
        $this->assertSame(
            0, preg_match_all('/<span class="v"><\/span>/', (string) $html),
            'Ha quedado una métrica con su etiqueta y el valor vacío.',
        );
    }

    /**
     * **El contrapunto, que es lo que impide «arreglarlo» ocultándolo todo.**
     *
     * La misma comprobación al revés: con dato, la métrica se pinta y con su formato de miles.
     * Sin este caso, dejar de emitir el bloque entero también pasaría el test de arriba.
     */
    public function test_a_zone_with_measurements_still_shows_them(): void
    {
        Zone::where('slug', 'jump')->update(['area_sqm' => 5000, 'rides_count' => 15]);

        $this->get('/')->assertOk()
            ->assertSee('<span class="v">5.000 m²</span>', false)
            ->assertSee('<span class="v">15</span>', false);
    }

    /**
     * ⚠️ **Y cubre las DOS superficies, que es donde vivía el defecto duplicado.**
     *
     * El bucle de zonas tiene dos ramas —`.zone-photo-card__meta` si la zona tiene foto,
     * `.zone-intro__meta` si no— y el mismo marcado estaba escrito en las dos. Arreglar una y
     * olvidar la otra era el resultado más probable, así que hoy las dos pintan el mismo
     * componente (`<x-site.zone-metrics>`) y este caso ejercita la rama SIN foto.
     */
    public function test_the_no_photo_zone_card_uses_the_same_metrics_component(): void
    {
        Zone::where('slug', 'jump')->update(['image' => null, 'area_sqm' => null, 'rides_count' => 7]);

        $html = (string) $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('zone-intro__meta', $html, 'no se está ejercitando la rama sin foto');
        $this->assertStringNotContainsString('<span class="v">0 m²</span>', $html);
        $this->assertStringContainsString('<span class="v">7</span>', $html);
    }

    /**
     * Precio mínimo (céntimos) de entrada vendible en el catálogo actual.
     * Se calcula en vivo del seeder activo para que los tests sigan funcionando
     * si el cliente ajusta precios desde el panel admin sin invalidar las pruebas.
     */
    private function cheapestEntryCents(): int
    {
        return (int) TicketType::query()
            ->where('is_sellable', true)
            ->where('is_active', true)
            ->where('type', TicketType::TYPE_ENTRY)
            ->with('prices')
            ->get()
            ->flatMap(fn (TicketType $type) => $type->prices)
            ->where('amount_cents', '>', 0)
            ->min('amount_cents');
    }
}
