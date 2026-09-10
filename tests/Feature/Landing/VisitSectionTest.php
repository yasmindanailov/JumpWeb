<?php

namespace Tests\Feature\Landing;

use App\Domain\Booking\Models\OpeningHour;
use App\Domain\Booking\Models\SpecialDate;
use App\Domain\Identity\Services\CookieConsent;
use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\DisplayTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * **La sección 07 «Visítanos»** (`DECISIONES #487`, carril de diseño Fase 2 · T2g).
 *
 * ⚠️⚠️ **Reescrita entera: la sección de `#307` —tres tarjetas con teléfono— la sustituye el
 * artboard del canvas.** De aquélla sobreviven dos propiedades y las dos siguen aquí: un solo
 * encabezado y ninguna línea de aparcamiento escrita en el código.
 *
 * Lo que vigila son las cosas que se rompen **en silencio**:
 *
 *  1. **Los CUATRO estados.** Es regla dura del sistema: «hoy no abre» y «hoy ya ha cerrado» son
 *     hechos distintos. Con dos, un jueves ya cerrado y un lunes de cierre dicen lo mismo — y no
 *     falla nada.
 *  2. **El día solo se resalta mientras su horario está VIGENTE.** Con el parque ya cerrado, un
 *     resaltado dice «esto es lo que rige ahora» sobre unas horas que ya pasaron.
 *  3. **La dirección vive SIEMPRE fuera del marco.** Es la regla que cerró la sección: un bloqueador
 *     tumba el widget **con las cookies aceptadas** y entonces no salta ningún aviso, sale un hueco.
 *  4. **Cero enlaces y cero botones con el mapa cargado**, y la puerta a Google Maps **solo** con el
 *     mapa bloqueado.
 *  5. **La entradilla se DERIVA del horario.** Un texto fijo afirmaría para toda instalación algo
 *     que solo es cierto en una.
 *
 * ⚠️ Todo se asevera sobre el subárbol de `#info`, no sobre la página: la portada dice «Visítanos»
 * también en el menú y en el pie, así que un `assertSee` sobre el documento entero pasaría en verde
 * con la sección BORRADA — que es exactamente cómo `#295` se quedó con una guarda vacía.
 */
class VisitSectionTest extends TestCase
{
    use RefreshDatabase;

    /** El subárbol de `<section id="info">`, aislado del resto del documento. */
    private function seccion(bool $conMapa = false): string
    {
        $peticion = $conMapa
            ? $this->withUnencryptedCookie(CookieConsent::COOKIE_NAME, CookieConsent::encode(['maps' => true, 'social' => false]))
            : $this;

        $html = (string) $peticion->get('/')->assertOk()->getContent();

        $this->assertStringContainsString(
            '<section id="info"',
            $html,
            'La sección «Visítanos» perdió su `id`: este caso miraría el vacío.',
        );

        preg_match('#<section id="info".*?</section>#s', $html, $m);

        return $m[0];
    }

    /**
     * Abre el parque todos los días en la ventana dada y congela el reloj.
     *
     * ⚠️⚠️ **El reloj se CONGELA y la ventana NO se estira** (`#327`): un caso que abría
     * 00:00–23:59 «para no depender de la hora» salió rojo a las 23:59:30, porque a esa hora la
     * ventana ya se cerró. *Estirar la ventana solo mueve el minuto en que falla.*
     */
    private function abrirTodosLosDias(string $desde, string $hasta, string $ahora): void
    {
        Carbon::setTestNow(Carbon::parse($ahora, DisplayTime::timezone()));

        OpeningHour::query()->delete();
        foreach (range(0, 6) as $dia) {
            OpeningHour::create(['weekday' => $dia, 'open_time' => $desde, 'close_time' => $hasta, 'is_closed' => false]);
        }
    }

