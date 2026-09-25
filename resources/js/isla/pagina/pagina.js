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

/** ¿Quedan huecos hoy? Alguna hora VENDIBLE y con plazas en la respuesta de `POST /availability/{id}/times`. */
export function quedanHuecos(respuesta) {
    const horas = Array.isArray(respuesta?.data) ? respuesta.data : [];

    return horas.some((h) => Boolean(h?.sellable) && Number(h?.available) > 0);
}

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
 * Las props que la página le da a la isla. `config` es lo que da la página (su `page`, su `today` sin los huecos, su
 * menú…); `estado`, lo que cambia (`vista`, `calculo` de la calculadora, `huecos`, `cookies`); `acciones`, lo que hace
 * cada botón. `textos` es el grupo `isla` de `lang/`.
 */
export function propsDeLaIsla({ config, estado, acciones, textos }) {
    const calculo = estado.calculo ?? null;
    const falta = calculo?.falta || null;
    const conHoy = Boolean(config.today) && ! estado.vista.hoy && ! falta;
    const accion = falta
        ? { label: textos?.accion?.[falta === 'dia' ? 'elige_dia' : 'elige_hora'] ?? '', href: falta === 'dia' ? '#p3-dia' : '#p3-hora' }
        : { ...config.page.action, onClick: () => acciones.reservar(conHoy && Boolean(estado.huecos)) };

    return {
        page: { ...config.page, action: accion },
        today: conHoy ? { ...config.today, slots: Boolean(estado.huecos) } : null,
        chosen: calculo?.elegido ? { text: calculo.elegido, label: calculo.boton, widgetVisible: estado.vista.cta, onClick: acciones.irAlResumen } : null,
        ctaVisible: estado.vista.cta,
        compact: false,
        homeLabel: config.homeLabel ?? null,
        menuItems: config.menuItems ?? [],
        contact: config.contact ?? { phone: '', whatsapp: '' },
        lang: config.lang ?? '',
        // La cuenta se abre en el LATERAL del cajón hasta la T5 (Mi cuenta en la isla): Mi QR (el carné), Mi cuenta (su
        // índice) o, sin sesión, entrar.
        account: config.owner
            ? { state: 'session', onQr: () => acciones.abrirCuenta('card'), onClick: () => acciones.abrirCuenta('home') }
            : { state: 'guest', onClick: () => acciones.abrirCuenta('login') },
        cookies: estado.cookies ? { onAccept: acciones.aceptarCookies, onReject: acciones.rechazarCookies, onConfigure: acciones.configurarCookies, onPolicy: acciones.politicaCookies } : null,
        onNavigate: acciones.navegar,
    };
}
