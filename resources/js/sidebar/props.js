/**
 * **Las props de la RAÍZ del motor** (`Sidebar.vue`), que son también las de su sección de compra.
 *
 * ⚠️ Viven aquí desde la T3e·2 (`DECISIONES #682`) porque las leen DOS componentes que tienen que coincidir: la raíz
 * las pasa a su sección de compra con `v-bind="props"`, y con la isla como carcasa esa sección es la de la isla
 * (`isla/SeccionCompra.vue`). Una prop que la sección no declare acabaría de ATRIBUTO en el DOM, así que la lista
 * se escribe una vez. La sección de compra del cajón declara las suyas una a una, con su porqué.
 */
export const PROPS_MOTOR = {
    /** El grupo `tickets` del locale activo, inyectado por el servidor en el montaje (§4.5). */
    messages: { type: Object, default: () => ({}) },
    /** Textos de interfaz que no son del grupo `tickets` (velo de carga, etiquetas del armazón). */
    ui: { type: Object, default: () => ({}) },
    /** El grupo `account`, podado a lo que el paso de identificación y el área de cliente pintan. */
    account: { type: Object, default: () => ({}) },
    /** El grupo `auth`, con los textos de login y alta. */
    auth: { type: Object, default: () => ({}) },
    /** Los idiomas que ofrece el selector del perfil (`Platform\Services\SiteLocales`). */
    locales: { type: Array, default: () => [] },
    /** Quién pintó la página, para que la cesta sepa de quién es antes de preguntar a nadie. */
    userId: { type: [Number, String], default: null },
    /** El pedido del que habla el desenlace. Llega ya CONSUMIDO por `Http\Sidebar\SidebarEntry`. */
    orderCode: { type: String, default: '' },
    /** Las rutas que pintan las pantallas de desenlace, compuestas con `route()` en el servidor. */
    urls: { type: Object, default: () => ({}) },
};
