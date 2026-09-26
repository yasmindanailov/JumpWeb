/**
 * **MI CUENTA EN LA ISLA, en marcha** (T5a de `docs/specs/isla-y-landing-nueva.md` §4.13, `DECISIONES #773`): qué vista
 * se ve, qué se pide al servidor y qué hace cada botón. Lo que DECIDE (la vista de cada apertura, la banda, la flecha)
 * está en `vista.js`, puro y probado; aquí solo se conecta con el mundo.
 *
 *   · **La capa** la abre el controlador del paquete (`openAccount`, la puerta `/mi-cuenta`, el enlace `#mi-cuenta`):
 *     aquí se OYE (`useSuperficie({ cuenta: true })`) y se sitúa en la vista de su zona (`vistaDeApertura`).
 *   · **Los datos** son los del motor, que se usan SIN tocarlos (el carné, el contexto de cuenta, el alta, el descargo):
 *     los stores y sus módulos planos son del carril del SPA (`sidebar/**`); la isla solo los lee y los llama.
 *   · **Entrar, crear la cuenta o volver de Google abre la sesión RECARGANDO esta página en Mi cuenta**
 *     (`#mi-cuenta`). Lo obliga una medida: los textos de Mi cuenta viajan solo con sesión (`SidebarBoot::personal()`;
 *     mandarlos a todo visitante eran ~5 KB en cada página, `PERF-02`), y es el mismo camino que el área del cajón
 *     (`account/after-auth.js`): la contraseña no sobrevive en memoria y la página entera sabe ya quién es.
 */
import { computed, effectScope, inject, nextTick, onScopeDispose, reactive, shallowRef, watch } from 'vue';
import { TEXTOS_ISLA } from '../../sidebar/carcasa.js';
import { cajonHost } from '../../sidebar/host-bridge.js';
import { api } from '../../sidebar/api.js';
import { t, tp } from '../../sidebar/i18n.js';
import { useAccountStore } from '../../sidebar/stores/account.js';
import { useAccountContextStore } from '../../sidebar/stores/accountContext.js';
import { useCardStore } from '../../sidebar/stores/card.js';
import { useAuthStore } from '../../sidebar/stores/auth.js';
import { useWaiverStore } from '../../sidebar/stores/waiver.js';
import { cardImageUrl, cardIsDrawable, tokenGroups } from '../../sidebar/account/card.js';
import { emptyGoogleScreen, loadGoogleScreen, submitGoogleScreen } from '../../sidebar/account/google.js';
import { landOnAccount } from '../../sidebar/account/after-auth.js';
import { conVuelta } from '../../sidebar/reanudar.js';
import { enlaceDeCuenta } from '../../cajon/enlace-cuenta.js';
import { tomarAvisoDelServidor } from '../pagina/aviso-servidor.js';
import { useSuperficie } from '../compra/useSuperficie.js';
import {
    cuentaQueYaExiste, datosVacios, entradaVacia, errorDeEntrar, erroresDelServidor, firmaPendiente, formularioDeAlta,
    revisarDatos,
} from '../compra/datos.js';
import { VISTA, ckDeCuenta, lineaProxima, vistaDeApertura } from './vista.js';
import { avisoDeAnalitica, avisoDeCuenta } from './avisos.js';
import { tituloDe } from './reservas.js';
import { useReservasCuenta } from './useReservasCuenta.js';
import { useHijosCuenta } from './useHijosCuenta.js';

/** Los pasos de Ajustes (T5e): al entrar en uno, su formulario empieza vacío. */
const PASOS_DE_AJUSTES = [VISTA.CLAVE, VISTA.CORREO, VISTA.OTRAS, VISTA.DESVINCULAR, VISTA.FIRMA, VISTA.BORRAR];

/** La «G» del botón de Google: el MISMO fichero que la compra de la isla y el botón oficial del cajón (`#695`). */
const MARCA_GOOGLE = '/images/providers/google.svg';

