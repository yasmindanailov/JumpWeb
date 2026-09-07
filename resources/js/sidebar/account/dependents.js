/**
 * **Los menores a cargo vistos desde el cajón** (Fase 6 · C, `docs/specs/menores-a-cargo.md` §4.1,
 * §4.3, §9.8).
 *
 * Módulo PLANO, sin Vue (`CE-6`): lo que la pantalla necesita decidir sobre lo que publica
 * `GET /me/dependents` —qué frase enseñar de cada uno, si hay algo que firmar en su nombre y cómo
 * se lee su fecha— vive aquí con su `node --test`, y `stores/dependents.js` solo coloca lo que
 * responde el servidor.
 *
 * ⚠️ **El cliente no decide NADA sobre un menor** (`CE-4`): ni su edad, ni si sigue siendo menor,
 * ni si su exención está al día. Lo publica el servidor (`age`, `is_minor`, `adult_from`,
 * `waiver.*`), derivado con el «hoy» del parque; aquí solo se traduce a claves de texto y booleanos.
 * Un cliente que restara fechas por su cuenta cumpliría los 18 dos horas antes o después que la puerta.
 */

import { t, tp } from '../i18n.js';

/**
 * Las opciones de RELACIÓN del titular con el menor (`#236`), en el orden en que se ofrecen.
 *
 * ⚠️ Es la MISMA lista que `Dependent::RELATIONSHIPS` en el servidor, y el servidor es quien manda:
 * aquí solo se pintan. Si alguna vez dejan de coincidir, el alta falla con un 422 por campo —que es
 * la conducta correcta— en vez de guardar una relación que el dominio no reconoce.
 */
export const RELATIONSHIPS = ['father', 'mother', 'legal_guardian', 'grandparent', 'other'];

/**
 * Los campos del formulario de alta, vacíos. `born_on` viaja en `Y-m-d`, que es lo que da
 * `<input type="date">`; `relationship` nace vacío para que el desplegable obligue a elegir en vez
 * de colar un valor por defecto que nadie ha mirado.
 */
export function dependentForm() {
    return { name: '', surname: '', relationship: '', born_on: '' };
}

/**
 * La fecha de nacimiento como la lee el cliente (`dd/mm/aaaa`), a partir del `Y-m-d` del servidor.
 *
 * ⚠️ Sin `Date` a propósito: es una fecha SIN hora y sin zona, y construir un `Date` con ella la
 * movería un día en los husos negativos. Reordenar la cadena no puede equivocarse. Una entrada que
 * no tenga esa forma sale tal cual: enseñar algo raro es mejor que enseñar nada.
 */
export function bornOnLabel(iso) {
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(iso ?? ''));

    return match ? `${match[3]}/${match[2]}/${match[1]}` : String(iso ?? '');
}

/**
 * La clave de `account.dependents.*` que describe la EXENCIÓN de un menor, o `''` fuera del modo
 * interno (ahí no hay nada que decir: la gestiona el parque, y la tarjeta del titular ya lo cuenta).
 *
 *  · sin firma → «sin firmar» · firmada en una versión anterior → «anterior» · vigente → «firmada».
 *
 * @param {{waiver?: {mode?: string, signed?: boolean, outdated?: boolean}}|null} dependent
 */
export function dependentWaiverKey(dependent) {
    const waiver = dependent?.waiver;

    if (waiver?.mode !== 'interno') return '';
    if (waiver.signed !== true) return 'account.dependents.waiver_unsigned';

    return waiver.outdated === true
        ? 'account.dependents.waiver_outdated'
        : 'account.dependents.waiver_current';
}

/**
 * ¿La tarjeta tiene que ofrecer FIRMAR en su nombre? Solo en modo interno, solo si sigue siendo
 * menor (§4.1: a los 18 la exención del adulto ya no le cubre, y el servidor rechazaría la firma), y
 * solo si no hay firma o la que hay es de una versión anterior.
 *
 * @param {{is_minor?: boolean, waiver?: {mode?: string, signed?: boolean, outdated?: boolean}}|null} dependent
 */
export function dependentNeedsSignature(dependent) {
    const waiver = dependent?.waiver;

    return dependent?.is_minor === true
        && waiver?.mode === 'interno'
        && (waiver.signed !== true || waiver.outdated === true);
}

