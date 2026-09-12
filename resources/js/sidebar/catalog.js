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
 * **Cuál de las dos secciones lleva la franja de TINTA** (`#552`).
 *
 * La «tarjeta grande» separa las dos mitades del catálogo **cambiando de superficie, no inventando un
 * color**: una franja en tinta y la otra en papel. Cuál de las dos va en tinta es una decisión de
 * DISEÑO, así que vive en un sitio y con nombre — no repartida en un `v-if` de la plantilla.
 *
 * ⚠️⚠️ **Y hubo que preguntarla porque las dos fuentes del canvas se contradicen**: su argumento dice
 * *«el cumpleaños en tinta contra el papel del resto»* y sus dos dibujos —la propuesta y la puerta
 * descartada— ponen la tinta en «Vengo a saltar», o sea en las ENTRADAS. `[DECIDIDO owner]` con las dos
 * renderizadas delante.
 */
const SECCION_EN_TINTA = 'services';

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
 * @returns {Array<{key: string, ink: boolean, items: Array<object>}>}
 */
export function sectionsFrom(products) {
    const list = Array.isArray(products) ? products : [];

    return [
        { key: 'entries', ink: SECCION_EN_TINTA === 'entries', items: list.filter((p) => p?.type === 'entry').map(toItem) },
        { key: 'services', ink: SECCION_EN_TINTA === 'services', items: list.filter((p) => p?.type === 'pack').map(toItem) },
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

/**
 * **La puerta de categoría: qué sección queda abierta al pulsar una** (`#553`).
 *
 * `[DECIDIDO owner]`: **exclusivo**. Abrir una cierra la otra, y volver a pulsar la abierta la cierra.
 * Con las dos abiertas se vuelve a la lista larga de hoy —medida en **1.033 px de contenido en una
 * ventana de 650**— y la bifurcación, que es lo que esta forma viene a dar, desaparece.
 *
 * ⚠️ Vive aquí y no dentro del componente porque **es una regla, no un estado de pintura** (`CE-6`):
 * en un módulo plano tiene `node --test`, y dentro de un `.vue` no la alcanza ninguna red.
 *
 * @param {string} abierta la sección abierta ahora, o `''` si no hay ninguna
 * @param {string} key la que se acaba de pulsar
 * @returns {string} la que queda abierta
 */
export function alternarSeccion(abierta, key) {
    return abierta === key ? '' : key;
}

/**
 * **¿Se ve el cuerpo de esta sección?**
 *
 * ⚠️⚠️ **BUSCAR ABRE.** Con el buscador escrito, las secciones con resultados se enseñan ABIERTAS
 * aunque nadie las haya pulsado: si no, quien busca «cumple» recibe una puerta cerrada y la sensación
 * de que no hay nada. *El texto del buscador ES la intención; pedir además un toque para ver lo que ya
 * has pedido es cobrar dos veces por la misma decisión.*
 *
 * ⚠️ Argumentos POSICIONALES y no un objeto, **y no por peso**: se cambió creyendo que ahorraba bytes
 * del chunk y medido salió al revés (279,17 → 279,23 kB). Se queda porque cuatro valores sueltos leen
 * mejor que una desestructuración, no porque pese menos. *Una poda que no se mide no es una poda.*
 *
 * @param {string} abierta la sección abierta ahora, o `''`
 * @param {string} key la sección que se está pintando
 * @param {boolean} hayBusqueda si el buscador tiene texto
 * @param {boolean} casa si esta sección tiene resultados para esa búsqueda
 */
export function cuerpoVisible(abierta, key, hayBusqueda, casa) {
    return hayBusqueda ? casa : abierta === key;
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
