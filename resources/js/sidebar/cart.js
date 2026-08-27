/**
 * La CESTA del cajón (Fase 4 · paso 4.3·2).
 *
 * Módulo plano, sin Vue y **sin tocar el almacén del navegador**: la persistencia en `localStorage`
 * llega en 4.3·3 y se le pasará el almacén por parámetro. Aquí no puede haber ni un `localStorage`
 * global, y no es purismo: en el Node del contenedor —el que corre `npm run test:js` y el
 * renderizador SSR del gate— `typeof localStorage === 'undefined'` (medido), así que un módulo que lo
 * leyera del global no se podría probar.
 *
 * ⚠️ **Aquí no se decide nada de negocio** (`CE-4`). Si una línea cabe, con cuántas unidades entra y
 * con cuál se funde lo dice `POST /cart/validate-line`; qué vale la cesta lo dice
 * `POST /orders/quote`. Este módulo aplica esas respuestas a una lista.
 *
 * **La línea se guarda con el vocabulario de la API** (`product_id`, `quantity`) y no con el del
 * dominio (`ticket_type_id`, `qty`): la cesta viaja a tres endpoints con esa forma —disponibilidad,
 * validación y presupuesto—, y guardarla de otra manera obligaría a traducir en cada viaje.
 */

/**
 * Una línea de la cesta, en la forma que aceptan `availability/{id}/times`, `cart/validate-line`,
 * `orders/quote` y `POST orders`.
 *
 * ⚠️ **La hora se guarda canónica (`HH:MM:SS`)**, no como se pinta. El dominio compara franjas por
 * CADENA, así que una hora sin segundos no casa con ninguna y falla EN SILENCIO: presupuesto sin
 * líneas en vez de error.
 *
 * @typedef {{product_id: number, date: string, time: string, quantity: number, event_data: object, addons: Array<{product_id: number, quantity: number}>}} CartLine
 */

/**
 * Añade una línea, o la funde con la que el servidor haya señalado.
 *
 * ⚠️ **La fusión NO se decide aquí**: `merges_with_index` viene en el veredicto y solo ocurre entre
 * entradas del mismo producto, día y hora **sin complementos** —un pack es siempre su propio bloque,
 * porque fundirlo mezclaría dos fiestas y cambiaría señal y ocupación—. Un cliente que la reinventara
 * crearía una línea duplicada donde el servidor habría sumado cantidades.
 *
 * ⚠️ Y la cantidad que entra es la **efectiva** del veredicto, no la que se pidió: el servidor recorta
 * al cupo en silencio desde siempre (anti-manipulación), y pintar la pedida enseña una reserva que no
 * se tiene.
 *
 * @param {CartLine[]} cart
 * @param {CartLine} line
 * @param {{quantity: number, merges_with_index: number|null}} verdict
 * @returns {CartLine[]} una cesta NUEVA
 */
export function addLine(cart, line, verdict) {
    const quantity = verdict.quantity;
    const target = verdict.merges_with_index;

    // Los menores asignados (Fase 6 · tanda 4, `menores-a-cargo.md` §9.9.3 D8): al FUNDIR se unen los
    // de las dos líneas —los de la existente primero— y al RECORTAR la cantidad se recortan con ella:
    // nunca más menores que unidades, también cuando es el servidor quien decide cuántas entran.
    const requested = Array.isArray(line.dependent_ids) ? line.dependent_ids.map(Number) : [];

    if (target !== null && target !== undefined && cart[target] !== undefined) {
        return cart.map((existing, index) => (
            index === target
                ? {
                    ...existing,
                    quantity: existing.quantity + quantity,
                    dependent_ids: [...new Set([...(existing.dependent_ids ?? []).map(Number), ...requested.slice(0, quantity)])],
                }
                : existing
        ));
    }

    return [...cart, { ...line, quantity, dependent_ids: requested.slice(0, quantity) }];
}

/**
 * Quita la línea que ocupa esa posición.
 *
 * ⚠️ **El índice es el de la CESTA, no el ordinal de lo pintado.** El presupuesto salta las líneas
 * cuyo producto dejó de venderse y conserva el índice original, así que la segunda línea que se ve
 * puede ser la número 3. Quitar por ordinal borra otra reserva.
 *
 * @param {CartLine[]} cart
 * @param {number} index
 * @returns {CartLine[]}
 */
