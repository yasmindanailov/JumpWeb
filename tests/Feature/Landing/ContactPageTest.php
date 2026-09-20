<?php

namespace Tests\Feature\Landing;

use App\Domain\Booking\Models\OpeningHour;
use App\Domain\Content\Models\Faq;
use App\Domain\Platform\Models\Setting;
use Database\Seeders\LandingContentSeeder;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * **`/contacto`: PREGUNTAR, Y NADA DE LO QUE YA HACE «VISÍTANOS»**
 * (`DECISIONES #535`, carril de diseño Fase 3 · T3b, artboard `Contacto PJP` 1a/1b).
 *
 * ❗❗❗ **LO QUE ESTA PÁGINA NO TIENE ES SU DISEÑO, y por eso hace falta una guarda INVERTIDA.** El
 * canvas le pone a esta URL el riesgo de ser «un trozo de la portada con otro título», y lo que la
 * salva es que aquí **no hay mapa, ni tabla de horario, ni aparcamiento**: eso lo contesta la sección
 * «Visítanos» con su horario en vivo. Un mapa devuelto por descuido no rompería nada —la página
 * seguiría funcionando— y por eso ninguna guarda normal lo vería.
 *
 * 📜 **Lo que este fichero vigilaba antes** (`#434`, auditoría M4/M5) y por qué cambia: la tarjeta de
 * la dirección iba POSADA sobre el marco del mapa y tapaba al 100 % el botón «Cargar el mapa»; los
 * dos casos que lo vigilaban **se quedan sin sujeto** al retirarse el mapa, y se sustituyen por la
 * propiedad más fuerte —que no haya mapa ninguno—. El caso del parking se queda tal cual: sigue
 * siendo un dato de negocio que no puede volver al código.
 *
 * ⚠️⚠️ **Este fichero mira el MARCADO, y por eso se MUDA con la vista** (`#649`, `specs/paquete-de-instancia.md`
 * §4.5.bis): el día que `/contacto` viva en su instancia, estos casos se quedan sin sujeto en cualquier
 * máquina sin el paquete —medido el 19-09—. La CONDUCTA del producto (lo que recibe la vista, el envío, el
 * correo) está en `tests/Feature/ContactPageTest` y sobrevive. Un caso nuevo va allí si afirma sobre datos.
 */
class ContactPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LandingContentSeeder::class);
        Cache::flush();
        foreach ([
            'address.line1' => 'Ctra. de Prueba, 1',
            'address.line2' => '30000 Ciudad',
            'address.maps_url' => 'https://maps.app.goo.gl/prueba',
            'address.maps_embed_url' => 'https://www.google.com/maps/embed?pb=PRUEBA123',
            'contact.phone' => '+34 968 47 12 30',
            'contact.email' => 'hola@ejemplo.es',
        ] as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value, 'group' => 'contact']);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  El instrumento, antes que lo que mide
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * **Guarda de la guarda.** Sin esto, un cambio de nombre dejaría los casos de abajo buscando
     * ausencias en un documento vacío y pasando en verde: *comprobar que algo NO está es la forma
     * más fácil de escribir un test que no mira nada*.
     */
    public function test_the_scan_reads_a_page_with_the_pieces_it_expects(): void
    {
        $html = $this->html();

        $this->assertStringContainsString('page--contact', $html, 'la página no se sirve o cambió de clase raíz');
        foreach (['contact-form', 'channel__value', 'answers__link', 'where__addr'] as $pieza) {
            $this->assertStringContainsString($pieza, $html, "falta `{$pieza}`: el localizador se quedó sin sujeto");
        }
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  Lo que la página NO hace
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * **NI MAPA, NI IFRAME DE TERCERO.** La dirección va escrita, que es la regla que cerró
     * «Visítanos»: el dato crítico, siempre fuera del iframe.
     *
     * ⚠️ Se comprueban las TRES formas de traerlo de vuelta —la tarjeta, el marco de consentimiento
     * y la URL de inserción—, porque el `maps_embed_url` sigue configurado en el panel y basta con
     * volver a pintarlo.
     */
    public function test_there_is_no_map_on_this_page(): void
    {
        $html = $this->html();

        $this->assertStringNotContainsString('map-card', $html, 'vuelve la tarjeta del mapa: el mapa vive en «Visítanos» (`#535`)');
        $this->assertStringNotContainsString('consent-frame', $html, 'vuelve un marco de consentimiento: aquí no se embebe nada de terceros');
        $this->assertStringNotContainsString('PRUEBA123', $html, 'vuelve la URL de inserción del mapa');
        $this->assertStringNotContainsString('<iframe', $html, 'vuelve un iframe a la página de contacto');
    }

    /**
     * **«Parking gratis 2h» no vuelve** (auditoría M5, `[DECIDIDO owner]`): era un dato de negocio
     * escrito en el código, y la FAQ del panel ya lo dice.
     */
    public function test_parking_is_gone_from_the_page_and_from_the_copy(): void
    {
        $this->assertStringNotContainsStringIgnoringCase('parking', $this->html(), '«Parking gratis 2h» vuelve a estar en el código (auditoría M5)');

        foreach (['es', 'en', 'fr'] as $locale) {
            $copy = (string) file_get_contents(base_path("lang/{$locale}/landing.php"));
            $this->assertDoesNotMatchRegularExpression(
                "/'parking'\s*=>/",
                $copy,
                "`lang/{$locale}/landing.php` vuelve a llevar la clave `parking`: es un dato de negocio y va en el panel o fuera.",
            );
        }
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  La dirección y su salida
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * **La dirección va ESCRITA y la salida apunta a un ancla que EXISTE.**
     *
     * ⚠️⚠️ La segunda mitad no es celo: el precedente roto está fichado y medido —`#478` enlazó a
     * `/precios#zona-<slug>` y esa página emite **cero** anclas así—. Un ancla que no está no falla:
     * el navegador se queda donde estaba, y por eso nadie lo ve. Aquí se comprueba contra la PORTADA
     * de verdad, no contra una constante.
     */
    public function test_the_address_is_written_and_points_at_a_real_anchor(): void
    {
        $xpath = $this->xpath();

        // ⚠️⚠️ **Acotado al ELEMENTO, y no es celo.** La primera versión aseveraba la dirección sobre
        // la página entera y **la mutación sobrevivió**: el JSON-LD de schema.org pinta el mismo
        // `streetAddress` unido con coma, así que el caso pasaba en verde con el bloque visible
        // uniendo las dos líneas con un espacio. *Acota al elemento antes de creerte un test verde*
        // (`#295`, `#303`) — aquí lo cazó el arnés, no una relectura.
        $direccion = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' where__addr ')]");
        $this->assertSame(1, $direccion->length, 'la dirección no se pinta escrita');
        $this->assertSame(
            'Ctra. de Prueba, 1, 30000 Ciudad',
            trim((string) $direccion->item(0)?->textContent),
            'las dos líneas del panel no se unen con coma: «Ctra. de Prueba, 1 30000 Ciudad» se lee como un número de portal',
        );

        $destino = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' where__cta ')]/@href");
        $this->assertSame(1, $destino->length, 'falta la salida al mapa y al horario');
        $href = (string) $destino->item(0)?->nodeValue;
        $ancla = (string) parse_url($href, PHP_URL_FRAGMENT);
        $this->assertNotSame('', $ancla, 'la salida ya no lleva ancla');

        $portada = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('id="'.$ancla.'"', $portada, "la portada NO pinta `#{$ancla}`: la salida lleva a la nada y nada fallaría");
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  Los canales
    // ─────────────────────────────────────────────────────────────────────────────────

    /** Un canal sin dato no se anuncia; sin ninguno, el bloque entero desaparece. */
    public function test_channels_come_from_the_panel_and_an_empty_one_is_not_painted(): void
    {
        $this->assertStringContainsString('+34 968 47 12 30', $this->html(), 'el teléfono del panel no se pinta');
        $this->assertStringContainsString('hola@ejemplo.es', $this->html(), 'el correo del panel no se pinta');

        Setting::updateOrCreate(['key' => 'contact.phone'], ['value' => '', 'group' => 'contact']);
        Setting::updateOrCreate(['key' => 'contact.email'], ['value' => '', 'group' => 'contact']);
        Cache::flush();
        Setting::flushMemo();

        $this->assertStringNotContainsString('channels__title', $this->html(), 'el bloque de canales se pinta vacío: sin ningún dato no hay nada que ofrecer');
    }

    /**
     * ❗❗ **EL MISMO NÚMERO ES UNA TARJETA, NO DOS.** Es la respuesta que el dato da a la pregunta
     * que el canvas le hacía al owner: con dos tarjetas, la página repetiría el mismo número bajo dos
     * rótulos distintos, que es justo lo que hace creer que son dos números.
     *
     * ⚠️ El control es la otra mitad: con números DISTINTOS tienen que salir las dos.
     */
    public function test_one_card_when_phone_and_whatsapp_are_the_same_number(): void
    {
        Setting::updateOrCreate(['key' => 'contact.whatsapp'], ['value' => '+34 968 47 12 30', 'group' => 'contact']);
        Cache::flush();
        Setting::flushMemo();

        // Dos tarjetas: la del número compartido y la del correo — que también es un canal.
        $this->assertSame(
            2,
            substr_count($this->html(), 'class="channel__value"'),
            'el mismo número sale en dos tarjetas: teléfono y WhatsApp comparten número y deben compartir tarjeta',
        );

        // CONTROL: números distintos → dos tarjetas de número + la del correo.
        Setting::updateOrCreate(['key' => 'contact.whatsapp'], ['value' => '+34 600 11 22 33', 'group' => 'contact']);
        Cache::flush();
        Setting::flushMemo();

        $this->assertSame(
            3,
            substr_count($this->html(), 'class="channel__value"'),
            'con números distintos tienen que salir las dos tarjetas de número, más la del correo',
        );
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  El formulario
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * **EL BOTÓN ES TINTA, NO NARANJA.** El relleno de acción significa COMPRAR en toda la web
     * (`#551`, la grieta 01 del canvas) y enviar un mensaje no es comprar.
     *
     * ⚠️ Se asevera sobre el `<button>` acotado, no sobre la página: `class="btn` aparece en el
     * armazón y en el pie, así que buscarlo suelto pasaría en verde con el botón repintado.
     */
    public function test_the_send_button_is_the_secondary_fill(): void
    {
        $boton = $this->xpath()->query('//form//button[@type="submit"]/@class');

        $this->assertSame(1, $boton->length, 'la página no tiene un botón de envío');
        $clases = preg_split('/\s+/', (string) $boton->item(0)?->nodeValue) ?: [];
        $this->assertContains('btn--ink', $clases, 'el botón de enviar perdió el relleno secundario: en naranja diría «comprar»');
    }

    /**
     * **EL AVISO DE PRIVACIDAD SE SIRVE, SIN CASILLA Y CON EL ENLACE FUERA DE LA FRASE.**
     *
     * `[DECIDIDO owner, 2026-09-12]`: aviso, no casilla — el mismo criterio que `#350` fijó para las
     * dos altas. ⚠️ Y el enlace es un CONTROL propio: dentro del párrafo medía 18 px de alto contra
     * el suelo táctil de 48, y habría estrenado la segunda excepción de `#264`.
     */
    public function test_the_privacy_notice_is_served_as_a_notice_with_its_own_link(): void
    {
        $xpath = $this->xpath();

        $bloque = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' contact-form__privacy ')]");
        $this->assertSame(1, $bloque->length, 'la página no dice qué se hace con lo que escribes');

        $casilla = $xpath->query('//form//input[@type="checkbox"]');
        $this->assertSame(0, $casilla->length, 'vuelve la casilla de consentimiento: `#350` decidió aviso, no aceptación');

        $enlace = $xpath->query(
            "//*[contains(concat(' ', normalize-space(@class), ' '), ' contact-form__privacy ')]"
            .'/a[contains(@href, "privacidad")]',
        );
        $this->assertSame(1, $enlace->length, 'el enlace a la política no es hijo directo del aviso: dentro del párrafo no llega al mínimo táctil');
    }

    /**
     * **EL TEMA ES OPCIONAL Y NO VIENE ELEGIDO.** Con «Un cumpleaños» preseleccionado, quien no toca
     * el desplegable manda un tema que no ha elegido: *una ausencia no es una afirmación*.
     */
    public function test_the_topic_select_has_no_preselected_option(): void
    {
        $opciones = $this->xpath()->query('//select[@name="topic"]/option');

        $this->assertGreaterThan(1, $opciones->length, 'el desplegable de tema no se pinta');
        $this->assertSame('', (string) $opciones->item(0)?->getAttribute('value'), 'la primera opción del tema ya afirma algo');

        $marcadas = $this->xpath()->query('//select[@name="topic"]/option[@selected]');
        $this->assertSame(0, $marcadas->length, 'el tema viene elegido de fábrica');
    }

    // ⚠️ Que un tema desconocido se RECHACE y que el tema LLEGUE AL ASUNTO son conducta del producto, no
    // marcado: viven en `tests/Feature/ContactPageTest` desde el 20-09 (`#649`). Aquí solo lo que se ve.

    // ─────────────────────────────────────────────────────────────────────────────────
    //  La chapa de atajos y el plazo
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * **LOS ATAJOS SALEN DEL INVENTARIO**, no de tres `href` escritos en la plantilla: una página en
     * mantenimiento deja de ofrecerse aquí también, y el ancla de Dudas solo sale si la portada la
     * pinta. Sin ninguno, la chapa entera no se dibuja.
     */
    public function test_the_shortcuts_follow_the_inventory(): void
    {
        $this->assertStringContainsString(route('precios'), $this->html(), '«Tarifas» no se ofrece');

        Setting::updateOrCreate(['key' => 'maintenance.page.precios'], ['value' => '1', 'group' => 'maintenance']);
        Cache::flush();
        Setting::flushMemo();

        $enlaces = $this->xpath()->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' answers__link ')]/@href");
        $hrefs = [];
        foreach ($enlaces as $a) {
            $hrefs[] = (string) $a->nodeValue;
        }
        $this->assertNotContains(route('precios'), $hrefs, 'una página en mantenimiento se sigue ofreciendo en la chapa');
    }

    /** Sin ninguna duda en el panel, la portada no pinta la sección: el ancla no se ofrece. */
    public function test_the_faq_shortcut_needs_the_section_to_exist(): void
    {
        Faq::query()->delete();
        Cache::flush();

        $this->assertStringNotContainsString(url('/#faq'), $this->html(), 'se ofrece el ancla de Dudas con la sección sin pintar');
    }

    /**
     * **EL PLAZO SE DERIVA DEL HORARIO.** `[DECIDIDO owner, 2026-09-12]`: el horario de atención ES
     * el de apertura, así que aquí no hay campo nuevo. ⚠️ Y sin horario publicado no se promete nada:
     * `HeroStatus::current()` devuelve `null` y la entradilla cae a la redacción sin promesa.
     */
    public function test_the_reply_window_is_derived_and_is_not_promised_without_hours(): void
    {
        // ⚠️ **El horario se SIEMBRA aquí**: `LandingContentSeeder` no lo trae, así que sin esto el
        // caso arrancaría ya en la rama «sin horario» y las dos aserciones medirían lo mismo — un
        // caso sin sujeto que pasa en verde (la trampa que `#495` dejó escrita para su `setUp`).
        for ($weekday = 0; $weekday <= 6; $weekday++) {
            OpeningHour::updateOrCreate(
                ['weekday' => $weekday],
                ['open_time' => '10:00:00', 'close_time' => '21:00:00', 'is_closed' => false],
            );
        }
        Cache::flush();

        $this->assertStringContainsString((string) __('site.contact_intro'), $this->html(), 'la entradilla no dice el plazo con horario publicado');

        OpeningHour::query()->delete();
        Cache::flush();

        $html = $this->html();
        $this->assertStringContainsString((string) __('site.contact_intro_plain'), $html, 'sin horario, la entradilla no cae a la redacción sin promesa');
        $this->assertStringNotContainsString((string) __('site.contact_intro'), $html, 'sin horario se sigue prometiendo un horario de respuesta');
    }

    // ─────────────────────────────────────────────────────────────────────────────────

    private function html(): string
    {
        return $this->get('/contacto')->assertOk()->getContent();
    }

    private function xpath(): DOMXPath
    {
        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>'.$this->html());
        libxml_clear_errors();

        return new DOMXPath($dom);
    }
}
