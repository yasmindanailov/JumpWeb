/**
 * La COSTURA DE INTENCIÓN, lado SPA (`docs/specs/sidebar-spa.md` §4.1, `DECISIONES #117`).
 *
 * Tres vistas de la landing no abren el cajón «vacío»: lo abren PIDIENDO algo concreto —los packs, o
 * las entradas de una zona—. La landing solo DECLARA la intención (`$store.purchase.openWith(...)`);
 * **cada motor registra cómo se aplica**, que es lo que impide que la landing sepa qué hay dentro.
 *
 * ⚠️⚠️ **Este módulo existe porque ese último eslabón NO ESTABA.** Medido en staging con un navegador
 * el 2026-08-21: la cadena llegaba hasta `machine.queueIntent()` y ahí moría —`takeIntent()` no lo
 * llamaba nadie fuera de `machine.test.js`—, así que los tres enlaces profundos abrían el cajón en el
 * catálogo raíz. **No fallaban: no hacían nada**, que es exactamente el modo de fallo que la costura
 * de 4.0a se creó para impedir, y ningún gate podía verlo. Lo guarda hoy `SidebarIntentWiringTest`.
 *
 * ⚠️ **Vive en un módulo PLANO y no en `Sidebar.vue` por dos motivos que apuntan igual**: `CE-6` —la
 * lógica fuera de los componentes, que solo pintan— y el techo de `SidebarComponentBudgetTest`, que
 * para `Sidebar.vue` **solo encoge**. Así esto se prueba con `node --test` sin montar Vue.
 */

/**
 * A qué sección del catálogo lleva cada intención.
 *
 * Los valores son las claves que emite `catalog.js::sectionsFrom()` y que `CatalogStep.vue` usa para
 * componer el `id` del cuerpo de cada sección: **son CONTRATO con esos dos ficheros**.
 */
const SECTION_FOR = { packs: 'services', zone: 'entries' };

/**
 * Traduce la intención de la landing a un destino dentro del catálogo.
 *
 * @param {{type?: string, slug?: string}|null} intent
 * @returns {{section: string, zone: string|null, exact: boolean}|null} `null` si no hay nada que hacer
 */
export function resolveIntent(intent) {
    const type = intent && typeof intent === 'object' ? intent.type : null;
    const section = SECTION_FOR[type];

    if (! section) {
        return null;
    }

    if (type === 'zone') {
        const slug = typeof intent.slug === 'string' ? intent.slug.trim() : '';

        // ⚠️ `exact: false` NO es un detalle de implementación, es una LIMITACIÓN declarada. El motor
        // retirado hacía scroll a la ZONA concreta (`catalog-open-entries-zone`), y la SPA no puede:
        // `catalog.js::toItem()` descarta el campo `zone` que la API sí publica, así que el dato no
        // llega al componente. Se lleva a la sección «Entradas», que es lo correcto hasta donde el
        // modelo alcanza, y se DICE que no es exacto en vez de fingir paridad.
        return slug === '' ? null : { section, zone: slug, exact: false };
    }

    return { section, zone: null, exact: true };
}

/**
 * El `id` del ancla que `CatalogStep.vue` emite para el cuerpo de una sección.
 *
 * ⚠️ Es CONTRATO con ese componente (`:id="'catalog-sec-' + section.key"`). Si allí cambia el
 * prefijo, aquí hay que cambiarlo: el enlace profundo dejaría de encontrar su destino **sin fallar**.
 */
export function anchorFor(target) {
    return target && target.section ? `catalog-sec-${target.section}` : null;
}

/**
 * Aplica una intención: lleva el cajón al catálogo y desplaza hasta la sección pedida.
 *
 * Todo el contacto con el mundo va INYECTADO —el paso, la búsqueda del ancla y el desplazamiento—
 * para que la decisión se pruebe entera sin DOM y sin Vue.
 *
 * ⚠️ **El ancla se ESPERA, no se supone.** El catálogo llega por red y el componente lo pinta después;
 * mirar el DOM en el instante del clic encontraría la nada. La espera es por CONDICIÓN y acotada: si
 * el ancla no aparece se devuelve el motivo en vez de fallar en silencio.
 *
 * @param {{type?: string, slug?: string}|null} intent
 * @param {{goToCatalog: Function, waitForAnchor: Function, scrollTo: Function}} deps
 * @returns {Promise<{applied: boolean, reason?: string, anchor?: string|null, exact?: boolean, zone?: string|null}>}
 */
export async function applyIntent(intent, deps) {
    const target = resolveIntent(intent);

    if (! target) {
        return { applied: false, reason: 'no_intent' };
    }

    // Primero el paso: si el cliente venía a mitad del embudo, un enlace profundo pide EMPEZAR ahí.
    // Es lo mismo que hacía `showPacks()` del motor retirado antes de emitir su evento.
    deps.goToCatalog();

    const anchor = anchorFor(target);
    const element = await deps.waitForAnchor(anchor);

    if (! element) {
        // La sección puede no existir legítimamente: `CatalogStep.vue` no pinta las secciones vacías,
        // y un catálogo sin packs (o sin entradas) no tiene dónde aterrizar. No es un error: es que no
        // hay destino, y el cajón se queda en el catálogo, que es el mejor sitio posible.
        return { applied: false, reason: 'anchor_missing', anchor };
    }

    deps.scrollTo(element);

    return { applied: true, anchor, exact: target.exact, zone: target.zone };
}