export function removeLine(cart, index) {
    return cart.filter((_, position) => position !== index);
}

/**
 * La cesta en la forma que viaja a la API.
 *
 * `event_data` viaja —el servidor lo necesita para crear el pedido y lo sanea él— pero **no vuelve
 * nunca** en ninguna respuesta: son datos personales de un menor y los endpoints son públicos.
 *
 * @param {CartLine[]} cart
 * @returns {Array<object>}
 */
export function toApiItems(cart) {
    return cart.map((line) => ({
        product_id: line.product_id,
        date: line.date,
        time: line.time,
        quantity: line.quantity,
        ...(Object.keys(line.event_data ?? {}).length > 0 ? { event_data: line.event_data } : {}),
        ...(line.addons?.length > 0 ? { addons: line.addons } : {}),
    }));
}

/**
 * La cesta en la forma que viaja a `POST /orders`, y SOLO ahí: la de siempre más los menores a cargo
 * de cada línea (Fase 6 · tanda 4, `menores-a-cargo.md` §9.9.3 D1/D8).
 *
 * ⚠️ `toApiItems()` no los lleva a propósito: alimenta la disponibilidad, la validación de línea y el
 * presupuesto, tres endpoints públicos que no tienen titular contra el que comprobar nada. Los ids son
 * punteros opacos, pero mandarlos donde nadie los lee es ruido en la ruta de más tráfico.
 *
 * @param {CartLine[]} cart
 * @returns {Array<object>}
 */
export function toCheckoutItems(cart) {
    return toApiItems(cart).map((item, index) => {
        const ids = Array.isArray(cart[index]?.dependent_ids) ? cart[index].dependent_ids.map(Number) : [];

        return ids.length > 0 ? { ...item, dependent_ids: ids } : item;
    });
}

/** El día de HOY en el huso del navegador (`Y-m-d`), para caducar las líneas de días pasados. */
export function todayIso(now = new Date()) {
    const pad = (n) => String(n).padStart(2, '0');

    return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
}

/**
 * Las filas que el carrito PINTA, emparejando cada línea tarificada con las respuestas del pack que
 * el cliente tiene en memoria.
 *
 * ⚠️ **El emparejamiento es por `index`, no por posición en el array.** Una línea cuyo producto ya no
 * se vende **no se tarifica** y desaparece de la respuesta; el hueco en la secuencia de `index` es la
 * única señal de que existió. Recorrer las dos listas en paralelo pinta los precios de una línea
 * sobre otra.
 *
 * Las respuestas del evento se emparejan con su etiqueta aquí porque el presupuesto **no las
 * devuelve** (RGPD: son datos de un menor y el endpoint es público) y las etiquetas están en
 * `GET catalog/products/{id}`. El emparejado es el mismo que hace `TicketType::eventAnswers()`: orden
 * del ESQUEMA y fuera las vacías.
 *
 * Y los menores asignados a la línea (Fase 6 · tanda 4): en la cesta viajan como IDS, y el nombre lo
 * pone aquí el mapa de `GET /me/dependents` que el titular tiene en memoria — el almacén nunca lo ve.
 *
 * @param {Array<object>} quoteLines  `lines` de `POST /orders/quote`
 * @param {CartLine[]} cart
 * @param {Record<number, Array<{key: string, label: string}>>} fieldsByProduct
 * @param {Record<number, {id: number, name: string}>} dependentsById  ver `assignment.js::dependentsById`
 * @returns {Array<object>}
 */
export function cartRows(quoteLines, cart, fieldsByProduct = {}, dependentsById = {}) {
    return quoteLines.map((line) => {
        const ids = Array.isArray(cart[line.index]?.dependent_ids) ? cart[line.index].dependent_ids.map(Number) : [];

        return {
            ...line,
            event: eventAnswers(fieldsByProduct[line.product_id] ?? [], cart[line.index]?.event_data ?? {}),
            // Lo que hay que volver a pedir para que esta línea se pueda comprar (Fase 4 · paso 4.5·1).
            pending: pendingEventFields(fieldsByProduct[line.product_id] ?? [], cart[line.index]?.event_data ?? {}),
            dependent_ids: ids,
            dependents: ids.map((id) => dependentsById[id]).filter(Boolean),
        };
    });
}

