/**
 * **LOS HIJOS EN MI CUENTA, en marcha** (T5d de `docs/specs/isla-y-landing-nueva.md` §4.13, `DECISIONES #777`): lo que se
 * pide al servidor y qué hace cada botón de «Quién viene contigo», «Añade a tus hijos» y la ficha de un hijo. Lo que decide
 * está en `hijos.js`, puro y probado.
 *
 *   · Los datos son los del motor, usados SIN tocarlo: `stores/dependents.js` (la lista, el alta, la firma, quitar) y
 *     `stores/waiver.js` (el texto del descargo que se enseña y se firma: su `id` viaja con cada alta, `#441`).
 *   · **Varios hijos en un gesto, una petición por hijo** (el alta es de uno): se guardan en orden y cada uno que entra
 *     sale del formulario, así que un fallo a mitad deja a la vista SOLO lo que falta, con su error en su ficha.
 *   · ⚠️ El `409` del texto que cambió mientras se rellenaba no llega con su código a través del alta del motor: tras un
 *     fallo que no es de un campo se relee el texto y, si su versión cambió, se desmarca la casilla y se dice —no se
 *     firma un texto que no se ha leído—.
 */
import { computed, reactive } from 'vue';
import { api } from '../../sidebar/api.js';
import { t as texto, tp } from '../../sidebar/i18n.js';
import { useDependentsStore } from '../../sidebar/stores/dependents.js';
import { useWaiverStore } from '../../sidebar/stores/waiver.js';
import { signupNeedsWaiver, signupWaiverDocumentId } from '../../sidebar/account/dependents.js';
import { erroresDeFicha, fechaTecleada, fichaVacia, fichasDe, formularioHijos, hijoDe, isoDeFecha, quienDe, relacionesDe, revisarHijos } from './hijos.js';

/** «Hoy» del navegador en `Y-m-d`: solo para la forma y la pista de la edad (si es menor lo decide el servidor). */
const hoyLocal = () => new Date().toLocaleDateString('sv-SE');

export function useHijosCuenta({ textos, props, emailVerified }) {
    const menores = useDependentsStore();
    const descargo = useWaiverStore();
    const s = reactive({ h: formularioHijos(), errores: { lista: [], descargo: '' }, aviso: '', hijo: null, preguntar: false, firmaCasilla: false, firmaError: '' });
    const opciones = () => ({ api, messages: props.messages, auth: props.auth });
    const firma = computed(() => signupNeedsWaiver(descargo.document));

    /** Si el texto del descargo cambió (se relee): la casilla se desmarca y se dice. Devuelve si cambió. */
    async function textoCambiado() {
        const antes = descargo.document?.id ?? null;

        await descargo.reloadLegal({ api });

        return firma.value && descargo.document?.id !== antes;
    }

    function empezar() {
        Object.assign(s, { h: formularioHijos(), errores: { lista: [], descargo: '' }, aviso: '' });
        menores.forget();
        descargo.ensureLegal({ api });
    }

    function cambiar(i, campo, valor) {
        const ficha = s.h.lista[i];

        if (! ficha) return;
        ficha[campo] = campo === 'fecha' ? fechaTecleada(valor) : valor;
        if (s.errores.lista[i]?.[campo]) s.errores.lista[i] = { ...s.errores.lista[i], [campo]: '' };
    }

    /** «Guardar»: lo que falta se dice antes; después, un alta por hijo, en orden. `'listo'`, `'error'` o `'caducada'`. */
    async function guardar() {
        const falta = revisarHijos(s.h, { firma: firma.value, textos, hoy: hoyLocal() });

        Object.assign(s, { errores: falta ?? { lista: [], descargo: '' }, aviso: '' });
        if (falta) return 'error';

        while (s.h.lista.length) {
            const f = s.h.lista[0];
            const ok = await menores.add({ name: f.nombre.trim(), surname: '', relationship: f.rel, born_on: isoDeFecha(f.fecha) }, { ...opciones(), documentId: signupWaiverDocumentId(descargo.document) });

            if (! ok) return fallo();
            s.h.lista.shift();
        }

        return 'listo';
    }

    async function fallo() {
        if (menores.expired) return 'caducada';
        const { ficha, descargo: casilla, aviso } = erroresDeFicha(menores.fields, menores.notice);

        if (! Object.keys(ficha).length && ! casilla && await textoCambiado()) {
            s.h.descargo = false;
            Object.assign(s, { errores: { lista: [], descargo: texto(textos, 'mi_cuenta.hijos.errores.descargo_nuevo') }, aviso: '' });
        } else {
            Object.assign(s, { errores: { lista: [ficha], descargo: casilla }, aviso });
        }

        return 'error';
    }

    /** Firmar por un hijo (su ficha): la casilla, y el texto que se enseña. `true` si firmó. */
    async function firmar() {
        if (! s.firmaCasilla) {
            s.firmaError = texto(textos, 'mi_cuenta.hijos.errores.descargo');

            return false;
        }
        s.firmaError = '';
        const { ok, stale } = await menores.signWaiver({ id: s.hijo, documentId: signupWaiverDocumentId(descargo.document) }, opciones());

        if (stale) {
            await descargo.reloadLegal({ api });
            Object.assign(s, { firmaCasilla: false, firmaError: texto(textos, 'mi_cuenta.hijos.errores.descargo_nuevo') });
        } else if (! ok) {
            s.firmaError = menores.notice || texto(textos, 'mi_cuenta.hijo.fallo');
        }

        return ok;
    }

    /** Quitarlo de la cuenta, tras preguntar. Devuelve su nombre si salió (para decirlo arriba), o `''`. */
    async function quitar() {
        const nombre = hijo.value?.nombre ?? '';
        const ok = await menores.remove(s.hijo, opciones());

        s.preguntar = false;

        return ok ? tp(textos, 'mi_cuenta.hijo.quitado', { nombre }) : '';
    }

    const dato = computed(() => menores.items.find((d) => d.id === s.hijo) ?? null);
    const hijo = computed(() => hijoDe(dato.value, { textos, emailVerified: emailVerified() }));

    return {
        s, menores, descargo, firma, empezar, cambiar, guardar, firmar, quitar,
        cargar: () => menores.ensure({ api }),
        otro: () => { s.h.lista.push(fichaVacia()); },
        quitarFicha: (i) => { if (s.h.lista.length > 1) s.h.lista.splice(i, 1); s.errores.lista.splice(i, 1); },
        abrir: (id) => { Object.assign(s, { hijo: id, preguntar: false, firmaCasilla: false, firmaError: '' }); menores.forget(); descargo.ensureLegal({ api }); },
        quien: computed(() => ({ hijos: quienDe(menores.items, { textos, emailVerified: emailVerified() }), cargando: ! menores.loaded })),
        pantalla: computed(() => ({ fichas: fichasDe(s.h, { textos, hoy: hoyLocal() }), relaciones: relacionesDe(textos), errores: s.errores, aviso: s.aviso, descargo: s.h.descargo, firma: firma.value })),
        hijo,
        ficha: computed(() => ({ ...hijo.value, preguntar: s.preguntar, casilla: s.firmaCasilla, error: s.firmaError, quitando: menores.removingId === s.hijo })),
    };
}
