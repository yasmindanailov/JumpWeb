/**
 * **LOS AJUSTES DE MI CUENTA, en marcha** (T5e de `docs/specs/isla-y-landing-nueva.md` §4.13, `DECISIONES #778`): qué se
 * pide al servidor, cuándo, y qué hace cada botón de «Ajustes» y de sus pasos. Lo que decide está en `ajustes.js`, puro y
 * probado.
 *
 *   · **Los datos son los del motor, usados SIN tocarlo** (como la compra y los hijos): el perfil (`stores/profile.js`),
 *     las credenciales (`credentials.js`), la privacidad (`privacy.js`), el descargo (`waiver.js`) y el cierre de
 *     sesión (`account/sign-out.js`). Son del carril del SPA; aquí se leen y se llaman.
 *   · **Cada plegable pide lo suyo al ABRIRSE**, una vez: Ajustes va plegado al final («nada esencial vive aquí») y
 *     quien no lo abre no paga `GET /me`, las identidades, el descargo ni los pedidos.
 *   · **Los plegables abiertos y lo escrito en «Tus datos» se conservan** al ir a un paso y volver: la flecha vuelve «al
 *     mismo punto», y un punto con el plegable cerrado ya no es el mismo.
 *   · Los formularios de los pasos (correo, otras sesiones, desvincular, firmar, borrar) comparten UN estado (`f`,
 *     `errores`, `fallo`, `codigo`), que se vacía al entrar en cada uno: solo se ve uno a la vez, y un código pedido en un
 *     paso no puede aparecer en otro.
 *   · ▶ **Lo sensible se confirma con un CÓDIGO al correo** (A3b de `specs/acceso-con-codigo.md` §4.10, `#857`): el primer
 *     toque de la acción manda el código (`POST /me/confirm-code`) y enseña su campo; el segundo lo usa. Viaja por los
 *     GUARDIANES públicos de los stores del motor (`run`, `runForm`: limpian, llaman y colocan el veredicto) con el cuerpo
 *     de la isla: los stores siguen sin tocarse (desde la A4b del cajón, `#813`, también mandan el código).
 */
import { computed, reactive, watch } from 'vue';
import { api } from '../../sidebar/api.js';
import { t as texto, tp } from '../../sidebar/i18n.js';
import { useProfileStore, profileBody } from '../../sidebar/stores/profile.js';
import { useCredentialsStore } from '../../sidebar/stores/credentials.js';
import { usePrivacyStore } from '../../sidebar/stores/privacy.js';
import { useWaiverStore } from '../../sidebar/stores/waiver.js';
import { fieldError } from '../../sidebar/account/form-outcome.js';
import { runForm } from '../../sidebar/account/form-run.js';
import { signOut } from '../../sidebar/account/sign-out.js';
import { conVuelta } from '../../sidebar/reanudar.js';
import { protegido } from './seguro.js';
import {
    correoDe, datosDe, descargoDe, googleDe, hayCambios, idiomasDe, interruptoresDe, recibosDe, reservaQueImpide,
    revisarCodigo, revisarCorreo, revisarDatosCuenta,
} from './ajustes.js';
import { nacimientoDeAlta } from '../compra/datos.js';
import { erroresDelCodigo } from '../compra/acceso.js';

const POR_PAGINA_RECIBOS = 10;

/** El formulario de un paso, vacío. */
const pasoVacio = () => ({ correo: '', codigo: '', entiendo: false, casilla: false });

/** El código de confirmar de un paso: sin pedir. `reenvios`, cuántos se han pedido OTRA vez (la pista dice «otro»). */
const codigoVacio = () => ({ enviado: false, reenvios: 0 });

/**
 * @param {{textos: object, props: object, locale: string, proxima: import('vue').ComputedRef, contexto: object,
 *          decir: (texto: string, tono?: string) => void}} deps
 */