    /**
     * La mitad de DÓNDE necesita datos: sin ellos no hay dirección que pintar ni marco que bloquear.
     *
     * ⚠️ **La BD de test nace VACÍA de ajustes**, así que un caso que mire la dirección sin sembrarla
     * asevera sobre un sujeto que no existe — y el primer intento de este fichero lo hizo en dos
     * casos. *Un caso sin sujeto no vigila nada.*
     */
    private function sembrarUbicacion(string $linea1 = 'Calle de PruebaZZ'): void
    {
        Setting::updateOrCreate(['key' => 'address.line1'], ['value' => $linea1]);
        Setting::updateOrCreate(['key' => 'address.line2'], ['value' => '30000 CiudadZZ']);
        Setting::updateOrCreate(['key' => 'address.maps_url'], ['value' => 'https://maps.example.com/pjp']);
        // ⚠️ Sin URL de INSERCIÓN el bloqueo previo no pinta placeholder: cae al pin decorativo y la
        // puerta a Google Maps no llega a existir (`#219`). Es otro estado del producto, no éste.
        Setting::updateOrCreate(['key' => 'address.maps_embed_url'], ['value' => 'https://www.google.com/maps/embed?pb=zz']);
        Setting::flushMemo();
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  Los cuatro estados
    // ─────────────────────────────────────────────────────────────────────────────────

    public function test_abierto_ahora_dice_hasta_que_hora(): void
    {
        $this->abrirTodosLosDias('09:00:00', '21:30:00', '2026-09-02 12:00:00');

        $seccion = $this->seccion();

        $this->assertStringContainsString(__('landing.info.state.open'), $seccion);
        $this->assertStringContainsString(__('landing.info.state.open_line', ['time' => '21:30']), $seccion);
    }

    public function test_antes_de_abrir_dice_que_abre_hoy_y_su_ventana_entera(): void
    {
        $this->abrirTodosLosDias('16:30:00', '21:30:00', '2026-09-02 11:00:00');

        $seccion = $this->seccion();

        $this->assertStringContainsString(__('landing.info.state.later'), $seccion);
        $this->assertStringContainsString(
            __('landing.info.state.later_line', ['opens' => '16:30', 'closes' => '21:30']),
            $seccion,
            'Antes de abrir hay que decir la ventana ENTERA: sin la hora de cierre, quien planifica no sabe si le da tiempo.',
        );
    }

    /**
     * ❗❗❗ **LOS DOS CIERRES NO DICEN LO MISMO, y ésta es la propiedad que motiva el fichero.**
     *
     * Antes de `#487` los dos caían en «Abrimos mañana a las 11:30», así que un jueves con el parque
     * ya cerrado y un lunes de cierre eran indistinguibles — **con la tabla enseñando las horas de
     * hoy justo debajo**.
     */
    public function test_ya_cerrado_y_hoy_cerrado_son_dos_frases_distintas(): void
    {
        // (a) Hoy abría y la ventana ya pasó.
        $this->abrirTodosLosDias('09:00:00', '14:00:00', '2026-09-02 20:00:00');
        $yaCerrado = $this->seccion();

        $this->assertStringContainsString(__('landing.info.state.closed_now'), $yaCerrado);
        $this->assertStringNotContainsString(__('landing.info.state.closed_today'), $yaCerrado);

        // (b) Hoy NO abre. Mismo instante, mismo horario, solo cambia el día de hoy.
        OpeningHour::query()->where('weekday', Carbon::now(DisplayTime::timezone())->dayOfWeek)
            ->update(['is_closed' => true]);

        $hoyCerrado = $this->seccion();

        $this->assertStringContainsString(__('landing.info.state.closed_today'), $hoyCerrado);
        $this->assertStringNotContainsString(__('landing.info.state.closed_now'), $hoyCerrado);
    }

    /**
     * ❗❗ **El día se resalta SOLO mientras su horario está vigente.**
     *
     * ⚠️ Las dos mitades: sin la primera, un `assertStringNotContainsString` pasaría en verde con el
     * resaltado borrado del todo.
     */
    public function test_hoy_se_resalta_solo_mientras_su_horario_esta_vigente(): void
    {
        $this->abrirTodosLosDias('09:00:00', '21:30:00', '2026-09-02 12:00:00');
        $this->assertStringContainsString('is-now', $this->seccion(), 'Con el parque abierto, la fila de hoy tiene que estar marcada.');

        // Mismo día y mismo horario: solo pasa la hora de cierre.
        Carbon::setTestNow(Carbon::parse('2026-09-02 22:00:00', DisplayTime::timezone()));

        $this->assertStringNotContainsString(
            'is-now', $this->seccion(),
            "Con el parque ya cerrado la fila de hoy sigue marcada.\n".
            '▶ El resaltado dice «esto es lo que rige ahora» sobre unas horas que ya pasaron.',
        );
    }

    /** Sin horario en el panel, el producto no dice ni «abierto» ni la tabla: no se inventa nada. */
    public function test_sin_horario_no_hay_estado_ni_tabla_pero_si_direccion(): void
    {
        $this->sembrarUbicacion();
        OpeningHour::query()->delete();

        $seccion = $this->seccion();

        $this->assertStringNotContainsString('visit__state', $seccion);
        $this->assertStringNotContainsString('visit__rows', $seccion);
        // La mitad de DÓNDE sobrevive: es lo único que no depende del horario.
        $this->assertStringContainsString('visit__place', $seccion);
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  La entradilla, derivada
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * **La entradilla sale del HORARIO, no de una cadena fija.**
     *
     * ⚠️ Las dos mitades y con horarios distintos: aseverar solo una frase pasaría en verde con la
     * entradilla escrita a fuego.
     */
    public function test_la_entradilla_se_deriva_del_horario(): void
    {
        // Un solo horario, todos los días.
        $this->abrirTodosLosDias('10:00:00', '20:00:00', '2026-09-02 12:00:00');
        $this->assertStringContainsString(__('landing.info.lede_one'), $this->seccion());

        // Dos: el fin de semana cambia.
        OpeningHour::query()->whereIn('weekday', [0, 6])->update(['open_time' => '11:00:00']);
        $this->assertStringContainsString(
            __('landing.info.lede_two', ['a' => 'lunes a viernes', 'b' => 'sábado a domingo']),
            $this->seccion(),
        );
    }

    /**
     * ⚠️ **Los grupos CERRADOS no cuentan como horario.** Un parque con «L–V abierto», «sábado
     * cerrado» y «domingo abierto» tiene TRES filas y **dos** horarios: contar filas diría «tres».
     */
    public function test_un_dia_cerrado_no_cuenta_como_horario(): void
    {
        $this->abrirTodosLosDias('10:00:00', '20:00:00', '2026-09-02 12:00:00');
        OpeningHour::query()->where('weekday', 6)->update(['is_closed' => true]);
        OpeningHour::query()->where('weekday', 0)->update(['open_time' => '11:00:00']);

        $this->assertStringContainsString(__('landing.info.lede_two', ['a' => 'lunes a viernes', 'b' => 'domingo']), $this->seccion());
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  El mapa y la dirección
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * ❗❗❗ **LA DIRECCIÓN VIVE SIEMPRE FUERA DEL MARCO**, con mapa y sin mapa.
     *
     * Es la regla que cerró la sección en el canvas, y su motivo es un modo de fallo real: **un
     * bloqueador tumba el widget con las cookies aceptadas** y entonces no salta ningún aviso —sale
     * un hueco—, así que quien la tuviera dentro del condicional del consentimiento se quedaría sin
     * saber dónde es.
     */
    public function test_la_direccion_esta_con_mapa_y_sin_mapa(): void
    {
        $this->sembrarUbicacion();

        $this->assertStringContainsString('Calle de PruebaZZ', $this->seccion(conMapa: false));
        $this->assertStringContainsString('Calle de PruebaZZ', $this->seccion(conMapa: true));
    }

    /**
     * ❗❗ **Con el mapa cargado: cero enlaces y cero botones.** Es la regla con la que 07 cerró, y
     * lo que la sostiene es que el propio mapa ya es interactivo.
     */
    public function test_con_el_mapa_cargado_la_seccion_no_tiene_ni_un_enlace_ni_un_boton(): void
    {
        $this->abrirTodosLosDias('09:00:00', '21:30:00', '2026-09-02 12:00:00');
        $this->sembrarUbicacion();
        SpecialDate::query()->delete();

        $seccion = $this->seccion(conMapa: true);

        $this->assertStringNotContainsString('<a ', $seccion);
        $this->assertStringNotContainsString('<button', $seccion);
    }

    /**
     * **Y con el mapa BLOQUEADO vuelve la puerta a Google Maps.**
     *
     * ⚠️ Es la otra mitad del caso de arriba: sin ella, borrar el enlace dejaría al visitante sin
     * ninguna forma de abrir el mapa y el caso anterior seguiría en verde.
     */
    public function test_con_el_mapa_bloqueado_hay_puerta_a_google_maps(): void
    {
        $this->sembrarUbicacion();

        $seccion = $this->seccion(conMapa: false);

        $this->assertStringContainsString(__('landing.info.open_in_maps'), $seccion);
        $this->assertStringContainsString('https://maps.example.com/pjp', $seccion);
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  Las fechas especiales
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * **La que viene se anuncia FUERA del pliegue; el pliegue solo existe si hay más de una.**
     *
     * ⚠️ «Lo que urge no se esconde detrás de un clic», y un control que abre una lista de un
     * elemento —el que ya está anunciado encima— cuesta 48 px para no decir nada.
     */
    public function test_la_fecha_proxima_se_anuncia_y_el_pliegue_pide_mas_de_una(): void
    {
        $this->abrirTodosLosDias('09:00:00', '21:30:00', '2026-09-02 12:00:00');
        SpecialDate::query()->delete();

        // Cero fechas: ni aviso ni pliegue.
        $sinNada = $this->seccion();
        $this->assertStringNotContainsString('visit__exception', $sinNada);
        $this->assertStringNotContainsString('visit__fold', $sinNada);

        // Una sola: se anuncia, y el pliegue sigue sin existir.
        SpecialDate::create(['date' => '2026-09-10', 'is_closed' => true]);
        $unaSola = $this->seccion();
        $this->assertStringContainsString('visit__exception', $unaSola);
        $this->assertStringNotContainsString('visit__fold', $unaSola);

        // Dos: ya hay algo que plegar.
        SpecialDate::create(['date' => '2026-12-25', 'is_closed' => true]);
        $this->assertStringContainsString('visit__fold', $this->seccion());
    }

    /**
     * ⚠️ **Una fecha LEJANA no se anuncia fuera del pliegue.** El aviso existe porque una regla no
     * avisa de su excepción; un calendario entero está muerto 360 días al año.
     */
    public function test_una_fecha_lejana_no_se_anuncia_fuera_del_pliegue(): void
    {
        $this->abrirTodosLosDias('09:00:00', '21:30:00', '2026-09-02 12:00:00');
        SpecialDate::query()->delete();
        SpecialDate::create(['date' => '2026-12-25', 'is_closed' => true]);
        SpecialDate::create(['date' => '2026-12-31', 'is_closed' => true]);

        $seccion = $this->seccion();

        $this->assertStringNotContainsString('visit__exception', $seccion);
        // Pero siguen dentro del pliegue: no se pierden, se guardan.
        $this->assertStringContainsString('visit__fold', $seccion);
    }

    /**
     * **El aviso es una FRASE y son DOS, no una fila de datos** (`#489`).
     *
     * Escribía `:date — :detail` —«sáb. 19 sep. — 11:30 – 22:30»— y eso, sobre Nube y pegado a las
     * dos filas del horario semanal, **se lee como una tercera fila del horario**. El artboard dice
     * para qué existe el aviso: es *«la única frase de la sección que puede arruinarte el viaje»*.
     *
     * ⚠️⚠️ **Y «cerramos» y «abrimos a otra hora» son hechos distintos.** Con una sola plantilla, el
     * día que cierra se anunciaría como «horario especial: Cerrado», que es una frase que no dice lo
     * que pasa. Por eso el caso ejercita **las dos ramas**, no una.
     *
     * ⚠️ No se comprueba que diga «festivo», y es a propósito: `special_dates` es cualquier
     * excepción de horario —un evento privado, una apertura extraordinaria—, así que llamarlas
     * festivas afirmaría algo que el dato no dice. Es la disciplina de `#487`.
     */
    public function test_el_aviso_de_la_fecha_proxima_es_una_frase_y_distingue_el_cierre(): void
    {
        $this->abrirTodosLosDias('09:00:00', '21:30:00', '2026-09-02 12:00:00');

        // Rama ABIERTA: horario distinto ese día.
        SpecialDate::query()->delete();
        SpecialDate::create(['date' => '2026-09-10', 'is_closed' => false, 'open_time' => '11:30', 'close_time' => '22:30']);
        $abierta = $this->seccion();

        $this->assertStringContainsString('11:30 – 22:30', $abierta, 'el aviso perdió el horario del día especial');
        // ⚠️ Se asevera el TEXTO y no la clave: derivarlo de `lang/` haría que un retorno a
        // «:date — :detail» pasara en verde, porque el caso buscaría lo que la plantilla dijera.
        $this->assertStringContainsString(
            'horario especial',
            $abierta,
            'el aviso volvió a ser una fila de datos: no dice qué pasa ese día.',
        );

        // Rama CERRADA: la frase cambia entera, no solo el detalle.
        SpecialDate::query()->delete();
        SpecialDate::create(['date' => '2026-09-10', 'is_closed' => true]);
        $cerrada = $this->seccion();

        $this->assertStringContainsString('Cerramos', $cerrada, 'el día que cierra no lo dice: es la mitad que se olvida');
        $this->assertStringNotContainsString(
            'horario especial',
            $cerrada,
            'el día que CIERRA se anuncia como «horario especial», que no dice lo que pasa.',
        );
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  Lo que la sección NO dice (`[DECIDIDO owner, 2026-09-10]`)
    // ─────────────────────────────────────────────────────────────────────────────────

    /** UN solo encabezado: era el caso extremo del molde editorial que `#297` midió aquí. */
    public function test_la_seccion_tiene_un_solo_encabezado(): void
    {
        $this->assertSame(
            1, preg_match_all('/<h[1-6]\b/', $this->seccion()),
            'La sección de horarios tiene más de un encabezado: es el molde editorial volviendo.',
        );
    }

    /**
     * **Ni teléfono ni «Cómo llegar»**, y las dos son decisión del owner con la consecuencia
     * delante: el teléfono sigue en el menú y en el pie, así que no se pierde.
     */
    public function test_la_seccion_no_ofrece_ni_telefono_ni_como_llegar(): void
    {
        Setting::updateOrCreate(['key' => 'contact.phone'], ['value' => '+34 600 000 000']);
        Setting::flushMemo();

        $seccion = $this->seccion(conMapa: true);

        $this->assertStringNotContainsString('tel:', $seccion);
        $this->assertStringNotContainsString(__('landing.info.directions'), $seccion);
    }

    /**
     * **Sin línea de aparcamiento** (`[DECIDIDO owner, 2026-09-10]`: no entra en esta tanda).
     *
     * ⚠️ `#297` ya lo había decidido una vez —«Parking gratis 2h» estaba en el código y no en el
     * panel— y `#307` lo retiró. El artboard trae otra frase y **el dato sigue sin campo**, así que
     * volver a escribirlo sería revertir aquella decisión por la puerta de atrás.
     */
    public function test_la_seccion_no_escribe_ninguna_linea_de_aparcamiento(): void
    {
        $seccion = mb_strtolower($this->seccion(conMapa: true));

        foreach (['aparca', 'parking', 'gratis'] as $palabra) {
            $this->assertStringNotContainsString(
                $palabra, $seccion,
                "La sección vuelve a escribir el aparcamiento («{$palabra}»), y ese dato no está en el panel.",
            );
        }
    }
}
