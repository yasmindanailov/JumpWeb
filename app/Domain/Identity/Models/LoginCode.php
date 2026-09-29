<?php

namespace App\Domain\Identity\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Un CÓDIGO DE UN SOLO USO enviado a un correo (`docs/specs/acceso-con-codigo.md` §4.1, `DECISIONES #848`). Lo emite y
 * lo gasta `Identity\Services\LoginCodes`; nadie más escribe aquí.
 *
 * ⚠️ La fila guarda la HUELLA (`code_hash`, un HMAC), nunca el código: el código en claro solo viaja al correo. Y aun
 * así la fila es PII (el correo y la IP de quien lo pidió), así que no vive más de lo que sirve: un código dura 10
 * minutos y la fila, un día (la poda, `model:prune` en `routes/console.php`); la supresión (`RGPD-01`) se lleva las
 * del titular en el acto.
 *
 * @property string $email
 * @property string $purpose
 * @property string $code_hash
 * @property int $attempts
 * @property Carbon $expires_at
 * @property Carbon|null $used_at
 */
class LoginCode extends Model
{
    use MassPrunable;

    /** ENTRAR en una cuenta que ya existe. La A2 añade `confirm` (una acción sensible, ya con sesión). */
    public const PURPOSE_LOGIN = 'login';

    /** Horas que vive la fila tras nacer; el código muere mucho antes (10 minutos). */
    public const RETENTION_HOURS = 24;

    /** Sin `updated_at`: los intentos y el uso se escriben con consultas condicionadas (`LoginCodes`). */
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    /** @return Builder<static> */
    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<', now()->subHours(self::RETENTION_HOURS));
    }
}
