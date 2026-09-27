/**
 * **«TUS DATOS» de la compra de la isla, sobre el motor** (T3e·3 de `docs/specs/isla-y-landing-nueva.md` §4.10,
 * `DECISIONES #692`).
 *
 * El alta, el acceso y lo que se debe antes de pagar son los del motor (`usePurchaseFlow`: `submitRegister()` con
 * el contexto `purchase` —pay-first—, `submitLogin()`, `buyerDue`), y el descargo, su store. Lo que es de la isla
 * vive aquí: el formulario del diseño (un solo paso para quien no tiene cuenta, «ya existe» al ENVIAR, `#688`), qué
 * pedir con sesión y a dónde llevar cada «no». Las reglas sin estado, en `datos.js` con su `node --test`.
 *
 * ⚠️⚠️ **Con sesión, solo si falta algo** (`#785`, el owner: una pantalla que solo decía «Hola, Ana» era fricción):
 * `faltaAlgo()` decide si se enseña —el teléfono que ese pedido exige, la casilla que esa cuenta nunca firmó— y, si no,
 * la compra va a «Pagar», que dice con qué cuenta se compra. Y la cuenta NUEVA que vuelve de Google completa aquí su alta
 * (`cuenta: 'google'`: su nombre y la casilla, como en Mi cuenta), sin salir a Mi cuenta a mitad de pagar.
 */
import { computed, nextTick, reactive, watch } from 'vue';
import { STEPS } from '../../sidebar/machine.js';
import { api } from '../../sidebar/api.js';
import { t } from '../../sidebar/i18n.js';
import { useWaiverStore } from '../../sidebar/stores/waiver.js';
import { useAccountContextStore } from '../../sidebar/stores/accountContext.js';
import {
    cuentaQueYaExiste, datosVacios, entradaVacia, errorDeEntrar, erroresDelAcceso, erroresDelServidor, firmaPendiente,
    formularioDeAlta, hayQuePedir, revisarDatos,
} from './datos.js';

/** La marca de «la cuenta nace en esta compra», que sobrevive al viaje al banco (misma pestaña) y no lleva datos. */
const CUENTA_NUEVA = 'jw-isla-cuenta-nueva';

/**
 * El alta que vuelve de Google (`account/google.js`), pedida SOLO al reanudar (`#785`): es rara, y en el trozo de la compra
 * la bajaría quien abre cualquier compra (medido: +1,18 KiB).
 */
const altaDeGoogle = () => import('../../sidebar/account/google.js');