export function useSeccionCuenta(props) {
    const textos = inject(TEXTOS_ISLA, {});
    const { abierta, cerrar: cerrarSuperficie } = useSuperficie({ cuenta: true });
    const zonas = useAccountStore();
    const contexto = useAccountContextStore();
    const carne = useCardStore();
    const authStore = useAuthStore();
    const waiverStore = useWaiverStore();
    const locale = document.documentElement.lang || 'es';
    const opciones = () => ({ api, messages: props.messages, auth: props.auth });
    /** Un texto del motor (el grupo `account`): los de las pantallas que el mockup no dibuja (`#773`·d). */
    const delMotor = (clave) => t(props.account, clave);

    /**
     * `vista` y `subpaso` (el olvido de Entrar, el descargo de Crear); `dir`, la entrada de la vista; `desde` y
     * `qrDesde`, a dónde vuelve la flecha; `ocupado`, lo que espera al servidor; `aviso`, la confirmación de arriba
     * (se queda hasta salir de su vista: WCAG 2.2.1); `renovar`, la pregunta de «Renovar mi QR»; `ent`, «Entra»; `f`,
     * «Crea tu cuenta», con sus `errores` y su `avisoAlta`; `token`, el del anti-bot; `google`, el alta que vuelve.
     */
    const e = reactive({
        vista: VISTA.INICIO, subpaso: '', dir: null, desde: null, qrDesde: null, ocupado: null, aviso: null,
        renovar: false, ent: entradaVacia(), f: datosVacios(), errores: {}, avisoAlta: '', token: '',
        google: emptyGoogleScreen(), rSel: null, cambiarDesdeReserva: false,
    });
    let scroll = 0;
    // Las reservas (T5b): la próxima, las otras y el historial, y el contacto del parque para «Cambiar o cancelar».
    const reservas = useReservasCuenta({ textos, locale });
    const proxima = computed(() => reservas.listas.value.proxima);
    const hoy = computed(() => proxima.value?.reservation?.today === true);
    // Los hijos (T5d): Quién viene contigo, Añade a tus hijos y la ficha de cada uno.
    const hijos = useHijosCuenta({ textos, props, emailVerified: () => contexto.context?.email_verified });
    // Los Ajustes (T5e): sus cuatro plegables, sus pasos y «Cerrar sesión». Viajan en su trozo CON su lógica: van plegados
    // al final («nada esencial vive aquí») y Mi cuenta pinta sin ellos (medido: dentro eran +9,3 KiB de lógica y +15,4 del
    // bloque). Se traen al pintar el inicio, dentro de un `effectScope`: sus `watch` viven y mueren con esta sección. Lo
    // que dicen, arriba (`decir`, más abajo). Sin red, `null`, y el siguiente intento vuelve a pedirlo.
    const alcance = effectScope();
    onScopeDispose(() => alcance.stop());
    const ajustes = shallowRef(null);
    let trayendoAjustes = null;

    function conAjustes() {
        trayendoAjustes ??= import('./useAjustesCuenta.js')
            .then(({ useAjustesCuenta }) => {
                ajustes.value ??= alcance.run(() => useAjustesCuenta({ textos, props, locale, proxima, contexto, decir: (texto, tono) => decir(texto, tono) }));

                return ajustes.value;
            })
            .catch(() => { trayendoAjustes = null; return null; });

        return trayendoAjustes;
    }

    const caja = () => document.querySelector('[data-isla-scroll]');
    const enfocarError = () => nextTick(() => caja()?.querySelector('[aria-invalid="true"]')?.focus());

    function cargar(vista) {
        if (vista === VISTA.INICIO || vista === VISTA.QR) carne.ensure({ api });
        // Las reservas, al entrar; con la de HOY, la ruta al parque para «Cómo llegar» de Tu QR (situación 14); y «Antes de
        // venir» de la próxima (T5c).
        if (vista === VISTA.INICIO || vista === VISTA.QR) {
            reservas.cargar().then(() => {
                if (hoy.value) reservas.cargarSitio();
                reservas.cargarAntes(proxima.value?.reservation?.id);
            });
        }
        if (vista === VISTA.CAMBIAR) reservas.cargarSitio();
        if (vista === VISTA.INICIO) { hijos.cargar(); conAjustes(); }
        if (vista === VISTA.HIJOS) hijos.empezar();
        if (vista === VISTA.CREAR || vista === VISTA.ALTA_GOOGLE) waiverStore.ensureLegal();
        // Tu descargo (T5e): el texto que se firma y el estado, también si se llega sin haber abierto «Privacidad».
        if (vista === VISTA.FIRMA) { waiverStore.ensureStatus({ api }); waiverStore.ensureLegal({ api }); }
        if (PASOS_DE_AJUSTES.includes(vista)) conAjustes().then((aj) => aj?.empezarPaso());
        if (vista === VISTA.ALTA_GOOGLE) {
            loadGoogleScreen({ api }).then((pantalla) => {
                e.google = pantalla;
                // El nombre se copia solo si no hay nada escrito (quien vuelve de un 409 ya corrigió el suyo).
                if (pantalla.pending !== null && ! authStore.form.name) authStore.form.name = pantalla.pending.name;
            });
        }
    }

    /**
     * Lleva el scroll de la capa a un bloque (`#mi-cuenta/antes`), cuando ya está pintado. Un bloque que depende de una
     * respuesta (Antes de venir, la próxima) aún no existe al abrir: se espera a que aparezca, con un tope de dos segundos.
     */
    function irAlBloque(bloque, intentos = 20) {
        nextTick(() => setTimeout(() => {
            const c = caja();
            const el = bloque ? document.getElementById(bloque) : null;

            if (c && el) c.scrollTop = el.getBoundingClientRect().top - c.getBoundingClientRect().top + c.scrollTop - 12;
            else if (bloque && intentos > 0) irAlBloque(bloque, intentos - 1);
        }, intentos === 20 ? 60 : 100));
    }

    /**
     * La acción de una tarea: la que resuelve una pantalla de la cuenta («Añade a tus hijos», `via: account`) se abre aquí
     * mismo; WhatsApp, aparte (la aplicación, en un móvil); una página de la web, en esta pestaña.
     */
    function hacerTarea(accion) {
        if (! accion?.url) return;
        if (accion.via === 'account') a(VISTA.HIJOS);
        else if (accion.via === 'whatsapp') window.open(accion.url, '_blank', 'noopener');
        else window.location.assign(accion.url);
    }

    /** Al abrirse, la vista de su zona: la que pidió la apertura (aún sin consumir) o la ya aplicada al motor. */
    function situar(zona) {
        const host = cajonHost();
        const { vista, bloque, plegable } = vistaDeApertura(zona, { sesion: contexto.identified, bloque: enlaceDeCuenta(window.location.hash)?.bloque ?? '' });

        // Un plegable de Ajustes (la zona del cajón que lo era, `#mi-cuenta/acceso`): abierto, y la capa baja a él.
        if (plegable) conAjustes().then((aj) => aj?.abrir(plegable));

        // El aviso que dejó el servidor al volver a esta página (T5e·2, `#779`: la vuelta de Google al entrar o al vincular,
        // un correo…), la primera vez que se abre Mi cuenta: arriba, con su tono, y hasta salir de la vista.
        const delServidor = tomarAvisoDelServidor(document);

        Object.assign(e, {
            vista, subpaso: '', dir: null, ocupado: null, renovar: false, errores: {}, avisoAlta: '',
            aviso: delServidor ? { ...delServidor, en: vista } : null,
            desde: host?.cuentaDesde ?? null, qrDesde: vista === VISTA.QR ? (host?.cuentaDesde ?? 'fuera') : null,
            ent: entradaVacia(), f: datosVacios(), rSel: null, cambiarDesdeReserva: false,
        });
        cargar(vista);
        if (bloque) irAlBloque(bloque);
    }

    watch(abierta, (dentro) => { if (dentro) situar(cajonHost()?.accountZone || zonas.zone); }, { immediate: true });
    watch(() => zonas.zone, (zona) => { if (abierta.value) situar(zona); });
    // Una confirmación se va al salir de la vista en la que se dijo.
    watch(() => e.vista, (vista) => { if (e.aviso && e.aviso.en !== vista) e.aviso = null; });
    // La sesión murió con la capa abierta (el carné responde 401): lo que se ve pasa a ser Entrar.
    watch(() => carne.expired, (caducada) => { if (caducada && abierta.value) { contexto.refresh({ api }); Object.assign(e, { vista: VISTA.ENTRAR, subpaso: '', dir: null }); } });

    /** La confirmación de arriba; con `tono = 'danger'`, lo que no salió (T5e). Se queda hasta salir de su vista. */
    const decir = (texto, tono = 'success') => { e.aviso = { texto, en: e.vista, tono }; };

    // ── Los avisos de la cuenta (T5e·2) ──────────────────────────────────────────────────────────────

    /** El de la cuenta (`avisos.js`): confirmar el correo, firmar su descargo o el de sus hijos. Uno, y en su orden. */
    const avisoCuenta = computed(() => avisoDeCuenta(contexto.context, { textos, reenvio: { segundos: authStore.resendSeconds, quedan: authStore.resendsLeft } }));
    // El cupo del reenvío se arma UNA vez, cuando hay que confirmar el correo (la misma puerta que el índice del cajón).
    watch(() => avisoCuenta.value?.tipo, (tipo) => { if (tipo === 'verificar') authStore.allowVerificationResend(); }, { immediate: true });

    /** «Reenviar el correo»: el reenvío del motor, que ya sabe si toca (espera y cupo) y a qué correo. */
    async function reenviarVerificacion() {
        const r = await authStore.resendVerification({ api });

        if (r?.ok) decir(t(textos, 'mi_cuenta.avisos.reenviado'));
        else if (! r?.skipped) decir(t(props.messages, 'errors.try_later'), 'danger');
    }

    /** El enlace del aviso de la cuenta: reenviar, firmar SU descargo (su paso) o ir a sus hijos. */
    function hacerAviso(hace) {
        if (hace === 'reenviar') reenviarVerificacion();
        else if (hace === 'firmar') a(VISTA.FIRMA);
        else if (hace === 'hijos') irAlBloque('quien');
    }

    /**
     * El aviso de la analítica: «Entendido» lo despide (el servidor lo confirma antes de quitarlo) y «Privacidad» también
     * —quien va a donde se retira ya lo ha leído, como en el cajón— y abre ese plegable de Ajustes.
     */
    function hacerAnalitica(que) {
        contexto.dismissAnalyticsNotice({ api });
        if (que !== 'privacidad') return;
        conAjustes().then((aj) => aj?.abrir('privacidad'));
        irAlBloque('ajustes');
    }

    // ── Moverse dentro de la capa ────────────────────────────────────────────────────────────────────

    /**
     * A una vista, recordando el punto de la LISTA para volver a él (la flecha vuelve «al mismo punto del scroll»). Solo
     * al salir del inicio: de «Tu reserva» a «Cambiar o cancelar» no se pisa el punto al que volverá la lista.
     */
    function a(vista, extra = {}) {
        if (e.vista === VISTA.INICIO) scroll = caja()?.scrollTop ?? 0;
        Object.assign(e, { vista, subpaso: '', dir: 'fwd', renovar: false, ...extra });
        cargar(vista);
    }

    function aInicio() {
        Object.assign(e, { vista: VISTA.INICIO, subpaso: '', dir: 'back', renovar: false, rSel: null });
        nextTick(() => setTimeout(() => { const c = caja(); if (c) c.scrollTop = scroll; }, 60));
    }

    /** La tarjeta de «Cambiar o cancelar»: la de la reserva abierta o, desde el inicio, la próxima. */
    const tarjetaCambiar = computed(() => (e.cambiarDesdeReserva ? reservas.buscar(e.rSel) : proxima.value));
    const cambiarVista = computed(() => (tarjetaCambiar.value ? reservas.cambiar(tarjetaCambiar.value) : null));

    /** «Escribirnos por WhatsApp»: el mensaje ya escrito, en otra pestaña (o en la aplicación, en un móvil). */
    function escribir() {
        if (cambiarVista.value?.whatsapp) window.open(cambiarVista.value.whatsapp, '_blank', 'noopener');
    }

    /** La X: cierra la capa sin perder nada. El enlace que la abrió se va con ella: recargar no la reabre. */
    function cerrar() {
        cerrarSuperficie();
        if (enlaceDeCuenta(window.location.hash)) {
            try { window.history.replaceState(window.history.state, '', `${window.location.pathname}${window.location.search}`); } catch { /* sin historial */ }
        }
    }

    /** Abierta desde el menú de la isla, la flecha vuelve a él: se cierra la capa y la isla de la página lo abre. */
    function alMenu() {
        cerrar();
        setTimeout(() => window.dispatchEvent(new CustomEvent('isla:abrir', { detail: { panel: 'menu' } })), 30);
    }

    // ── Con sesión: Tu QR ────────────────────────────────────────────────────────────────────────────

    async function renovarQr() {
        if (e.ocupado) return;
        e.ocupado = 'renovar';

        try {
            if (await carne.rotate(opciones())) decir(t(textos, 'mi_cuenta.qr.renovado'));
        } finally {
            Object.assign(e, { ocupado: null, renovar: false });
        }
    }

    // ── Con sesión: los hijos (T5d) ──────────────────────────────────────────────────────────────────

    /** «Antes de venir» de la próxima, otra vez: «Añade a tus hijos» pasa a hecha en cuanto hay uno. */
    const recargarAntes = () => reservas.recargarAntes(proxima.value?.reservation?.id);

    /** «Guardar» de Añade a tus hijos: uno a uno; con todos dentro, su «Guardado». */
    async function guardarHijos() {
        if (e.ocupado) return;
        e.ocupado = 'hijos';
        const r = await hijos.guardar();

        e.ocupado = null;
        if (r === 'listo') {
            recargarAntes();
            Object.assign(e, { vista: VISTA.HIJOS_LISTO, subpaso: '', dir: 'fwd' });
        } else if (r === 'caducada') {
            Object.assign(e, { vista: VISTA.ENTRAR, subpaso: '', dir: null });
        } else {
            enfocarError();
        }
    }

    /** «Firmar en su nombre», desde su ficha: lo confirma arriba. */
    async function firmarHijo() {
        if (e.ocupado) return;
        e.ocupado = 'firmar';
        const ok = await hijos.firmar();

        e.ocupado = null;
        if (ok) {
            recargarAntes();
            decir(tp(textos, 'mi_cuenta.hijo.firmado_ok', { nombre: hijos.hijo.value?.nombre ?? '' }));
        } else {
            enfocarError();
        }
    }

    /** Quitarlo, tras la pregunta: de vuelta en Mi cuenta, y lo dice arriba. */
    async function quitarHijo() {
        const dicho = await hijos.quitar();

        if (! dicho) return;
        recargarAntes();
        aInicio();
        decir(dicho);
    }

    // ── Con sesión: los Ajustes (T5e) ────────────────────────────────────────────────────────────────

    /** Lo que hizo un paso o un botón de Ajustes: la sesión que murió lleva a Entrar; un «no», a su campo. */
    function tras(r) {
        if (r === 'caducada') Object.assign(e, { vista: VISTA.ENTRAR, subpaso: '', dir: null });
        else if (r === 'error') enfocarError();
    }

    /**
     * La acción de un paso de Ajustes (la de la isla): espera al servidor y, si sale, vuelve a Mi cuenta —al mismo punto,
     * con el plegable abierto— y lo dice arriba (`dicho`). El correo no vuelve: queda su desenlace.
     *
     * @param {string} hace  el método de `useAjustesCuenta` que la hace
     */
    async function hacerPaso(hace, dicho = '') {
        if (e.ocupado || ! ajustes.value) return;
        e.ocupado = e.vista;
        const r = await ajustes.value[hace]();

        e.ocupado = null;
        if (r === 'ok' && dicho) {
            aInicio();
            decir(t(textos, dicho));
        } else {
            tras(r);
        }
    }

    async function guardarDatos() {
        if (e.ocupado || ! ajustes.value) return;
        e.ocupado = 'datos';
        const r = await ajustes.value.guardarDatos();

        e.ocupado = null;
        tras(r);
    }

    /** «Borrar mi cuenta», el botón del contenido: al salir bien, la página se va (el composable navega). */
    async function borrarCuenta() {
        if (e.ocupado || ! ajustes.value) return;
        e.ocupado = 'borrar';
        const r = await ajustes.value.borrar();

        if (r !== 'ok') { e.ocupado = null; tras(r); }
    }

    /** «Cerrar sesión»: el cierre del motor, que navega a la portada si el servidor lo confirma. */
    async function salir() {
        if (e.ocupado || ! ajustes.value) return;
        e.ocupado = 'salir';
        if (! await ajustes.value.salir()) e.ocupado = null;
    }

    // ── Sin sesión: Entra, el olvido, Crea tu cuenta y el alta de Google ────────────────────────────

    /**
     * La sesión recién abierta: esta página, recargada en Mi cuenta. La acción sigue «cargando» hasta que se va, y
     * los campos de la contraseña se vacían antes (un dispositivo compartido no la conserva ni un instante de más).
     */
    function recargarEnMiCuenta() {
        authStore.form.password = '';

        try {
            window.history.replaceState(window.history.state, '', `${window.location.pathname}${window.location.search}#mi-cuenta`);
        } catch { /* sin historial: recarga igual y la puerta de siempre la lleva a su cuenta */ }
        window.location.reload();
    }

    async function entrar() {
        if (e.ocupado) return;
        e.ocupado = 'entrar';
        Object.assign(authStore.form, { email: e.ent.valor.trim(), password: e.ent.clave });
        const r = await authStore.login(opciones());

        if (r?.ok) return recargarEnMiCuenta();

        authStore.form.password = '';
        e.ocupado = null;
        e.ent = { ...e.ent, error: errorDeEntrar(r, textos) };
        enfocarError();
    }

    /** «¿Has olvidado tu contraseña?»: el enlace al correo escrito, y su confirmación, que no dice si existe (`SEC-06`). */
    async function olvido() {
        if (e.ocupado) return;
        e.ocupado = 'olvido';
        authStore.form.email = e.ent.valor.trim();
        const r = await authStore.requestPasswordLink(opciones());

        e.ocupado = null;
        if (r?.sent) return Object.assign(e, { subpaso: 'olvido', dir: 'fwd' });

        e.ent = { ...e.ent, error: r?.errors?.fields?.email || r?.errors?.global || '' };
        enfocarError();

        return null;
    }

    const firma = computed(() => firmaPendiente({ cuenta: 'nueva', documento: waiverStore.document }));

    function fallarAlta(errores, texto = '') {
        Object.assign(e, { errores, avisoAlta: texto, ocupado: null });
        enfocarError();
    }

    /**
     * «Crear mi cuenta»: el alta SUELTA del motor (`registerStandalone`, contexto `standalone`), que abre la sesión
     * (`#331`). Lo que falta se dice antes de preguntar; los «no» del servidor, bajo su campo, con su mensaje. Un
     * correo que ya tiene cuenta: «Ya hay una cuenta con este correo: entra con él.» (el diseño).
     */
    async function crear() {
        if (e.ocupado) return;
        const errores = revisarDatos({ ...e.f, cuenta: 'nueva' }, { firmaPendiente: firma.value, textos });

        if (Object.keys(errores).length) return fallarAlta(errores);

        Object.assign(e, { ocupado: 'crear', errores: {}, avisoAlta: '' });
        Object.assign(authStore.form, formularioDeAlta(e.f), { turnstile_token: e.token });
        const r = await authStore.registerStandalone(opciones());

        if (r?.ok && r.identified) return recargarEnMiCuenta();

        // El token del anti-bot es de un solo uso: vacío, el widget pide otro.
        e.token = '';
        authStore.form.password = '';
        // Un 201 sin sesión es el señuelo, que la isla no puede disparar (no pinta su campo): se dice lo genérico.
        if (r?.ok) return fallarAlta({}, t(props.messages, 'errors.try_later'));

        const error = r?.errors ?? authStore.registerError;

        if (cuentaQueYaExiste(error?.signup)) return fallarAlta({ correo: t(textos, 'mi_cuenta_alta.ya_existe') });

        const { errores: delServidor, resto } = erroresDelServidor(error?.fields);

        if (delServidor.descargo) e.f.descargo = false;

        return fallarAlta(delServidor, resto[0] ?? (Object.keys(delServidor).length ? '' : error?.summary?.[0] ?? ''));
    }

    /** «Continuar con Google»: la ida la hace el SERVIDOR y vuelve a esta página, en Mi cuenta (`next`, `SEC-08`). */
    function aGoogle() {
        window.location.assign(conVuelta(props.urls?.google, `${window.location.pathname}${window.location.search}#mi-cuenta`));
    }

    /**
     * Completar el alta que vuelve de Google (`account/google.js`, la misma secuencia que el cajón). Al terminar, a la
     * cuenta —o a la compra de la que salió, si salió de una (`landOnAccount`, `reanudar`)—.
     */
    async function completarGoogle() {
        if (e.ocupado) return;
        e.ocupado = 'google';
        const { state, result } = await submitGoogleScreen({ state: e.google, form: authStore.form, api, waiver: waiverStore.document, messages: props.messages, auth: props.auth });

        e.google = state;
        if (result.ok) return landOnAccount({ urls: props.urls, reanudar: true });

        // El 409 del descargo RELEE y desmarca: lo que se leyó ya no es lo que se firmaría (`#175`).
        if (result.stale) { authStore.form.accept_waiver = false; await waiverStore.reloadLegal({ api }); }
        e.ocupado = null;
        enfocarError();

        return null;
    }

    // ── Lo que se pinta ──────────────────────────────────────────────────────────────────────────────

    const ck = computed(() => ckDeCuenta({
        vista: e.vista, subpaso: e.subpaso, desde: e.desde, qrDesde: e.qrDesde, dir: e.dir, ocupado: e.ocupado,
        entrada: e.ent, textos, altaGoogle: { pendiente: e.google.pending !== null },
        cambiar: { desdeReserva: e.cambiarDesdeReserva, whatsapp: Boolean(cambiarVista.value?.whatsapp) },
        hijo: { nombre: hijos.hijo.value?.nombre ?? '', firmar: Boolean(hijos.hijo.value?.firmar) },
        // Los pasos de Ajustes (T5e): el correo ya pedido deja su desenlace sin acción; tu descargo, «Firmar» solo si
        // hace falta y hay texto que firmar.
        ajuste: {
            enviado: Boolean(ajustes.value?.paso.value.correo?.pendiente),
            firmar: Boolean(ajustes.value?.bloque.value.descargo?.firmar && waiverStore.document),
        },
        rotulos: {
            altaGoogle: t(props.account, 'google.title'), altaGoogleBoton: t(props.account, 'google.submit'),
            altaGoogleEnviando: t(props.account, 'google.submitting'),
        },
        acciones: {
            cerrar, alMenu, aInicio, entrar, crear, completarGoogle, escribir, guardarHijos, firmarHijo,
            guardarClave: () => hacerPaso('guardarClave', 'mi_cuenta.clave.guardada'),
            enviarCorreo: () => hacerPaso('enviarCorreo'),
            cerrarOtras: () => hacerPaso('cerrarOtras', 'mi_cuenta.otras_sesiones.hecho'),
            desvincular: () => hacerPaso('desvincular', 'mi_cuenta.desvincular.hecho'),
            firmar: () => hacerPaso('firmar', 'mi_cuenta.descargo.firmado'),
            aReserva: () => Object.assign(e, { vista: VISTA.RESERVA, subpaso: '', dir: 'back' }),
            aEntrar: () => Object.assign(e, { vista: VISTA.ENTRAR, subpaso: '', dir: 'back', errores: {}, avisoAlta: '' }),
            volverDelDescargo: () => Object.assign(e, { subpaso: '', dir: 'back' }),
        },
    }));

    const qr = computed(() => ({
        src: cardImageUrl(carne.card),
        codigo: tokenGroups(carne.card?.token),
        dibujable: cardIsDrawable(carne.card),
        cargando: carne.loading && ! carne.loaded,
        fallo: carne.notice || '',
    }));

    // La línea de arriba: con las reservas ya llegadas, la próxima con qué Y cuántos; antes, la del contexto sembrado.
    const linea = computed(() => {
        const r = proxima.value?.reservation;

        return r ? lineaProxima(r, { locale, titulo: tituloDe(r) }) : lineaProxima(contexto.context?.next_reservation ?? null, { locale });
    });

    const inicio = computed(() => ({
        nombre: contexto.context?.first_name ?? '',
        linea: linea.value,
        qr: qr.value,
        aviso: e.aviso?.en === VISTA.INICIO ? e.aviso.texto : '',
        avisoTono: e.aviso?.en === VISTA.INICIO ? (e.aviso.tono ?? 'success') : 'success',
        hoy: hoy.value, renovar: e.renovar, renovando: e.ocupado === 'renovar', sinQr: delMotor('account.card.unavailable'),
        proxima: reservas.bloque(proxima.value),
        // El contexto ya dice que hay próxima y aún no han llegado las reservas: su hueco espera, sin saltos.
        esperandoProxima: reservas.s.proximas === null && Boolean(contexto.context?.next_reservation),
        // «Antes de venir» de la próxima y, arriba, «Siguiente: …» (T5c).
        antes: reservas.antes(proxima.value),
        chip: reservas.chip(proxima.value),
        otras: reservas.otras.value,
        quien: hijos.quien.value,
        // Ajustes y «Cerrar sesión» (T5e), cuando llega su trozo.
        ajustes: ajustes.value?.bloque.value ?? null,
        // Los avisos de la cuenta (T5e·2), arriba.
        avisos: { cuenta: avisoCuenta.value, analitica: avisoDeAnalitica(contexto.context, { textos, motor: props.account }) },
        saliendo: e.ocupado === 'salir',
    }));

    const vistaQr = computed(() => ({
        qr: qr.value, renovar: e.renovar, renovando: e.ocupado === 'renovar', irCuenta: e.qrDesde !== 'cuenta',
        aviso: e.aviso?.en === VISTA.QR ? e.aviso.texto : '',
        avisoTono: e.aviso?.en === VISTA.QR ? (e.aviso.tono ?? 'success') : 'success',
        comoLlegar: hoy.value ? reservas.rutaAlParque.value : '',
    }));

    const social = computed(() => ({ social: Boolean(props.urls?.google), apple: false, marcaGoogle: MARCA_GOOGLE }));

    /** Los textos de siempre de completar el alta de Google (`account.google.*`, solo en su puerta) y del alta. */
    const rotulosGoogle = computed(() => ({
        titulo: delMotor('google.title'), intro: delMotor('google.intro'), correo: delMotor('google.email_label'),
        correoPista: delMotor('google.email_hint'), caducada: delMotor('google.expired'), empezar: delMotor('google.restart'),
        nombre: delMotor('register.name'), revisa: delMotor('register.fix_errors'), privacidad: delMotor('register.privacy_notice'),
        leerPrivacidad: delMotor('register.privacy_read'), casilla: delMotor('register.accept_waiver'), leerDescargo: delMotor('register.waiver_read'),
    }));

    return {
        abierta, textos, e, ck, inicio, vistaQr, social, firma, authStore, waiverStore, rotulosGoogle,
        tx: (clave) => t(textos, clave),
        sinQr: computed(() => delMotor('account.card.unavailable')),
        pantallaEntrar: computed(() => ({ paso: e.subpaso === 'olvido' ? 'olvido' : 'id', valor: e.ent.valor, clave: e.ent.clave, error: e.ent.error, ...social.value })),
        // Lo que se dice arriba de «Entra» (T5e·2): la vuelta de Google que no salió («No has terminado de entrar…»).
        avisoEntrar: computed(() => (e.aviso?.en === VISTA.ENTRAR ? e.aviso : null)),
        abrirQr: () => a(VISTA.QR, { qrDesde: 'cuenta' }),
        aInicio, decir, renovarQr, olvido, aGoogle, irAlBloque,
        // Las reservas (T5b): abrir una de «Otras reservas», pedir un cambio (desde la próxima o desde la abierta) y el
        // historial que crece.
        abrirReserva: (id) => { a(VISTA.RESERVA, { rSel: id }); reservas.cargarAntes(id); },
        aCambiar: (desdeReserva) => a(VISTA.CAMBIAR, { cambiarDesdeReserva: desdeReserva === true }),
        masHistorial: () => reservas.mas(),
        reservaAbierta: computed(() => reservas.bloque(reservas.buscar(e.rSel))),
        // Su «Antes de venir» (T5c): cada reserva tiene sus tareas, también la que se abre desde «Otras reservas».
        antesAbierta: computed(() => reservas.antes(reservas.buscar(e.rSel))),
        hacerTarea,
        // Los hijos (T5d): el alta (sus fichas, «Añadir otro hijo», la casilla) y la ficha de uno (firmar, quitar).
        pantallaHijos: hijos.pantalla,
        fichaHijo: computed(() => ({ ...hijos.ficha.value, aviso: e.aviso?.en === VISTA.HIJO ? e.aviso.texto : '' })),
        abrirHijos: () => a(VISTA.HIJOS),
        abrirHijo: (id) => { a(VISTA.HIJO); hijos.abrir(id); },
        cambiarHijo: (i, campo, valor) => hijos.cambiar(i, campo, valor),
        otroHijo: () => hijos.otro(),
        quitarFicha: (i) => hijos.quitarFicha(i),
        casillaHijos: (v) => { hijos.s.h.descargo = v; hijos.s.errores = { ...hijos.s.errores, descargo: '' }; },
        casillaHijo: (v) => { hijos.s.firmaCasilla = v; hijos.s.firmaError = ''; },
        preguntarQuitar: (si) => { hijos.s.preguntar = si; },
        quitarHijo,
        // Los Ajustes (T5e): el bloque (sus plegables, «Tus datos», los interruptores, las descargas, los recibos y
        // «Cerrar sesión») y sus pasos, con su formulario.
        alternarAjuste: (id) => ajustes.value?.alternar(id),
        datoAjuste: (campo, valor) => ajustes.value?.cambiarDato(campo, valor),
        guardarDatos,
        pasoAjuste: (vista) => a(vista),
        vincular: () => ajustes.value?.vincular(),
        interruptor: (nombre, valor) => ajustes.value?.interruptor(nombre, valor),
        descargarDatos: () => ajustes.value?.descargarDatos(),
        masRecibos: () => ajustes.value?.masRecibos(),
        salir,
        // El paso de Ajustes que se ve, cuando su lógica ya está (hasta entonces, `null`: no se pinta).
        pasoDeAjuste: computed(() => (ajustes.value ? {
            ...ajustes.value.paso.value,
            ocupado: ajustes.value.paso.value.ocupado || e.ocupado === 'borrar',
            aviso: e.aviso?.en === e.vista ? e.aviso : null,
        } : null)),
        cambiarPaso: (campo, valor) => ajustes.value?.cambiarPaso(campo, valor),
        enlaceClave: () => ajustes.value?.enlaceClave(),
        reenviarCorreo: () => ajustes.value?.reenviarCorreo(),
        cancelarCorreo: () => ajustes.value?.cancelarCorreo(),
        borrarCuenta,
        // Los avisos de la cuenta (T5e·2).
        hacerAviso,
        hacerAnalitica,
        cambiarVista,
        guardarQr: () => decir(t(textos, 'mi_cuenta.qr.guardado')),
        pedirRenovar: (si) => { e.renovar = si; },
        cambiarEntrada: (campo, valor) => { e.ent = { ...e.ent, [campo]: valor, error: '' }; },
        cambiarAlta: (campo, valor) => { e.f[campo] = valor; if (e.errores[campo]) e.errores = { ...e.errores, [campo]: '' }; },
        aCrear: () => { Object.assign(e, { vista: VISTA.CREAR, subpaso: '', dir: 'fwd', f: datosVacios(), errores: {}, avisoAlta: '' }); cargar(VISTA.CREAR); },
        leerDescargo: () => Object.assign(e, { subpaso: 'descargo', dir: 'fwd' }),
        google: computed(() => ({ ...e.google, nombre: authStore.form.name, descargo: Boolean(authStore.form.accept_waiver) })),
        cambiarGoogle: (campo, valor) => { if (campo === 'nombre') authStore.form.name = valor; if (campo === 'descargo') authStore.form.accept_waiver = valor; },
    };
}
