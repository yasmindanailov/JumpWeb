<?php

namespace App\Domain\Identity\Models;

use App\Domain\Identity\Exceptions\ImmutableRecordException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Fase 6 · waiver — una VERSIÓN publicada de un documento legal, en UN idioma
 * (`docs/specs/waiver-probatorio.md` §4.2). Es el snapshot de lo que se le enseñó a quien firmó:
 * título y cuerpo YA interpolados (datos fiscales resueltos), con su hash.
 *
 * INMUTABLE: publicar crea fila; `updating` y `deleting` lanzan. Vive en Identity —y no en Content,
 * donde se redacta— porque es «lo que el titular aceptó», e Identity no puede mirar a Content
 * (`ModuleBoundariesTest`): el texto llega desde la capa de entrega como datos planos.
 *
 * ⚠️ La guarda es de MODELO: `DB::table()->update()` la salta, igual que salta cualquier otra guarda
 * de este repo. Por eso la fila lleva su hash y `verifyHash()` existe: una alteración por debajo no
 * pasa desapercibida, se detecta.
 */
class LegalDocumentVersion extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected $casts = [
        'body' => 'array',
        'version' => 'integer',
        'published_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $row): void {
            throw ImmutableRecordException::for(self::class, 'update');
        });
        static::deleting(function (self $row): void {
            throw ImmutableRecordException::for(self::class, 'delete');
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    /**
     * @return HasMany<WaiverSignature, $this>
     */
    public function signatures(): HasMany
    {
        return $this->hasMany(WaiverSignature::class);
    }

    /** «v3·es»: lo que ve el titular en su lista de consentimientos. */
    public function label(): string
    {
        return 'v'.$this->version.'·'.$this->locale;
    }

    /**
     * @return list<array{h:string,p:string}>
     */
    public function sections(): array
    {
        return self::normaliseBody(is_array($this->body) ? $this->body : []);
    }

    public function verifyHash(): bool
    {
        return hash_equals((string) $this->body_hash, self::hashFor((string) $this->title, $this->sections()));
    }

    /**
     * Hash canónico del texto: JSON sin escapes de título + secciones normalizadas, en orden.
     * Forma y orden están FIJADOS aquí desde el primer commit (spec §4.7): cambiar cualquiera de los
     * dos invalidaría la verificación de todo lo ya firmado.
     *
     * @param  list<array{h:string,p:string}>  $sections
     */
    public static function hashFor(string $title, array $sections): string
    {
        $canonical = json_encode(
            ['title' => $title, 'sections' => array_values($sections)],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );

        return hash('sha256', $canonical);
    }

    /**
     * Secciones {h,p} recortadas, sin las vacías, como lista.
     *
     * @param  array<int|string,mixed>  $body
     * @return list<array{h:string,p:string}>
     */
    public static function normaliseBody(array $body): array
    {
        $out = [];
        foreach ($body as $section) {
            if (! is_array($section)) {
                continue;
            }
            $h = trim((string) ($section['h'] ?? ''));
            $p = trim((string) ($section['p'] ?? ''));
            if ($h === '' && $p === '') {
                continue;
            }
            $out[] = ['h' => $h, 'p' => $p];
        }

        return $out;
    }
}
