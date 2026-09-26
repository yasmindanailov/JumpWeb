/**
 * **LOS AJUSTES DE MI CUENTA, en marcha** (T5e de `docs/specs/isla-y-landing-nueva.md` §4.13, `DECISIONES #778`): qué se
 * pide al servidor, cuándo, y qué hace cada botón de «Ajustes» y de sus pasos. Lo que decide está en `ajustes.js`, puro y
 * probado.
 *
 *   · **Los datos son los del motor, usados SIN tocarlo** (como la compra y los hijos): el perfil (`stores/profile.js`),
 *     las credenciales (`credentials.js`), la privacidad (`privacy.js`), el descargo (`waiver.js`), el olvido
 *     (`auth.js`) y el cierre de sesión (`account/sign-out.js`). Son del carril del SPA; aquí se leen y se llaman.
 *   · **Cada plegable pide lo suyo al ABRIRSE**, una vez: Ajustes va plegado al final («nada esencial vive aquí») y
 *     quien no lo abre no paga `GET /me`, las identidades, el descargo ni los pedidos.
 *   · **Los plegables abiertos y lo escrito en «Tus datos» se conservan** al ir a un paso y volver: la flecha vuelve «al
 *     mismo punto», y un punto con el plegable cerrado ya no es el mismo.
 *   · Los formularios de los pasos (contraseña, correo, otras sesiones, desvincular, firmar, borrar) comparten UN estado
 *     (`f`, `errores`, `fallo`), que se vacía al entrar en cada uno: solo se ve uno a la vez, y una contraseña escrita en
 *     un paso no puede aparecer en otro.
 */
import { computed, reactive, watch } from 'vue';
import { api } from '../../sidebar/api.js';
import { t as texto, tp } from '../../sidebar/i18n.js';
import { useProfileStore } from '../../sidebar/stores/profile.js';
import { useCredentialsStore } from '../../sidebar/stores/credentials.js';
import { usePrivacyStore } from '../../sidebar/stores/privacy.js';
import { useWaiverStore } from '../../sidebar/stores/waiver.js';
import { useAuthStore } from '../../sidebar/stores/auth.js';
import { fieldError } from '../../sidebar/account/form-outcome.js';
import { signOut } from '../../sidebar/account/sign-out.js';
import { conVuelta } from '../../sidebar/reanudar.js';
import {
    correoDe, datosDe, descargoDe, googleDe, hayCambios, idiomasDe, interruptoresDe, recibosDe, reservaQueImpide,
    revisarCorreo, revisarDatosCuenta,
} from './ajustes.js';

const POR_PAGINA_RECIBOS = 10;

/** El formulario de un paso, vacío. */
const pasoVacio = () => ({ actual: '', nueva: '', correo: '', clave: '', entiendo: false, casilla: false });

/**
 * @param {{textos: object, props: object, locale: string, proxima: import('vue').ComputedRef, contexto: object,
 *          decir: (texto: string, tono?: string) => void}} deps
 */
