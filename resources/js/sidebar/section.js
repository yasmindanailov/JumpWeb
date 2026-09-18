/**
 * **Las SECCIONES del cajón, y las señales que el cajón publica según cuál esté activa**
 * (`docs/specs/area-cliente.md` §4.1 y §4.5).
 *
 * Es el nivel de navegación que faltaba. Hasta hoy el cajón tenía uno solo —los once pasos del
 * embudo, en `machine.js`— y `DECISIONES #66` decidió que hospedará también el ÁREA DE CLIENTE.
 *
 * ⚠️⚠️ **Y NO se cuelga del grafo del embudo, que es la decisión que lo ordena todo.**
 * `FUNNEL_TRANSITIONS` está cerrado con guarda (`machine.test.js`, `#119`) porque un área de cliente
 * **no es un embudo**: sus pantallas se navegan libremente, sin orden ni vuelta atrás obligatoria.
 * Mezclar los dos modelos en un mapa obligaría a razonar sobre uno cada vez que se toca el otro.
 *
 * Módulo PLANO, sin un solo `import` de Vue (`CE-6`): así se prueba con `node --test` y no hace falta
 * montar un runner de componentes para saber qué publica el cajón.
 */

/**
 * Las secciones que el cajón puede enseñar.
 *
 * Con NOMBRE y no con número, al revés que los pasos del embudo. Aquellos conservan sus números
 * porque durante la convivencia de motores `$wire.step` era el vocabulario de la paridad; aquí no hay
 * nada que espejar y un nombre no obliga a mantener una tabla de equivalencias en la cabeza.
 */
export const SECTIONS = {
    PURCHASE: 'purchase',
    ACCOUNT: 'account',
};

/** Con la que abre el cajón. La compra: es lo que el cliente viene a hacer desde la landing. */
export const DEFAULT_SECTION = SECTIONS.PURCHASE;

/** ¿Es una sección conocida? Lo usa el store para no aterrizar en una pantalla que no existe. */
export function isSection(value) {
    return Object.values(SECTIONS).includes(value);
}

/**
 * El «modo» que el cajón PUBLICA hacia fuera, ya con la sección tenida en cuenta.
 *
 * ⚠️ **No es cosmético y no vive dentro del cajón** (el aviso lleva en `machine.js` desde 4.1):
 * `layout.blade.php` pinta `is-{modo}` en `.sidecart__panel` y el CSS colapsa con él el bloque de
 * cuenta. Un motor que no publique esta señal deja el panel en `is-catalog` para siempre.
 *
 * ⚠️⚠️ **Y la sección de cuenta publica un modo PROPIO en vez de reutilizar `cart`, que también
 * colapsaría el bloque.** Reutilizarlo habría ahorrado una regla de CSS a cambio de que la señal
 * dijera «carrito» con el cliente mirando sus pedidos: exactamente el fallo de `DECISIONES #115` —una
 * comprobación que mide una cosa y se lee como otra es PEOR que no tenerla—. El modo del embudo lo
 * sigue calculando `modeOf(step)`, que no se toca, así que `SidebarProgressParityTest` —que congela
 * los once pasos contra el mapa del servidor— no se entera de nada.
 *
 * Una sección desconocida degrada al modo del embudo: es el estado en el que el cajón sabe estar.
 */
export function publishedMode(section, purchaseMode) {
    return section === SECTIONS.ACCOUNT ? SECTIONS.ACCOUNT : purchaseMode;
}

/**
 * `true` SOLO mientras el embudo pide identificarse. Es lo que BLOQUEA los botones de login que
 * `account-context` pinta fuera del cajón, para no ofrecer un modal de login encima de una pantalla
 * que ya está pidiendo lo mismo.
 *
 * ⚠️ En la sección de cuenta es **siempre `false`**, y conviene decir por qué no es un detalle: si
 * quedara pegado en `true` —el cliente estaba en el paso 5 y saltó a sus pedidos— los botones de
 * fuera se quedarían muertos sin nada en pantalla que lo explicara. La señal describe lo que el
 * cliente está VIENDO, no por dónde pasó.
 */
/**
 * **El código del pedido que el cajón ANUNCIA como comprado**, o cadena vacía (F4 · T3b).
 *
 * Es la tercera señal que el cajón publica hacia fuera (`jw:cajon:purchased`, el contrato de incrustación de
 * `specs/cajon-empaquetable.md` §4.2), y vive aquí por lo mismo que sus dos hermanas: **la RAÍZ del cajón no
 * puede conocer los pasos del embudo** (`CE-6`, y lo vigila `SidebarComponentBudgetTest`). Los pasos son de la
 * sección de compra; una raíz que los conoce ha vuelto a ser una pantalla. Por eso recibe el HECHO —«el embudo
 * está en confirmado», que deriva el store con el vocabulario de `machine.js`— y no el paso: este módulo sigue
 * sin colgarse del grafo del embudo, igual que sus dos hermanas.
 *
 * ⚠️ Se pide la sección además del hecho: el área de cliente puede estar delante con el embudo aparcado en
 * confirmado, y anunciar una compra mientras el cliente mira sus pedidos sería contarla donde no ocurre.
 *
 * ⚠️ Quien no repite el aviso es el controlador, que recuerda el último pedido anunciado: esta pantalla se
 * repinta —el resumen llega después del sondeo— y una landing que contara conversiones contaría de más.
 */
export function publishedPurchase(section, purchaseConfirmed, orderCode) {
    if (section !== SECTIONS.PURCHASE || ! purchaseConfirmed) return '';

    return orderCode || '';
}

export function publishedIdentifying(section, purchaseIdentifying) {
    return section === SECTIONS.ACCOUNT ? false : purchaseIdentifying;
}
