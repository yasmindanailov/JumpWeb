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
import { computed, inject, nextTick, reactive, watch } from 'vue';
import { TEXTOS_ISLA } from '../../sidebar/carcasa.js';
import { cajonHost } from '../../sidebar/host-bridge.js';
import { api } from '../../sidebar/api.js';
import { t } from '../../sidebar/i18n.js';
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
import { useSuperficie } from '../compra/useSuperficie.js';
import {
    cuentaQueYaExiste, datosVacios, entradaVacia, errorDeEntrar, erroresDelServidor, firmaPendiente, formularioDeAlta,
    revisarDatos,
} from '../compra/datos.js';
import { VISTA, ckDeCuenta, lineaProxima, vistaDeApertura } from './vista.js';
import { tituloDe } from './reservas.js';
import { useReservasCuenta } from './useReservasCuenta.js';

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

    const caja = () => document.querySelector('[data-isla-scroll]');
    const enfocarError = () => nextTick(() => caja()?.querySelector('[aria-invalid="true"]')?.focus());

    function cargar(vista) {
        if (vista === VISTA.INICIO || vista === VISTA.QR) carne.ensure({ api });
        // Las reservas, al entrar; con la de HOY, la ruta al parque para «Cómo llegar» de Tu QR (situación 14).
        if (vista === VISTA.INICIO || vista === VISTA.QR) reservas.cargar().then(() => { if (hoy.value) reservas.cargarSitio(); });
        if (vista === VISTA.CAMBIAR) reservas.cargarSitio();
        if (vista === VISTA.CREAR || vista === VISTA.ALTA_GOOGLE) waiverStore.ensureLegal();
        if (vista === VISTA.ALTA_GOOGLE) {
            loadGoogleScreen({ api }).then((pantalla) => {
                e.google = pantalla;
                // El nombre se copia solo si no hay nada escrito (quien vuelve de un 409 ya corrigió el suyo).
                if (pantalla.pending !== null && ! authStore.form.name) authStore.form.name = pantalla.pending.name;
            });
        }
    }

    /** Lleva el scroll de la capa a un bloque (`#mi-cuenta/antes`), cuando ya está pintado. */
    function irAlBloque(bloque) {
        nextTick(() => setTimeout(() => {
            const c = caja();
            const el = bloque ? document.getElementById(bloque) : null;

            if (c && el) c.scrollTop = el.getBoundingClientRect().top - c.getBoundingClientRect().top + c.scrollTop - 12;
        }, 60));
    }

    /** Al abrirse, la vista de su zona: la que pidió la apertura (aún sin consumir) o la ya aplicada al motor. */
    function situar(zona) {
        const host = cajonHost();
        const { vista, bloque } = vistaDeApertura(zona, { sesion: contexto.identified, bloque: enlaceDeCuenta(window.location.hash)?.bloque ?? '' });

        Object.assign(e, {
            vista, subpaso: '', dir: null, ocupado: null, aviso: null, renovar: false, errores: {}, avisoAlta: '',
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

    const decir = (texto) => { e.aviso = { texto, en: e.vista }; };

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
        rotulos: {
            altaGoogle: t(props.account, 'google.title'), altaGoogleBoton: t(props.account, 'google.submit'),
            altaGoogleEnviando: t(props.account, 'google.submitting'),
        },
        acciones: {
            cerrar, alMenu, aInicio, entrar, crear, completarGoogle, escribir,
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
        hoy: hoy.value, renovar: e.renovar, renovando: e.ocupado === 'renovar', sinQr: delMotor('account.card.unavailable'),
        proxima: reservas.bloque(proxima.value),
        // El contexto ya dice que hay próxima y aún no han llegado las reservas: su hueco espera, sin saltos.
        esperandoProxima: reservas.s.proximas === null && Boolean(contexto.context?.next_reservation),
        otras: reservas.otras.value,
    }));

    const vistaQr = computed(() => ({
        qr: qr.value, renovar: e.renovar, renovando: e.ocupado === 'renovar', irCuenta: e.qrDesde !== 'cuenta',
        aviso: e.aviso?.en === VISTA.QR ? e.aviso.texto : '',
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
        abrirQr: () => a(VISTA.QR, { qrDesde: 'cuenta' }),
        aInicio, decir, renovarQr, olvido, aGoogle, irAlBloque,
        // Las reservas (T5b): abrir una de «Otras reservas», pedir un cambio (desde la próxima o desde la abierta) y el
        // historial que crece.
        abrirReserva: (id) => a(VISTA.RESERVA, { rSel: id }),
        aCambiar: (desdeReserva) => a(VISTA.CAMBIAR, { cambiarDesdeReserva: desdeReserva === true }),
        masHistorial: () => reservas.mas(),
        reservaAbierta: computed(() => reservas.bloque(reservas.buscar(e.rSel))),
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
