/**
 * **EL CÓDIGO DE UN SOLO USO, sin estado** — la regla del `CodeInput` del diseño (zip (6) del 30-09,
 * `components/forms/CodeInput`; A4a de `docs/specs/acceso-con-codigo.md` §4.11, `#861`), con su `node --test`. La pieza
 * que lo pinta (`steps/CodeInput.vue`) solo pinta y avisa (`CE-6`).
 */

/** Las cifras de un código (`Identity\Services\LoginCodes::LENGTH`). */
export const CODE_LENGTH = 6;

/**
 * Lo que queda de lo escrito o pegado: solo cifras, y como mucho seis. Pegar «482 913» o «482-913» vale igual: el espacio y
 * el guion son de cómo se LEE el código (el correo lo parte en dos grupos), no del código.
 */
export function codeDigits(raw) {
    return String(raw ?? '').replace(/\D/g, '').slice(0, CODE_LENGTH);
}

/**
 * ¿Hay que avisar de que el código está completo? Con la sexta cifra, y UNA vez por código: sin la segunda condición, cada
 * repintado con el mismo código volvería a entrar, y cada intento de más gasta uno de los cinco del servidor (`LoginCodes`).
 */
export function isNewlyComplete(digits, lastAnnounced) {
    return digits.length === CODE_LENGTH && digits !== lastAnnounced;
}
