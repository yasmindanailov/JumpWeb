<?php

namespace App\Domain\Platform\Services;

use App\Domain\Platform\Models\Setting;

/**
 * **Las credenciales del cliente OAuth CENTRAL** de JumpSystem
 * (`docs/specs/google-business-profile.md` §4.2·5; `DECISIONES #719`).
 *
 * No son del parque: son de JumpSystem, las mismas para todas las instalaciones, y entran **solo por
 * CLI** (`app:set-setting`, claves protegidas). El panel ni las edita ni las enseña.
 *
 * ⚠️⚠️ **Se leen con consulta FRESCA, jamás con `Setting::value()`** (§4.2·7). `Setting` memoriza la
 * tabla entera por proceso y la purga en sus propios eventos: un worker de cola que lleve horas vivo
 * —que es exactamente quien corre la sincronización— tiene el memo de cuando arrancó, y el `INSERT`
 * que el owner acaba de hacer por SSH **no pasa por esos eventos**. Con `value()`, aprovisionar una
 * instalación dejaría la conexión «sin configurar» hasta el siguiente reinicio, sin que nada fallara.
 *
 * ⚠️ **Hacen falta LAS DOS.** Es la lección de `PublicConfigResource` que ya pagó el login con Google
 * (`specs/auth-con-google.md` §10): con media credencial la ida a Google sale y lo que falla es el
 * canje, al final, con un error de Google que no dice cuál de las dos falta.
 */
final class GoogleBusinessCredentials
{
    public const CLIENT_ID_KEY = 'google_business.client_id';

    public const CLIENT_SECRET_KEY = 'google_business.client_secret';

    private function __construct(
        public readonly string $clientId,
        #[\SensitiveParameter]
        private readonly string $clientSecret,
    ) {}

    /**
     * Lee las dos claves de la base **sin pasar por el memo** de {@see Setting}.
     */
    public static function fresh(): self
    {
        /** @var array<string,mixed> $rows */
        $rows = Setting::query()
            ->whereIn('key', [self::CLIENT_ID_KEY, self::CLIENT_SECRET_KEY])
            ->pluck('value', 'key')
            ->all();

        return new self(
            clientId: trim((string) ($rows[self::CLIENT_ID_KEY] ?? '')),
            clientSecret: trim((string) ($rows[self::CLIENT_SECRET_KEY] ?? '')),
        );
    }

    /** El secreto. Se pide por método para que ningún volcado de propiedades lo arrastre. */
    public function secret(): string
    {
        return $this->clientSecret;
    }

    /** Las DOS, o nada. */
    public function configured(): bool
    {
        return $this->clientId !== '' && $this->clientSecret !== '';
    }

    /**
     * ⚠️⚠️ **El secreto no aparece en un volcado.** `dd()`, `var_dump()`, el reporte de una excepción
     * y el `context` de un log imprimen las propiedades de un objeto: sin esto, una traza de la
     * sincronización llevaría dentro la credencial que autentica a JumpSystem ante Google (§4.2·6).
     *
     * @return array<string,string>
     */
    public function __debugInfo(): array
    {
        return [
            'clientId' => $this->clientId,
            'clientSecret' => $this->clientSecret === '' ? '(vacío)' : '(oculto)',
        ];
    }
}