/** Hay algo que firmar y esta cuenta PUEDE firmarlo. */
export const DEPENDENT_WAIVER_SIGN = 'sign';

/** Hay algo que firmar y primero hay que verificar el correo. */
export const DEPENDENT_WAIVER_VERIFY = 'verify';

/**
 * ❗❗❗ **QUÉ SE LE OFRECE al titular en la tarjeta de un menor — `#441`, y es un ESTADO, no un
 * booleano.**
 *
 * `dependentNeedsSignature()` contesta *«¿falta firma?»*, que no es lo mismo que *«¿puede firmarla
 * ahora?»*: `WaiverSigner` exige el correo del titular verificado para firmar por un menor a cargo,
 * y hasta hoy esta pantalla no lo miraba. **Reproducido con control**: la tarjeta ofrecía casilla y
 * botón, y el `POST` respondía **409 `waiver_email_unverified`**; con el correo verificado, 201.
 *
 * ▶ *Es el mismo defecto que `#329` arregló para el waiver del TITULAR* —«un botón que no puede
 * funcionar»— **vivo en la tarjeta del MENOR**, que es la puerta por la que aquella corrección no
 * pasó. Y no es una ventana de dos minutos: se puede iniciar sesión sin verificar, así que quien no
 * abre el correo lo ve cada vez que entra.
 *
 * ⚠️ **`=== false` y no `!`**, igual que {@link accountNoticeFrom}: con el contexto aún sin cargar
 * (`undefined`) se ofrece FIRMAR. Suponer lo peor escondería la acción a quien sí puede hacerla.
 *
 * @param {{is_minor?: boolean, waiver?: object}|null} dependent
 * @param {boolean|undefined} emailVerified  el `email_verified` del contexto de cuenta
 * @returns {'sign'|'verify'|null}
 */
export function dependentWaiverAction(dependent, emailVerified) {
    if (! dependentNeedsSignature(dependent)) return null;

    return emailVerified === false ? DEPENDENT_WAIVER_VERIFY : DEPENDENT_WAIVER_SIGN;
}

/**
 * La clave del aviso de COBERTURA, o `''`. Hoy solo uno: «ya tiene 18 años» (§4.1: la fila
 * sobrevive a la mayoría de edad y se MARCA, no se borra).
 *
 * @param {{is_minor?: boolean}|null} dependent
 */
export function coverageKey(dependent) {
    return dependent?.is_minor === false ? 'account.dependents.adult' : '';
}

/**
 * La lista con un menor sustituido por su versión nueva (tras firmar) — o añadido, si no estaba.
 *
 * @param {Array<{id: number}>} list
 * @param {{id: number}} dependent
 */
export function replaceDependent(list, dependent) {
    const found = (list ?? []).some((row) => row.id === dependent.id);

    return found
        ? (list ?? []).map((row) => (row.id === dependent.id ? dependent : row))
        : [...(list ?? []), dependent];
}

