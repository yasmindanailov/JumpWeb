/**
 * **LA ISLA VIVA EN UNA PÁGINA DECLARADA** (T4e de `docs/specs/isla-y-landing-nueva.md` §4.12): lo que DECIDE, sin
 * pintar (`CE-6`). Es la transcripción de `paginas/entradas/pagina.jsx` del diseño —el mockup manda (`#767`)—:
 *   · la isla cede su botón con cualquier primario de la página a la vista (`data-isla-cta`, fuera de su franja);
 *   · calla [Hoy] cuando la pieza 6 ya lo dice (`[data-hoy-linea]`) o mientras se calcula;
 *   · mientras se calcula, su acción es el paso que falta («Elige el día», «Elige la hora»); con todo elegido, lo
 *     elegido en corto y el botón de la calculadora (situación `elegido`);
 *   · «Reservar para hoy» lleva a la pieza de precio con hoy ya elegido.
 * Los textos son del grupo `isla` de `lang/` (los de la acción) y de la página (su acción y su «desde»).
 */

/**
 * Qué se ve de la página, con la geometría del diseño: un primario cuenta en cuanto asoma FUERA de la franja de la
 * isla (si la isla está abajo, por encima de ella; si arriba, por debajo), y la línea [Hoy] de la pieza 6, igual.
 * Recibe rectángulos (`getBoundingClientRect()`) y el alto de la ventana.
 */
export function medirVista({ ctas = [], hoyLinea = null, isla = null, alto }) {
    let arriba = 0;
    let abajo = alto;

    if (isla && isla.height) {
        if (isla.top > alto / 2) abajo = isla.top;
        else arriba = isla.bottom;
    }
    const ve = (r) => Boolean(r) && r.height > 0 && r.bottom > arriba && r.top < abajo;

    return { cta: ctas.some(ve), hoy: ve(hoyLinea) };
}

/**
 * La acción de la tarea en la isla: su enlace, o —con `zone`— abrir la cuenta en esa zona, como el menú (el `href` de su
 * puerta no serviría en la misma página: cambiar solo el ancla no la recarga).
 */
function accionDeTarea({ label, href, zone }, acciones) {
    return zone ? { label, onClick: () => acciones.abrirCuenta(zone, 'isla') } : { label, href };
}

/**
 * Las props que la página le da a la isla. `config` es lo que da la página (su `page`, su `today` con sus huecos
 * —`slots`, del hecho de la página: los mismos que dicen su cabecera y su cierre—, su menú…); `estado`, lo que cambia
 * (`vista`, `calculo` de la calculadora, `cookies`); `acciones`, lo que hace cada botón. `textos`, el grupo `isla`.
 */
export function propsDeLaIsla({ config, estado, acciones, textos }) {
    const calculo = estado.calculo ?? null;
    const falta = calculo?.falta || null;
    const cuenta = config.owner ? (config.cuenta ?? null) : null;
    const conHoy = Boolean(config.today) && ! estado.vista.hoy && ! falta;
    const huecos = Boolean(config.today?.slots);
    const accion = falta
        ? { label: textos?.accion?.[falta === 'dia' ? 'elige_dia' : 'elige_hora'] ?? '', href: falta === 'dia' ? '#p3-dia' : '#p3-hora' }
        : { ...config.page.action, onClick: () => acciones.reservar(conHoy && huecos) };

    return {
        page: { ...config.page, action: accion },
        today: conHoy ? { ...config.today, slots: huecos } : null,
        chosen: calculo?.elegido ? { text: calculo.elegido, label: calculo.boton, widgetVisible: estado.vista.cta, onClick: acciones.irAlResumen } : null,
        ctaVisible: estado.vista.cta,
        // La oferta de la página en sus 3 últimos días (situación `oferta`): la decide la página, que sabe su fecha.
        offer: config.offer ?? null,
        compact: false,
        homeLabel: config.homeLabel ?? null,
        menuItems: config.menuItems ?? [],
        contact: config.contact ?? { phone: '', whatsapp: '' },
        lang: config.lang ?? '',
        // La cuenta, en su capa de la isla (T5, `#773`): Mi QR (el carné), Mi cuenta (su inicio) o, sin sesión, entrar. De
        // dónde viene (`from`: el menú o la acción de la isla) viaja con ella: desde el menú, su flecha vuelve a él.
        // Con sesión, lo que el SERVIDOR sabe de la próxima (`config.cuenta`, `Http\Cuenta\AntesDeVenir::paraLaIsla`, T5c):
        // su primera tarea pendiente —el punto del menú y «Siguiente: …» en Mi cuenta— y si es HOY.
        account: config.owner
            ? { state: 'session', pending: Boolean(cuenta?.pending), pendingText: cuenta?.pendingText ?? null,
                onQr: (x) => acciones.abrirCuenta('card', x?.from), onClick: (x) => acciones.abrirCuenta('home', x?.from) }
            : { state: 'guest', onClick: (x) => acciones.abrirCuenta('login', x?.from) },
        // «Hoy a las 17:00» (situación 14): manda sobre casi todo y su acción es «Ver mi QR», que abre Tu QR en su capa.
        bookingToday: cuenta?.bookingToday ? { text: cuenta.bookingToday.text } : null,
        // La tarea (situación 13): en la portada y en la página de lo reservado, con su acción: un enlace a su sitio o, si
        // la resuelve una pantalla de la cuenta (`zone`, «Añade a tus hijos», T5d), la cuenta abierta en esa zona.
        task: cuenta?.task ? { text: cuenta.task.text, product: cuenta.task.product ?? null, action: accionDeTarea(cuenta.task.action, acciones) } : null,
        cookies: estado.cookies ? { onAccept: acciones.aceptarCookies, onReject: acciones.rechazarCookies, onConfigure: acciones.configurarCookies, onPolicy: acciones.politicaCookies } : null,
        cookiePrefs: estado.preferencias ?? null,
        notice: estado.aviso ?? null,
        onNavigate: acciones.navegar,
    };
}

/**
 * **La segunda capa de las cookies** («Tus cookies»): las necesarias, que no se apagan, y cada finalidad OPCIONAL de la
 * instalación (`categorias`, las del `<body>`) con su estado, su título y su texto LEGALES (`legales`: el grupo `panel`
 * de `lang/<idioma>/cookies.php`, los de la web de siempre) y lo que hace cada botón. Una finalidad sin título legal
 * sale con su nombre: nunca un interruptor sin decir qué enciende.
 */
export function preferenciasDeCookies({ categorias = [], prefs = {}, legales = {}, textos = {}, acciones }) {
    return {
        necesarias: { titulo: legales.necessary_title ?? '', texto: legales.necessary_desc ?? '', etiqueta: legales.always_on ?? '' },
        categorias: categorias.map((id) => ({ id, titulo: legales[`${id}_title`] ?? id, texto: legales[`${id}_desc`] ?? '', activa: Boolean(prefs[id]) })),
        textos: {
            si: textos?.cookies?.si ?? '', no: textos?.cookies?.no ?? '',
            aceptar: legales.accept_all ?? '', rechazar: legales.reject_all ?? '', politica: legales.policy_link ?? '',
        },
        onCambiar: acciones.cambiar,
        onAceptarTodas: acciones.aceptarTodas,
        onRechazarTodas: acciones.rechazarTodas,
        onPolitica: acciones.politica,
    };
}