export function useAjustesCuenta({ textos, props, locale, proxima, contexto, decir }) {
    const perfil = useProfileStore();
    const credenciales = useCredentialsStore();
    const privacidad = usePrivacyStore();
    const descargo = useWaiverStore();
    const opciones = () => ({ api, messages: props.messages, auth: props.auth });
    const tx = (clave) => texto(textos, clave);

    const s = reactive({
        abiertos: [], d: datosDe(null), tocado: false, erroresDatos: {},
        f: pasoVacio(), errores: {}, fallo: '', codigo: codigoVacio(), interruptor: '',
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
        Object.assign(s, { f: pasoVacio(), errores: {}, fallo: '', codigo: codigoVacio() });
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

    /**
     * «Guardar los cambios»: el nombre, el teléfono, la fecha de nacimiento (vacía, la BORRA: `profile.apply` manda
     * `null`) y el idioma; el correo es el de ahora (cambiarlo es su paso).
     */
    async function guardarDatos() {
        const falta = revisarDatosCuenta(s.d, textos);

        s.erroresDatos = falta;
        if (Object.keys(falta).length || ! perfil.user) return 'error';
        const ok = await perfil.apply({
            name: s.d.nombre.trim(), phone: s.d.telefono.trim(), born_on: nacimientoDeAlta(s.d.nacimiento) ?? '', locale: s.d.idioma,
            email: perfil.user.email,
        }, opciones());

        if (ok) {
            s.tocado = false;
            s.d = datosDe(perfil.user);
            // «Hola, Ana» sale del contexto de cuenta: con otro nombre, se relee.
            contexto.refresh({ api });
            decir(tx('mi_cuenta.ajustes.guardado'));

            return 'ok';
        }
        s.erroresDatos = {
            nombre: fieldError(perfil.fields, 'name'), telefono: fieldError(perfil.fields, 'phone'),
            nacimiento: fieldError(perfil.fields, 'born_on'), idioma: fieldError(perfil.fields, 'locale'),
        };
        if (perfil.notice) decir(perfil.notice, 'danger');

        return perfil.expired ? 'caducada' : 'error';
    }

    // ── Lo que se confirma con un código al correo (A3b, `#857`) ───────────────────────────────────

    /**
     * Pide el código de confirmar `accion` al correo de la cuenta. Con él de camino, el paso enseña su campo (`enviado`).
     * El tope (uno por minuto) enseña el campo igual —hay uno recién enviado— y dice cuánto esperar bajo él.
     *
     * @returns {Promise<'enviado'|'caducada'|'error'>}
     */
    async function pedirCodigo(accion, { otro = false } = {}) {
        const r = await api.post('/me/confirm-code', { action: accion });

        if (r.status === 401) return 'caducada';
        if (r.ok || r.error?.code === 'too_many_requests') {
            s.codigo = { enviado: true, reenvios: s.codigo.reenvios + (r.ok && otro ? 1 : 0) };
            s.f.codigo = '';
            // El tope: el campo sale igual (hay uno recién enviado) y, bajo él, cuánto esperar para pedir otro.
            s.errores = r.ok ? {} : { codigo: erroresDelCodigo(r, textos).aviso };

            return r.ok ? 'enviado' : 'error';
        }
        s.fallo = erroresDelCodigo(r, textos, texto(props.messages, 'errors.try_later')).aviso;

        return 'error';
    }

    /**
     * Confirmar con el código: sin él pedido, lo pide; con él escrito, hace la acción con él (`hacer(codigo)`). Lo que
     * falta se dice antes de preguntar.
     */
    async function conCodigo(accion, hacer) {
        Object.assign(s, { fallo: '' });
        if (! s.codigo.enviado) return pedirCodigo(accion);

        s.errores = revisarCodigo(s.f, { enviado: true, textos });
        if (s.errores.codigo) return 'error';

        return hacer(s.f.codigo.trim());
    }

    /**
     * El «no» de una acción con código, en el paso: el del código bajo su campo —y sus casillas, vacías, para escribir el
     * bueno: el diseño, como el cajón—; el resto, arriba.
     */
    function colocarConCodigo(store, campos = {}) {
        const r = colocar(store, { codigo: 'code', ...campos });

        if (s.errores.codigo) s.f.codigo = '';

        return r;
    }

    /**
     * «Enviarme el código» y después «Enviar el código al correo nuevo»: el código de CONFIRMAR va al correo de la cuenta,
     * y con él el servidor deja pendiente el nuevo y le manda SU código (`PATCH /me`, A2). El nuevo se escribe en la misma
     * pantalla ({@see confirmarCorreo}).
     */
    async function enviarCorreo() {
        const errores = revisarCorreo(s.f, { actual: perfil.user?.email ?? '', textos });

        Object.assign(s, { errores, fallo: '' });
        if (Object.keys(errores).length || ! perfil.user) return 'error';

        return conCodigo('change_email', async (codigo) => {
            const g = datosDe(perfil.user);
            const cuerpo = { ...profileBody({ name: g.nombre, phone: g.telefono, locale: g.idioma, email: s.f.correo.trim() }), code: codigo };

            if (! await perfil.run(() => api.patch('/me', cuerpo), opciones())) return colocarConCodigo(perfil, { correo: 'email' });
            Object.assign(s, { f: pasoVacio(), codigo: codigoVacio(), errores: {} });

            return 'pendiente';
        });
    }

    /** «Confirmar el correo»: el código que llegó al buzón NUEVO (`POST /me/pending-email/confirm`, A2b). */
    async function confirmarCorreo() {
        s.errores = revisarCodigo(s.f, { enviado: true, textos });
        s.fallo = '';
        if (s.errores.codigo) return 'error';

        if (await perfil.run(() => api.post('/me/pending-email/confirm', { code: s.f.codigo.trim() }), opciones())) {
            // «Hola, Ana» y el aviso de confirmar el correo salen del contexto de cuenta: con otro correo, se relee.
            contexto.refresh({ api });

            return 'ok';
        }
        const r = colocarConCodigo(perfil);
        // Otra cuenta tomó el correo mientras tanto: su «no» no tiene campo aquí, va arriba.
        const ocupado = fieldError(perfil.fields, 'email');

        if (ocupado) s.fallo = ocupado;

        return r;
    }

    /** «Pedir otro código» del correo nuevo: el servidor manda otro (y el enlace de siempre) al buzón nuevo. */
    async function reenviarCorreo() {
        if (await perfil.resendPending(opciones())) {
            s.codigo = { enviado: true, reenvios: s.codigo.reenvios + 1 };
            Object.assign(s, { errores: {}, fallo: '' });
            s.f.codigo = '';
        } else {
            s.fallo = perfil.notice || texto(props.messages, 'errors.try_later');
        }
    }

    async function cancelarCorreo() {
        if (await perfil.cancelPending(opciones())) {
            Object.assign(s, { f: pasoVacio(), codigo: codigoVacio(), errores: {} });
            decir(tx('mi_cuenta.correo.cancelado'));
        } else {
            s.fallo = perfil.notice || texto(props.messages, 'errors.try_later');
        }
    }

    /** «Pedir otro código» de un paso que confirma: otro código de ESA acción; el nuevo anula el anterior. */
    const ACCION_DE_PASO = { correo: 'change_email', 'otras-sesiones': 'close_sessions', desvincular: 'unlink_google', borrar: 'delete_account' };

    async function otroCodigo(vista) {
        const accion = ACCION_DE_PASO[vista];

        if (vista === 'correo' && perfil.user?.pending_email) return reenviarCorreo();
        if (accion) await pedirCodigo(accion, { otro: true });

        return null;
    }

    async function cerrarOtras() {
        return conCodigo('close_sessions', async (codigo) => (
            await credenciales.run(() => api.post('/me/sessions/revoke-others', { code: codigo }), opciones()) ? 'ok' : colocarConCodigo(credenciales)
        ));
    }

    async function desvincular() {
        return conCodigo('unlink_google', async (codigo) => {
            if (! await credenciales.run(() => api.delete('/me/identities/google', { code: codigo }), opciones())) return colocarConCodigo(credenciales);
            // La lista, releída (como `unlinkIdentity` del motor): la foto vieja enseñaría el vínculo que se acaba de quitar.
            credenciales.$patch({ identities: null });
            await credenciales.ensureIdentities({ api });

            return 'ok';
        });
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

        return conCodigo('delete_account', async (codigo) => {
            // El guardián del motor, con el cuerpo de la isla: el código (como `deleteAccount` del store desde la A4b).
            if (await runForm(privacidad, () => api.delete('/me', { code: codigo }), opciones())) {
                window.location.assign(props.urls?.home || '/');

                return 'ok';
            }

            return colocarConCodigo(privacidad);
        });
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

    // Protegido DENTRO (T5f, `seguro.js`): si no se puede componer, el bloque deja su hueco y Mi cuenta sigue.
    const bloque = computed(() => protegido('ajustes', () => {
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
    }));

    const paso = computed(() => ({
        f: s.f, errores: s.errores, fallo: s.fallo,
        // El código de confirmar: si ya va de camino, cuántos «otro», y a qué correo (el de la cuenta).
        codigo: { ...s.codigo, correo: String(perfil.user?.email ?? '') },
        correo: perfil.user ? correoDe(perfil.user, { textos, ahora: Date.now() }) : null,
        google: googleDe(credenciales.identities, { puedeVincular: false }),
        reserva: reservaQueImpide(proxima.value, { locale, textos }),
        ocupado: credenciales.busy || perfil.busy || privacidad.busy || descargo.busy,
        documento: descargo.document,
    }));

    return {
        s, bloque, paso, alternar, abrir, empezarPaso, cambiarDato, guardarDatos, enviarCorreo, confirmarCorreo, otroCodigo,
        reenviarCorreo, cancelarCorreo, cerrarOtras, desvincular, vincular, borrar, interruptor, descargarDatos, firmar, salir,
        masRecibos: () => cargarRecibos(true),
        cambiarPaso: (campo, valor) => { s.f[campo] = valor; if (s.errores[campo]) s.errores = { ...s.errores, [campo]: '' }; },
    };
}
