/**
 * **El carné QR visto desde el cajón** (Fase 6 · A, `docs/specs/identidad-qr-puerta.md` §4.1, §4.5,
 * §9.6 B·2).
 *
 * Módulo PLANO, sin Vue (`CE-6`): lo poco que la pantalla decide sobre lo que publica `GET /me/card`
 * vive aquí con su `node --test`, y `stores/card.js` solo coloca lo que responde el servidor.
 *
 * ⚠️ **El cliente NO dibuja el QR** (`CE-4`, B·1): lo dibuja el servidor en `png_url` —los MISMOS bytes
 * que el adjunto del correo— y aquí solo se compone la URL con su versión. Un codificador de QR en el
 * navegador habría costado ≥ 8 KiB de chunk para repetir un dibujo que el servidor ya sabe hacer.
 */

/**
 * El token en grupos de CUATRO, para dictarlo en la puerta si la cámara falla: `JW0X 3K9M …`.
 *
 * Es exactamente lo que teclea el mostrador —`CardToken::normalize()` quita los espacios y sube a
 * mayúsculas—, así que agruparlo no cambia lo que vale. `''` sin token.
 *
 * @param {string|null|undefined} token
 */
export function tokenGroups(token) {
    const clean = String(token ?? '').toUpperCase().replace(/[^0-9A-Z]/g, '');

    return clean.match(/.{1,4}/g)?.join(' ') ?? '';
}

/**
 * ¿Hay carné que DIBUJAR? El servidor lo dice con `token` (y `png_url`) nulos cuando su clave de
 * cifrado rotó (§8.1): entonces la pantalla ofrece renovar en vez de pintar un hueco.
 *
 * @param {{token?: string|null, png_url?: string|null}|null|undefined} card
 */
export function cardIsDrawable(card) {
    return typeof card?.token === 'string' && card.token !== '' && typeof card?.png_url === 'string' && card.png_url !== '';
}

/**
 * La URL de la imagen con la VERSIÓN del carné.
 *
 * ⚠️ Un `<img>` cuyo `src` no cambia **no se vuelve a pedir** aunque el servidor diga `no-store`: al
 * renovar, el token cambia pero la URL de `png_url` es la misma para todos los carnés del titular. Se
 * le añade `issued_at` —que cambia con cada carné— para que el navegador pida la imagen nueva. `''`
 * si no hay nada que dibujar.
 *
 * @param {{png_url?: string|null, issued_at?: string|null}|null|undefined} card
 */
export function cardImageUrl(card) {
    if (! cardIsDrawable(card)) return '';

    const url = card.png_url;
    const version = encodeURIComponent(String(card.issued_at ?? ''));

    if (version === '') return url;

    return `${url}${url.includes('?') ? '&' : '?'}v=${version}`;
}