/**
 * Los campos OBLIGATORIOS del pack que esta línea todavía no tiene contestados
 * (Fase 4 · paso 4.5·1).
 *
 * ⚠️ **Esto ENUMERA, no valida, y la distinción es la regla `#38(f)`.** Quién decide si una respuesta
 * vale es el servidor, y no por ceremonia: `sanitizeEventData()` aplica `preg_replace('/\D+/', '')` a
 * los campos `number`, así que una edad contestada «cinco» el servidor **la ve vacía** y cualquier
 * validación ingenua en el cliente la ve contestada. Copiar esa regla aquí era exactamente lo que
 * aquella decisión prohibió. Lo que sí puede saber el cliente —y no es negocio— es si hay algo
 * escrito o no hay nada.
 *
 * **Por qué hace falta**: la cesta persistida vuelve SIN `event_data` (`#38(d)`, RGPD: son el nombre
 * de un menor, su edad y sus alergias), así que una línea de pack restaurada está incompleta **por
 * construcción**. El presupuesto la tarifica igual —medido: 200 con su total correcto—, de modo que
 * sin esto el cliente ve una cesta perfecta y el fallo aparece al final del embudo, al pagar, con un
 * 422 `line_event_required` que no puede arreglar desde ninguna pantalla.
 *
 * @param {Array<{key: string, label: string, required?: boolean, type?: string}>} fields  esquema del producto
 * @param {Record<string, unknown>} answers  lo contestado hasta ahora
 * @returns {Array<{key: string, label: string, required: boolean, type: string}>}
 */
export function pendingEventFields(fields, answers) {
    return (Array.isArray(fields) ? fields : [])
        .filter((field) => field?.required === true)
        .filter((field) => {
            const value = answers?.[field.key];

            return value === null || value === undefined || typeof value === 'object' || String(value).trim() === '';
        })
        .map((field) => ({
            key: field.key,
            label: field.label ?? '',
            required: true,
            type: field.type ?? 'text',
        }));
}

/**
 * ¿Hay alguna línea de la cesta que no se pueda comprar todavía por falta de respuestas?
 *
 * Es la guarda que impide llevar al pago una cesta que el servidor va a rechazar. Recorre las FILAS
 * ya compuestas —no la cesta cruda— porque el emparejado por `index` con el presupuesto es lo que
 * dice qué línea se pinta de verdad.
 *
 * @param {Array<{pending?: Array<object>}>} rows  filas de `cartRows()`
 */
export function hasPendingEventFields(rows) {
    return (Array.isArray(rows) ? rows : []).some((row) => (row?.pending ?? []).length > 0);
}

/**
 * Respuestas del evento emparejadas con su etiqueta, en el orden del esquema y sin las vacías.
 *
 * Espejo de `TicketType::eventAnswers()`, que **no sanea**: sanear es del servidor y ocurre al crear
 * el pedido. Lo que este emparejado sí respeta es su criterio de «vacío», que es el que decide qué
 * fila se pinta.
 *
 * @param {Array<{key: string, label: string}>} fields
 * @param {Record<string, unknown>} answers
 * @returns {Array<{key: string, label: string, value: string}>}
 */
export function eventAnswers(fields, answers) {
    return fields
        .map((field) => ({ key: field.key, label: field.label, value: answers?.[field.key] }))
        .filter((row) => row.value !== null && row.value !== undefined && typeof row.value !== 'object' && String(row.value) !== '')
        .map((row) => ({ ...row, value: String(row.value) }));
}

// ── La PERSISTENCIA (Fase 4 · paso 4.3·4) ─────────────────────────────────────────────────────
//
// ⚠️ **El almacén entra por PARÁMETRO y nunca se lee del global**, y no es purismo: en el Node del
// contenedor —el que corre `npm run test:js` y el renderizador SSR del gate— `typeof localStorage`
// es `undefined` (medido), así que un módulo que lo leyera del global no se podría probar. Y lo que
// lanza en un navegador no es `getItem`: es el ACCESO a la propiedad `window.localStorage` cuando el
// origen es opaco o el usuario bloqueó los datos de sitio, así que quien lo pase tiene que leerlo
// dentro de su propio `try`.

/** La clave, con espacio de nombres y VERSIÓN. Un formato que no reconozcamos se descarta entero. */
export const STORAGE_KEY = 'jw.cart.v1';

