/**
 * **LA ENTRADA DE LA ISLA DE UNA PÁGINA** (T4e de `docs/specs/isla-y-landing-nueva.md` §4.12): una entrada de Vite propia
 * que la página pide con `scripts` de `<x-pagina>` (`isla`) y que monta la isla EN REPOSO al cargar, en su propio
 * contenedor al final del `<body>`. Lo que le da la página (su tipo, su acción, su «desde», hoy, el menú, el contacto) y
 * lo que solo sabe el producto (sus textos de `lang/<idioma>/isla.php`, si hay sesión, la política de cookies) llegan
 * juntos del layout en `#jw-isla-pagina`.
 * ⚠️ El motor del cajón (295 KiB) NO viaja aquí: llega al pulsar, con el cargador del paquete (`cajon`). Techo de peso
 * en `SidebarBundleBudgetTest`.
 */
import { createApp, h } from 'vue';
import '../isla.css';
import IslaPagina from './IslaPagina.vue';
import { CLAVE_TEXTOS } from '../piezas/textos.js';

/**
 * Monta la isla de la página, o nada si la página no la declara.
 *
 * ⚠️ **Su SITIO lo da la página** (`[data-jw-isla]`, contrato página↔isla §4.3): el PRIMER hijo de la columna de la
 * página, como en el diseño (`#root` de `paginas/kids.card.html`), porque la isla va `sticky` —arriba en escritorio y,
 * con `order` en móvil, abajo junto al pulgar— y `sticky` solo funciona dentro de su columna. El sitio va con
 * `display: contents`: así la isla es el hijo de la columna y no del sitio. Medido el 25-09: montada al final del
 * `<body>`, la isla quedaba al pie del documento (y = 7985) y la geometría daba por visible toda la página.
 */
export function montarIslaDePagina({ doc = document } = {}) {
    let datos = null;

    try { datos = JSON.parse(doc.getElementById('jw-isla-pagina')?.textContent ?? 'null'); } catch { datos = null; }
    if (! datos?.config?.page) return null;
    let sitio = doc.querySelector('[data-jw-isla]');

    if (! sitio) {
        sitio = doc.createElement('div');
        doc.body.prepend(sitio);
    }
    sitio.setAttribute('data-jw-isla-pagina', '');
    sitio.style.display = 'contents';
    const textos = datos.textos ?? {};
    const app = createApp({ render: () => h(IslaPagina, { config: datos.config, textos }) });

    app.provide(CLAVE_TEXTOS, () => textos);
    app.mount(sitio);

    return app;
}

montarIslaDePagina();
