/**
 * **LOS AVISOS DE LA CUENTA en Mi cuenta de la isla** (T5e·2 de `docs/specs/isla-y-landing-nueva.md` §4.13, `#779`): los
 * que pinta el índice del cajón (`account/zones/AccountHomeZone.vue`) y la isla no pintaba. Puro, sin Vue (`CE-6`), con su
 * `node --test`; lo que hace cada botón, en `useSeccionCuenta.js`.
 *
 *   · **El de la cuenta, UNO y en su orden** —la decisión es la del cajón, `accountNoticeFrom` (`#331`, owner: «mejor un
 *     mensaje con los dos estados, para no saturar»)—: sin el correo confirmado, confirmarlo (y, si dejó el descargo
 *     aceptado, que se firmará al confirmarlo); con él, firmar SU descargo (o volver a firmarlo); y después, el de sus hijos.
 *     Confirmar el correo reenvía con la MISMA puerta que el alta (`account/verify.js`: espera y cupo, los del servidor).
 *   · **El de la analítica** (`#678` T3a·4), aparte y no en esa cadena —en ella lo tapaba un descargo sin firmar durante
 *     semanas, y es el segundo canal de un aviso legal—: su texto y su «Entendido» los trae el contexto, y se queda hasta
 *     despedirlo.
 */
import { t as texto, tp } from '../../sidebar/i18n.js';
import { WAIVER_NOTICE_DEPENDENTS, WAIVER_NOTICE_VERIFY, accountNoticeFrom } from '../../sidebar/account/waiver.js';
import { resendGate } from '../../sidebar/account/verify.js';

/**
 * El aviso de la cuenta, o `null`. `accion.hace` dice qué hace su enlace (`reenviar`, `firmar`, `hijos`).
 *
 * @param {object|null} contexto  el contexto de cuenta (`/me/account-context`: `email_verified`, `waiver`)
 * @param {{textos: object, reenvio: {segundos: number, quedan: number}}} deps  la espera y el cupo del reenvío (`auth`)
 * @returns {null|{tipo: string, icono: string, texto: string, detalle: string, accion: null|{texto: string, hace: string, deshabilitada: boolean}}}
 */
export function avisoDeCuenta(contexto, { textos, reenvio }) {
    const aviso = accountNoticeFrom(contexto);

    if (! aviso) return null;

    if (aviso.kind === WAIVER_NOTICE_VERIFY) {
        const puerta = resendGate({ secondsLeft: reenvio?.segundos, resendsLeft: reenvio?.quedan });

        return {
            tipo: 'verificar',
            icono: 'mail-warning',
            texto: texto(textos, 'mi_cuenta.avisos.verificar'),
            detalle: [
                aviso.withWaiver ? texto(textos, 'mi_cuenta.avisos.verificar_descargo') : '',
                puerta.exhausted ? texto(textos, 'mi_cuenta.avisos.limite') : tp(textos, 'mi_cuenta.avisos.quedan', { n: reenvio?.quedan ?? 0 }),
            ].filter(Boolean).join(' '),
            accion: puerta.exhausted ? null : {
                texto: puerta.waiting ? tp(textos, 'mi_cuenta.avisos.reenviar_en', { s: reenvio?.segundos ?? 0 }) : texto(textos, 'mi_cuenta.avisos.reenviar'),
                hace: 'reenviar',
                deshabilitada: ! puerta.canResend,
            },
        };
    }

    if (aviso.kind === WAIVER_NOTICE_DEPENDENTS) {
        return {
            tipo: 'hijos', icono: 'file-signature', texto: texto(textos, 'mi_cuenta.avisos.hijos'), detalle: '',
            accion: { texto: texto(textos, 'mi_cuenta.avisos.hijos_boton'), hace: 'hijos', deshabilitada: false },
        };
    }

    return {
        tipo: 'firmar', icono: 'file-signature',
        texto: texto(textos, contexto?.waiver?.outdated === true ? 'mi_cuenta.avisos.firmar_nuevo' : 'mi_cuenta.avisos.firmar'),
        detalle: '',
        accion: { texto: texto(textos, 'mi_cuenta.avisos.firmar_boton'), hace: 'firmar', deshabilitada: false },
    };
}

/**
 * El aviso de la analítica, o `null`: su texto y su «Entendido», del servidor; su otro botón lleva a donde se retira y se
 * llama COMO LO NOMBRA la frase del servidor («Privacidad y datos», `account.privacy.title`, el mismo del cajón): el
 * botón y lo que la frase dice tienen que coincidir. Sin ese rótulo, el del plegable.
 *
 * @param {object|null} contexto
 * @param {{textos: object, motor?: object}} deps  `motor`: el grupo `account`
 * @returns {null|{texto: string, despedir: string, privacidad: string}}
 */
export function avisoDeAnalitica(contexto, { textos, motor = {} }) {
    const aviso = contexto?.analytics_notice;

    if (! aviso?.text) return null;

    return {
        texto: String(aviso.text),
        despedir: String(aviso.dismiss ?? ''),
        privacidad: texto(motor, 'account.privacy.title') || texto(textos, 'mi_cuenta.ajustes.privacidad'),
    };
}
