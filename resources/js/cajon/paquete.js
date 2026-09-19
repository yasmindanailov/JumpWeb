/**
 * **EL CARGADOR DEL PAQUETE** — la segunda de las dos líneas que escribe una landing ajena
 * (F4 · T5, `docs/specs/cajon-empaquetable.md` §4.1).
 *
 * Es una entrada de Vite propia, y ésa es toda su razón de ser: hasta la T5, una página que no era del
 * producto tenía que cargar `app.js`, o sea **la coreografía del nav, el hero, los raíles y el imán de scroll
 * de la landing de JumpWeb** para poder abrir un cajón. Aquí no hay nada de eso: se instala el cerrojo de
 * scroll y se llama a `installCajon()`. El motor —Vue, Pinia y el embudo— sigue llegando con `import()` en la
 * primera apertura, igual que en el producto, así que esta entrada es lo único que la página descarga de
 * verdad hasta que alguien toca «Reservar».
 *
 * ⚠️ **El cerrojo se crea AQUÍ porque en una página ajena no hay nadie más que lo tenga.** En el producto lo
 * comparten el nav, las ofertas y el cajón, y por eso `installCajon()` lo recibe por parámetro (dueño único,
 * `ui/scroll-lock.js`). Una landing de instancia que ya tenga superpuestos propios **no debe cargar esta
 * entrada dos veces**: el cajón vive en `window.JumpWeb.cajon` y montarlo otra vez sería un segundo dueño de
 * `body.no-scroll`.
 *
 * ⚠️ **Mismo dominio.** El cajón habla con `/api/v1` con la cookie de sesión y CSRF (§0), así que esta línea
 * la escribe una página SERVIDA POR LA INSTALACIÓN, no un tercero desde otro dominio: un `<script type=
 * "module">` de otro origen exigiría CORS y las llamadas con credenciales, otra historia entera.
 */
import { installScrollLock } from '../ui/scroll-lock.js';
import { installCajon } from './index.js';

installCajon({ scrollLock: installScrollLock() });