const STORAGE_VERSION = 1;

/**
 * Los campos que el saneador conoce, en el orden del contrato.
 *
 * Existe para que un test pueda comprobar que el servidor no ha añadido ninguno sin que el cliente se
 * entere: `CartPayload::lineRules()` publica sus claves, y compararlas es más barato que descubrirlo
 * con un 422 en producción.
 *
 * @type {ReadonlyArray<string>}
 */
export const SANITISED_FIELDS = ['product_id', 'date', 'time', 'quantity', 'event_data', 'addons', 'dependent_ids'];

/**
 * Entero al estilo de la regla `integer` de Laravel, que **no es estricta**: acepta la cadena `'3'`.
 *
 * ⚠️ Medido contra el servidor: `quantity: "3"` da 200 y se tarifica como 3. Un `Number.isInteger`
 * a secas la descartaría y el servidor la habría aceptado — una divergencia que solo aparece con
 * datos viejos, o sea en producción y meses después. `'03'` sí se rechaza, como `FILTER_VALIDATE_INT`.
 */
function toInt(value) {
    if (typeof value === 'number') {
        return Number.isInteger(value) ? value : null;
    }

    if (typeof value === 'string' && /^[+-]?(0|[1-9]\d*)$/.test(value.trim())) {
        return Number(value.trim());
    }

    return null;
}

/**
 * `Y-m-d` con ida y vuelta REAL, como `date_format:Y-m-d` de PHP.
 *
 * ⚠️ **Una expresión regular no basta**, y es la única divergencia que salió al pasar un corpus por
 * los dos lados: `/^\d{4}-\d{2}-\d{2}$/` acepta `2026-02-30`, que el servidor RECHAZA porque
 * reconstruye la fecha y la compara con la entrada. Aquí se reconstruye igual.
 *
 * La fecha se arma con `setUTCFullYear` y no con `new Date(y, m, d)` por dos motivos: los años de dos
 * cifras se mapearían a 19xx, y así no interviene el huso del navegador para nada.
 */
function validDate(value) {
    if (typeof value !== 'string' || ! /^\d{4}-\d{2}-\d{2}$/.test(value)) {
        return null;
    }

    const [year, month, day] = value.split('-').map(Number);
    const probe = new Date(0);
    probe.setUTCFullYear(year, month - 1, day);

    const sameDay = probe.getUTCFullYear() === year
        && probe.getUTCMonth() === month - 1
        && probe.getUTCDate() === day;

    return sameDay ? value : null;
}

/**
 * `H:i` o `H:i:s` → hora CANÓNICA `H:i:s`.
 *
 * ⚠️ **`10:00` es legal y hay que NORMALIZARLA, no rechazarla**: el servidor acepta los dos formatos
 * (`date_format:H:i:s,H:i`) y le añade los segundos. Descartarla borraría líneas que el servidor
 * habría aceptado. Se guarda canónica porque el dominio compara franjas por CADENA, y una hora sin
 * segundos no casa con ninguna y falla **en silencio**.
 */
function canonicalTime(value) {
    if (typeof value !== 'string') {
        return null;
    }

    const match = value.trim().match(/^([01]\d|2[0-3]):([0-5]\d)(?::([0-5]\d))?$/);

    return match === null ? null : `${match[1]}:${match[2]}:${match[3] ?? '00'}`;
}

/**
 * Una línea cruda → una línea válida, o `null` si no lo es.
 *
 * ⚠️ **Espeja los criterios de `CartPayload::lineRules()`, no los de `Cart::sanitize()`**, y esa
 * elección es la decisión de diseño del paso. Los dos saneadores del servidor son OPUESTOS:
 *  - `Cart::sanitize()` **corrige**: `qty: 0`, `-5` y `'abc'` salen los tres como **1**. En una cesta
 *    de sesión eso es tolerable porque solo la escribe el servidor; en `localStorage` —que el usuario
 *    puede editar y que sobrevive a los despliegues— convertiría una línea corrupta en **una compra
 *    de una unidad que nadie pidió**, con su precio y todo;
 *  - `CartPayload` **rechaza**, pero rechaza el CUERPO ENTERO con un 422: medido, una sola línea con
 *    `date: ''` deja la cesta sin presupuesto, sin horas y sin poder preguntar si cabe otra línea —
 *    el cajón inservible y sin botón para quitar la culpable.
 *
 * La síntesis es la que el propio `CartPayload` documenta para una cesta de sesión: **descartar la
 * línea mala y restaurar el resto**, aplicando sus criterios de formato.
 */
