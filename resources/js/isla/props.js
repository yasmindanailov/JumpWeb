/**
 * Las props de la isla (`IslaFlotante.vue`), con los nombres de `ParkIsland.jsx` para poder comparar fichero con
 * fichero. Viven fuera del componente por la regla `CE-6` del SPA (`SidebarComponentBudgetTest`): un componente
 * pinta, y su declaración y su lógica van en módulos planos.
 */
export const PROPS_ISLA = {
    /** El grupo `isla` de `lang/` del idioma activo. */
    textos: { type: Object, default: () => ({}) },
    placement: { type: String, default: 'auto' },
    compact: { type: [String, Boolean], default: 'auto' },
    gutter: { type: String, default: 'clamp(16px, 4vw, 48px)' },
    mobileContext: { type: String, default: 'auto' },
    maxWidth: { type: Number, default: 760 },
    scrim: { type: Boolean, default: true },
    ctaVisible: { type: Boolean, default: false },
    page: { type: Object, default: () => ({ kind: 'portada', from: '' }) },
    today: { type: Object, default: null },
    offer: { type: String, default: null },
    reassurance: { type: String, default: null },
    quote: { type: Object, default: null },
    chosen: { type: Object, default: null },
    filling: { type: Object, default: null },
    task: { type: Object, default: null },
    resume: { type: Object, default: null },
    payment: { type: String, default: null },
    paymentText: { type: String, default: null },
    bookingToday: { type: Object, default: null },
    help: { type: Object, default: null },
    notice: { type: String, default: null },
    account: { type: Object, default: () => ({ state: 'guest', pending: false }) },
    menuItems: { type: Array, default: () => [] },
    homeLabel: { type: String, default: null },
    contact: { type: Object, default: () => ({ phone: '', whatsapp: '' }) },
    lang: { type: String, default: '' },
    plans: { type: Object, default: null },
    cookies: { type: Object, default: null },
    onNavigate: { type: Function, default: null },
    onRetry: { type: Function, default: null },
    onPayBizum: { type: Function, default: null },
    onManual: { type: Function, default: null },
    onDismiss: { type: Function, default: null },
};
