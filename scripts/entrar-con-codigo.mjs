/**
 * ENTRAR EN LA PUERTA DEL CAJÓN CON UN CÓDIGO AL CORREO, para los instrumentos del navegador (la A4a de
 * `docs/specs/acceso-con-codigo.md` §4.11, `#848`/`#849`): desde ella ningún cliente escribe una contraseña en el cajón, así
 * que las sondas que entraban con la de la cuenta de pruebas (`probe-card@jumpweb.test`) pasan por aquí.
 *
 * ⚠️ **Una pieza y no seis copias**: la usan las sondas del cajón (`sonda-cajon`, `sonda-embudo`, `sonda-armazon`,
 * `sonda-foco-cuenta`, `sonda-cajon-apertura`, `color-del-cajon` y `sonda-cajon-a4a`). Si la puerta vuelve a cambiar, se
 * cambia AQUÍ. Las de la isla (`sonda-isla`, `sonda-cuenta`) son de plataforma y llevan su propia copia del lector.
 *
 * ⚠️ El código se lee de MAILPIT, de su asunto («482 913 es tu código para entrar», `Notifications\LoginCode`): dentro del
 * contenedor, en `http://mailpit:8025` (`SONDA_MAILPIT` para otro). Solo vale uno llegado DESPUÉS de pedirlo: el buzón
 * guarda los de antes, y el de antes ya no entra.
 * ⚠️ Antes de pedirlo se sueltan los limitadores de la puerta (uno por minuto y correo, diez por minuto e IP, y los de
 * entrar; `SEC-06`): una sonda que entra dos veces seguidas no puede decidir su resultado por un 429. Solo en LOCAL: cada
 * sonda lo comprueba antes de llamar aquí.
 */
import process from 'node:process';
import { execFileSync } from 'node:child_process';

export const MAILPIT = process.env.SONDA_MAILPIT ?? 'http://mailpit:8025';

const tinker = (php) => execFileSync('php', ['artisan', 'tinker', '--execute', php], { encoding: 'utf8' }).trim();

/** Suelta los limitadores de pedir y de escribir un código para `correo`, y los de la IP del contenedor. */
export function limitadoresACero(correo) {
    tinker(`$h = App\\Domain\\Identity\\Services\\SelfSignup::emailHash('${correo}'); foreach (['login-code-ip|127.0.0.1', 'login-code-email|'.$h, 'login-code-email-hour|'.$h, 'login-ip|127.0.0.1', Illuminate\\Support\\Str::transliterate('${correo}|127.0.0.1')] as $k) { Illuminate\\Support\\Facades\\RateLimiter::clear($k); }`);
}

/** Las seis cifras del último código llegado a `correo` DESPUÉS de `desde` (ms), o `null` si en 15 s no llega ninguno. */
export async function codigoDelBuzon(correo, desde) {
    const url = `${MAILPIT}/api/v1/search?query=${encodeURIComponent(`to:"${correo}"`)}&limit=1`;

    for (let i = 0; i < 60; i += 1) {
        const ultimo = (await fetch(url).then((r) => r.json()).catch(() => null))?.messages?.[0];
        const cifras = /(\d{3}) (\d{3}) /.exec(ultimo?.Subject ?? '');
        if (cifras && Date.parse(ultimo.Created) >= desde - 2000) return `${cifras[1]}${cifras[2]}`;
        await new Promise((listo) => setTimeout(listo, 250));
    }

    return null;
}

/**
 * Entra por la puerta del cajón que esté en pantalla —el paso 5 de la compra o la zona de entrar de Mi cuenta—: el correo,
 * «Continuar» y el código del buzón, que con la sexta cifra se comprueba SOLO (el `CodeInput` del diseño, `#861`): no hay
 * que pulsar «Entrar». Con `recordar`, marca «Mantener la sesión iniciada en este dispositivo» (`#858`) ANTES del código.
 * Devuelve el código con el que entró; si no llega ninguno, lanza diciendo a quién se esperaba.
 */
export async function entrarConCodigo(page, correo, { recordar = false } = {}) {
    const puerta = page.locator('.sidecart__panel');

    limitadoresACero(correo);
    await puerta.locator('#login-email').fill(correo);
    const desde = Date.now();
    await puerta.locator('.auth__submit').click();
    // ⚠️ Un correo SIN cuenta no da un «no»: la puerta pasa a la cara del alta. Sin esto, una máquina sin la cuenta de
    // pruebas (no viaja en el repo) fallaría como un «Timeout» que no dice nada.
    await puerta.locator('#login-code, #reg-name').first().waitFor({ timeout: 15000 });
    if (await puerta.locator('#reg-name').isVisible()) {
        throw new Error(`entrar con código: ${correo} no tiene cuenta en esta máquina (la puerta ofrece darse de alta)`);
    }

    const codigo = await codigoDelBuzon(correo, desde);
    if (codigo === null) throw new Error(`entrar con código: no llegó ninguno a ${correo} (${MAILPIT})`);

    if (recordar) await puerta.locator('.auth__row input[type="checkbox"]').check();
    await puerta.locator('#login-code').fill(codigo);

    return codigo;
}
