<?php

namespace App\Domain\Identity\Contracts;

use App\Domain\Identity\Models\UserIdentity;
use Illuminate\Support\Str;

/**
 * Lo que un proveedor externo AFIRMA sobre una persona, ya comprobado
 * (`docs/specs/auth-con-google.md` §6.3).
 *
 * ⚠️⚠️ **Que exista uno de estos objetos significa que la afirmación está VERIFICADA.** Solo lo
 * construye el servicio `GoogleOAuth` —nombrado en prosa: una anotación resoluble se convierte en un
 * `use` y un contrato no importa un servicio (`#320`)—, y solo después de haber cambiado el código
 * por un `id_token` en una petición servidor-a-servidor autenticada con nuestro secreto y de haber
 * comprobado emisor, destinatario, caducidad y `nonce`. Nada que llegue del navegador se
 * convierte en un `SocialProfile`: ésa es exactamente la forma ingenua que permitiría fabricar un
 * token que afirme cualquier correo.
 *
 * ⚠️ **`emailVerified` es lo que dice el PROVEEDOR sobre SU buzón**, y no dice nada sobre la cuenta
 * de JumpWeb que pueda tener ese mismo correo. Confundir las dos cosas fue el bloqueante P12 de la
 * revisión: la guarda miraba el `email_verified` de Google y nunca el de la cuenta destino.
 *
 * ⚠️ Del ámbito `profile` llegan además `picture`, `given_name` y `locale`: **se descartan aquí**
 * (art. 5.1.c, minimización). Lo que no entra en este objeto no existe para el resto del sistema.
 */
final readonly class SocialProfile
{
    /** Claves de la foto que viaja en sesión entre las dos peticiones (§6.3·3). */
    private const SESSION_KEYS = ['provider', 'subject', 'email', 'email_verified', 'name'];

    public function __construct(
        /** `google` hoy; la columna y este campo son genéricos para que Apple no exija otra tabla. */
        public string $provider,
        /** El `sub` de OpenID Connect: el identificador ESTABLE. Nunca el correo (§6.1). */
        public string $subject,
        /** Normalizado a minúsculas y sin espacios, como en las dos puertas que ya existen. */
        public string $email,
        /** Lo que el proveedor afirma sobre SU buzón. `false` ⇒ no se vincula ni se crea nada (§5.2). */
        public bool $emailVerified,
        /** Puede venir vacío o abreviado («Ana G.»): la pantalla de alta lo deja editar (§7). */
        public ?string $name = null,
    ) {}

    public static function google(string $subject, string $email, bool $emailVerified, ?string $name = null): self
    {
        return new self(
            UserIdentity::PROVIDER_GOOGLE,
            $subject,
            Str::lower(trim($email)),
            $emailVerified,
            self::cleanName($name),
        );
    }

    /**
     * La foto que se guarda en la SESIÓN DEL SERVIDOR entre el retorno de Google y el envío de la
     * pantalla de alta (§6.3·3).
     *
     * ⚠️⚠️ **Jamás en campos del formulario.** Si estos datos viajaran por el navegador, cualquiera
     * crearía una cuenta con la identidad verificada de otro — y a esa cuenta se le firma un descargo
     * probatorio. Es la misma doctrina que el `waiver_document_id`: el servidor solo acepta lo que él
     * mismo sirvió.
     *
     * @return array<string, mixed>
     */
    public function toSession(): array
    {
        return [
            'provider' => $this->provider,
            'subject' => $this->subject,
            'email' => $this->email,
            'email_verified' => $this->emailVerified,
            'name' => $this->name,
        ];
    }

    /**
     * Reconstruye la foto guardada, o `null` si no tiene la forma esperada.
     *
     * Devuelve `null` en vez de lanzar porque el llamante ya tiene que saber tratar el caso «no hay
     * nada guardado» (sesión caducada, dos pestañas, vuelta atrás del navegador): un formato
     * inesperado es el mismo desenlace para quien mira, y así no hay dos caminos que probar.
     *
     * @param  mixed  $row
     */
    public static function fromSession($row): ?self
    {
        if (! is_array($row)) {
            return null;
        }

        foreach (self::SESSION_KEYS as $key) {
            if (! array_key_exists($key, $row)) {
                return null;
            }
        }

        if (! is_string($row['provider']) || ! is_string($row['subject']) || ! is_string($row['email'])) {
            return null;
        }
        if ($row['provider'] === '' || $row['subject'] === '' || $row['email'] === '') {
            return null;
        }
        if (! is_bool($row['email_verified'])) {
            return null;
        }
        if ($row['name'] !== null && ! is_string($row['name'])) {
            return null;
        }

        return new self(
            $row['provider'],
            $row['subject'],
            Str::lower(trim($row['email'])),
            $row['email_verified'],
            self::cleanName($row['name']),
        );
    }

    /** El nombre que sirve para rellenar el formulario: recortado, acotado y nunca cadena vacía. */
    private static function cleanName(?string $name): ?string
    {
        if ($name === null) {
            return null;
        }

        $clean = trim(preg_replace('/\s+/u', ' ', $name) ?? '');

        return $clean !== '' ? mb_substr($clean, 0, 120) : null;
    }
}
