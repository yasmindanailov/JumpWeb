/**
 * **LA COMPRA DE LA ISLA sobre el motor** (T3e de `docs/specs/isla-y-landing-nueva.md` §4.10, `DECISIONES #692`).
 *
 * La secuencia es la del cajón (`usePurchaseFlow()`, `#691`): el montaje —catálogo, `/config`, la pausa, la cesta y
 * el desenlace—, la admisión, el alta, el cobro y el sondeo son LOS MISMOS. Lo que es de la isla vive aquí y en los
 * tres que llama: la pantalla 0 de entradas y de fiestas (`usePantallaCero.js`), «Tus datos» (`useDatosCompra.js`),
 * «Pagar» y los desenlaces (`usePagoCompra.js`); la traducción a sus pantallas, sin estado y con su `node --test`, en
 * `vista.js`, `fiesta.js`, `pasos.js`, `recibo.js`, `datos.js` y `linea.js`.
 *
 * El orden, sobre la máquina (que no cambia): «Continuar» mete la línea (`→ CART`) y admite (`checkout()`: `IDENTIFY`
 * sin sesión, `PAY` con ella); «Tus datos» identifica (`→ PAY`); «Pagar» crea el pedido y sale al banco
 * (`→ REDIRECTING`); los desenlaces los pone la vuelta del banco. Volver a la pantalla 0 quita la línea (`→ CATALOG`).
 *
 * ⚠️ Los stores del motor se comparten con el cajón (una sola app, un solo Pinia), y con la isla como carcasa el
 * cajón no monta su compra: aquí se usan sin pasar por sus pasos (`selectProduct()` borraría día y hora).
 */
import { computed, inject, onMounted, reactive, watch } from 'vue';
import { usePurchaseFlow } from '../../sidebar/usePurchaseFlow.js';
import { TEXTOS_ISLA } from '../../sidebar/carcasa.js';
import { cajonHost } from '../../sidebar/host-bridge.js';
import { STEPS, isOutcome } from '../../sidebar/machine.js';
import { api } from '../../sidebar/api.js';
import { t, tp } from '../../sidebar/i18n.js';
import { useSuperficie } from './useSuperficie.js';
import { useDatosCompra } from './useDatosCompra.js';
import { usePagoCompra } from './usePagoCompra.js';
import { usePantallaCero } from './usePantallaCero.js';
import { borradorDeIntencion, sigueSola } from './intencion.js';
import { euros, horasCercanas, horasDelSelector } from './vista.js';
import { meterLinea, pedidoDe } from './linea.js';
import { alPrincipio, irA } from './ir-a.js';
import { lineaListo, marcasDe, reciboDe, resumenDeLaCesta, resumenDelPedido } from './recibo.js';
import { ckDelPaso, direccion, empiezaOtra, pantallaListo, pasoDelMotor, rango, volverDeLaPantallaCero } from './pasos.js';
import { pistaDelDescargo } from './datos.js';
import {
    almacenDeLaPestana, conVuelta, esVuelta, marcarSalida, sinVuelta, tomarMarca, vueltaDe, vuelveAqui,
} from '../../sidebar/reanudar.js';
import { tomarAvisoDelServidor } from '../pagina/aviso-servidor.js';
import { anunciar, dejar, loQueDeja } from '../pagina/compra-cerrada.js';

/** Lo que `SeccionCompra.vue` da a los pasos de después de la pantalla 0, que viajan en otro trozo (`PasosCompra.vue`). */
export const COMPRA = Symbol('la compra de la isla');

/**
 * La «G» de Google en el botón del diseño (`#695`, `[DECIDIDO owner]`: la forma del sistema, con el logotipo que exigen
 * sus normas). Es el MISMO fichero que pinta el botón oficial del cajón (`sidebar/steps/GoogleButton.vue`, con su
 * porqué: raíz-relativa, en `public/`, inerte dentro de un `<img>`).
 */
const MARCA_GOOGLE = '/images/providers/google.svg';

