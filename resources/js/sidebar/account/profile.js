/**
 * **Lo que la pantalla de «Tus datos» necesita componer** (`specs/area-cliente.md` §9, paso 7b).
 *
 * Módulo PLANO (`CE-6`). Aquí no se decide nada de negocio: si el correo se puede cambiar, si hay
 * uno pendiente y hasta cuándo vale su enlace lo dice el SERVIDOR —`pending_email` y
 * `pending_email_expires_at` vienen resueltos—; esto solo los coloca.
 */

/** Los campos que el formulario del perfil edita, tal como llegan de `GET /me`. */
export function profileForm(user) {
    return {
        name: String(user?.name ?? ''),
        email: String(user?.email ?? ''),
        phone: String(user?.phone ?? ''),
        locale: String(user?.locale ?? ''),
    };
}

/**
 * Minutos que le quedan al enlace de confirmación, o `0` si no hay ninguno o ya caducó.
 *
 * ⚠️⚠️ **El «ahora» entra por PARÁMETRO, y es la lección de `DECISIONES #64`**: una función que
 * mirase el reloj por su cuenta no se podría probar sin congelarlo, y su test amanecería rojo el día
 * que la ventana cruzara un límite. Aquí el reloj lo pone quien llama, y el test lo fija.
 *
 * ⚠️ **Se redondea hacia ARRIBA**: a falta de 30 segundos, decir «0 min» sonaría a caducado cuando
 * todavía sirve. Decir «1 min» es cierto hasta el último instante.
 */
export function minutesLeft(expiresAt, now = Date.now()) {
    if (! expiresAt) return 0;

    const end = Date.parse(String(expiresAt));

    if (Number.isNaN(end)) return 0;

    return Math.max(0, Math.ceil((end - now) / 60000));
}

/**
 * El aviso del cambio pendiente ya compuesto, o `null` si no hay ninguno.
 *
 * ⚠️ **`null` es un estado real**: sin cambio pendiente la pantalla no pinta el bloque, y pintarlo
 * vacío anunciaría una espera que no existe.
 */
export function pendingNotice(user, account, now = Date.now(), translate) {
    if (! user?.pending_email) return null;

    return {
        email: user.pending_email,
        minutes: minutesLeft(user.pending_email_expires_at, now),
        message: translate(account, 'account.profile.pending_email_msg', {
            email: user.pending_email,
            minutes: minutesLeft(user.pending_email_expires_at, now),
            current: user.email,
        }),
    };
}
