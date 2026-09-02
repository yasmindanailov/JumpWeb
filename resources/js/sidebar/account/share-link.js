/**
 * **Compartir o copiar un enlace**, con el gesto que el dispositivo sepa hacer
 * (`specs/waiver-por-reserva.md` §13.8).
 *
 * El enlace del justificante se reparte por WhatsApp a los padres, así que en un teléfono el gesto
 * natural es **compartir** —el propio sistema ofrece WhatsApp— y en un escritorio, **copiar**.
 *
 * ⚠️ **Vive en un módulo plano y no en el componente** (`CE-6`): decidir cuál de los dos caminos toca
 * y qué pasa cuando falla es una REGLA, y una regla dentro de un `.vue` pierde su red — los
 * componentes se comparan por su árbol, y un árbol no dice qué rama se eligió.
 *
 * ⚠️⚠️ **Las dos APIs fallan de formas que hay que distinguir**, y por eso se devuelve un estado y no
 * un booleano:
 *
 *  · `navigator.share` **lanza `AbortError` cuando la persona CIERRA la hoja de compartir**. Eso no
 *    es un fallo: es un «no, gracias». Tratarlo como error enseñaría un mensaje rojo a quien acaba de
 *    cambiar de idea, así que se devuelve `cancelled` y la pantalla no dice nada.
 *  · `navigator.clipboard` **no existe fuera de contexto seguro** (http sin TLS) y puede rechazar sin
 *    permiso. Ahí se devuelve `failed` y la pantalla lo dice: el `input` de solo lectura sigue
 *    estando, y el cliente puede seleccionarlo a mano.
 *
 * ⚠️ Las dos dependencias entran POR PARÁMETRO, no se leen del global: en el Node del contenedor
 * —donde corre `npm run test:js`— `navigator` no existe, y un módulo que lo leyera de fuera no se
 * podría probar. Es el mismo patrón que `cart.js` con `localStorage`.
 *
 * @param {string} url
 * @param {{share?: ((data: object) => Promise<void>)|null, copy?: ((text: string) => Promise<void>)|null}} deps
 * @returns {Promise<'shared'|'copied'|'cancelled'|'failed'>}
 */
export async function shareOrCopy(url, { share = null, copy = null } = {}) {
    if (typeof url !== 'string' || url === '') {
        return 'failed';
    }

    if (typeof share === 'function') {
        try {
            await share({ url });

            return 'shared';
        } catch (error) {
            // ⚠️ Cerrar la hoja de compartir NO es un fallo. Se distingue por el `name` y no por el
            // mensaje, que cambia con el idioma del sistema.
            if (error?.name === 'AbortError') {
                return 'cancelled';
            }
            // Cualquier otro fallo de `share` cae al portapapeles: es lo que hace útil tener los dos.
        }
    }

    if (typeof copy === 'function') {
        try {
            await copy(url);

            return 'copied';
        } catch {
            return 'failed';
        }
    }

    return 'failed';
}

/**
 * Las dependencias REALES del navegador, o `null` cuando no existen.
 *
 * ⚠️ **`navigator.share` se ofrece solo si además hay `canShare`**: algunos navegadores de escritorio
 * declaran `share` y abren un diálogo inútil. Y el acceso va dentro de `try` porque leer
 * `navigator.clipboard` **lanza** en algunos contextos, igual que `window.localStorage`.
 */
export function browserShareDeps(nav = typeof navigator === 'undefined' ? null : navigator) {
    try {
        return {
            share: typeof nav?.share === 'function' && typeof nav?.canShare === 'function'
                ? (data) => nav.share(data)
                : null,
            copy: typeof nav?.clipboard?.writeText === 'function'
                ? (text) => nav.clipboard.writeText(text)
                : null,
        };
    } catch {
        return { share: null, copy: null };
    }
}