/**
 * **Cuántos menores se pintan de una vez — y por qué esta pantalla SÍ se pagina.**
 *
 * ⚠️⚠️ **La decisión se tomó con el número del servidor delante, no a ojo** (encargo del owner,
 * 2026-08-28: «añade paginación **de ser necesario**»). Lo medido en
 * `App\Domain\Identity\Services\DependentSettings`:
 *  · `DEFAULT_MAX_PER_ACCOUNT = 20` — el tope de una instalación recién instalada;
 *  · `MAX_PER_ACCOUNT_MIN = 1` … **`MAX_PER_ACCOUNT_MAX = 100`** — el rango que el panel admite en
 *    Ajustes (`Filament/Pages/Settings.php`), o sea el techo real que puede llegar a esta pantalla.
 *
 * ▶ **20 por defecto y 100 como techo no es «pequeño»**, así que la lista se pagina. Y hay dos
 * razones más, las dos de dominio y las dos hacen que el número REAL pueda superar al tope vigente:
 *  · **bajar el ajuste no retira a nadie**: el tope lo aplica `DependentRegistry::add()` al declarar
 *    uno nuevo, así que una cuenta con 20 sigue teniendo 20 el día que el parque baja el tope a 5;
 *  · **cumplir 18 no borra la fila** (§4.1: se MARCA, no se borra), así que la lista de una familia
 *    veterana solo crece.
 *
 * ⚠️ **Pero el paginador NO se pinta si no hace falta** (`dependentsPager()` devuelve `null` con una
 * sola página), que es la mitad del encargo que se pierde si uno se queda con «pagina»: la cuenta
 * normal declara dos o tres menores y no puede ver una barra de páginas para tres tarjetas. Es la
 * misma regla que `account/orders.js::pageInfo()` ya aplica en «Mis pedidos».
 *
 * **6 por página**, y el número sale de MEDIR la tarjeta en el cajón, no de redondear. Medido en
 * navegador (cajón a 380 px, pantalla de 820), `DependentCard` tiene **dos regímenes**:
 *  · **196 px** cuando solo informa —nombre, edad, estado de la exención, «Quitar»—, que es el estado
 *    de régimen: la exención firmada no se vuelve a ofrecer;
 *  · **322 px** mientras la exención está sin firmar, porque entonces la tarjeta lleva dentro el
 *    formulario de firma (texto plegable + casilla + botón).
 * ▶ Seis tarjetas son **1.176 px** en el primer régimen —una pantalla y media— y 1.932 en el segundo,
 * que es transitorio. Con veinte serían 3.920 y 6.440: cinco y ocho pantallas, que es exactamente lo
 * que el encargo llama «hay que mejorarla». **El número es un solo `const`**: si el owner lo quiere
 * más corto, se cambia aquí y el paginador se recoloca solo.
 */
export const DEPENDENTS_PER_PAGE = 6;

/**
 * La última página que tiene sentido para `total` filas. **Nunca 0**: una lista vacía sigue estando
 * en la página 1, y devolver 0 dejaría `clampPage()` mandando a una página que no existe.
 *
 * @param {number} total
 * @param {number} perPage
 */
export function lastPageOf(total, perPage = DEPENDENTS_PER_PAGE) {
    const rows = Math.max(0, Math.trunc(Number(total) || 0));
    const size = Math.max(1, Math.trunc(Number(perPage) || DEPENDENTS_PER_PAGE));

    return Math.max(1, Math.ceil(rows / size));
}

/**
 * La página pedida, metida dentro de lo que existe.
 *
 * ⚠️ **Esto no es defensa contra entradas raras: es la conducta de QUITAR.** Con seis por página y
 * siete menores, quitar el séptimo deja la página 2 vacía — y sin recolocar, la pantalla se queda en
 * blanco con una lista que sí tiene seis filas. Lo mismo al cambiar de titular.
 *
 * @param {number} page
 * @param {number} total
 * @param {number} perPage
 */
export function clampPage(page, total, perPage = DEPENDENTS_PER_PAGE) {
    const last = lastPageOf(total, perPage);
    const wanted = Math.trunc(Number(page) || 1);

    return Math.min(Math.max(wanted, 1), last);
}

/**
 * Las filas de una página. Recorta la página ANTES de cortar, así que una página fuera de rango
 * devuelve la última con filas y nunca un array vacío teniendo datos.
 *
 * @param {Array<object>} list
 * @param {number} page
 * @param {number} perPage
 */
export function pageSlice(list, page, perPage = DEPENDENTS_PER_PAGE) {
    const rows = Array.isArray(list) ? list : [];
    const size = Math.max(1, Math.trunc(Number(perPage) || DEPENDENTS_PER_PAGE));
    const current = clampPage(page, rows.length, size);

    return rows.slice((current - 1) * size, current * size);
}

/**
 * El paginador, o **`null` cuando todo cabe en una página**.
 *
 * ⚠️ `null` es la respuesta, no un descuido: es lo que evita que una cuenta con dos menores vea una
 * barra de páginas. Misma forma y mismos rótulos que `orders.js::pageInfo()` —`.pagination` del
 * sitio— para que las tres listas del cajón se paginen igual.
 *
 * @param {number} total  cuántos menores hay EN TOTAL, no en la página
 * @param {number} page
 * @param {object} messages  el grupo `account` del montaje
 * @param {number} perPage
 */
