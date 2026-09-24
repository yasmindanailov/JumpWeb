/**
 * **LOS PASOS DE LA COMPRA DE LA ISLA tras la pantalla 0, sin estado** (T3e·3 de `docs/specs/isla-y-landing-nueva.md`
 * §4.10, `DECISIONES #692`): «Tus datos», «Pagar», la salida al banco y los desenlaces. De lo que sabe la compra a
 * la descripción del paso (`ck`) que pinta `CompraIsla`, como el guion del diseño (`paginas/compra/compra.jsx`).
 *
 * Los pasos del COBRO los manda la máquina del motor (`pasoDelMotor`), porque llegan también de fuera: la vuelta del
 * banco recarga la página y aterriza en su desenlace. Los de antes («cuando», «datos», «pagar») son de la isla.
 */
import { STEPS, isOutcome } from '../../sidebar/machine.js';
import { t as texto, tp as textoCon } from '../../sidebar/i18n.js';
import { diaDelPlazo } from './vista.js';

/**
 * ¿Una apertura empieza una compra NUEVA? Con intención (la landing pidió una zona o un producto), sí, aunque la
 * anterior se quedara en un desenlace. ⚠️⚠️ Sin intención, NO si la máquina está en un desenlace: es la vuelta del
 * banco, y empezar borraría «¡Reservado!» a quien acaba de pagar.
 */
export function empiezaOtra(intencion, step) {
    return intencion !== null && intencion !== undefined ? true : ! isOutcome(step);
}

/** El paso que manda la MÁQUINA, o `null` si manda la isla. */
export function pasoDelMotor(step) {
    if (step === STEPS.REDIRECTING) return 'banco';
    if (step === STEPS.CONFIRMED) return 'listo';
    if (step === STEPS.DECLINED) return 'fallido';
    if (step === STEPS.VERIFYING) return 'verificando';

    return null;
}

/**
 * El orden de los pasos, del diseño: avanzar entra por la derecha y volver, por la izquierda. Dentro de «Tus datos»,
 * sus vistas (el descargo, «Entra») van detrás, y el olvido de «Entra», un poco más.
 */
export function rango(paso, vista = null, pasoEntrada = null) {
    if (paso === 'cuando') return 0;
    if (paso === 'datos') return vista ? 1.5 + (vista === 'entrar' && pasoEntrada === 'olvido' ? 0.2 : 0) : 1;
    if (paso === 'pagar') return 2;
    if (paso === 'listo') return 4;

    return 3;
}

/** La dirección de un cambio de paso: `fwd`, `back`, o `null` la primera vez. */
export function direccion(antes, ahora) {
    if (antes === null || antes === undefined || antes === ahora) return null;

    return ahora > antes ? 'fwd' : 'back';
}

/**
 * La descripción del paso para `CompraIsla`, fuera de la pantalla 0.
 *
 * @param {object} e
 *   `paso` · `vista` (`descargo` · `entrar`, dentro de «Tus datos») · `entrada` (el estado de «Entra»: `paso`,
 *   `valor`, `clave`) · `textos` · `resumen` ({ summary, total }) · `importe` (lo que cobra la pasarela, ya escrito) ·
 *   `ocupado` (el paso que espera al servidor) · `horaNueva` (la elegida en «perdida») · `acciones` ({ volver,
 *   continuar, entrar, pagar, salir, reintentar, elegirHora, miQr, cerrar }).
 */
