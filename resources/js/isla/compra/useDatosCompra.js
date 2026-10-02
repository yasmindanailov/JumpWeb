/**
 * **«TUS DATOS» de la compra de la isla, sobre el motor** (T3e·3 de `docs/specs/isla-y-landing-nueva.md` §4.10,
 * `DECISIONES #692`).
 *
 * El alta, el acceso y lo que se debe antes de pagar son los del motor (`usePurchaseFlow`: `submitRegister()` con
 * el contexto `purchase` —pay-first—, `enterWith()` tras entrar con el código, `buyerDue`), y el descargo, su store. Lo que es de la isla
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
    cuentaQueYaExiste, datosVacios, entradaVacia, erroresDelServidor, firmaPendiente, formularioDeAlta, hayQuePedir,
    nacimientoDeAlta, revisarDatos,
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
    const estado = reactive({ f: datosVacios(), errores: {}, aviso: '', nota: '', vista: null, ent: entradaVacia(), token: '', google: null });
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
        Object.assign(estado, { f: datosVacios(), errores: {}, aviso: '', nota: '', vista: null, ent: entradaVacia(), google: null });
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
            form: { name: estado.f.nombre.trim(), born_on: nacimientoDeAlta(estado.f.nacimiento) ?? '', accept_waiver: estado.f.descargo === true },
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

    /**
     * **Entrar con un código al correo** (A3 del acceso con código, `#848`/`#849`). La puerta, entrar y sus «no» viajan con
     * los pasos (`acceso.js`): este `import()` es del trozo que la compra ya pidió al montarse, así que no espera a la red, y
     * la compra no paga su peso.
     */
    const acceso = () => import('./pasos-diferidos.js').then((m) => m.acceso);
    const noDelCodigo = async (r) => (await acceso()).erroresDelCodigo(r, textos, aviso('errors.try_later'));

    /**
     * **LA PUERTA** (`#849`): ¿tiene cuenta este correo? Con cuenta, el servidor le manda ya el código (`next: code`); nuevo,
     * `register`. El límite del correo dice `next: code` también: hay uno recién enviado.
     */
    async function puerta(correo) {
        return (await acceso()).puerta(api, correo);
    }

    /**
     * ENTRAR con el código (`POST /auth/login`): el servidor abre la sesión —recordada 90 días solo con la casilla,
     * `recordar`, `#858`— y devuelve el perfil, y se entra como tras cualquier otra puerta (`flow.enterWith`, como el
     * alta de Google).
     */
    async function entrarConCodigo(correo, codigo, recordar) {
        const r = await (await acceso()).entrar(api, correo, codigo, recordar);

        if (! r.ok) return { ok: false, r };
        await flow.enterWith(r);

        return { ok: true, r };
    }

    /** «Tus datos» con una cuenta que YA EXISTE: su código va de camino (o acaba de ir) y se pide aquí mismo. */
    async function aSuCodigo(r) {
        estado.f.cuenta = 'existe';
        estado.f.codigo = '';

        return r.ok || r.error?.params?.next ? fallar({}) : fallar({}, (await noDelCodigo(r)).aviso);
    }

    async function alta() {
        // La puerta primero (`#849`): si el correo ya tiene cuenta, se le manda el código y se pide aquí, sin intentar el
        // alta (que avisaría al titular de «alguien intentó registrarse» por nada).
        const { siguiente, r: rp } = await puerta(estado.f.correo);

        if (siguiente === 'code') return aSuCodigo(rp);
        if (! rp.ok) return fallar({}, (await noDelCodigo(rp)).aviso);

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

        // Otra pestaña la creó entre la puerta y el alta: a su código, como si la puerta lo hubiera dicho.
        if (cuentaQueYaExiste(e?.signup)) return aSuCodigo((await puerta(estado.f.correo)).r);

        const { errores, resto } = erroresDelServidor(e?.fields);

        if (errores.descargo) estado.f.descargo = false;

        return fallar(errores, resto[0] ?? (Object.keys(errores).length ? '' : e?.summary?.[0] ?? ''));
    }

    /** «Esta cuenta ya existe»: entra con el código que le llegó. */
    async function entrar() {
        const { ok, r } = await entrarConCodigo(estado.f.correo, estado.f.codigo, estado.f.recordar);

        if (ok) return trasIdentificarse();

        const { errores, aviso: texto } = await noDelCodigo(r);

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

        Object.assign(estado, { errores: {}, aviso: '', nota: '' });
        if (f.cuenta === 'dentro') return deDentro();
        if (f.cuenta === 'google') return altaGoogle();

        return f.cuenta === 'existe' ? entrar() : alta();
    }

    /**
     * «Pedir otro código»: desde «Entra» (con su correo) o desde «Esta cuenta ya existe» (con el del formulario). El nuevo
     * anula el anterior; el límite (uno por minuto) dice cuánto esperar, en la línea de error de cada sitio.
     */
    async function otroCodigo() {
        const enEntrar = estado.vista === 'entrar';
        const { r } = await puerta(enEntrar ? estado.ent.valor : estado.f.correo);
        const error = r.ok ? '' : (await noDelCodigo(r)).aviso;

        if (enEntrar) {
            Object.assign(estado.ent, { error, codigo: '', reenvios: estado.ent.reenvios + (r.ok ? 1 : 0) });
        } else if (error) {
            fallar({}, error);
        } else {
            Object.assign(estado, { errores: {}, aviso: '' });
            estado.f.codigo = '';
        }
    }

    /** Lo que avisa «Tus datos» de «Entra»: abrirlo (con el correo que ya hubiera) o, desde «ya existe», otro código. */
    function abrirEntrar(modo) {
        if (modo === 'otro') return otroCodigo();

        Object.assign(estado, { vista: 'entrar', ent: entradaVacia(estado.f.correo.trim()), errores: {}, aviso: '' });

        return null;
    }

    function cambiarEntrada(campo, valor) {
        estado.ent[campo] = valor;
        estado.ent.error = '';
    }

    /**
     * «Continuar» de «Entra». Con el CORREO, la puerta: con cuenta, a su código (que ya va de camino); nuevo, a «Tus datos»
     * con el correo puesto, a darse de alta (`#849`). Con el CÓDIGO, entrar: se vuelve a «Tus datos» ya con sesión —«Hola»
     * y solo lo que falte—, como el diseño (`PjcEntrar`: «al entrar vuelve a Tus datos con todo relleno»). Devuelve si
     * ya hay sesión.
     */
    async function continuarEntrada() {
        if (estado.ent.paso === 'id') {
            const { siguiente, r } = await puerta(estado.ent.valor);

            // El límite del correo también dice `code`: hay uno recién enviado, que se escribe igual (sin error).
            if (siguiente === 'code') {
                Object.assign(estado.ent, { paso: 'codigo', codigo: '', error: '' });
            } else if (siguiente === 'register') {
                // Una nota NEUTRA, no un error: no ha hecho nada mal, solo aún no tiene cuenta.
                const correo = estado.ent.valor.trim();
                Object.assign(estado, { vista: null, ent: entradaVacia(), errores: {}, aviso: '', nota: t(textos, 'compra.datos.nueva') });
                Object.assign(estado.f, { correo, cuenta: 'nueva' });
            } else {
                estado.ent.error = (await acceso()).errorDeEntrar(r, textos, aviso('errors.try_later'));
            }

            return false;
        }

        const { ok, r } = await entrarConCodigo(estado.ent.valor, estado.ent.codigo, estado.ent.recordar);

        if (! ok) {
            estado.ent.error = (await acceso()).errorDeEntrar(r, textos, aviso('errors.try_later'));

            return false;
        }

        Object.assign(estado, { vista: null, ent: entradaVacia() });
        await trasIdentificarse();

        return true;
    }

    /** La flecha dentro del paso: del código de «Entra», a su correo; de lo demás, a «Tus datos». */
    function volver() {
        if (estado.vista === 'entrar' && estado.ent.paso === 'codigo') {
            Object.assign(estado.ent, { paso: 'id', codigo: '', error: '' });

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
        preparar, cambiar, continuar, otroCodigo, abrirEntrar, cambiarEntrada, continuarEntrada, volver, cuentaNueva,
        faltaAlgo, altaGooglePendiente,
    };
}