export function sanitizeLine(raw) {
    if (raw === null || typeof raw !== 'object' || Array.isArray(raw)) {
        return null;
    }

    const productId = toInt(raw.product_id);
    const date = validDate(raw.date);
    const time = canonicalTime(raw.time);
    const quantity = toInt(raw.quantity);

    if (productId === null || productId < 1 || date === null || time === null || quantity === null || quantity < 1) {
        return null;
    }

    // Un `addons` que no es lista se trata como ausente; un complemento mal formado se descarta SOLO
    // él, que es lo que hace el saneador de sesión. La línea sigue siendo comprable sin él.
    const addons = (Array.isArray(raw.addons) ? raw.addons : [])
        .map((addon) => {
            if (addon === null || typeof addon !== 'object') return null;

            const id = toInt(addon.product_id);
            const qty = toInt(addon.quantity);

            return id !== null && id >= 1 && qty !== null && qty >= 1 ? { product_id: id, quantity: qty } : null;
        })
        .filter(Boolean);

    // Los menores a cargo para los que son estas entradas (Fase 6 · tanda 4, `menores-a-cargo.md`
    // §9.9.3 D8): SOLO ids —punteros opacos que resuelve la sesión de su dueño, nunca un nombre—, y con
    // el MISMO veredicto que el servidor (`integer|min:1|distinct`): un id que no sea entero o un
    // repetido descarta la línea, como una cantidad imposible. ⚠️ Que no haya MÁS ids que unidades no
    // es regla de forma en el servidor, así que aquí tampoco: lo recorta quien asigna.
    const dependentIds = sanitizeDependentIds(raw.dependent_ids);
    if (dependentIds === null) {
        return null;
    }

    // ⚠️ `event_data` NO se restaura NUNCA: no se persiste (`DECISIONES #38(d)`, art. 9 del RGPD).
    // Se deja el objeto vacío para que la forma en memoria sea siempre la misma.
    return { product_id: productId, date, time, quantity, event_data: {}, addons, dependent_ids: dependentIds };
}

/**
 * `dependent_ids` con las reglas del servidor: ausente = ninguno; lista de enteros ≥ 1 sin repetidos;
 * cualquier otra cosa invalida la línea (`null`).
 *
 * @returns {number[]|null}
 */
function sanitizeDependentIds(raw) {
    if (raw === undefined || raw === null) {
        return [];
    }

    if (! Array.isArray(raw)) {
        return null;
    }

    const ids = raw.map(toInt);

    if (ids.some((id) => id === null || id < 1) || new Set(ids).size !== ids.length) {
        return null;
    }

    return ids;
}

/**
 * ¿Qué hacer con una cesta guardada cuando el titular de ahora es otro?
 *
 * **La tabla tiene CINCO casillas y una no existe en el servidor.** En sesión, el logout hace
 * `session()->invalidate()`, que vacía cesta y marcador **a la vez**, así que nadie tuvo nunca que
 * decidir qué pasa con «había dueño X y ahora no hay nadie». `localStorage` no tiene invalidate ni
 * caducidad: esa casilla es la que el cajón inventa entero, es la MÁS probable en la tablet de un
 * parque —Alice cierra sesión, Bob abre el cajón sin identificarse— y es la fuga que la persistencia
 * introduce.
 *
 * | guardada | ahora | qué se hace | por qué |
 * |---|---|---|---|
 * | sin dueño | anónimo | conservar | visitante que aún no se ha identificado |
 * | sin dueño | X | conservar | **el flujo principal**: añado al carrito y luego entro para pagar |
 * | X | X | conservar | mismo titular |
 * | X | Y | PURGAR | la tablet compartida: Bob no hereda la cesta de Alice |
 * | X | anónimo | PURGAR | ⚠️ el logout — la casilla que el servidor no tiene |
 *
 * ⚠️ La comparación va con los dos lados casteados: el servidor lo hace con `(int)` a propósito, y
 * aquí hace más falta todavía porque `localStorage` solo guarda texto. Un `70 !== '70'` purgaría la
 * cesta de su propio dueño en cada carga.
 */