export function dependentsPager(total, page, messages, perPage = DEPENDENTS_PER_PAGE) {
    const last = lastPageOf(total, perPage);

    if (last <= 1) return null;

    const current = clampPage(page, total, perPage);

    return {
        current,
        last,
        canPrev: current > 1,
        canNext: current < last,
        label: t(messages, 'account.dependents.pagination.label'),
        prevLabel: t(messages, 'account.dependents.pagination.prev'),
        nextLabel: t(messages, 'account.dependents.pagination.next'),
        pageLabel: tp(messages, 'account.dependents.pagination.page', { current, last }),
    };
}

/**
 * **El estado de la PANTALLA de menores**: qué página se mira y si el formulario de alta está
 * desplegado. Con sus transiciones, que es lo que hace que valga la pena que viva aquí.
 *
 * ⚠️⚠️ **Las dos transiciones interesantes son las que un `.vue` habría escondido**, y las dos son
 * la misma familia: *«¿en qué página queda el cliente después de esto?»*.
 *  · **`added()`**: al declarar uno, el servidor lo devuelve y `replaceDependent()` lo pone AL FINAL
 *    de la lista. Con la lista paginada, ese final puede estar en otra página — el cliente pulsaría
 *    «Añadir», el formulario se cerraría y **no vería al menor que acaba de declarar**. Por eso el
 *    alta salta a la última página, que es donde ha caído.
 *  · **`removed()`**: quitar el único de la última página deja al cliente en una página vacía.
 *
 * ⚠️ **Objeto con métodos y no funciones sueltas, a propósito**: `CE-6` pide que el componente pinte
 * y no decida, y el techo de `SidebarComponentBudgetTest` son 40 líneas de código. Con el estado en
 * `ref()`s sueltos y estas cuatro transiciones escritas en el `.vue`, la zona se iba a 41. Aquí las
 * cuatro tienen `node --test`; el componente lo envuelve en `reactive()` y solo llama.
 *
 * ⚠️ **No toca el store ni la API**: `perPage` entra por parámetro para poder ejercerlo con listas
 * de tres en las pruebas sin declarar dieciocho menores.
 *
 * @param {number} perPage
 */
export function dependentsView(perPage = DEPENDENTS_PER_PAGE) {
    return {
        perPage,

        /** La página que se mira. Siempre ≥ 1. */
        page: 1,

        /** ¿Está desplegado el formulario de alta? Nace PLEGADO: la pantalla es la lista. */
        adding: false,

        /** Lo que se está tecleando. Vive aquí y no en el store: no es del servidor. */
        form: dependentForm(),

        /**
         * Sube cada vez que el texto firmable se RELEE tras un 409: las tarjetas desmarcan su
         * casilla al verlo cambiar (CAJ-3, `#175`) — lo que se leyó ya no es lo que se firma.
         *
         * ⚠️ Vive aquí y no como un `ref()` suelto en la zona porque **es estado de la PANTALLA**,
         * como la página y el formulario desplegado; y porque el componente tiene techo de líneas
         * (`CE-6`) y un `ref` más lo pasaba. *Subir el techo en vez de colocar el estado donde le
         * toca es exactamente lo que ese gate existe para impedir.*
         */
        reread: 0,

        /** El texto se releyó: las casillas marcadas dejan de valer. */
        rereadDocument() {
            this.reread += 1;
        },

        /** Despliega el alta con el formulario limpio. */
        open() {
            this.adding = true;
            this.form = dependentForm();
        },

        /** Lo pliega y tira lo tecleado: cancelar es cancelar. */
        cancel() {
            this.adding = false;
            this.form = dependentForm();
        },

        /** Tras un alta que SALIÓ: pliega, limpia y salta a la página donde ha caído el nuevo. */
        added(total) {
            this.cancel();
            this.page = lastPageOf(total, this.perPage);
        },

        /** Tras quitar uno: la página en la que estaba puede haberse quedado vacía. */
        removed(total) {
            this.page = clampPage(this.page, total, this.perPage);
        },

        /** Ir a una página, sin salirse de las que existen. */
        go(page, total) {
            this.page = clampPage(page, total, this.perPage);
        },

        /** Las tarjetas que toca pintar ahora. */
        rows(list) {
            return pageSlice(list, this.page, this.perPage);
        },
    };
}
