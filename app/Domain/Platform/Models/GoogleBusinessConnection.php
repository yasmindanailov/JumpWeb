<?php

namespace App\Domain\Platform\Models;

use App\Domain\Platform\Enums\GoogleBusinessStatus;
use App\Domain\Platform\Services\GoogleBusinessLocation;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * **La conexión con la ficha de Google** — la fila única que guarda el token de refresco del parque
 * (`docs/specs/google-business-profile.md` §4.2; `DECISIONES #524`).
 *
 * ⚠️⚠️ **El token NO se lee por la propiedad.** El cast `encrypted` lanza `DecryptException` si la
 * `APP_KEY` rotó sin `APP_PREVIOUS_KEYS`, y eso ocurriría **dentro** de la pasada, de la pantalla del
 * panel o de un `toArray()`. Se lee por {@see self::readToken()}, que convierte ese caso en lo que
 * significa —«caducada», hay que reconectar— en vez de en un 500.
 *
 * ⚠️ **Quién conectó es una FK del esquema y no hay relación de Eloquent** (§4.0): Platform no depende
 * de ningún módulo (`ModuleBoundariesTest` dice `'Platform' => []`). El nombre del usuario lo resuelve
 * la capa de entrega, que sí puede.
 *
 * @property GoogleBusinessStatus $status
 * @property CarbonImmutable|null $status_changed_at
 * @property CarbonImmutable|null $connected_at
 * @property string|null $token_fingerprint
 * @property int|null $connected_by_user_id
 */
class GoogleBusinessConnection extends Model
{
    protected $table = 'google_business_connections';

    /**
     * ⚠️ Ni el token ni su huella salen en `toArray()`/`toJson()`. La pantalla del panel es Livewire y
     * **serializa sus propiedades públicas al snapshot que viaja al navegador**: sin esto, el token
     * cifrado acabaría en el HTML de la página de ajustes.
     *
     * @var list<string>
     */
    protected $hidden = ['refresh_token', 'token_fingerprint'];

    /**
     * Lista blanca de asignación masiva, como el resto de la casa: las escrituras reales usan claves
     * de constantes de código, nunca de la petición.
     *
     * @var list<string>
     */
    protected $fillable = [
        'status', 'status_changed_at',
        'refresh_token', 'token_fingerprint',
        'location_name', 'account_name', 'location_title', 'place_id', 'maps_uri', 'new_review_uri',
        'connected_by_user_id', 'connected_at',
    ];

    /** @return array<string,string> */
    protected function casts(): array
    {
        return [
            'refresh_token' => 'encrypted',
            'status' => GoogleBusinessStatus::class,
            'status_changed_at' => 'immutable_datetime',
            'connected_at' => 'immutable_datetime',
        ];
    }

    /** La fila única, o `null` si el parque nunca conectó. */
    public static function current(): ?self
    {
        return static::query()->first();
    }

    /**
     * El valor CIFRADO tal cual está en el atributo, sin pasar por el cast.
     *
     * ⚠️ **`getAttributes()` y no `getRawOriginal()`** (medido el 2026-09-20): el segundo devuelve lo
     * que se leyó de la base, así que entre un `$conexion->refresh_token = 'nuevo'` y su `save()`
     * seguiría entregando el token ANTERIOR. Es el tipo de desfase que no rompe ningún test y sí
     * rompe una reconexión.
     */
    private function cipherText(): ?string
    {
        $raw = $this->getAttributes()['refresh_token'] ?? null;

        return is_string($raw) && $raw !== '' ? $raw : null;
    }

    /**
     * ¿Hay un token GUARDADO? Mira el atributo en crudo **a propósito**: preguntarlo por la propiedad
     * descifraría, y «hay token pero no se puede leer» es un estado distinto de «no hay token» —el
     * primero es «caducada» y el segundo «lista para conectar»—. Distinguirlos es lo que evita
     * mandar al admin a reconectar cuando lo que pasa es que falta la llave de la aplicación.
     */
    public function hasStoredToken(): bool
    {
        return $this->cipherText() !== null;
    }

    /**
     * El token de refresco, o `null` si no hay o **no se puede descifrar**.
     *
     * ⚠️⚠️ **Se descifra a mano en vez de leer `$this->refresh_token`**, aunque el cast haría lo mismo
     * (medido: `Crypt::decryptString()` sobre el crudo devuelve exactamente lo que entrega el cast).
     * El motivo es que por la propiedad **el fallo es invisible**: ni un lector ni el análisis estático
     * ven que ahí puede saltar una excepción —Larastan llegó a declarar muerto este `catch`, y la
     * medición demostró que no lo está—. Un método que existe justo porque esta lectura puede fallar
     * tiene que enseñar dónde falla.
     *
     * ⚠️ **No se registra nada del fallo de cifrado** (§4.2·5): el mensaje de `DecryptException` no
     * dice nada accionable y lo que sí haría es meter en el log una pista sobre el material cifrado.
     * El estado «caducada» que sale de aquí ya es el aviso, y ése sí se cuenta.
     */
    public function readToken(): ?string
    {
        $cifrado = $this->cipherText();

        if ($cifrado === null) {
            return null;
        }

        try {
            $token = Crypt::decryptString($cifrado);
        } catch (DecryptException) {
            return null;
        }

        return $token !== '' ? $token : null;
    }

    /**
     * El `parent` con el que se piden las reseñas de la ficha conectada (§4.2·10).
     *
     * ⚠️ `null` si falta la cuenta —una conexión elegida antes de la T1·5— o si no hay ficha. Quien
     * lo use tiene que decirlo, no adivinarlo: pedir con el `name` a secas devuelve un 404 que se
     * lee como «ficha perdida».
     */
    public function reviewsParent(): ?string
    {
        return GoogleBusinessLocation::reviewsParentFor($this->account_name, $this->location_name);
    }

    /**
     * La huella de un token, para comparar-y-escribir (§4.2·7).
     *
     * Es `sha256` y no el token: guardar la huella permite saber **si el token cambió** sin tener que
     * descifrar el guardado para compararlo —que es justo lo que no se puede hacer cuando el motivo
     * de la comparación es que el descifrado falla—.
     */
    public static function fingerprint(#[\SensitiveParameter] string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * ¿Sigue vigente el token con el que un worker salió a llamar?
     *
     * ⚠️⚠️ **Esto es lo que impide que una pasada vieja pise una reconexión nueva.** Entre que el
     * worker cogió el token y que Google le contestó `invalid_grant`, el admin puede haber reconectado
     * desde el panel: marcar «caducada» entonces apagaría una conexión que funciona, y el admin vería
     * su reconexión deshacerse sola.
     */
    public function tokenStillIs(#[\SensitiveParameter] string $token): bool
    {
        $stored = $this->token_fingerprint;

        return is_string($stored) && hash_equals($stored, self::fingerprint($token));
    }
}