export function decideOwnership(storedOwner, currentOwner) {
    if (storedOwner === null || storedOwner === undefined) {
        return 'keep';
    }

    return String(storedOwner) === String(currentOwner) ? 'keep' : 'purge';
}

/** Lee el almacén sin dejar que un fallo suyo tumbe el cajón. */
function read(storage) {
    try {
        return storage?.getItem(STORAGE_KEY) ?? null;
    } catch {
        return null;
    }
}

/**
 * Escribe en el almacén sin dejar que un fallo suyo tumbe el cajón: se degrada a memoria.
 *
 * ⚠️ Sin almacén devuelve `false`, no `true`. El encadenamiento opcional no lanza —`null?.setItem()`
 * es `undefined`—, así que un `return true` detrás informaría de que se persistió cuando no se ha
 * escrito nada; y quien decida enseñar «tu cesta se guarda» se lo creería.
 */
function write(storage, value) {
    if (storage === null || storage === undefined) {
        return false;
    }

    try {
        storage.setItem(STORAGE_KEY, value);

        return true;
    } catch {
        return false;
    }
}

function forget(storage) {
    try {
        storage?.removeItem(STORAGE_KEY);
    } catch {
        // Un almacén que no deja borrar tampoco dejará leer; la cesta vive en memoria y ya está.
    }
}

/**
 * El sobre guardado, o `null` si no hay nada que valga.
 *
 * ⚠️ `JSON.parse` no filtra: con la clave ausente `getItem` devuelve `null` y `JSON.parse(null)` NO
 * lanza —coacciona a la cadena `'null'` y devuelve `null`—; con la clave vacía sí lanza; y basura
 * estructuralmente válida (`'42'`, `'[]'`, `'"hola"'`) pasa el parseo y revienta después. Por eso la
 * FORMA se valida a mano tras parsear.
 */
function parseEnvelope(raw) {
    if (typeof raw !== 'string' || raw === '') {
        return null;
    }

    let payload;

    try {
        payload = JSON.parse(raw);
    } catch {
        return null;
    }

    if (payload === null || typeof payload !== 'object' || Array.isArray(payload)) {
        return null;
    }

    if (payload.v !== STORAGE_VERSION || ! Array.isArray(payload.lines)) {
        return null;
    }

    return payload;
}

/**
 * Restaura la cesta guardada.
 *
 * Hace los CUATRO saneados que la sesión hacía gratis y `localStorage` no:
 *  1. **formato** — descarta las líneas que la API rechazaría (ver `sanitizeLine`);
 *  2. **titular** — purga si la cesta es de otro (ver `decideOwnership`);
 *  3. **caducidad** — descarta las líneas cuyo día ya pasó. La sesión caducaba a los 120 minutos;
 *     `localStorage` no caduca nunca, y medido: `POST orders/quote` tarifica **con importes completos**
 *     una fecha de hace 19 meses, así que sin esto el cliente ve un total creíble y el rechazo le
 *     llega al pulsar pagar, ya identificado;
 *  4. **tope** — recorta al máximo de líneas. Con 51, los TRES endpoints que reciben la cesta
 *     responden 422 sobre el array entero, así que el cajón queda inservible incluso para quitar.
 *
 * La comparación de fechas es de CADENAS (`Y-m-d` ordena lexicográficamente): sin `Date`, no hay huso
 * que pueda mover el corte un día.
 *
 * @param {{getItem: Function, setItem: Function, removeItem: Function}|null} storage
 * @param {{owner: number|string|null, today: string, maxLines: number}} context
 * @returns {{lines: Array<object>, purged: boolean, dropped: number}}
 */
export function load(storage, { owner = null, today, maxLines = 50 } = {}) {
    const payload = parseEnvelope(read(storage));

    if (payload === null) {
        return { lines: [], purged: false, dropped: 0 };
    }

    if (decideOwnership(payload.owner, owner) === 'purge') {
        forget(storage);

        return { lines: [], purged: true, dropped: payload.lines.length };
    }

    const sane = payload.lines
        .map(sanitizeLine)
        .filter((line) => line !== null && (today === undefined || line.date >= today));

    const lines = sane.slice(0, maxLines);

    return { lines, purged: false, dropped: payload.lines.length - lines.length };
}