export function useAjustesCuenta({ textos, props, locale, proxima, contexto, decir }) {
    const perfil = useProfileStore();
    const credenciales = useCredentialsStore();
    const privacidad = usePrivacyStore();
    const descargo = useWaiverStore();
    const auth = useAuthStore();
    const opciones = () => ({ api, messages: props.messages, auth: props.auth });
    const tx = (clave) => texto(textos, clave);

    const s = reactive({
        abiertos: [], d: datosDe(null), tocado: false, erroresDatos: {},
        f: pasoVacio(), errores: {}, fallo: '', enviado: false, interruptor: '',
        pedidos: null, pagina: 0, ultima: 1, cargandoRecibos: false,
    });

    // «Tus datos» se rellena con el perfil cuando llega, y otra vez tras guardar; lo que se está escribiendo no se pisa.
    watch(() => perfil.user, (user) => { if (user && ! s.tocado) s.d = datosDe(user); }, { immediate: true });

    async function cargarRecibos(siguiente = false) {
        if (s.cargandoRecibos || (! siguiente && s.pedidos !== null) || (siguiente && s.pagina >= s.ultima)) return;
        s.cargandoRecibos = true;

        try {
            const pagina = siguiente ? s.pagina + 1 : 1;
            const r = await api.get(`/me/orders?per_page=${POR_PAGINA_RECIBOS}&page=${pagina}`);

            if (r.ok) Object.assign(s, { pedidos: (siguiente ? s.pedidos : []).concat(r.data?.data ?? []), pagina, ultima: Number(r.data?.meta?.last_page ?? 1) });
            else if (! siguiente) s.pedidos = [];
        } finally {
            s.cargandoRecibos = false;
        }
    }

    /** Lo que pide cada plegable al abrirse (una vez: los stores del motor no repiten lo que ya tienen). */
    function cargarPlegable(id) {
        if (id === 'datos' || id === 'acceso' || id === 'privacidad') perfil.ensure({ api });
        if (id === 'acceso') credenciales.ensureIdentities({ api });
        if (id === 'privacidad') { descargo.ensureStatus({ api }); descargo.ensureLegal({ api }); }
        if (id === 'recibos') cargarRecibos();
    }

    function alternar(id) {
        const abierto = s.abiertos.includes(id);

        s.abiertos = abierto ? s.abiertos.filter((x) => x !== id) : [...s.abiertos, id];
        if (! abierto) cargarPlegable(id);
    }

    /** Abre un plegable desde fuera (la puerta de una zona del cajón, `#mi-cuenta/privacidad`). */
    function abrir(id) {
        if (! s.abiertos.includes(id)) s.abiertos = [...s.abiertos, id];
        cargarPlegable(id);
    }

    /** Al entrar en un paso: su formulario, vacío; lo que dijo el servidor la vez anterior, fuera. */
    function empezarPaso() {
        Object.assign(s, { f: pasoVacio(), errores: {}, fallo: '', enviado: false });
        credenciales.reset();
        privacidad.reset();
        descargo.reset();
        perfil.ensure({ api });
    }

    /** El veredicto de un formulario del motor, en el paso: los campos bajo su campo y el resto arriba. */
    function colocar(store, campos) {
        s.errores = Object.fromEntries(Object.entries(campos).map(([nuestro, suyo]) => [nuestro, fieldError(store.fields, suyo)]).filter(([, v]) => v));
        s.fallo = store.notice || (Object.keys(s.errores).length ? '' : texto(props.messages, 'errors.try_later'));

        return store.expired ? 'caducada' : 'error';
    }

    // ── Tus datos ──────────────────────────────────────────────────────────────────────────────────

    function cambiarDato(campo, valor) {
        s.d = { ...s.d, [campo]: valor };
        s.tocado = true;
        if (s.erroresDatos[campo]) s.erroresDatos = { ...s.erroresDatos, [campo]: '' };
    }

    /** «Guardar los cambios»: el nombre, el teléfono y el idioma; el correo es el de ahora (cambiarlo es su paso). */
    async function guardarDatos() {
        const falta = revisarDatosCuenta(s.d, textos);

        s.erroresDatos = falta;
        if (Object.keys(falta).length || ! perfil.user) return 'error';
        const ok = await perfil.apply({ name: s.d.nombre.trim(), phone: s.d.telefono.trim(), locale: s.d.idioma, email: perfil.user.email }, opciones());

        if (ok) {
            s.tocado = false;
            s.d = datosDe(perfil.user);
            // «Hola, Ana» sale del contexto de cuenta: con otro nombre, se relee.
            contexto.refresh({ api });
            decir(tx('mi_cuenta.ajustes.guardado'));

            return 'ok';
        }
        s.erroresDatos = { nombre: fieldError(perfil.fields, 'name'), telefono: fieldError(perfil.fields, 'phone'), idioma: fieldError(perfil.fields, 'locale') };
        if (perfil.notice) decir(perfil.notice, 'danger');

        return perfil.expired ? 'caducada' : 'error';
    }

    // ── Los pasos que piden la contraseña actual ───────────────────────────────────────────────────

    const faltaClave = (campo = 'clave') => (s.f[campo] ? {} : { [campo]: tx('compra.datos.errores.clave') });

    async function guardarClave() {
        const errores = { ...faltaClave('actual'), ...(s.f.nueva.length >= 8 ? {} : { nueva: tx('compra.datos.errores.contrasena') }) };

        Object.assign(s, { errores, fallo: '' });
        if (Object.keys(errores).length) return 'error';

        return (await credenciales.changePassword({ currentPassword: s.f.actual, password: s.f.nueva }, opciones()))
            ? 'ok' : colocar(credenciales, { actual: 'current_password', nueva: 'password' });
    }

    /** El enlace para crear una contraseña (quien entró con Google no tiene) o recuperarla: al correo de la cuenta. */
    async function enlaceClave() {
        if (! perfil.user?.email) return;
        auth.form.email = perfil.user.email;
        const r = await auth.requestPasswordLink(opciones());

        if (r?.sent) decir(tx('compra.entrar.olvido_texto'));
        else if (! r?.skipped) s.fallo = r?.errors?.fields?.email || r?.errors?.global || texto(props.messages, 'errors.try_later');
    }

    async function enviarCorreo() {
        const errores = revisarCorreo(s.f, { actual: perfil.user?.email ?? '', textos });

        Object.assign(s, { errores, fallo: '' });
        if (Object.keys(errores).length || ! perfil.user) return 'error';
        const g = datosDe(perfil.user);
        const ok = await perfil.apply({ name: g.nombre, phone: g.telefono, locale: g.idioma, email: s.f.correo.trim(), currentPassword: s.f.clave }, opciones());

        if (! ok) return colocar(perfil, { correo: 'email', clave: 'current_password' });
        Object.assign(s, { f: pasoVacio(), enviado: true });

        return 'ok';
    }

    async function reenviarCorreo() {
        if (await perfil.resendPending(opciones())) decir(tx('mi_cuenta.correo.reenviado'));
        else s.fallo = perfil.notice || texto(props.messages, 'errors.try_later');
    }

    async function cancelarCorreo() {
        if (await perfil.cancelPending(opciones())) { s.enviado = false; decir(tx('mi_cuenta.correo.cancelado')); } else s.fallo = perfil.notice || texto(props.messages, 'errors.try_later');
    }

    async function cerrarOtras() {
        Object.assign(s, { errores: faltaClave(), fallo: '' });
        if (s.errores.clave) return 'error';

        return (await credenciales.revokeOtherSessions({ currentPassword: s.f.clave }, opciones())) ? 'ok' : colocar(credenciales, { clave: 'current_password' });
    }

    async function desvincular() {
        Object.assign(s, { errores: faltaClave(), fallo: '' });
        if (s.errores.clave) return 'error';

        return (await credenciales.unlinkIdentity('google', { currentPassword: s.f.clave }, opciones())) ? 'ok' : colocar(credenciales, { clave: 'current_password' });
    }

    /** «Vincular Google»: la ida la hace el SERVIDOR y vuelve a esta página, a Mi cuenta en «Acceso» (`next`, `SEC-08`). */
    function vincular() {
        const url = props.urls?.google_link;

        if (url) window.location.assign(conVuelta(url, `${window.location.pathname}${window.location.search}#mi-cuenta/acceso`));
    }

    /**
     * «Borrar mi cuenta». ⚠️ Al salir bien la sesión YA NO VALE (el servidor revoca todas las credenciales): se sale a la
     * portada, como el cajón y la web; todo lo que hay pintado alrededor habla de una cuenta que ya no existe.
     */
    async function borrar() {
        if (! s.f.entiendo) return 'error';
        Object.assign(s, { errores: faltaClave(), fallo: '' });
        if (s.errores.clave) return 'error';

        if (await privacidad.deleteAccount({ currentPassword: s.f.clave }, opciones())) {
            window.location.assign(props.urls?.home || '/');

            return 'ok';
        }

        return colocar(privacidad, { clave: 'current_password' });
    }

    // ── Privacidad ─────────────────────────────────────────────────────────────────────────────────

    const INTERRUPTORES = { novedades: 'setMarketing', analitica: 'setAnalytics', encuestas: 'setSurveys' };

    /** Un interruptor se aplica al soltarlo, sin «Guardar» y sin contraseña: retirar tiene que ser tan fácil como dar. */
    async function interruptor(nombre, valor) {
        if (s.interruptor || ! INTERRUPTORES[nombre]) return;
        s.interruptor = nombre;

        try {
            const ok = await privacidad[INTERRUPTORES[nombre]](valor, { api });

            decir(ok ? tx('mi_cuenta.ajustes.guardado') : tx('mi_cuenta.ajustes.no_guardado'), ok ? 'success' : 'danger');
        } finally {
            s.interruptor = '';
        }
    }

    /** «Descargar mis datos»: el documento de portabilidad, como fichero (el del cajón: `saveExport`). */
    async function descargarDatos() {
        if (privacidad.busy) return;
        if (await privacidad.exportData(opciones())) decir(tp(textos, 'mi_cuenta.ajustes.descargado', { que: tx('mi_cuenta.ajustes.tus_datos') }));
        else decir(privacidad.notice || texto(props.messages, 'errors.try_later'), 'danger');
    }

    /** Firmar (o volver a firmar) SU descargo: el texto que se enseña, y solo ese (`#175`). */
    async function firmar() {
        if (! s.f.casilla) {
            s.errores = { casilla: tx('mi_cuenta.descargo.errores.casilla') };

            return 'error';
        }
        Object.assign(s, { errores: {}, fallo: '' });

        if (await descargo.accept(opciones())) {
            // El aviso de arriba («tienes pendiente el descargo») lo lee el contexto de cuenta.
            contexto.refresh({ api });

            return 'ok';
        }
        if (descargo.reread) {
            Object.assign(s.f, { casilla: false });
            s.errores = { casilla: tx('mi_cuenta.hijos.errores.descargo_nuevo') };

            return 'error';
        }

        return colocar(descargo, {});
    }

    /** Cierra la sesión (el cierre del motor navega a la portada si el servidor lo confirma). Devuelve si se cerró. */
    async function salir() {
        const ok = await signOut({ api, urls: props.urls });

        if (! ok) decir(texto(props.messages, 'errors.try_later'), 'danger');

        return ok;
    }

    // ── Lo que se pinta ────────────────────────────────────────────────────────────────────────────

    const bloque = computed(() => {
        const user = perfil.user;

        return {
            abiertos: s.abiertos,
            cargandoPerfil: ! user,
            datos: { ...s.d, errores: s.erroresDatos, cambiado: hayCambios(s.d, user), guardando: perfil.busy && s.tocado },
            idiomas: idiomasDe(props.locales),
            correo: user ? correoDe(user, { textos, ahora: Date.now() }) : null,
            google: googleDe(credenciales.identities, { puedeVincular: Boolean(props.urls?.google_link) }),
            interruptores: user ? interruptoresDe(user) : null,
            cambiando: s.interruptor,
            descargo: descargoDe(descargo.status, { textos, motor: props.account }),
            exportando: privacidad.busy,
            recibos: s.pedidos === null ? null : recibosDe(s.pedidos, { locale, textos, messages: props.messages }),
            hayMasRecibos: s.pagina < s.ultima,
            cargandoRecibos: s.cargandoRecibos,
        };
    });

    const paso = computed(() => ({
        f: s.f, errores: s.errores, fallo: s.fallo, enviado: s.enviado,
        correo: perfil.user ? correoDe(perfil.user, { textos, ahora: Date.now() }) : null,
        google: googleDe(credenciales.identities, { puedeVincular: false }),
        reserva: reservaQueImpide(proxima.value, { locale, textos }),
        ocupado: credenciales.busy || perfil.busy || privacidad.busy || descargo.busy,
        documento: descargo.document,
    }));

    return {
        s, bloque, paso, alternar, abrir, empezarPaso, cambiarDato, guardarDatos, guardarClave, enlaceClave, enviarCorreo,
        reenviarCorreo, cancelarCorreo, cerrarOtras, desvincular, vincular, borrar, interruptor, descargarDatos, firmar, salir,
        masRecibos: () => cargarRecibos(true),
        cambiarPaso: (campo, valor) => { s.f[campo] = valor; if (s.errores[campo]) s.errores = { ...s.errores, [campo]: '' }; },
    };
}
