/**
 * **La pantalla de «revisa tu correo» del alta SUELTA, y su reenvío**
 * (`docs/specs/auth-en-cajon.md` §4.10·2).
 *
 * ⚠️⚠️ **El servidor no dice NADA de este botón, y por eso las reglas viven aquí.**
 * `POST /api/v1/auth/email/resend` responde **202 siempre**: exista la cuenta, esté ya verificada o
 * haya saltado un limitador, la respuesta es la misma —es la misma anti-enumeración del alta y del
 * enlace de recuperar (`SEC-06`)—. `Identity\Services\SelfSignup::resendVerification()` devuelve
 * `false` cuando descarta el envío, pero **ese `false` no sale del servidor**.
 *
 * ▶ Consecuencia: la espera y el tope de reenvíos son **estado de la PANTALLA**, no del dominio, y es
 * exactamente lo que hace el modal de la web —`Register::resend()` lleva su propio contador y el
 * Blade su propia cuenta atrás—. Aquí se transcriben, no se inventan.
 *
 * ⚠️⚠️ **Y los 30 segundos NO son un número redondo: son los del servidor.**
 * `SelfSignup::resendVerification()` aplica un limitador por IP de **30 s** (y otro por correo de 60,
 * que protege al buzón de una víctima, no a este cliente). Una cuenta atrás más corta ofrecería el
 * botón mientras el servidor **descarta el envío en silencio y responde 202 igual**: el cliente
 * pulsaría, vería «reenviado» y no llegaría nada. Es literalmente la familia de `DECISIONES #115`
 * —una señal que dice una cosa y significa otra— y la razón de que este valor esté aquí con su
 * porqué y no suelto en un componente.
 *
 * Módulo PLANO, sin Vue (`CE-6`): reglas puras, sin temporizador. El reloj lo pone el componente,
 * como el sondeo del desenlace pone el suyo.
 */

/**
 * Los segundos que hay que esperar entre reenvíos.
 *
 * ⚠️ **Espeja el limitador por IP del servidor.** Si allí cambia, aquí también: bajarlo por su cuenta
 * devuelve el botón que no hace nada.
 */
export const RESEND_COOLDOWN_SECONDS = 30;

/**
 * Cuántas veces se puede reenviar desde esta pantalla.
 *
 * Es el mismo tope que `Register::resendsLeft` en la web. No lo impone el servidor —que se defiende
 * con sus limitadores—: impide que la pantalla invite a insistir indefinidamente.
 */
export const MAX_RESENDS = 4;

/**
 * En qué estado está el botón de reenviar.
 *
 * Tres estados y no dos, porque el cliente necesita distinguirlos: **puede** pulsar, **espera** unos
 * segundos, o **se acabó** — y el tercero no se arregla esperando, así que su texto tiene que decir
 * otra cosa.
 *
 * @param {{secondsLeft?: number, resendsLeft?: number}} state
 * @returns {{canResend: boolean, waiting: boolean, exhausted: boolean}}
 */
export function resendGate({ secondsLeft, resendsLeft } = {}) {
    // ⚠️ `typeof` y no `Number()`: **`Number(null)` es CERO**, así que un campo ausente que llegara
    // como `null` se leería como «cero segundos, adelante» — exactamente el agujero que esta función
    // cerró. Un número es un número; lo demás es que alguien se equivocó de campo.
    const isNumber = (value) => typeof value === 'number' && Number.isFinite(value);

    const exhausted = ! isNumber(resendsLeft) || resendsLeft <= 0;

    // ⚠️⚠️ **FALLA CERRADA, y esto lo escribió un fallo real.** Unos segundos que no son un número
    // —porque quien llama le pasó el store entero y allí el campo se llama `resendSeconds`— NO pueden
    // leerse como «ya puedes reenviar». Con el default permisivo que tenía esta función, ese descuido
    // **no fallaba**: la puerta ignoraba la cuenta atrás, el botón se ofrecía siempre y el servidor
    // descartaba el reenvío en silencio contestando 202 — el cliente pulsaba y no le llegaba nada.
    // ▶ Ahora ese mismo descuido deja el botón **permanentemente deshabilitado**: sigue siendo un
    // error, pero es un error que se VE en la primera prueba en vez de uno que solo nota el cliente.
    const waiting = ! exhausted && (! isNumber(secondsLeft) || secondsLeft > 0);

    return { canResend: ! exhausted && ! waiting, waiting, exhausted };
}

/**
 * El siguiente valor de la cuenta atrás. Nunca baja de cero.
 *
 * ⚠️ El suelo importa: un contador negativo pintaría «Reenviar en -3 s» y, peor, dejaría
 * `secondsLeft > 0` en falso por accidente si alguien lo comparara con `!== 0`.
 */
export function nextSecond(secondsLeft) {
    const value = Number(secondsLeft);

    return Number.isFinite(value) && value > 0 ? value - 1 : 0;
}
