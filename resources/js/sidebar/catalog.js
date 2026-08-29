/**
 * El CATÁLOGO del paso 1: de lo que publica `GET /catalog/products` a lo que pinta `CatalogStep`.
 *
 * **Por qué existe este módulo** (Fase 4 · paso 4.7·2b·2·B, `DECISIONES #67`). Estas tres funciones
 * vivían dentro de `Sidebar.vue`, y ahí no tenían red por partida doble:
 *  · `node --test` no las alcanzaba —`CE-6`: la lógica va en módulos PLANOS, y esto es lógica—;
 *  · y el diff de árbol tampoco, porque a Vue **se le pasaba el view-model del SERVIDOR** traducido a
 *    mano en el test. O sea: el gate comparaba el marcado de un catálogo que esta traducción no había
 *    tocado. Un renombre aquí salía VERDE con el cajón real pintando filas vacías — es exactamente el
 *    fallo que ya ocurrió una vez con los complementos (`#46(a)`) y que `SidebarAddonsParityTest` tuvo
 *    que cubrir por el otro lado.
 *
 * Al sacarlo, el mismo código lo ejecutan **el cajón vivo y el gate**: `Sidebar.vue` lo importa y el
 * renderizador SSR construye con él las props a partir de la respuesta REAL de la API.
 *
 * ⚠️ **Es presentación, no negocio.** Qué se vende ya lo decidió `Booking\Contracts\ProductCatalog`;
 * aquí solo se reparte por `type` y se renombran campos. Si alguna vez hay que DECIDIR algo (ocultar,
 * ordenar por otra cosa, calcular un precio), no es sitio: eso baja al dominio y se publica por la API.
 */

/**
 * Agrupa el catálogo plano en las DOS secciones que el cajón enseña.
 *
 * El orden dentro de cada sección es el de llegada, que es el `position` del panel: la API ya lo
 * ordena (`CatalogReader::sellableQuery()` hace `orderBy('position')`), así que reordenar aquí sería
 * pisar una decisión del panel.
 *
 * ⚠️ Las dos secciones se emiten **siempre, aunque estén vacías**: `CatalogStep` decide qué pinta, y
 * devolver una lista corta cambiaría el árbol sin que nadie lo pidiera.
 *
 * @param {Array<object>} products lo que viene en `data` de `GET /catalog/products`
 * @returns {Array<{key: string, items: Array<object>}>}
 */
export function sectionsFrom(products) {
    const list = Array.isArray(products) ? products : [];

    return [
        { key: 'entries', items: list.filter((p) => p?.type === 'entry').map(toItem) },
        { key: 'services', items: list.filter((p) => p?.type === 'pack').map(toItem) },
    ];
}

/**
 * El producto de la API → la fila que el catálogo pinta. **Renombra; no calcula.**
 *
 * ⚠️ Los nombres de la izquierda son CONTRATO con `CatalogStep.vue` y los de la derecha lo son con
 * `openapi/v1.yaml`. Los dos lados tienen guarda: el de la API en `ApiContractTest`, y el de aquí en
 * el diff de árbol, que desde 4.7·2b·2·B pasa por esta función.
 *
 * `search` se compone aquí y no en el componente porque el buscador compara contra un texto ya
 * normalizado; hacerlo por fila en cada pulsación es trabajo repetido en el camino caliente.
 */
export function toItem(product) {
    const features = Array.isArray(product?.features) ? product.features : [];

    return {
        id: product?.id,
        name: product?.name,
        is_pack: product?.type === 'pack',
        // ⚠️ La clave del MARCADOR, tal cual la manda el servidor (`#259`). No lleva respaldo aquí
        // a propósito: el respaldo es del dominio (`ProductIcon::forProduct`) y ponerle otro en el
        // cliente sería una segunda regla que algún día dirá algo distinto. Si llega `undefined`,
        // el componente aplica el suyo, que es el mismo.
        icon: product?.icon,
        featured: product?.featured ?? false,
        badge: product?.badge ?? '',
        features: features.join(' · '),
        from: product?.from_price_cents ?? null,
        period_label: product?.period_label ?? '',
        deposit_label: product?.deposit_label ?? '',
        search: [product?.name, ...features].join(' ').toLowerCase(),
    };
}

/** Cuántos productos hay en total, contando las dos secciones. */
export function totalItems(sections) {
    return (Array.isArray(sections) ? sections : [])
        .reduce((n, section) => n + (section?.items?.length ?? 0), 0);
}

/**
 * ¿Se enseña el buscador?
 *
 * El umbral lo publica `GET /config` como `catalog_search_min_items`, y sale de la MISMA fuente que
 * usa la web (`CatalogSettings::searchMinItems()`, verificado): es ajuste de instalación —un parque
 * con cuatro productos no necesita buscador y uno con treinta sí—. **Ausente o no numérico = no hay
 * umbral = no se enseña.**
 *
 * ⚠️ La comparación es **estrictamente mayor**, y no es un detalle de estilo: así lo declara el
 * contrato (`openapi/v1.yaml`: «total > catalog_search_min_items») y así lo aplica el servidor
 * (`Purchase.php`: `$catalogTotal > CatalogSettings::searchMinItems()`). Con `>=`, un catálogo de
 * exactamente el tamaño del umbral enseñaría el buscador en un motor y no en el otro.
 */
export function searchIsEnabled(sections, threshold) {
    return typeof threshold === 'number' && totalItems(sections) > threshold;
}