/**
 * Guarda la cesta.
 *
 * ⚠️ **`event_data` no viaja al almacén, y esa es la razón de ser de esta función.** En la instalación
 * sembrada son el nombre de un MENOR, su edad y sus alergias —dato de salud, art. 9—, y dejarlos en
 * el navegador los pone fuera del alcance de `User::anonymize()` (`RGPD-01`), sin caducidad y en un
 * dispositivo que puede ser compartido. Es la única desviación consciente de la paridad de la fase.
 *
 * ⚠️ Y antes de escribir se RELEE: si lo guardado es de otro titular, esta cesta no lo pisa — la
 * purga que hizo otra pestaña no se puede deshacer desde esta.
 *
 * @returns {boolean} si llegó a persistirse (un `false` no es un error: la cesta sigue en memoria)
 */
export function save(storage, { owner = null, lines = [] } = {}) {
    const stored = parseEnvelope(read(storage));

    if (stored !== null && decideOwnership(stored.owner, owner) === 'purge') {
        forget(storage);

        return false;
    }

    const payload = {
        v: STORAGE_VERSION,
        owner: owner ?? null,
        lines: lines.map((line) => ({
            product_id: line.product_id,
            date: line.date,
            time: line.time,
            quantity: line.quantity,
            addons: (line.addons ?? []).map((addon) => ({ product_id: addon.product_id, quantity: addon.quantity })),
            // Solo los IDS de los menores (`menores-a-cargo.md` §4.8, §9.9.3 D8): un puntero opaco que
            // solo la sesión de su dueño resuelve. El nombre no puede llegar aquí por construcción.
            dependent_ids: (line.dependent_ids ?? []).map((id) => Number(id)),
        })),
    };

    return write(storage, JSON.stringify(payload));
}

/** Olvida la cesta guardada. Lo usa la purga por cambio de titular. */
export function clear(storage) {
    forget(storage);
}

/**
 * Reconcilia la cesta con lo que el presupuesto acaba de decir.
 *
 * ⚠️ **No basta con no pintar la línea muerta: hay que BORRARLA**, o viaja igual en el siguiente
 * `POST /orders` y el pedido revienta con «:product = —» (el fallo P8, que la web ya pagó una vez y
 * que en `localStorage` no caduca nunca).
 *
 * Se descartan dos cosas, y la segunda no se adivina leyendo:
 *  - las líneas cuyo `index` **no vuelve**: el hueco en la secuencia es la señal de que el producto
 *    dejó de venderse;
 *  - las líneas que vuelven con `unit_price_cents: null`, o sea **sin precio para la tarifa de ese
 *    día**. Cuentan en el badge, suman 0 al total y el checkout las rechaza sin decir cuál es: es el
 *    mismo síntoma del bug P8 por otra puerta.
 *
 * Y de las que sobreviven se quitan los complementos si el presupuesto los devolvió vacíos habiéndolos
 * enviado: `CartPricer` atrapa el error del resolutor y tarifica la línea **sin ninguno**, así que un
 * complemento retirado hace que el total mienta a la baja mientras la cesta guardada lo conserva.
 *
 * ⚠️ **Reconciliar DESPLAZA los índices**, así que quien la llame tiene que volver a presupuestar
 * antes de pintar: las filas y el botón de quitar se emparejan por el `index` del presupuesto, y
 * pintar el viejo sobre la cesta podada enseña las respuestas de otra línea y deja el botón mudo.
 * Por eso devuelve `changed`.
 *
 * @returns {{lines: Array<object>, changed: boolean}}
 */
export function reconcile(cart, quoteLines) {
    const byIndex = new Map((quoteLines ?? []).map((line) => [line.index, line]));

    const lines = cart
        .map((line, index) => {
            const quoted = byIndex.get(index);

            if (quoted === undefined || quoted.unit_price_cents === null) {
                return null;
            }

            const sentAddons = line.addons ?? [];
            const lostAddons = sentAddons.length > 0 && (quoted.addons ?? []).length === 0;

            return lostAddons ? { ...line, addons: [] } : line;
        })
        .filter(Boolean);

    const changed = lines.length !== cart.length
        || lines.some((line, i) => line !== cart[i]);

    return { lines, changed };
}
