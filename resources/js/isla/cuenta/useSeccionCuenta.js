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
 *     (`account/after-auth.js`): nada del acceso sobrevive en memoria y la página entera sabe ya quién es.
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
import { paginaConSelector } from '../pagina/con-selector.js';
import { useSuperficie } from '../compra/useSuperficie.js';
import {
    cuentaQueYaExiste, datosVacios, entradaVacia, erroresDelServidor, firmaPendiente, formularioDeAlta, nacimientoDeAlta,
    revisarDatos,
} from '../compra/datos.js';
import { entrar as entrarConCodigo, errorDeEntrar, erroresDelCodigo, puerta } from '../compra/acceso.js';
import { VISTA, ckDeCuenta, lineaProxima, vistaDeApertura } from './vista.js';
import { avisoDeAnalitica, avisoDeCuenta } from './avisos.js';
import { tituloDe } from './reservas.js';
import { useReservasCuenta } from './useReservasCuenta.js';
import { useHijosCuenta } from './useHijosCuenta.js';
import { useConexion } from './useConexion.js';
import { protegido } from './seguro.js';

/** Los pasos de Ajustes (T5e): al entrar en uno, su formulario empieza vacío. */
const PASOS_DE_AJUSTES = [VISTA.CORREO, VISTA.OTRAS, VISTA.DESVINCULAR, VISTA.FIRMA, VISTA.BORRAR];

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
     * `vista` y `subpaso` (el descargo de Crear); `dir`, la entrada de la vista; `desde` y `qrDesde`, a dónde vuelve la
     * flecha; `ocupado`, lo que espera al servidor; `aviso`, la confirmación de arriba (se queda hasta salir de su vista:
     * WCAG 2.2.1); `renovar`, la pregunta de «Renovar mi QR»; `ent`, «Entra» (su correo y, después, su código: A3 del
     * acceso con código, `#849`); `f`, «Crea tu cuenta», con sus `errores`, su `avisoAlta` y su `nota` (el correo de
     * «Entra» que aún no tiene cuenta); `token`, el del anti-bot; `google`, el alta que vuelve, y `googleNacimiento`, su
     * fecha como se TECLEA (el motor la quiere en `Y-m-d`: se convierte al enviar, `#792`).
     */
    const e = reactive({
        vista: VISTA.INICIO, subpaso: '', dir: null, desde: null, qrDesde: null, ocupado: null, aviso: null,
        renovar: false, ent: entradaVacia(), f: datosVacios(), errores: {}, avisoAlta: '', nota: '', token: '',
        google: emptyGoogleScreen(), googleNacimiento: '', errorNacimiento: '', rSel: null, cambiarDesdeReserva: false,
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

    // Sin conexión (T5f): lo que GUARDA se envuelve (`guarda`), y sin red no se intenta. Su fallo retira la confirmación
    // que hubiera (el diseño: uno u otro) y sube la capa a él: lo que falló puede estar al fondo (un interruptor de
    // Ajustes), y el aviso y su «Volver a intentarlo» viven arriba.
    const red = useConexion({ alFallar: () => { e.aviso = null; caja()?.scrollTo?.({ top: 0, behavior: 'smooth' }); } });
    const guarda = red.guarda;

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

    /**
     * Lo que Mi cuenta deja al pasar a la compra (T5f), para volver a ello cuando la flecha de la compra la devuelve
     * (`desde: 'compra'`): su propia flecha (de dónde se abrió) y el punto del scroll.
     */
    let antesDeLaCompra = null;

    /** Al abrirse, la vista de su zona: la que pidió la apertura (aún sin consumir) o la ya aplicada al motor. */
    function situar(zona) {
        const host = cajonHost();
        // De vuelta de la compra que abrió aquí (T5f): el inicio, entrando por la izquierda, con su flecha y en su punto.
        const deLaCompra = host?.cuentaDesde === 'compra' ? antesDeLaCompra : null;
        const { vista, bloque, plegable } = vistaDeApertura(zona, { sesion: contexto.identified, bloque: enlaceDeCuenta(window.location.hash)?.bloque ?? '' });

        // Un plegable de Ajustes (la zona del cajón que lo era, `#mi-cuenta/acceso`): abierto, y la capa baja a él.
        if (plegable) conAjustes().then((aj) => aj?.abrir(plegable));

        // El aviso que dejó el servidor al volver a esta página (T5e·2, `#779`: la vuelta de Google al entrar o al vincular,
        // un correo…), la primera vez que se abre Mi cuenta: arriba, con su tono, y hasta salir de la vista.
        const delServidor = tomarAvisoDelServidor(document);

        Object.assign(e, {
            vista, subpaso: '', dir: deLaCompra ? 'back' : null, ocupado: null, renovar: false, errores: {}, avisoAlta: '', nota: '',
            aviso: delServidor ? { ...delServidor, en: vista } : null,
            desde: deLaCompra ? deLaCompra.desde : (host?.cuentaDesde ?? null),
            qrDesde: vista === VISTA.QR ? (host?.cuentaDesde ?? 'fuera') : null,
            ent: entradaVacia(), f: datosVacios(), rSel: null, cambiarDesdeReserva: false,
        });
        cargar(vista);
        if (deLaCompra) volverAlPunto(deLaCompra.scroll);
        else if (bloque) irAlBloque(bloque);
    }

    watch(abierta, (dentro) => { if (dentro) situar(cajonHost()?.accountZone || zonas.zone); }, { immediate: true });
    watch(() => zonas.zone, (zona) => { if (abierta.value) situar(zona); });
    // Una confirmación se va al salir de la vista en la que se dijo; el fallo de la red, al cambiar de vista o de paso.
    watch(() => e.vista, (vista) => { if (e.aviso && e.aviso.en !== vista) e.aviso = null; });
    watch(() => [e.vista, e.subpaso], () => red.olvidar());
    // La sesión murió con la capa abierta (el carné responde 401): lo que se ve pasa a ser Entrar.
    watch(() => carne.expired, (caducada) => { if (caducada && abierta.value) { contexto.refresh({ api }); Object.assign(e, { vista: VISTA.ENTRAR, subpaso: '', dir: null }); } });

    /**
     * La confirmación de arriba; con `tono = 'danger'`, lo que no salió (T5e). Se queda hasta salir de su vista. Lo que se
     * dice ahora sustituye al fallo de la red que hubiera (T5f).
     */
    const decir = (texto, tono = 'success') => { e.aviso = { texto, en: e.vista, tono }; red.olvidar(); };

    // ── Los avisos de la cuenta (T5e·2) ──────────────────────────────────────────────────────────────

    /** El de la cuenta (`avisos.js`): confirmar el correo, firmar su descargo o el de sus hijos. Uno, y en su orden. */
    const avisoCuenta = computed(() => protegido('avisos', () => avisoDeCuenta(contexto.context, { textos, reenvio: { segundos: authStore.resendSeconds, quedan: authStore.resendsLeft } }), { roto: null }));
    // El cupo del reenvío se arma UNA vez, cuando hay que confirmar el correo (la misma puerta que el índice del cajón).
    watch(() => avisoCuenta.value?.tipo, (tipo) => { if (tipo === 'verificar') authStore.allowVerificationResend(); }, { immediate: true });

    /** «Reenviar el correo»: el reenvío del motor, que ya sabe si toca (espera y cupo) y a qué correo. */
    async function reenviarVerificacion() {
        const r = await authStore.resendVerification({ api });

        if (r?.ok) decir(t(textos, 'mi_cuenta.avisos.reenviado'));
        else if (! r?.skipped) decir(t(props.messages, 'errors.try_later'), 'danger');
    }

    /** El enlace del aviso de la cuenta: reenviar (sin red, no se intenta: T5f), firmar SU descargo (su paso) o ir a sus hijos. */
    function hacerAviso(hace) {
        if (hace === 'reenviar') guarda(reenviarVerificacion)();
        else if (hace === 'firmar') a(VISTA.FIRMA);
        else if (hace === 'hijos') irAlBloque('quien');
    }

    /**
     * El aviso de la analítica: «Entendido» lo despide (el servidor lo confirma antes de quitarlo) y «Privacidad» también
     * —quien va a donde se retira ya lo ha leído, como en el cajón— y abre ese plegable de Ajustes. Sin red (T5f),
     * «Entendido» no se intenta y deja su reintento; «Privacidad» es sobre todo ir allí, y va: el aviso sigue hasta que
     * el servidor lo despida.
     */
    function hacerAnalitica(que) {
        const despedir = () => contexto.dismissAnalyticsNotice({ api });

        if (que !== 'privacidad') {
            guarda(despedir)();

            return;
        }
        despedir();
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

    /** La lista, de vuelta en el punto en que se dejó (cuando ya está pintada). */
    function volverAlPunto(arriba) {
        nextTick(() => setTimeout(() => { const c = caja(); if (c) c.scrollTop = arriba; }, 60));
    }

    function aInicio() {
        Object.assign(e, { vista: VISTA.INICIO, subpaso: '', dir: 'back', renovar: false, rSel: null });
        volverAlPunto(scroll);
    }

    /** La tarjeta de «Cambiar o cancelar»: la de la reserva abierta o, desde el inicio, la próxima. */
    const tarjetaCambiar = computed(() => (e.cambiarDesdeReserva ? reservas.buscar(e.rSel) : proxima.value));
    const cambiarVista = computed(() => (tarjetaCambiar.value ? reservas.cambiar(tarjetaCambiar.value) : null));

    /** «Escribirnos por WhatsApp»: el mensaje ya escrito, en otra pestaña (o en la aplicación, en un móvil). */
    function escribir() {
        if (cambiarVista.value?.whatsapp) window.open(cambiarVista.value.whatsapp, '_blank', 'noopener');
    }

    /** El enlace que abrió Mi cuenta (`#mi-cuenta…`) se va con ella: recargar no la reabre. */
    function soltarEnlace() {
        if (enlaceDeCuenta(window.location.hash)) {
            try { window.history.replaceState(window.history.state, '', `${window.location.pathname}${window.location.search}`); } catch { /* sin historial */ }
        }
    }

    /** La X: cierra la capa sin perder nada. */
    function cerrar() {
        cerrarSuperficie();
        soltarEnlace();
    }

    /**
     * «Elegir día» de Reservar otra vez y «Reserva tu primera visita» (T5f): la COMPRA de la isla en la misma capa, ya
     * situada en su producto y su gente (la intención `linea` SIN día ni hora: «solo falta el día y la hora») o, sin
     * intención de producto, eligiendo zona. `desde: 'cuenta'` le da la flecha que vuelve aquí; lo que se deja, arriba.
     */
    function aLaCompra(intencion = {}) {
        antesDeLaCompra = { desde: e.desde, scroll: caja()?.scrollTop ?? 0 };
        soltarEnlace();
        cajonHost()?.openWith?.({ ...intencion, desde: 'cuenta' });
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

    /**
     * Lo que hizo un paso o un botón de Ajustes: la sesión que murió lleva a Entrar; un «no», a su campo; el código recién
     * pedido (o el correo nuevo ya pendiente, con el suyo de camino), a escribirlo: el foco va a su campo (A3b, `#857`).
     */
    function tras(r) {
        if (r === 'caducada') Object.assign(e, { vista: VISTA.ENTRAR, subpaso: '', dir: null });
        else if (r === 'error') enfocarError();
        else if (r === 'enviado' || r === 'pendiente') nextTick(() => caja()?.querySelector('input[autocomplete="one-time-code"]')?.focus());
    }

    /**
     * La acción de un paso de Ajustes (la de la isla): espera al servidor y, si sale, vuelve a Mi cuenta —al mismo punto,
     * con el plegable abierto— y lo dice arriba (`dicho`). Pedir el código de confirmar, o dejar pendiente el correo
     * nuevo, no sale del paso: queda a escribir el código.
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

    // ── Sin sesión: Entra (con un código al correo), Crea tu cuenta y el alta de Google ──────────────

    /** La sesión recién abierta: esta página, recargada en Mi cuenta. La acción sigue «cargando» hasta que se va. */
    function recargarEnMiCuenta() {
        try {
            window.history.replaceState(window.history.state, '', `${window.location.pathname}${window.location.search}#mi-cuenta`);
        } catch { /* sin historial: recarga igual y la puerta de siempre la lleva a su cuenta */ }
        window.location.reload();
    }

    const otraVezLuego = () => t(props.messages, 'errors.try_later');

    /** Un correo que YA tiene cuenta, desde «Crea tu cuenta»: su código va de camino, y se escribe en Entra. */
    function aSuCodigo(correo) {
        Object.assign(e, {
            vista: VISTA.ENTRAR, subpaso: '', dir: 'fwd', ocupado: null, errores: {}, avisoAlta: '', nota: '',
            ent: { ...entradaVacia(correo), paso: 'codigo' },
            aviso: { texto: t(textos, 'mi_cuenta_alta.ya_existe'), en: VISTA.ENTRAR, tono: 'info' },
        });
    }

    /**
     * «Continuar» de Entra (A3 del acceso con código, `#849`), como en la compra. Con el CORREO, la puerta: con cuenta, a
     * su código (que ya va de camino; el límite del correo dice lo mismo: hay uno recién enviado); nuevo, a «Crea tu
     * cuenta» con el correo puesto y una nota NEUTRA —no ha hecho nada mal—. Con el CÓDIGO, entrar (sesión recordada 90
     * días, `#848`): la página se recarga en Mi cuenta.
     */
    async function entrar() {
        if (e.ocupado) return null;
        e.ocupado = 'entrar';
        const correo = e.ent.valor.trim();

        if (e.ent.paso === 'id') {
            const { siguiente, r } = await puerta(api, correo);

            e.ocupado = null;
            if (siguiente === 'code') {
                e.ent = { ...e.ent, paso: 'codigo', codigo: '', error: '' };
            } else if (siguiente === 'register') {
                Object.assign(e, { vista: VISTA.CREAR, subpaso: '', dir: 'fwd', f: { ...datosVacios(), correo }, errores: {}, avisoAlta: '', nota: t(textos, 'compra.datos.nueva') });
                cargar(VISTA.CREAR);
            } else {
                e.ent = { ...e.ent, error: errorDeEntrar(r, textos, otraVezLuego()) };
                enfocarError();
            }

            return null;
        }

        const r = await entrarConCodigo(api, correo, e.ent.codigo, e.ent.recordar);

        if (r.ok) return recargarEnMiCuenta();

        e.ocupado = null;
        e.ent = { ...e.ent, error: errorDeEntrar(r, textos, otraVezLuego()) };
        enfocarError();

        return null;
    }

    /** «Pedir otro código»: el nuevo anula el anterior; el límite (uno por minuto) dice cuánto esperar, bajo el código. */
    async function otroCodigo() {
        if (e.ocupado) return;
        e.ocupado = 'otro';
        const { r } = await puerta(api, e.ent.valor);

        e.ocupado = null;
        e.ent = { ...e.ent, codigo: '', error: r.ok ? '' : errorDeEntrar(r, textos, otraVezLuego()), reenvios: e.ent.reenvios + (r.ok ? 1 : 0) };
    }

    const firma = computed(() => firmaPendiente({ cuenta: 'nueva', documento: waiverStore.document }));

    function fallarAlta(errores, texto = '') {
        Object.assign(e, { errores, avisoAlta: texto, ocupado: null });
        enfocarError();
    }

    /**
     * «Crear mi cuenta»: el alta SUELTA del motor (`registerStandalone`, contexto `standalone`), que abre la sesión
     * (`#331`), sin contraseña (A3, `#849`). Lo que falta se dice antes de preguntar; los «no» del servidor, bajo su
     * campo, con su mensaje. ▶ La PUERTA primero, como en la compra: un correo que ya tiene cuenta recibe su código y va
     * a escribirlo en Entra («Ya hay una cuenta con este correo: entra con él.»), sin intentar el alta —que avisaría al
     * titular de «alguien intentó registrarse» por nada—.
     */
    async function crear() {
        if (e.ocupado) return;
        const errores = revisarDatos({ ...e.f, cuenta: 'nueva' }, { firmaPendiente: firma.value, textos });

        if (Object.keys(errores).length) return fallarAlta(errores);

        Object.assign(e, { ocupado: 'crear', errores: {}, avisoAlta: '', nota: '' });
        const correo = e.f.correo.trim();
        const { siguiente, r: rp } = await puerta(api, correo);

        if (siguiente === 'code') return aSuCodigo(correo);
        if (! rp.ok) {
            const { errores: delCodigo, aviso: texto } = erroresDelCodigo(rp, textos, otraVezLuego());

            return fallarAlta(delCodigo, texto);
        }

        Object.assign(authStore.form, formularioDeAlta(e.f), { turnstile_token: e.token });
        const r = await authStore.registerStandalone(opciones());

        if (r?.ok && r.identified) return recargarEnMiCuenta();

        // El token del anti-bot es de un solo uso: vacío, el widget pide otro.
        e.token = '';
        // Un 201 sin sesión es el señuelo, que la isla no puede disparar (no pinta su campo): se dice lo genérico.
        if (r?.ok) return fallarAlta({}, otraVezLuego());

        const error = r?.errors ?? authStore.registerError;

        // La cuenta nació entre la puerta y el alta: su código, y a Entra.
        if (cuentaQueYaExiste(error?.signup)) {
            await puerta(api, correo);

            return aSuCodigo(correo);
        }

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
        // La fecha, opcional (`#792`): a medias o de un día que no existe se para aquí; vacía no viaja.
        const nacimiento = nacimientoDeAlta(e.googleNacimiento);

        if (nacimiento === null) {
            e.errorNacimiento = t(textos, 'compra.datos.errores.nacimiento');
            enfocarError();

            return null;
        }
        authStore.form.born_on = nacimiento;
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

    // ⚠️ Lo de cada paso se calcula SOLO en su paso (T5f): el `ck` pinta la capa entera —su banda, su flecha, su acción—, y
    // calculando siempre «Cambiar o cancelar» de la próxima, una reserva con un dato que no se puede leer se llevaba la capa
    // por delante, por encima de los bloques protegidos (lo cazó la sonda con una fecha rota). Y así depende de menos.
    const ck = computed(() => ckDeCuenta({
        vista: e.vista, subpaso: e.subpaso, desde: e.desde, qrDesde: e.qrDesde, dir: e.dir, ocupado: e.ocupado,
        entrada: e.ent, textos, altaGoogle: { pendiente: e.google.pending !== null },
        cambiar: e.vista === VISTA.CAMBIAR ? { desdeReserva: e.cambiarDesdeReserva, whatsapp: Boolean(cambiarVista.value?.whatsapp) } : null,
        hijo: e.vista === VISTA.HIJO ? { nombre: hijos.hijo.value?.nombre ?? '', firmar: Boolean(hijos.hijo.value?.firmar) } : null,
        // Los pasos de Ajustes (T5e): el correo ya pedido, «Confirmar el correo»; tu descargo, «Firmar» solo si hace falta y
        // hay texto que firmar; lo que se confirma con un código, «Enviarme el código» hasta pedirlo (A3b, `#857`).
        ajuste: {
            pendiente: e.vista === VISTA.CORREO && Boolean(ajustes.value?.paso.value.correo?.pendiente),
            firmar: e.vista === VISTA.FIRMA && Boolean(ajustes.value?.bloque.value.descargo?.firmar && waiverStore.document),
            codigoPedido: Boolean(ajustes.value?.paso.value.codigo?.enviado),
        },
        rotulos: {
            altaGoogle: t(props.account, 'google.title'), altaGoogleBoton: t(props.account, 'google.submit'),
            altaGoogleEnviando: t(props.account, 'google.submitting'),
        },
        // Lo que guarda, por `guarda` (T5f): sin red no se intenta y queda su «Volver a intentarlo».
        acciones: {
            cerrar, alMenu, aInicio, escribir,
            entrar: guarda(entrar), crear: guarda(crear), completarGoogle: guarda(completarGoogle),
            guardarHijos: guarda(guardarHijos), firmarHijo: guarda(firmarHijo),
            enviarCorreo: guarda(() => hacerPaso('enviarCorreo')),
            confirmarCorreo: guarda(() => hacerPaso('confirmarCorreo', 'mi_cuenta.correo.confirmado')),
            cerrarOtras: guarda(() => hacerPaso('cerrarOtras', 'mi_cuenta.otras_sesiones.hecho')),
            desvincular: guarda(() => hacerPaso('desvincular', 'mi_cuenta.desvincular.hecho')),
            firmar: guarda(() => hacerPaso('firmar', 'mi_cuenta.descargo.firmado')),
            aReserva: () => Object.assign(e, { vista: VISTA.RESERVA, subpaso: '', dir: 'back' }),
            aEntrar: () => Object.assign(e, { vista: VISTA.ENTRAR, subpaso: '', dir: 'back', errores: {}, avisoAlta: '', nota: '', ent: { ...e.ent, paso: 'id', codigo: '', error: '' } }),
            // Del código de Entra, a su correo (para corregirlo).
            aCorreo: () => Object.assign(e, { dir: 'back', ent: { ...e.ent, paso: 'id', codigo: '', error: '' } }),
            volverDelDescargo: () => Object.assign(e, { subpaso: '', dir: 'back' }),
        },
    }));

    // ⚠️⚠️ Cada bloque se compone PROTEGIDO (T5f, `seguro.js`): el que revienta sale roto —su hueco— y el resto sigue. Y un
    // `computed` se protege DENTRO, no donde se lee: Vue 3.5 vuelve a evaluar los `computed` de los que se depende al mirar
    // si hay que repintar (`isDirty` → `refreshComputed`), fuera de cualquier `try` de quien los lee, así que uno que lanza
    // se llevaba el repintado entero de Mi cuenta (lo cazó la sonda: la capa se quedaba sin sus reservas y sin su hueco).
    const qr = computed(() => protegido('qr', () => ({
        src: cardImageUrl(carne.card),
        codigo: tokenGroups(carne.card?.token),
        dibujable: cardIsDrawable(carne.card),
        cargando: carne.loading && ! carne.loaded,
        fallo: carne.notice || '',
    })));

    // La línea de arriba: con las reservas ya llegadas, la próxima con qué Y cuántos; antes, la del contexto sembrado. No es
    // un bloque: si no se puede componer, se queda vacía.
    const linea = computed(() => protegido('linea', () => {
        const r = proxima.value?.reservation;

        return r ? lineaProxima(r, { locale, titulo: tituloDe(r) }) : lineaProxima(contexto.context?.next_reservation ?? null, { locale });
    }, { roto: '' }));

    // Lo que se llama aquí (no un `computed`) se protege aquí. La línea y el chip se quedan vacíos; los avisos, sin aviso.
    const inicio = computed(() => ({
        nombre: contexto.context?.first_name ?? '',
        linea: linea.value,
        // Tu QR (las props de `BloqueQr`) y la confirmación de arriba (las de `AvisoCuenta`), cada una en su objeto.
        tuQr: { qr: qr.value, renovar: e.renovar, renovando: e.ocupado === 'renovar', sinQr: delMotor('account.card.unavailable') },
        aviso: e.aviso?.en === VISTA.INICIO ? { texto: e.aviso.texto, tono: e.aviso.tono ?? 'success' } : null,
        hoy: hoy.value,
        proxima: protegido('proxima', () => reservas.bloque(proxima.value)),
        // El contexto ya dice que hay próxima y aún no han llegado las reservas: su hueco espera, sin saltos.
        esperandoProxima: reservas.s.proximas === null && Boolean(contexto.context?.next_reservation),
        // «Antes de venir» de la próxima y, arriba, «Siguiente: …» (T5c).
        antes: protegido('antes', () => reservas.antes(proxima.value)),
        chip: protegido('chip', () => reservas.chip(proxima.value), { roto: '' }),
        // Estos cuatro son `computed`, protegidos dentro de su módulo.
        otras: reservas.otras.value,
        // T5f: Reservar otra vez (la última visita que se puede repetir) y la bienvenida de la cuenta sin reservas.
        otraVez: reservas.otraVez.value,
        bienvenida: reservas.cuentaNueva.value,
        quien: hijos.quien.value,
        // Ajustes y «Cerrar sesión» (T5e), cuando llega su trozo (su `bloque`, protegido en `useAjustesCuenta`).
        ajustes: ajustes.value?.bloque.value ?? null,
        // Los avisos de la cuenta (T5e·2), arriba.
        avisos: {
            cuenta: avisoCuenta.value,
            analitica: protegido('avisos', () => avisoDeAnalitica(contexto.context, { textos, motor: props.account }), { roto: null }),
        },
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
        pantallaEntrar: computed(() => ({ paso: e.ent.paso, valor: e.ent.valor, codigo: e.ent.codigo, recordar: e.ent.recordar, error: e.ent.error, reenvios: e.ent.reenvios, ...social.value })),
        // Lo que se dice arriba de «Entra» (T5e·2): la vuelta de Google que no salió («No has terminado de entrar…»).
        avisoEntrar: computed(() => (e.aviso?.en === VISTA.ENTRAR ? e.aviso : null)),
        abrirQr: () => a(VISTA.QR, { qrDesde: 'cuenta' }),
        aInicio, decir, irAlBloque,
        // Lo que habla con el servidor (o sale a él), por `guarda` (T5f): sin red no se intenta.
        renovarQr: guarda(renovarQr), otroCodigo: guarda(otroCodigo), aGoogle: guarda(aGoogle),
        // Sin conexión (T5f): el aviso de arriba y el reintento de lo último que no se intentó.
        red: computed(() => ({ enLinea: red.enLinea.value, fallo: red.fallo.value !== null })),
        reintentar: () => red.fallo.value?.(),
        // Las reservas (T5b): abrir una de «Otras reservas», pedir un cambio (desde la próxima o desde la abierta) y el
        // historial que crece.
        abrirReserva: (id) => { a(VISTA.RESERVA, { rSel: id }); reservas.cargarAntes(id); },
        aCambiar: (desdeReserva) => a(VISTA.CAMBIAR, { cambiarDesdeReserva: desdeReserva === true }),
        masHistorial: () => reservas.mas(),
        // T5f: a la compra, situada en la última visita. «Reserva tu primera visita»: el selector de planes si la página lo
        // trae (T6a: el diseño cierra Mi cuenta y lo abre), y si no, la compra eligiendo zona.
        otraVez: () => { const o = reservas.otraVez.value; if (o) aLaCompra({ type: 'linea', id: o.id, quantity: o.n }); },
        primeraVisita: () => {
            if (! paginaConSelector(document)) return aLaCompra();
            cerrar();
            setTimeout(() => window.dispatchEvent(new CustomEvent('isla:abrir', { detail: { panel: 'plans' } })), 40);

            return undefined;
        },
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
        quitarHijo: guarda(quitarHijo),
        // Los Ajustes (T5e): el bloque (sus plegables, «Tus datos», los interruptores, las descargas, los recibos y
        // «Cerrar sesión») y sus pasos, con su formulario. Lo que guarda, por `guarda` (T5f).
        alternarAjuste: (id) => ajustes.value?.alternar(id),
        datoAjuste: (campo, valor) => ajustes.value?.cambiarDato(campo, valor),
        guardarDatos: guarda(guardarDatos),
        pasoAjuste: (vista) => a(vista),
        vincular: guarda(() => ajustes.value?.vincular()),
        interruptor: guarda((nombre, valor) => ajustes.value?.interruptor(nombre, valor)),
        descargarDatos: guarda(() => ajustes.value?.descargarDatos()),
        masRecibos: () => ajustes.value?.masRecibos(),
        salir: guarda(salir),
        // El paso de Ajustes que se ve, cuando su lógica ya está (hasta entonces, `null`: no se pinta).
        pasoDeAjuste: computed(() => (ajustes.value ? {
            ...ajustes.value.paso.value,
            ocupado: ajustes.value.paso.value.ocupado || e.ocupado === 'borrar',
            aviso: e.aviso?.en === e.vista ? e.aviso : null,
        } : null)),
        cambiarPaso: (campo, valor) => ajustes.value?.cambiarPaso(campo, valor),
        // «Pedir otro código» de un paso de Ajustes: el de confirmar esa acción, o el del correo nuevo ya pendiente.
        otroCodigoAjuste: guarda(() => ajustes.value?.otroCodigo(e.vista)),
        cancelarCorreo: guarda(() => ajustes.value?.cancelarCorreo()),
        borrarCuenta: guarda(borrarCuenta),
        // Los avisos de la cuenta (T5e·2).
        hacerAviso,
        hacerAnalitica,
        cambiarVista,
        guardarQr: () => decir(t(textos, 'mi_cuenta.qr.guardado')),
        pedirRenovar: (si) => { e.renovar = si; },
        cambiarEntrada: (campo, valor) => { e.ent = { ...e.ent, [campo]: valor, error: '' }; },
        cambiarAlta: (campo, valor) => { e.f[campo] = valor; if (e.errores[campo]) e.errores = { ...e.errores, [campo]: '' }; },
        aCrear: () => { Object.assign(e, { vista: VISTA.CREAR, subpaso: '', dir: 'fwd', f: datosVacios(), errores: {}, avisoAlta: '', nota: '' }); cargar(VISTA.CREAR); },
        leerDescargo: () => Object.assign(e, { subpaso: 'descargo', dir: 'fwd' }),
        google: computed(() => ({
            ...e.google, nombre: authStore.form.name, descargo: Boolean(authStore.form.accept_waiver),
            nacimiento: e.googleNacimiento, errorNacimiento: e.errorNacimiento || e.google.errors?.fields?.born_on || '',
        })),
        cambiarGoogle: (campo, valor) => {
            if (campo === 'nombre') authStore.form.name = valor;
            if (campo === 'descargo') authStore.form.accept_waiver = valor;
            if (campo === 'nacimiento') { e.googleNacimiento = valor; e.errorNacimiento = ''; }
        },
    };
}
