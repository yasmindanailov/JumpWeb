/**
 * **Las reglas del bloque de cuenta del panel** — el `.acct` que hasta el 2026-08-23 pintaba un
 * componente Livewire (`docs/specs/account-context-vue.md` §4.10).
 *
 * Módulo PLANO, sin Vue (`CE-6`): aquí viven las DECISIONES —qué inicial lleva el avatar, a dónde
 * lleva el aviso, qué sub-línea toca— y por eso se pueden probar con `node --test` y comparar contra
 * el Blade al que sustituyen. El componente pinta y no decide.
 *
 * ⚠️⚠️ **Ninguna regla de negocio vive aquí** (`CE-4`). Qué cuenta como próxima reserva, qué
 * formulario está pendiente y a qué URL lleva lo decide el SERVIDOR y llega resuelto en
 * `GET /me/account-context` —o en la semilla del montaje, que sale del mismo recurso—. Lo de aquí es
 * presentación: elegir entre dos textos y componer un destino.
 *
 * ⚠️ **Las fechas NO se componen**: `next_reservation.date_label` llega hecha del servidor porque
 * `Intl` no reproduce en español lo que compone Carbon —«Sáb. 5 sep.» frente a «Sáb 5 sept»— y su
 * fuente única es `Platform\Services\DisplayTime::dayLabel()` (`DayLabelSingleSourceTest`).
 */

import { t, tp } from '../i18n.js';

/**
 * La letra del avatar.
 *
 * ⚠️ **El punto medio no es adorno**: `firstName` puede llegar VACÍO —el servidor recorta el nombre,
 * y un nombre de solo espacios da cadena vacía, cosa que `CustomerAccountContextTest` fija—. Sin este
 * respaldo el avatar saldría como un círculo vacío, que se lee como un fallo de carga.
 *
 * Espeja `Str::upper(Str::substr($firstName, 0, 1))` del Blade. Se usa el operador de propagación y
 * no `charAt`, para no partir por la mitad un nombre que empiece por un carácter fuera del BMP.
 */
export function initialOf(firstName) {
    const first = [...String(firstName ?? '').trim()][0] ?? '';

    return first === '' ? '·' : first.toLocaleUpperCase();
}

/**
 * La sub-línea: la próxima reserva, o que no hay ninguna.
 *
 * ⚠️ Pinta `date_label` **tal como llega**. Recomponerla aquí sería la quinta copia de una fórmula
 * que ya divergió una vez en cuatro superficies.
 */
export function sublineOf(context, account) {
    const next = context?.next_reservation ?? null;

    if (next === null) return t(account, 'sidecart.no_upcoming');

    return tp(account, 'sidecart.next', { date: next.date_label, product: next.product_name });
}

/**
 * El aviso de formularios pendientes, o `null` si no hay ninguno.
 *
 * ⚠️⚠️ **Con UNO lleva al formulario; con VARIOS, a la lista dentro del cajón**, y esa asimetría es
 * del Blade original: con varios no hay un destino único que acertar, así que se atienden sin
 * navegar. `zone === null` significa **«esto no es del cajón: deja navegar»**.
 *
 * ⚠️ **El `href` se compone SIEMPRE, incluso cuando el cajón se hace cargo.** Es lo que hace que
 * funcionen el clic central y «abrir en pestaña nueva», y lo que evita un enlace que «no falla y no
 * hace nada» — la familia de `DECISIONES #117`.
 *
 * ⚠️ **Y si el contador dice uno pero la lista no lo trae, se degrada a la lista.** No es teoría: la
 * semilla del montaje viaja PODADA por cardinalidad (`Http\Sidebar\AccountContextSeed`), así que
 * esta función tiene que sobrevivir a un contexto en el que el contador y la lista no se
 * corresponden. Sin esta rama, ese caso daría un `href` `undefined`.
 */
export function alertOf(context, account, urls = {}) {
    const count = Number(context?.pending_forms_count ?? 0);

    if (count <= 0) return null;

    const only = count === 1 ? (context?.pending_forms?.[0] ?? null) : null;

    if (only !== null) {
        return {
            text: tp(account, 'sidecart.form_pending_one', { product: only.product_name }),
            href: only.url,
            zone: null,
        };
    }

    return {
        text: tp(account, 'sidecart.form_pending_many', { count }),
        href: urls.my_orders ?? '',
        zone: 'orders',
    };
}

/**
 * El contador de reservas próximas, o `null` si no hay ninguna.
 *
 * El número va `aria-hidden` en el marcado y su lectura la da la etiqueta de al lado, con la MISMA
 * clave que usa el índice del área para lo mismo: dos rótulos distintos para el mismo número es una
 * divergencia esperando su turno.
 */
export function counterOf(context, account) {
    const count = Number(context?.upcoming_count ?? 0);

    if (count <= 0) return null;

    return { count, label: tp(account, 'sidecart.upcoming_count', { count }) };
}

/**
 * **Todo el bloque, ya decidido**: la cara que toca y lo que lleva dentro.
 *
 * ⚠️⚠️ **La cara se decide por si HAY CONTEXTO, no por `userId`**, y eso importa más de lo que
 * parece: `userId` es una prop estática del montaje —del HTML de *esa* carga de página— y quien
 * consigue sesión dentro del embudo **no recarga**. Mirar `userId` dejaría el bloque saludando como
 * invitado a alguien que acaba de entrar, que es exactamente el fallo que este bloque existía para
 * evitar cuando se hizo Livewire en 2026-06-14. `userId` conserva su trabajo, que es otro: decirle a
 * la cesta si ha cambiado de dueño.
 */
export function panelOf(context, { account = {}, messages = {}, urls = {} } = {}) {
    if (! context) {
        return {
            identified: false,
            hello: t(account, 'sidecart.guest_hello'),
            subline: t(account, 'sidecart.guest_sub'),
            login: t(account, 'nav.login'),
            reservations: t(messages, 'my_reservations'),
        };
    }

    return {
        identified: true,
        initial: initialOf(context.first_name),
        hello: tp(account, 'nav.hello', { name: context.first_name }),
        subline: sublineOf(context, account),
        alert: alertOf(context, account, urls),
        counter: counterOf(context, account),
        signOut: t(account, 'nav.sign_out'),
        reservations: t(messages, 'my_reservations'),
        // ⚠️ El MISMO rótulo que el índice usa para su propia pantalla (`account.account.title`): el
        // botón y su destino tienen que llamarse igual, o el cliente cree que va a otro sitio.
        account: t(account, 'account.title'),
    };
}
