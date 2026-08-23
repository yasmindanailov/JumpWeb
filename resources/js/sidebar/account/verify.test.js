import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import { MAX_RESENDS, RESEND_COOLDOWN_SECONDS, nextSecond, resendGate } from './verify.js';

/**
 * La red de la pantalla de «revisa tu correo» y su reenvío (`specs/auth-en-cajon.md` §4.10·2).
 *
 * ⚠️ Lo que aquí se fija no es cosmético: el servidor responde **202 pase lo que pase**, así que si
 * esta pantalla ofrece el botón cuando el servidor va a descartar el envío, el cliente pulsa, lee
 * «reenviado» y **no le llega nada**. La guarda de que los 30 s espejan el limitador del servidor es
 * el caso más importante del fichero.
 */

describe('el botón de reenviar', () => {
    test('con la cuenta atrás a cero y reenvíos disponibles, se puede pulsar', () => {
        assert.deepEqual(
            resendGate({ secondsLeft: 0, resendsLeft: 4 }),
            { canResend: true, waiting: false, exhausted: false }
        );
    });

    test('mientras corre la cuenta atrás, se espera', () => {
        const gate = resendGate({ secondsLeft: 12, resendsLeft: 4 });

        assert.equal(gate.canResend, false);
        assert.equal(gate.waiting, true);
        assert.equal(gate.exhausted, false);
    });

    /**
     * ⚠️ **Agotado NO es «esperando», y la diferencia es el texto que se pinta.** Quien ha gastado sus
     * reenvíos no arregla nada esperando, así que enseñarle una cuenta atrás sería mentirle. La web
     * lo distingue igual: `resends_left` mientras quedan, `resend_limit` cuando no.
     */
    test('sin reenvíos, está agotado — y NO «esperando», aunque corra el reloj', () => {
        const gate = resendGate({ secondsLeft: 25, resendsLeft: 0 });

        assert.equal(gate.exhausted, true);
        assert.equal(gate.waiting, false, 'una cuenta atrás sobre un botón agotado promete algo que no va a pasar');
        assert.equal(gate.canResend, false);
    });

    test('un estado vacío no ofrece el botón: sin reenvíos declarados, no hay reenvío', () => {
        assert.deepEqual(resendGate(), { canResend: false, waiting: false, exhausted: true });
        assert.deepEqual(resendGate({}), { canResend: false, waiting: false, exhausted: true });
    });

    /**
     * ⚠️⚠️ **Falla CERRADA, y este caso nació de un fallo real cometido escribiendo esta misma
     * pantalla.**
     *
     * El store llama a sus campos `resendSeconds`/`resendsLeft` y este módulo espera
     * `secondsLeft`/`resendsLeft`. Pasarle el store entero —que es lo que apetece escribir— dejaba
     * `secondsLeft` en `undefined`, y con el default permisivo de antes eso significaba «cero
     * segundos, adelante»: **la puerta ignoraba la cuenta atrás entera**. No fallaba nada; el botón se
     * ofrecía siempre y el servidor tiraba el reenvío en silencio contestando 202 igual.
     *
     * ▶ Ahora el mismo descuido deja el botón deshabilitado para siempre: sigue siendo un error, pero
     * de los que se ven a la primera. *Ante la duda, no ofrecer* — porque la alternativa es prometerle
     * al cliente un correo que nadie va a mandar.
     */
    test('unos segundos que no son un número NO se leen como «ya puedes»', () => {
        for (const basura of [undefined, null, 'pronto', NaN, {}]) {
            const gate = resendGate({ secondsLeft: basura, resendsLeft: 4 });

            assert.equal(gate.canResend, false, `«${String(basura)}» no puede habilitar el botón`);
            assert.equal(gate.waiting, true);
        }
    });

    test('y un contador de reenvíos que no es un número tampoco', () => {
        for (const basura of [undefined, null, 'cuatro', NaN]) {
            assert.equal(resendGate({ secondsLeft: 0, resendsLeft: basura }).canResend, false);
        }
    });
});

describe('la cuenta atrás', () => {
    test('descuenta de uno en uno', () => {
        assert.equal(nextSecond(30), 29);
        assert.equal(nextSecond(1), 0);
    });

    /** ⚠️ Con suelo: un negativo pintaría «Reenviar en -3 s» y rompería cualquier comparación. */
    test('nunca baja de cero, ni con basura', () => {
        for (const valor of [0, -5, null, undefined, 'x', NaN, Infinity * -1]) {
            assert.equal(nextSecond(valor), 0, `«${String(valor)}» no puede dejar la cuenta atrás en negativo`);
        }
    });

    test('un valor no entero no rompe la cadena', () => {
        assert.equal(nextSecond(2.5), 1.5);
    });
});

describe('los dos números, que NO son arbitrarios', () => {
    /**
     * ⚠️⚠️ **Los 30 s espejan el limitador por IP de `SelfSignup::resendVerification()`.**
     *
     * El endpoint responde 202 aunque descarte el envío, así que una cuenta atrás **más corta** deja
     * el botón disponible mientras el servidor tira el reenvío a la basura: el cliente pulsa, ve
     * «reenviado» y no le llega nada, sin una sola línea de log del lado del cliente. Es la familia de
     * `DECISIONES #115` — una señal que dice una cosa y significa otra.
     *
     * Este caso no puede leer el PHP, así que lo que fija es el **valor acordado**: si alguien lo baja
     * «porque 30 s es mucho», este rojo le manda a mirar el servicio antes de tocarlo.
     */
    test('la espera espeja el limitador del servidor y no se acorta por comodidad', () => {
        assert.equal(RESEND_COOLDOWN_SECONDS, 30, 'el limitador por IP de `SelfSignup` son 30 s: bajarlo aquí ofrece un botón que no envía');
        assert.ok(RESEND_COOLDOWN_SECONDS > 0);
    });

    test('el tope de reenvíos es el mismo que el del modal de la web', () => {
        assert.equal(MAX_RESENDS, 4);
    });

    /** Y el primero se cuenta desde que se manda el alta, así que la pantalla nace esperando. */
    test('recién llegado a la pantalla no se puede reenviar todavía', () => {
        const gate = resendGate({ secondsLeft: RESEND_COOLDOWN_SECONDS, resendsLeft: MAX_RESENDS });

        assert.equal(gate.canResend, false, 'el alta ya gastó el limitador del servidor: ofrecer el botón al llegar es ofrecer un no-op');
        assert.equal(gate.waiting, true);
    });
});
