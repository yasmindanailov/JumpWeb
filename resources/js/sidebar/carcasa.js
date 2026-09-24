/**
 * **LA CARCASA DE LA COMPRA**, dicha por el arranque (`shell`, `DECISIONES #682`; T3e·2 de
 * `docs/specs/isla-y-landing-nueva.md` §4.10): el cajón lateral de siempre o la isla del sistema de diseño nuevo.
 *
 * La elige la instalación (`ShellSettings`, «Ajustes → Dónde se hace la compra») y viaja en la mitad compartida
 * del arranque. Dos lectores, y por eso vive en un módulo plano con su `node --test`:
 *  · el CONTROLADOR del paquete (`cajon/controller.js`), que decide en qué SUPERFICIE se abre cada cosa;
 *  · la RAÍZ del motor (`Sidebar.vue`), que monta la compra del cajón o la de la isla.
 *
 * ⚠️ Con la isla, **la cuenta sigue en el lateral** hasta que «Mi cuenta» viva también en ella (T5): por eso la
 * superficie se decide por APERTURA y no una vez por página.
 */
export const CAJON = 'cajon';

export const ISLA = 'isla';

/** La clave con la que la raíz del motor sabe qué compra monta (`provide` en `index.js`, `inject` en la raíz). */
export const CARCASA = Symbol('carcasa');

/** Y la de los rótulos de la isla (`boot.isla`, el grupo `isla` de `lang/`, que solo viaja con ella). */
export const TEXTOS_ISLA = Symbol('textos-isla');

/**
 * La carcasa que dice el arranque, o el CAJÓN si no dice ninguna que exista: es la conducta de siempre, y un
 * arranque viejo, recortado o ajeno no puede dejar a la página sin sitio donde comprar.
 *
 * @param {{shell?: string}|null|undefined} boot
 * @returns {'cajon'|'isla'}
 */
export function carcasaDe(boot) {
    return boot?.shell === ISLA ? ISLA : CAJON;
}

/**
 * Dónde se abre una apertura: la CUENTA, en el lateral (hasta la T5); todo lo demás, en la carcasa.
 *
 * @param {'cajon'|'isla'} carcasa
 * @param {{cuenta?: boolean}} [apertura]
 * @returns {'cajon'|'isla'}
 */
export function superficieDe(carcasa, { cuenta = false } = {}) {
    return cuenta ? CAJON : (carcasa === ISLA ? ISLA : CAJON);
}