export function useSeccionCompra(props) {
    const textos = inject(TEXTOS_ISLA, {});
    const flow = usePurchaseFlow(props);
    const { store, catalogStore, timeStore, selectionStore, cartStore, outcomeStore, authStore } = flow;
    const { abierta, cerrar: cerrarSuperficie } = useSuperficie();
    /**
     * `preparando` (`#785`): la compra va SOLA al paso que toque —«Reservar y pagar» de la calculadora, la vuelta de
     * Google— y la pantalla 0 no se enseña mientras: un esqueleto, sin nada que tocar (el owner: «al usuario le da tiempo
     * a presionar continuar o editar algo»). `sinDatos`: «Tus datos» no está en el camino de «Pagar» —no hizo falta, o
     * con la sesión ya abierta dejó de tener algo que pedir (`#857`)—.
     * `alEntrar` (`#822`, §4.16): la hora se llenó al CONTINUAR de la pantalla 0, no al pagar —la línea aún no está en la
     * cesta y nada se ha cobrado ni pedido—. `desde` (T5f): la compra la abrió Mi cuenta («Reservar otra vez», «Reserva tu
     * primera visita»), y la flecha de la pantalla 0 vuelve a ella; o el SELECTOR de planes (`#831`), y la flecha lo
     * reabre —`desdeHoy`: tal como se abrió, desde «Reservar para hoy» o no—.
     */
    const compra = reactive({
        borrador: borradorDeIntencion(null, []), precios: {}, llegaron: [], fichas: {}, grupos: [], cargandoHoras: false, intencion: null,
        paso: 'cuando', aviso: '', ocupado: null, pedido: null, pagado: null, dir: null, cercanas: [], horaNueva: null,
        preparando: false, sinDatos: false, alEntrar: false, desde: null, desdeHoy: false,
    });

    /**
     * ⚠️ **En COLA**: dos cambios seguidos (más gente, un par de calcetines) lanzan dos peticiones, y si la primera
     * respondiera la última la isla enseñaría el total VIEJO. En fila, lo último pedido es lo último que se pinta.
     */
    let cola = Promise.resolve();
    const enCola = (tarea) => (cola = cola.then(tarea, tarea));

    // Un PACK es lo único que pide el teléfono (`#787`: el servidor lo exige con cualquiera, `TicketType::anyPack`): una
    // fiesta y, desde la T6c·3, una excursión, que se vende por la pantalla de las entradas.
    const esPack = () => Boolean(compra.borrador.fiesta) || catalogStore.products.find((p) => p.id === compra.borrador.fila)?.type === 'pack';
    const datos = useDatosCompra({ flow, props, textos, esFiesta: esPack });
    const pago = usePagoCompra({ flow, props, textos, compra, enCola, alPagarMal, alLlenarse });
    const { vista, situar, cambiar, cargarHoras, extrasDelPedido } = usePantallaCero({ flow, compra, enCola, textos });

    /**
     * La compra que salió a Google y VUELVE a esta página (T3e·4, `sidebar/reanudar.js`): el servidor la sirve con la
     * compra abierta (`?compra=reanudar`) y aquí se toma su marca, UNA vez y solo en la vuelta. ⚠️ En otra página no
     * se toca: la cuenta nueva completa su alta en `/registro/google`, donde este motor también se monta, y la marca
     * tiene que seguir viva para que el alta vuelva a la compra. Y la barra de direcciones se deja limpia: recargar
     * no reabre nada.
     */
    const { pathname, search, hash } = window.location;
    let reanudacion = vuelveAqui(almacenDeLaPestana(), { ruta: pathname, busqueda: search }, Date.now())
        ? tomarMarca(almacenDeLaPestana(), Date.now())
        : null;

    if (esVuelta(search)) {
        try { window.history.replaceState(window.history.state, '', `${pathname}${sinVuelta(search)}${hash}`); } catch { /* sin historial */ }
    }

    // La vuelta prepara YA (`#785`, medido con `sonda-compra-directa`): la reanudación espera al catálogo, y mientras la
    // pantalla 0 se pintaba vacía. Si el montaje acaba sin catálogo, se suelta: nunca un esqueleto eterno.
    if (reanudacion) {
        compra.preparando = true;
        flow.ready.then(() => { if (catalogStore.products.length === 0) compra.preparando = false; });
    }

    /** El paso que se ve: el que manda la máquina (el cobro y los desenlaces) o el de la isla. */
    const paso = computed(() => pasoDelMotor(store.step) ?? compra.paso);

    /**
     * La intención de la landing (`index.js` la deja en la cola de la máquina): se toma una vez y, si el catálogo aún
     * no ha llegado, espera a él. Sin intención, «Para hoy» eligiendo zona. Una compra que ya terminó (o se quedó en
     * un desenlace) deja paso a la nueva: es lo que hace «Hacer otra reserva».
     *
     * ⚠️⚠️ **Pero SIN intención, un desenlace se queda** (`empiezaOtra`): es la vuelta del banco, que monta el motor
     * con la isla abierta en «¡Reservado!» o en el pago no completado. Empezar ahí borraba el desenlace y enseñaba la
     * pantalla 0 a quien acababa de pagar (lo cazó la sonda en el navegador).
     */
    function applyIntent() {
        compra.intencion = store.machine?.takeIntent?.() ?? compra.intencion;
        // La que sigue SOLA (`#785`) prepara ya, también mientras espera al catálogo: si no, la pantalla 0 se pintaba vacía
        // hasta que llegaba. Si el montaje acaba sin catálogo, se suelta: nunca un esqueleto eterno.
        if (sigueSola(compra.intencion)) {
            compra.preparando = true;
            flow.ready.then(() => { if (catalogStore.products.length === 0) compra.preparando = false; });
        }
        if (catalogStore.products.length === 0) return;
        const intencion = compra.intencion;

        compra.intencion = null;

        // La vuelta de Google sigue donde estaba; una intención nueva de la landing la deja atrás.
        const marca = intencion === null ? reanudacion : null;

        reanudacion = null;
        if (marca !== null) reanudar(marca);
        else if (empiezaOtra(intencion, store.step)) empezar(intencion);
    }

    /**
     * Reanuda la compra que volvió de Google: el borrador y el pedido de la marca, la línea de la cesta guardada (que el
     * montaje restauró) y la admisión del motor (`IDENTIFY` si canceló o la cuenta es nueva, `PAY` si entró). Mientras,
     * «preparando». Con sesión y nada que pedir, a «Pagar»; con una cuenta NUEVA, a completar su alta en «Tus datos»
     * (`#785`: su perfil espera en la sesión; antes salía a Mi cuenta). Si la línea ya no está (caducó, otro titular la
     * purgó), a la pantalla 0 con el borrador.
     */
    async function reanudar({ compra: guardada }) {
        if (! guardada?.borrador?.zona) return empezar(null);
        Object.assign(compra, { borrador: { ...guardada.borrador }, pedido: guardada.pedido ?? null, paso: 'cuando', preparando: true, aviso: '' });
        datos.preparar();
        // La vuelta que NO salió (T5e·2, `#779`: canceló en Google, esa cuenta ya es de otra…) lo dice en «Tus datos», con
        // su aviso de siempre; antes se volvía al mismo sitio sin decir nada. La que salió no necesita aviso: sigue a pagar.
        const delServidor = tomarAvisoDelServidor(document, { si: (a) => a.tono !== 'success' });

        try {
            await flow.ready;
            if (compra.pedido && cartStore.lines.length > 0) {
                compra.pedido = { ...compra.pedido, n: cartStore.lines[0].quantity ?? compra.pedido.n };
                if (store.step === STEPS.CART) await flow.checkout();
                if (store.step === STEPS.IDENTIFY || store.step === STEPS.PAY) {
                    if (store.step === STEPS.IDENTIFY) await datos.altaGooglePendiente();
                    await trasAdmitir();
                    if (delServidor && compra.paso === 'datos') datos.estado.aviso = delServidor.texto;

                    return;
                }
            }
        } finally {
            compra.preparando = false;
        }

        Object.assign(compra, { paso: 'cuando', pedido: null, aviso: cartStore.error });
        enCola(() => situar(compra.borrador));
    }

    /**
     * Tras la admisión (`IDENTIFY` o `PAY`), el paso que toca (`#785`): «Tus datos» solo si tiene algo que pedir
     * (`datos.faltaAlgo()`); si no, «Pagar» directamente, y «Pagar» lo sabe (`sinDatos`: sin «Paso 2 de 2» y su flecha
     * vuelve a la pantalla 0). El formulario se prepara siempre: el servidor puede devolver aquí al pagar.
     */
    async function trasAdmitir() {
        const pedir = await datos.faltaAlgo();

        Object.assign(compra, { paso: pedir ? 'datos' : 'pagar', sinDatos: ! pedir });
    }

    /**
     * «Continuar con Google» (`#695`): la ida es una redirección del SERVIDOR (`urls.google`, solo si la instalación
     * lo tiene) y vuelve a ESTA página (`next`, que el servidor valida contra el mismo sitio) con `?compra=reanudar`,
     * que la sirve con la compra abierta; la marca que se deja ahora en la pestaña dice dónde se estaba.
     */
    function aGoogle() {
        const vuelta = vueltaDe(window.location.pathname, window.location.search);

        marcarSalida(almacenDeLaPestana(), { vuelta, compra: { borrador: compra.borrador, pedido: compra.pedido }, ahora: Date.now() });
        window.location.assign(conVuelta(props.urls?.google, vuelta));
    }

    function empezar(intencion) {
        cartStore.setError('');
        if (isOutcome(store.step)) flow.addAnother();
        // «Reservar y pagar» de la calculadora de la página (T4d; la de la fiesta, T6b·3): con su selección entera —de una
        // fiesta, también la edad, el menú y la hora extra—, la compra sigue SOLA por su
        // camino de siempre (`continuar`: la línea se valida, entra en la cesta y se admite) hasta «Pagar», o hasta «Tus
        // datos» si falta algo (`#785`). Mientras, «preparando»: la pantalla 0 no se enseña, porque no hay nada que elegir
        // y se podía tocar mientras cargaba. Si al situarla ya no cabe —la hora se llenó— o el servidor dice que no, a la
        // pantalla 0 con lo que quepa y su aviso. ⚠️ FUERA de la cola: `continuar` la espera, y desde dentro se esperaría
        // a sí misma.
        const sola = sigueSola(intencion);

        Object.assign(compra, {
            paso: 'cuando', aviso: '', pedido: null, pagado: null, preparando: sola, sinDatos: false, alEntrar: false,
            // De dónde nace (su flecha, `volverDeLaPantallaCero`): de Mi cuenta (T5f) o del selector de planes (`#831`).
            desde: ['cuenta', 'selector'].includes(intencion?.desde) ? intencion.desde : null,
            desdeHoy: intencion?.desdeHoy === true,
        });
        const situada = enCola(() => situar(borradorDeIntencion(intencion, catalogStore.products)));

        // Si no puede seguir sola (falta un dato, la hora ya no cabe…), la capa va a lo que falta (el owner, 28-09, `ir-a.js`).
        if (sola) situada.then(() => (vista.value?.listo ? continuar() : irA(vista.value?.falta))).finally(() => { compra.preparando = false; });
    }

    watch(() => catalogStore.products, () => applyIntent());
    onMounted(applyIntent);

    // ── Los pasos ────────────────────────────────────────────────────────────────────────────────────

    /** «Continuar» de la pantalla 0: la línea a la cesta (la sustituye, `linea.js`) y la admisión del motor. */
    async function continuar() {
        if (compra.ocupado) return;
        compra.ocupado = 'cuando';
        compra.aviso = '';

        try {
            // El texto del descargo, ya: viaja mientras se valida la línea y se admite, y la casilla de «Tus datos» no
            // aparece tarde empujando el formulario.
            datos.waiverStore.ensureLegal();
            await enCola(() => {});
            const pedido = pedidoDe(compra.borrador, {
                minimo: catalogStore.minQuantity, maximo: timeStore.maxQuantity,
                guardian: catalogStore.product?.guardian_authorization, ...extrasDelPedido(),
            });

            cartStore.setError('');
            const r = await meterLinea({ api, pedido, resueltos: selectionStore.resolved, cartStore, messages: props.messages });

            // La hora se llenó ENTRE elegirla y continuar (`#822`, §4.16): el aviso del diseño con las cercanas y «Elegir
            // esta hora», no un texto en la pantalla 0. El pedido intentado queda como el perdido, para rehacerlo.
            if (! r.ok && r.horaLlena) {
                Object.assign(compra, { pedido, alEntrar: true });
                await alLlenarse(r.aviso);

                return;
            }
            // El «no» del servidor se pinta ARRIBA de la pantalla 0: la capa sube a él (se continúa desde abajo).
            if (! r.ok) { compra.aviso = r.aviso; alPrincipio(); return; }
            compra.pedido = { ...pedido, n: cartStore.lines[0]?.quantity ?? pedido.n };
            await admitir();
        } finally {
            compra.ocupado = null;
        }
    }

    /** La admisión del motor con la línea ya en la cesta, y el paso que toca después (`trasAdmitir`, `#785`). */
    async function admitir() {
        store.go(STEPS.CART);
        await flow.checkout();

        if (store.step !== STEPS.IDENTIFY && store.step !== STEPS.PAY) {
            Object.assign(compra, { paso: 'cuando', aviso: cartStore.error || t(props.messages, 'errors.try_later') });
            alPrincipio();

            return;
        }

        datos.preparar();
        await trasAdmitir();
    }

    /**
     * De «Tus datos» a «Pagar». ⚠️ Si con la sesión ya no falta nada, «Tus datos» SALE DEL CAMINO (`sinDatos`, el owner,
     * 30-09, `#857`): «Pagar» queda como la de quien llegó con sesión —«Reservas como Ana», sin «Paso 2 de 2»— y su flecha
     * vuelve a la reserva, no a una pantalla sin nada que pedir. A «Tus datos» solo se vuelve si falta algo de la cuenta.
     */
    async function aPagarDesdeDatos() {
        Object.assign(compra, { paso: 'pagar', sinDatos: ! await datos.faltaAlgo() });
    }

    /** «Continuar al pago» de «Tus datos». */
    async function continuarDatos() {
        if (compra.ocupado) return;
        compra.ocupado = 'datos';

        try {
            if (await datos.continuar()) await aPagarDesdeDatos();
        } finally {
            compra.ocupado = null;
        }
    }

    /**
     * «Continuar» de «Entra»: al entrar, a «Pagar» si ya no falta nada (`#785`: no se vuelve a un «Hola» vacío); si
     * falta algo —el teléfono, la casilla—, a «Tus datos» con sesión, a pedir solo eso.
     */
    async function entrarDatos() {
        if (compra.ocupado) return;
        compra.ocupado = 'entrar';

        try {
            if (await datos.continuarEntrada() && ! await datos.faltaAlgo()) await aPagarDesdeDatos();
        } finally {
            compra.ocupado = null;
        }
    }

    /** El «no» del pago sobre lo que el comprador debe (el teléfono): a «Tus datos», con el error en su campo. */
    function alPagarMal(errores) {
        Object.assign(compra, { paso: 'datos', sinDatos: false });
        Object.assign(datos.estado, { vista: null, errores: { telefono: errores.phone ?? '' }, aviso: errores.accept_terms ?? '' });
    }

    /**
     * **La hora se llenó al pagar** (T3e·6, `PjcPerdida`). Las horas del día, otra vez del servidor (`cargarHoras`, que
     * además vacía la hora del borrador si ya no cabe), y las cuatro con sitio más cercanas a la perdida. El paso cambia
     * cuando ya están: mientras, «Pagar» sigue esperando. ⚠️ Sin ninguna libre ese día, a la pantalla 0 con el aviso del
     * servidor, que es donde se elige otro día: una pantalla que dice «Estas sí:» y no enseña ninguna mentiría.
     */
    async function alLlenarse(aviso) {
        const perdida = compra.pedido?.hora ?? compra.borrador.hora;

        await enCola(cargarHoras);
        const cercanas = horasCercanas(horasDelSelector(timeStore.offered, { gente: compra.borrador.n, textos }), perdida);

        cartStore.setError('');
        if (cercanas.length === 0) {
            // Al continuar, la línea no llegó a la cesta: se vuelve a la pantalla 0 SIN sacar nada de ella (sus horas, ya
            // recargadas: la llena no se ofrece).
            if (compra.alEntrar) Object.assign(compra, { paso: 'cuando', pedido: null, alEntrar: false });
            else await aCuando();
            compra.aviso = aviso;

            return;
        }
        Object.assign(compra, { paso: 'perdida', cercanas, horaNueva: null, aviso: '' });
    }

    /**
     * «Elegir esta hora»: la línea, rehecha a esa hora (`pago.rehacer`), y de vuelta a «Pagar». Si se llenó al CONTINUAR
     * (`alEntrar`), la admisión que faltaba y el paso que toque, «Tus datos» o «Pagar» (`admitir`). Si también se llenó,
     * otra vez.
     */
    async function elegirHora() {
        if (compra.ocupado || ! compra.horaNueva) return;
        compra.ocupado = 'perdida';

        try {
            const hora = compra.horaNueva;

            if (await enCola(() => pago.rehacer({ hora }))) {
                if (compra.alEntrar) {
                    Object.assign(compra, { cercanas: [], horaNueva: null, alEntrar: false, borrador: { ...compra.borrador, hora } });
                    await admitir();
                } else {
                    Object.assign(compra, { paso: 'pagar', cercanas: [], horaNueva: null });
                }
            } else {
                await alLlenarse(compra.aviso);
            }
        } finally {
            compra.ocupado = null;
        }
    }

    /** La flecha de la hora que se llenó al CONTINUAR: a la pantalla 0, como estaba (la línea no llegó a la cesta). */
    function volverDeLaPerdida() {
        Object.assign(compra, { paso: 'cuando', pedido: null, alEntrar: false, cercanas: [], horaNueva: null, aviso: '' });
    }

    /** Volver de «Tus datos» a la pantalla 0: la línea sale de la cesta, y la pantalla 0 sigue como estaba. */
    async function aCuando() {
        Object.assign(compra, { paso: 'cuando', pedido: null, aviso: '', sinDatos: false });
        cartStore.setError('');
        await flow.removeLine(0);
        // Tras una vuelta de Google la pantalla 0 no se había pintado en esta página: se sitúa entera en su borrador.
        enCola(catalogStore.product?.id === compra.borrador.fila ? cargarHoras : () => situar({ ...compra.borrador }));
    }

    // Lo que la máquina hace sola —el sondeo que caduca, un reintento que ya no puede, el titular que cambió— devuelve
    // a la isla a su pantalla 0, con el aviso del motor. (Lo que la isla hace ella misma limpia antes ese aviso.)
    watch(() => store.step, (ahora, antes) => {
        if (ahora === STEPS.CATALOG && antes !== STEPS.CATALOG && (compra.paso !== 'cuando' || cartStore.error)) {
            Object.assign(compra, { paso: 'cuando', aviso: cartStore.error, pedido: null });
            enCola(cargarHoras);
        }

        if (ahora === STEPS.IDENTIFY && antes === STEPS.DECLINED) {
            datos.preparar();
            Object.assign(compra, { paso: 'datos', sinDatos: false });
        }
    });

    /** La X: cierra la isla. Tras «Listo», la próxima vez se empieza otra compra (el `cerrar()` del diseño). */
    function cerrar() {
        dejarAlCerrar();
        cerrarSuperficie();
        if (store.step === STEPS.CONFIRMED) empezar(null);
    }

    /**
     * Lo que deja, para la isla de la página (`#867`, `pagina/compra-cerrada.js`): a medias, la línea de la reserva; tras
     * «Listo», lo hecho. Se anuncia ANTES de cerrar, así la isla vuelve ya diciéndolo. Si cerrar va a RECARGAR la página
     * (entró en su cuenta aquí dentro, `authChanged`), lo deja también en la pestaña y, a medias, la marca de la vuelta:
     * «Sigue con tu reserva» seguirá por `reanudar()`, como al volver de Google.
     */
    function dejarAlCerrar() {
        const lo = loQueDeja({ paso: paso.value, pedido: compra.pedido, linea: resumen.value.summary, fiesta: paso.value === 'listo' && listo.value.fiesta });

        if (lo && cajonHost()?.authChanged) {
            const { pathname, search } = window.location;
            const vuelta = lo.estado === 'a-medias' ? vueltaDe(pathname, search) : null;
            const ahora = Date.now();

            if (vuelta) marcarSalida(almacenDeLaPestana(), { vuelta, compra: { borrador: compra.borrador, pedido: compra.pedido }, ahora });
            dejar(almacenDeLaPestana(), lo, { ruta: pathname, vuelta, ahora });
        }
        anunciar(lo);
    }

    /**
     * La flecha de la pantalla 0 cuando la abrió Mi cuenta (T5f; el diseño: «la compra vuelve aquí con su flecha»): la
     * capa pasa a Mi cuenta —su inicio, `desde: 'compra'`— y Mi cuenta vuelve al punto del que salió. La compra se queda
     * como estaba: nada se ha metido en la cesta en la pantalla 0.
     */
    const aLaCuenta = () => cajonHost()?.openAccount?.({ preventDefault() {} }, 'home', { desde: 'compra' });

    /**
     * La flecha de la pantalla 0 nacida del SELECTOR de planes (`#831`; `compra.jsx` del diseño: «Volver lo abre otra vez,
     * sin cerrar la isla»): la compra se cierra y el selector se abre tal como estaba (`fromToday`). Tras el cierre, como
     * la bienvenida de Mi cuenta: la isla deja antes la capa de la compra.
     */
    const alSelector = () => {
        const fromToday = compra.desdeHoy;

        cerrar();
        setTimeout(() => window.dispatchEvent(new CustomEvent('isla:abrir', { detail: { panel: 'plans', fromToday } })), 40);
    };

    // ── Lo que se pinta ──────────────────────────────────────────────────────────────────────────────

    // Si el pedido es una FIESTA («niños») o no (una excursión: «personas», T6c·3): lo sabe el pedido mientras se tiene.
    const deLaCesta = (quote) => resumenDeLaCesta(quote, { textos, locale: flow.locale, fiesta: compra.pedido?.fiesta !== false });

    // La foto de la última cesta presupuestada: se vacía al crear el pedido, y «saliendo al banco» sigue enseñándola.
    watch(() => cartStore.quote, (quote) => { if (quote?.lines?.length) compra.pagado = deLaCesta(quote); }, { immediate: true });

    /**
     * Lo de debajo: la línea, el total y la señal de la cesta mientras se compra (también con la hora llena: el diseño
     * la deja a la vista); del pedido, cuando ya existe.
     */
    const resumen = computed(() => {
        // La hora llena al CONTINUAR (`#822`): la cesta aún no tiene la línea; debajo, lo que se estaba eligiendo.
        if (paso.value === 'perdida' && compra.alEntrar) {
            const c = vista.value.ck ?? {};

            return { summary: c.summary ?? null, total: c.total ?? null, today: c.today ?? null };
        }
        if (['datos', 'pagar', 'perdida'].includes(paso.value)) return deLaCesta(cartStore.quote);
        if (outcomeStore.confirmation) {
            return resumenDelPedido(outcomeStore.confirmation, { textos, locale: flow.locale, ...(compra.pedido ? { fiesta: () => compra.pedido.fiesta !== false } : {}) });
        }

        return paso.value === 'banco' ? (compra.pagado ?? {}) : {};
    });

    watch(() => rango(paso.value, datos.estado.vista, datos.estado.ent.paso), (ahora, antes) => { compra.dir = direccion(antes, ahora); });

    const ck = computed(() => {
        if (paso.value === 'cuando') {
            const c = vista.value.ck;

            // «Preparando» (`#785`): lo de debajo, ya el de la cesta si lo hay; la acción, cargando y sin nada que pulsar.
            if (compra.preparando) {
                const cesta = cartStore.quote?.lines?.length ? deLaCesta(cartStore.quote) : {};

                return { ...c, ...cesta, dir: compra.dir, onBack: null, onClose: cerrar, action: { ...c.action, onClick: () => {}, loading: t(textos, 'pieza.cargando') } };
            }

            // Nacida en Mi cuenta (T5f), la flecha vuelve a ella, al mismo punto; nacida del selector (`#831`), lo reabre; si
            // no, no hay nada detrás: solo la X.
            // Sin estar lista, «Continuar» no es un botón muerto: lleva la capa a lo que falta (el owner, 28-09, `ir-a.js`).
            const falta = vista.value.falta;

            return {
                ...c, dir: compra.dir, onBack: volverDeLaPantallaCero(compra.desde, { aLaCuenta, alSelector }), onClose: cerrar,
                action: {
                    ...c.action, disabled: Boolean(c.action.disabled) && ! falta, onClick: vista.value.listo ? continuar : () => irA(falta),
                    loading: compra.ocupado === 'cuando' ? t(textos, 'pieza.cargando') : false,
                },
            };
        }

        return {
            ...ckDelPaso({
                paso: paso.value, vista: datos.estado.vista, entrada: datos.estado.ent, textos, resumen: resumen.value,
                ocupado: compra.ocupado, importe: euros(cartStore.quote?.online_amount_cents ?? 0, flow.locale),
                horaNueva: compra.horaNueva, sinDatos: compra.sinDatos, alEntrar: compra.alEntrar,
                acciones: {
                    cerrar,
                    // De «Pagar» a «Tus datos» si falta algo de la cuenta; si no (`#785`, `#857`), a la pantalla 0. De
                    // la hora llena al continuar (`#822`), a la pantalla 0 sin tocar la cesta.
                    volver: paso.value === 'pagar' ? (compra.sinDatos ? aCuando : () => { compra.paso = 'datos'; })
                        : paso.value === 'perdida' && compra.alEntrar ? volverDeLaPerdida
                            : datos.estado.vista ? datos.volver : aCuando,
                    continuar: continuarDatos, entrar: entrarDatos,
                    pagar: pago.pagar, salir: pago.salir, reintentar: pago.reintentar, elegirHora, miQr: pago.miQr,
                },
            }),
            dir: compra.dir,
        };
    });

    // Google, solo si la instalación lo tiene (`urls.google`); Apple sigue de corchete apagado (`#683`).
    const social = computed(() => ({ social: Boolean(props.urls?.google), apple: false, marcaGoogle: MARCA_GOOGLE }));
    // Las formas de pago (`#784`, `#786`): también del arranque, que no cambia.
    const marcas = marcasDe(props.urls);

    const pantallaDatos = computed(() => ({
        cuenta: datos.cuenta.value,
        nombrePila: datos.contexto.context?.first_name ?? '',
        valores: datos.estado.f,
        firmado: ! datos.firma.value,
        pedirTelefono: datos.pedirTelefono.value,
        errores: datos.estado.errores,
        aviso: datos.estado.aviso,
        // El correo de «Entra» que aún no tiene cuenta (A3, `#849`; la frase del zip (6), Z6g·1): «No hay ninguna cuenta con…».
        nueva: datos.estado.nueva,
        // La pista de la casilla: quién firma (el zip (6), Z6g·2). En ENTRADAS, si la instalación firma dentro, también con una
        // sola —puede ser la de un menor—: la compra no sabe para quién es cada entrada. En una fiesta, solo la primera frase:
        // los invitados firman con la invitación.
        pistaDescargo: pistaDelDescargo(
            (datos.contexto.context?.waiver?.mode ?? datos.waiverStore.legal?.mode) === 'interno' && ! esPack(),
            (clave) => t(textos, clave),
        ),
        // El alta que vuelve de Google (`#785`): su correo, a la vista.
        correoGoogle: datos.estado.google?.email ?? '',
        ...social.value,
    }));

    /**
     * «Entra» (`PjcEntrar`): solo correo (`#695`), luego su código (A3 del acceso con código, `#849`) con «Mantener la
     * sesión iniciada» (`#858`), y Google.
     */
    const pantallaEntrar = computed(() => {
        const { paso: pasoEntrada, valor, codigo, recordar, error, reenvios } = datos.estado.ent;

        return { paso: pasoEntrada, valor, codigo, recordar, error, reenvios, ocupado: compra.ocupado === 'entrar', ...social.value };
    });

    const listo = computed(() => pantallaListo({
        linea: lineaListo(outcomeStore.confirmation, { textos, locale: flow.locale }),
        confirmacion: outcomeStore.confirmation, correo: pago.listo.correo, qrSrc: pago.qrSrc.value, cuentaNueva: pago.listo.cuentaNueva,
        firmaDentro: datos.contexto.context?.waiver?.mode === 'interno', textos, locale: flow.locale,
    }));
    watch(paso, (ahora) => { if (ahora === 'listo') pago.listo.cuentaNueva = datos.cuentaNueva(); }, { immediate: true });

    /**
     * Un desenlace que aún espera lo suyo (el pedido para «Listo», el motivo para el pago no completado) enseña la
     * espera del sistema, no una pantalla a medias. Si no llega, se pinta con lo que haya: nunca una espera eterna.
     */
    const esperando = computed(() => flow.busy.value && (
        (paso.value === 'listo' && ! outcomeStore.confirmation) || (paso.value === 'fallido' && ! outcomeStore.declinedReason)
    ));

    return {
        abierta, textos, ck, paso, esperando, compra, flow, authStore, outcomeStore,
        preparando: computed(() => compra.preparando),
        fiesta: computed(() => Boolean(compra.borrador.fiesta)),
        cuando: computed(() => ({ ...vista.value.props, aviso: compra.aviso })), cambiar,
        datos, pantallaDatos, pantallaEntrar, aGoogle, pago, listo,
        // Las formas de pago, bajo el botón de «Pagar» (`JuntoPagar`, `#786`).
        marcas,
        recibo: computed(() => ({
            ...reciboDe({ quote: cartStore.quote, pedido: compra.pedido, textos, locale: flow.locale }), aviso: compra.aviso,
            // Sin «Tus datos» delante (`#785`), «Pagar» dice con qué cuenta se compra: en un móvil compartido, la última
            // ocasión de verlo (lo que hacía el «Hola, Ana» de «Tus datos»).
            como: compra.sinDatos && datos.contexto.context?.first_name ? tp(textos, 'compra.pagar.como', { nombre: datos.contexto.context.first_name }) : '',
        })),
        fallido: computed(() => ({ hora: outcomeStore.holdUntil, motivo: outcomeStore.declinedReason, aviso: compra.aviso })),
        perdida: computed(() => ({ cercanas: compra.cercanas, horaNueva: compra.horaNueva, alEntrar: compra.alEntrar })),
        elegirNueva: (hora) => { compra.horaNueva = hora; },
        otraReserva: () => empezar(null),
        rotuloOtra: t(textos, 'compra.listo.otra'),
        urls: props.urls ?? {},
        refreshBookingStatus: flow.refreshBookingStatus, refreshIdentity: flow.refreshIdentity,
        openProduct: (id) => { store.machine?.queueIntent?.({ type: 'product', id }); applyIntent(); return true; },
        applyIntent,
    };
}
