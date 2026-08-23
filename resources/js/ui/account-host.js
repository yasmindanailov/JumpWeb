/**
 * **El DUEÑO ÚNICO del hueco del bloque de cuenta** (`docs/specs/account-context-vue.md` §4.8).
 *
 * El servidor emite `<div id="sidecart-account" class="acct acct--pending">` con un **suelo** dentro:
 * el formulario de cerrar sesión, y nada más. Nace **colapsado**, así que no se ve. A partir de ahí
 * solo pueden pasar dos cosas, y cada una tiene aquí su función:
 *
 *  · **el motor monta** → `takeOver()`: vacía el hueco —porque `<Teleport>` **anexa y no vacía**, a
 *    diferencia de `app.mount()`— y lo expande. El bloque entra deslizando con la transición que el
 *    CSS ya tenía;
 *  · **el motor NO llega** (red caída, despliegue a media navegación) → `reveal()`: solo expande, y
 *    queda a la vista el suelo servido. Es la única forma de cerrar sesión que le queda al cliente.
 *
 * ⚠️⚠️ **UN dueño y no dos, y eso no es estilo.** Este repo ya ha pagado dos veces por lo contrario:
 * `body.no-scroll` llegó a tener **seis** escritores y el último en cerrar mandaba (`#58`), y la señal
 * `accountZone` documenta que «un solo consumidor no es estilo» porque con dos uno llega tarde
 * (`#120(u)`). Aquí la consecuencia de repartirlo sería peor que fea: si el motor monta y **nadie**
 * vacía, el cliente ve **dos** botones de cerrar sesión, el servido y el de Vue.
 *
 * ⚠️⚠️ **Vive en `ui/` y no en `sidebar/`, y es por lo mismo que `ui/scroll-lock.js`**: lo usan los
 * DOS mundos. El motor lo llama al montar (`sidebar/index.js`) y **la landing lo llama cuando el
 * motor no llega** (`app.js`, en el `catch` de `bootSpaEngine`). Dejarlo dentro de `sidebar/`
 * obligaría al entry de la landing a importar del motor, que es justo lo que el `import()` dinámico
 * existe para evitar. Es un módulo hoja, sin dependencias: no arrastra nada consigo.
 *
 * ⚠️ **El DOM va por parámetro** (`CE-6`, mismo patrón que `account/privacy.js`): es lo que permite
 * probar esto con `node --test` sin navegador.
 */

/** La clase que mantiene el hueco colapsado hasta que alguien decide qué enseñar. */
export const PENDING = 'acct--pending';

/**
 * **El motor se hace cargo**: fuera el suelo, y que se vea el bloque.
 *
 * ⚠️ El orden importa: **primero vaciar, después expandir**. Al revés, el suelo servido llegaría a
 * verse durante un fotograma antes de desaparecer.
 *
 * @param {Element|null} host
 * @returns {boolean} si había hueco del que hacerse cargo
 */
export function takeOver(host) {
    if (! host) return false;

    host.textContent = '';
    host.classList.remove(PENDING);

    return true;
}

/**
 * **El motor no ha llegado**: que se vea el suelo.
 *
 * No toca el contenido — es justo lo que hay que conservar.
 *
 * @param {Element|null} host
 * @returns {boolean} si había hueco que revelar
 */
export function reveal(host) {
    if (! host) return false;

    host.classList.remove(PENDING);

    return true;
}