export function useDatosCompra({ flow, props, textos, esFiesta = () => false }) {
    const { store, authStore, cartStore, buyerDue } = flow;
    const waiverStore = useWaiverStore();
    const contexto = useAccountContextStore();
    /**
     * `f`, el formulario; `vista`, lo que se abre dentro del paso (`descargo` · `entrar`); `ent`, «Entra» y su olvido
     * (`datos.js::entradaVacia`); `token`, el del anti-bot; `google`, el perfil que espera en la sesión tras volver de
     * Google sin cuenta (`{ name, email }`, `#785`).
     */
    const estado = reactive({ f: datosVacios(), errores: {}, aviso: '', vista: null, ent: entradaVacia(), token: '', google: null });
    const aviso = (clave) => t(props.messages, clave);

    // Con sesión manda la sesión; sin ella, el alta que vuelve de Google o lo que diga el alta («nueva», o «existe»).
    const cuenta = computed(() => (contexto.context ? 'dentro' : (estado.google ? 'google' : estado.f.cuenta)));
    const firma = computed(() => firmaPendiente({ cuenta: cuenta.value, contexto: contexto.context, documento: waiverStore.document }));
    /**
     * El teléfono, SOLO en una fiesta (`#787`, el owner: «obligatorio solo para reservas de cumpleaños»): al darse de alta
     * (con correo o con Google), o con sesión si la cuenta no lo tiene (`phone_missing`). Y siempre que el servidor lo pida
     * al pagar (`buyerDue.errors.phone`), que es la autoridad.
     */
    const pedirTelefono = computed(() => 'phone' in (buyerDue.errors ?? {})
        || (esFiesta() && (cuenta.value === 'dentro' ? flow.buyerNeed.value.phone : cuenta.value !== 'existe')));

    /** Al llegar desde la pantalla 0: el formulario en blanco y el texto del descargo pedido ya. */
    function preparar() {
        Object.assign(estado, { f: datosVacios(), errores: {}, aviso: '', vista: null, ent: entradaVacia(), google: null });
        waiverStore.ensureLegal();
    }

    /** El texto del descargo, ya llegado (o fallado): de él depende si hay casilla. */
    function conTextoDelDescargo() {
        waiverStore.ensureLegal();

        return new Promise((listo) => {
            if (! waiverStore.legalLoading) return listo();
            const parar = watch(() => waiverStore.legalLoading, (cargando) => { if (! cargando) { parar(); listo(); } });

            return null;
        });
    }

    /**
     * ¿«Tus datos» tiene algo que pedir? (`datos.js::hayQuePedir`). Con la admisión ya hecha: `PAY` es que hay sesión. Se
     * espera al texto del descargo, sin el que no se sabe si hay casilla que marcar.
     */
    async function faltaAlgo() {
        if (store.step !== STEPS.PAY) return true;
        await conTextoDelDescargo();

        return hayQuePedir({ identificado: true, pedirTelefono: pedirTelefono.value, firma: firma.value });
    }

    /**
     * La cuenta NUEVA que volvió de Google (`#785`): su perfil espera en la sesión del servidor y aquí se pide para
     * completar el alta en «Tus datos». Solo si la instalación ofrece Google. Devuelve si lo había.
     */
    async function altaGooglePendiente() {
        if (! props.urls?.google) return false;
        const pendiente = await altaDeGoogle().then(({ loadGooglePending }) => loadGooglePending({ api })).catch(() => null);

        if (pendiente === null) return false;
        estado.google = pendiente;
        if (! estado.f.nombre) estado.f.nombre = pendiente.name;

        return true;
    }

    /**
     * Completa el alta de Google (`account/google.js`, la secuencia de Mi cuenta y del cajón) y entra como tras
     * cualquier alta (`flow.enterWith`: el contexto de cuenta, la cesta, los menores y la admisión). Un perfil que ya
     * caducó vuelve al formulario de siempre con su aviso; un descargo republicado, a releer y marcar otra vez.
     */
    async function altaGoogle() {
        const { runGoogleSignup } = await altaDeGoogle();
        const r = await runGoogleSignup({
            form: { name: estado.f.nombre.trim(), accept_waiver: estado.f.descargo === true },
            api, waiver: waiverStore.document, messages: props.messages, auth: props.auth,
        });

        if (r.ok) {
            try { window.sessionStorage.setItem(CUENTA_NUEVA, '1'); } catch { /* sin almacenamiento: «Listo» no lo dirá */ }
            // El teléfono de una fiesta (`#787`): Google no lo da y su alta no lo acepta; va con el pago, como con sesión.
            if (pedirTelefono.value) buyerDue.phone = estado.f.telefono.trim();
            // La RESPUESTA entera de `GET /me`, como la del alta (`register.js`: `me`): de ella lee la cesta su titular.
            const yo = await api.get('/me');

            if (! yo?.ok) return fallar({}, aviso('errors.try_later'));
            await flow.enterWith(yo);

            return trasIdentificarse();
        }

        if (r.expired) {
            estado.google = null;

            return fallar({}, t(textos, 'compra.datos.google_caducada'));
        }

        if (r.stale) {
            estado.f.descargo = false;
            await waiverStore.reloadLegal({ api });

            return fallar({ descargo: t(textos, 'compra.datos.errores.descargo_nuevo') });
        }

        const { errores, resto } = erroresDelServidor(r.errors?.fields);

        return fallar(errores, resto[0] ?? (Object.keys(errores).length ? '' : r.errors?.summary?.[0] ?? ''));
    }

    function cambiar(campo, valor) {
        estado.f[campo] = valor;
        if (estado.errores[campo]) estado.errores = { ...estado.errores, [campo]: '' };
    }

    /** Con errores, el foco al primero: en el móvil, el campo en rojo podía quedar fuera de la vista (el diseño). */
    function enfocarElPrimero() {
        nextTick(() => document.querySelector('[data-isla-scroll] [aria-invalid="true"]')?.focus());
    }

    function fallar(errores, texto = '') {
        Object.assign(estado, { errores, aviso: texto });
        enfocarElPrimero();

        return false;
    }

    /**
     * Ya hay sesión: la compra sigue en `PAY`. ⚠️ Con menores asignables el motor vuelve a la cesta a asignarlos
     * (puerta 2, `DECISIONES #202`); la isla los deja para DESPUÉS de pagar (`compra.datos.linea`), así que sigue.
     * Y si a esa cuenta le falta el teléfono o nunca firmó, se queda aquí a pedirlo (`entrarCon` del diseño).
     */
    async function trasIdentificarse() {
        if (store.step === STEPS.CART && cartStore.notice === 'assign') {
            cartStore.setNotice('');
            await flow.checkout();
        }

        if (store.step !== STEPS.PAY) return fallar({}, cartStore.error || aviso('errors.try_later'));

        // El teléfono que ya se tecleó en el alta de Google (`#787`: en una fiesta va en el mismo paso) no se vuelve a pedir.
        const faltaTelefono = pedirTelefono.value && ! String(buyerDue.phone ?? '').trim();

        return ! (faltaTelefono || firma.value);
    }

    async function alta() {
        Object.assign(authStore.form, formularioDeAlta(estado.f), { turnstile_token: estado.token });
        const r = await flow.submitRegister();

        if (r?.ok && r.identified) {
            try { window.sessionStorage.setItem(CUENTA_NUEVA, '1'); } catch { /* sin almacenamiento: «Listo» no lo dirá */ }

            return trasIdentificarse();
        }

        // El token del anti-bot es de un solo uso y el servidor lo quema antes de mirar el correo: vacío = pide otro.
        estado.token = '';

        // Un 201 sin sesión es el señuelo (`register.js`), que la isla no puede disparar —no pinta su campo—: la
        // máquina quedó en «revisa tu correo», sin salida; se devuelve a «Tus datos» con el aviso genérico.
        if (r?.ok) {
            store.enter(STEPS.IDENTIFY);

            return fallar({}, aviso('errors.try_later'));
        }

        const e = r?.errors ?? authStore.registerError;

        if (cuentaQueYaExiste(e?.signup)) {
            estado.f.cuenta = 'existe';

            return fallar({});
        }

        const { errores, resto } = erroresDelServidor(e?.fields);

        if (errores.descargo) estado.f.descargo = false;

        return fallar(errores, resto[0] ?? (Object.keys(errores).length ? '' : e?.summary?.[0] ?? ''));
    }

    async function entrar() {
        Object.assign(authStore.form, { email: estado.f.correo.trim(), password: estado.f.contrasena });
        const r = await flow.submitLogin();

        if (r?.ok) return trasIdentificarse();

        const { errores, aviso: texto } = erroresDelAcceso(r, textos);

        return fallar(errores, texto);
    }

    /** Con sesión: el teléfono va con el pago (`buyerDue`, `#349`) y la firma, ahora (`POST /me/waiver`). */
    async function deDentro() {
        if (pedirTelefono.value) buyerDue.phone = estado.f.telefono.trim();

        if (firma.value) {
            if (! await waiverStore.accept({ messages: props.messages, auth: props.auth })) {
                estado.f.descargo = false;

                return fallar({ descargo: waiverStore.notice || t(textos, 'compra.datos.errores.descargo') });
            }

            await contexto.refresh?.();
        }

        if (store.step === STEPS.CART) await flow.checkout();

        return store.step === STEPS.PAY ? true : fallar({}, cartStore.error || aviso('errors.try_later'));
    }

    /** «Continuar al pago». Devuelve si se puede pasar a «Pagar». */
    async function continuar() {
        const f = { ...estado.f, cuenta: cuenta.value };
        const errores = revisarDatos(f, { pedirTelefono: pedirTelefono.value, firmaPendiente: firma.value, textos });

        if (Object.keys(errores).length) return fallar(errores);

        Object.assign(estado, { errores: {}, aviso: '' });
        if (f.cuenta === 'dentro') return deDentro();
        if (f.cuenta === 'google') return altaGoogle();

        return f.cuenta === 'existe' ? entrar() : alta();
    }

    /**
     * «¿Has olvidado tu contraseña?»: el enlace al correo, y la confirmación que no dice si existe (`SEC-06`). Desde
     * «Esta cuenta ya existe» (`solo`), con el correo del formulario, y su «volver» regresa a «Tus datos»; desde
     * «Entra», con el suyo, y vuelve a «Entra» (`PjcEntrar` del diseño).
     */
    async function olvido({ correo, solo }) {
        authStore.form.email = String(correo ?? '').trim();
        const r = await authStore.requestPasswordLink({ api, messages: props.messages, auth: props.auth });

        if (r?.sent) {
            Object.assign(estado, { vista: 'entrar', ent: { ...entradaVacia(authStore.form.email), paso: 'olvido', solo } });

            return;
        }

        const error = r?.errors?.fields?.email || r?.errors?.global || '';

        if (solo) fallar(r?.errors?.fields?.email ? { correo: error } : {}, r?.errors?.fields?.email ? '' : error);
        else estado.ent.error = error;
    }

    /** Lo que avisa «Tus datos» de «Entra»: abrirlo (con el correo que ya hubiera) o, desde «ya existe», el olvido. */
    function abrirEntrar(modo) {
        if (modo === 'olvido') return olvido({ correo: estado.f.correo, solo: true });

        Object.assign(estado, { vista: 'entrar', ent: entradaVacia(estado.f.correo.trim()), errores: {}, aviso: '' });

        return null;
    }

    function cambiarEntrada(campo, valor) {
        estado.ent[campo] = valor;
        estado.ent.error = '';
    }

    /**
     * «Continuar» de «Entra»: el acceso del motor. Al entrar se vuelve a «Tus datos» ya con sesión —«Hola» y solo lo
     * que falte—, como el diseño (`PjcEntrar`: «al entrar vuelve a Tus datos con todo relleno»), y la contraseña
     * no se queda en memoria.
     */
    async function entrarConClave() {
        Object.assign(authStore.form, { email: estado.ent.valor.trim(), password: estado.ent.clave });
        const r = await flow.submitLogin();

        if (! r?.ok) {
            estado.ent.error = errorDeEntrar(r, textos);

            return false;
        }

        Object.assign(estado, { vista: null, ent: entradaVacia() });
        await trasIdentificarse();

        return true;
    }

    /** La flecha dentro del paso: del olvido pedido en «Entra», a «Entra»; de lo demás, a «Tus datos». */
    function volver() {
        if (estado.vista === 'entrar' && estado.ent.paso === 'olvido' && ! estado.ent.solo) {
            estado.ent.paso = 'id';

            return;
        }

        Object.assign(estado, { vista: null, ent: entradaVacia() });
    }

    /** Si «Listo» debe decir que la cuenta se creó en esta compra. Se lee UNA vez. */
    function cuentaNueva() {
        try {
            const nueva = window.sessionStorage.getItem(CUENTA_NUEVA) === '1';

            window.sessionStorage.removeItem(CUENTA_NUEVA);

            return nueva;
        } catch {
            return false;
        }
    }

    return {
        estado, cuenta, firma, pedirTelefono, contexto, waiverStore,
        preparar, cambiar, continuar, olvido, abrirEntrar, cambiarEntrada, entrarConClave, volver, cuentaNueva,
        faltaAlgo, altaGooglePendiente,
    };
}
