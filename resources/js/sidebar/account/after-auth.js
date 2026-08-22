/**
 * **Dónde aterriza quien acaba de conseguir sesión DENTRO del cajón**
 * (`docs/specs/auth-en-cajon.md` §3.3, decisión del owner del 2026-08-23).
 *
 * ⚠️⚠️ **Aterrizar es NAVEGAR, y eso no es pereza: lo obliga una medida.** Los textos del área de
 * cliente viajan en el HTML **solo con sesión** —el montaje los envuelve en `auth()->check()`, igual
 * que los idiomas del selector—, y el cajón los recibe como props estáticas del `data-boot` de *esa*
 * carga de página. Quien consigue sesión sin recargar tiene en memoria el payload de invitado: el
 * índice de «Mi cuenta» le saldría con **el título y las seis entradas en blanco**, porque
 * `i18n.js::t()` devuelve `''` cuando falta una clave y en producción un texto ausente no puede
 * tumbar el cajón. Nada avisaría.
 *
 * ▶ La ruta a la que se va **ya es una PUERTA** (`Http\Sidebar\AccountDoor`): la página se recarga ya
 * identificada, el payload trae los textos y el cajón **nace abierto en el índice**. Cero API nueva y
 * cero bytes en las demás páginas, frente a los ~660 B por página pública que costaba la alternativa
 * —mandarle el grupo `account` a todo visitante anónimo—.
 *
 * ▶ **Y de propina resuelve dos cosas más**: la pila de retorno se reconstruye desde cero, así que
 * «volver» no puede enseñar un formulario de login a quien acaba de entrar; y el formulario muere con
 * la página, así que la contraseña no sobrevive en un campo para el siguiente que use el dispositivo.
 *
 * ⚠️ **No hace falta avisar a Livewire** (`logged-in`), y conviene decirlo para que nadie lo añada
 * «por simetría» con el embudo: ese evento existe para repintar el bloque de cuenta **sin recargar**,
 * que es justo lo que aquí no ocurre. Una navegación completa hace más de lo que el evento podría.
 *
 * Módulo PLANO con el `window` **por parámetro** (`CE-6`, mismo patrón que `account/privacy.js`): es
 * lo que permite probarlo con `node --test` sin navegador.
 */

/**
 * Lleva al cliente a su cuenta. Devuelve la URL a la que se fue.
 *
 * ⚠️ **Sin `urls.account` NO se inventa una ruta**: se recarga la página actual. Componer `/mi-cuenta`
 * a mano sería quemar el enrutador de Laravel en el cliente —lo que la spec del área prohíbe
 * expresamente— y en una instalación con otro idioma o prefijo llevaría a un 404. Recargar es la
 * degradación honesta: la sesión ya está puesta, así que el cliente ve la página que estaba mirando,
 * pero identificado.
 *
 * @param {{urls?: {account?: string}, win?: Window}} deps
 * @returns {string} la URL a la que se navegó, o `''` si solo se recargó
 */
export function landOnAccount({ urls = {}, win = window } = {}) {
    const url = typeof urls?.account === 'string' ? urls.account : '';

    if (url === '') {
        win.location.reload();

        return '';
    }

    win.location.assign(url);

    return url;
}
