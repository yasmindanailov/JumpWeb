/**
 * Las props de la isla (`IslaFlotante.vue`), con los nombres de `ParkIsland.jsx` para poder comparar fichero con
 * fichero. Viven fuera del componente por la regla `CE-6` del SPA (`SidebarComponentBudgetTest`): un componente
 * pinta, y su declaración y su lógica van en módulos planos.
 */
export const PROPS_ISLA = {
    /** El grupo `isla` de `lang/` del idioma activo. */
    textos: { type: Object, default: () => ({}) },
    placement: { type: String, default: 'auto' },
    /** El margen de la PÁGINA (zip del 27-09): en móvil, los bordes de la isla son los de la cabecera y los de cada bloque. */
    gutter: { type: String, default: 'var(--gutter)' },
    maxWidth: { type: Number, default: 760 },
    scrim: { type: Boolean, default: true },
    ctaVisible: { type: Boolean, default: false },
    page: { type: Object, default: () => ({ kind: 'portada', from: '' }) },
    /**
     * La razón de la pieza que se lee (Z6b, opción C «Da la razón»): con un botón de la página a la vista, la isla la dice
     * en un banner en vez de repetirlo. `{ type: 'razon'|'vivo', icon, svg, text, sub, title, detail, decision }`: `svg`, el
     * dibujo que manda el servidor (`Lucide::svg`); `title` y `detail`, lo que se lee al abrirla. Pasa por `useSinSaturar`.
     */
    reason: { type: Object, default: null },
    /** Lo que sigue con la capa cerrada (Z6b·3): «Confirmando tu pago», «¡Reservado!». `{ type, text, sub, onClick }`. */
    waiting: { type: Object, default: null },
    today: { type: Object, default: null },
    offer: { type: String, default: null },
    /** La frase que quita el miedo de la pieza que se lee (situación 5, `#866`): un texto o `{ text, decision }`. */
    reassurance: { type: [String, Object], default: null },
    quote: { type: Object, default: null },
    chosen: { type: Object, default: null },
    filling: { type: Object, default: null },
    task: { type: Object, default: null },
    resume: { type: Object, default: null },
    payment: { type: String, default: null },
    paymentText: { type: String, default: null },
    bookingToday: { type: Object, default: null },
    /** La compra (situación 10): la isla pasa a ser su contenedor. Qué pinta, en `piezas/CompraIsla.vue`. */
    checkout: { type: Object, default: null },
    /**
     * Con la compra abierta, ¿la isla deja quieta la página de detrás? Sí en el diseño (y en su banco). Montada sobre
     * el paquete del cajón, NO (T3e·2): ahí el scroll lo bloquea el controlador, que es su dueño único
     * (`ui/scroll-lock.js`), y dos escritores sobre la página es el fallo que ese dueño existe para evitar.
     */
    bloqueaPagina: { type: Boolean, default: true },
    /** La ayuda por WhatsApp (situación 12). `stuck`: la página ve que se atasca y la isla pone «¿Lo hablamos?» bajo la frase (Z6a). */
    help: { type: Object, default: null },
    notice: { type: String, default: null },
    /**
     * La cuenta, el control de la DERECHA de la barra (Z6a): sin sesión, «Cuenta» (`onClick`, Entrar); con sesión, «Mi QR»
     * (`onQr`, Tu QR). Las dos reciben `{ from: 'isla' }`. El punto: lima con `bookingToday`; naranja con `task` o `pending`.
     */
    account: { type: Object, default: () => ({ state: 'guest', pending: false }) },
    menuItems: { type: Array, default: () => [] },
    homeLabel: { type: String, default: null },
    contact: { type: Object, default: () => ({ phone: '', whatsapp: '' }) },
    lang: { type: String, default: '' },
    /** El idioma, en el pie del menú: su icono lo abre. Sin él, el icono solo se lee (el diseño, mientras la web habla uno). */
    onLanguage: { type: Function, default: null },
    plans: { type: Object, default: null },
    cookies: { type: Object, default: null },
    /**
     * La SEGUNDA capa de las cookies («Tus cookies», T4e): sus finalidades con su estado y lo que hace cada interruptor.
     * Aparte de `cookies` (la primera capa, que se va al decidir): así el menú la abre también después.
     */
    cookiePrefs: { type: Object, default: null },
    onNavigate: { type: Function, default: null },
    onRetry: { type: Function, default: null },
    onPayBizum: { type: Function, default: null },
    onManual: { type: Function, default: null },
    onDismiss: { type: Function, default: null },
};