export function ckDelPaso(e) {
    const t = (clave) => texto(e.textos, clave);
    const a = e.acciones ?? {};
    const pasoN = (n, nombre) => ({ stepStrong: textoCon(e.textos, 'compra.paso', { n, total: 2 }), step: ` · ${nombre}`, progress: [n, 2] });
    const ck = {
        key: `${e.paso}${e.vista ?? ''}`,
        stepStrong: '',
        step: '',
        progress: null,
        onBack: null,
        onClose: a.cerrar ?? null,
        summary: e.resumen?.summary ?? null,
        total: e.resumen?.total ?? null,
        // La SEÑAL de una fiesta («Hoy pagas 50 €»), del presupuesto o del pedido (`recibo.js::hoyPagas`).
        today: e.resumen?.today ?? null,
        note: null,
        action: null,
    };

    if (e.paso === 'datos') {
        const entrada = e.entrada ?? {};
        // «Entra» lleva su propia acción (`PjcEntrar`); su olvido y el descargo, ninguna: se vuelve con la flecha.
        const entrando = e.vista === 'entrar' && entrada.paso === 'id';
        const action = entrando
            ? {
                label: t('compra.entrar.continuar'),
                onClick: a.entrar,
                disabled: ! (String(entrada.valor ?? '').trim() && entrada.clave),
                loading: e.ocupado === 'entrar' ? t('compra.entrar.cargando') : false,
            }
            : (e.vista ? null : { label: t('compra.datos.continuar'), onClick: a.continuar, loading: e.ocupado === 'datos' ? t('compra.datos.cargando') : false });

        return {
            ...ck, ...pasoN(1, t('compra.datos.banda')),
            key: `datos${e.vista ?? ''}${e.vista === 'entrar' ? entrada.paso ?? '' : ''}`,
            onBack: a.volver ?? null,
            action,
        };
    }

    if (e.paso === 'pagar') {
        return {
            ...ck, ...pasoN(2, t('compra.pagar.banda')),
            onBack: a.volver ?? null,
            action: { label: textoCon(e.textos, 'compra.pagar.tarjeta', { importe: e.importe }), onClick: a.pagar, loading: e.ocupado === 'pagar' ? t('pieza.cargando') : false },
        };
    }

    if (e.paso === 'banco') return { ...ck, ...pasoN(2, t('compra.pagar.banda')), action: { label: t('compra.pagar.saliendo_boton'), onClick: a.salir } };

    // ⚠️ Sin Bizum (`#683`: corchete apagado, sin hueco), la acción del pago no completado es reintentar con tarjeta.
    if (e.paso === 'fallido') {
        return { ...ck, ...pasoN(2, t('compra.pagar.banda')), action: { label: t('compra.fallido.tarjeta'), onClick: a.reintentar, loading: e.ocupado === 'fallido' ? t('pieza.cargando') : false } };
    }

    if (e.paso === 'verificando') return { ...ck, ...pasoN(2, t('compra.pagar.banda')) };

    // La hora se llenó al pagar (T3e·6, `PjcPerdida`): no se cobró nada, y «Elegir esta hora» vuelve a «Pagar» con la
    // línea rehecha. Sin flecha, como el diseño: la salida es elegir otra hora o cerrar.
    if (e.paso === 'perdida') {
        return {
            ...ck, ...pasoN(2, t('compra.pagar.banda')),
            action: { label: t('compra.perdida.boton'), onClick: a.elegirHora, disabled: ! e.horaNueva, loading: e.ocupado === 'perdida' ? t('pieza.cargando') : false },
        };
    }

    if (e.paso === 'listo') return { ...ck, summary: null, total: null, today: null, action: { label: t('compra.listo.mi_qr'), onClick: a.miQr } };

    return ck;
}

/**
 * La tarea de una FIESTA en «Listo» (T3e·5): el formulario de invitados hasta su plazo y, si el producto la ofrece, la
 * invitación. Todo lo dice el SERVIDOR por reserva (`guest_form_url`, `guest_count_deadline`, `invitation_url`): sin
 * formulario no hay tarea; sin plazo, la frase va sin fecha; sin invitación, una cosa y no dos.
 */
export function tareaDeFiesta(linea, { textos = {}, locale = 'es' } = {}) {
    if (! linea?.guest_form_url) return null;
    const t = (clave) => texto(textos, `compra.listo.${clave}`);
    const fecha = diaDelPlazo(linea.guest_count_deadline, locale);
    const invitacion = Boolean(linea.invitation_url);

    return {
        id: 'fiesta',
        icon: 'party-popper',
        title: invitacion ? t('fiesta_intro') : t('fiesta_intro_una'),
        steps: [
            fecha ? textoCon(textos, 'compra.listo.fiesta_invitados', { fecha }) : t('fiesta_invitados_sin_fecha'),
            ...(invitacion ? [t('fiesta_invitacion')] : []),
        ],
        botones: [t('fiesta_formulario'), ...(invitacion ? [t('fiesta_compartir')] : [])],
    };
}

/** A dónde lleva cada botón de la tarea de la fiesta: el formulario, o su invitación (`null`: no es de la fiesta). */
export function destinoDeTarea(linea, boton, textos = {}) {
    if (! linea) return null;

    return boton === texto(textos, 'compra.listo.fiesta_compartir') ? (linea.invitation_url ?? null) : (linea.guest_form_url ?? null);
}

/**
 * «Listo» (`PjcListo`): la línea, el QR del carné, a dónde se envió y las tareas de «Antes de venir».
 *
 * ⚠️ De las tareas del diseño, aquí van las que salen de los DATOS del motor: la de la FIESTA (arriba) y, en las
 * entradas, añadir a los hijos y firmar por ellos si la instalación firma el descargo dentro (modo `interno`). Los
 * adultos que vienen y los calcetines dependen de qué zona es cada entrada y del texto del parque: son de la página (T4).
 */
export function pantallaListo({ linea, confirmacion, correo, qrSrc = '', cuentaNueva = false, firmaDentro = false, textos = {}, locale = 'es' }) {
    const t = (clave) => texto(textos, clave);
    const pack = (confirmacion?.lines ?? []).find((l) => l.is_pack) ?? null;
    const fiesta = tareaDeFiesta(pack, { textos, locale });
    const menores = firmaDentro ? { id: 'menores', icon: 'user-round-plus', texto: t('compra.listo.menores'), botones: [t('compra.listo.menores_boton')] } : null;

    return {
        fiesta: pack !== null,
        linea,
        codigo: confirmacion?.code ?? '',
        qrSrc,
        correo: correo ?? '',
        whatsapp: false,
        tareas: [pack ? fiesta : menores].filter(Boolean),
        cuentaNueva,
    };
}
