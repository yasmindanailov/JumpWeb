<?php

namespace App\Domain\Platform\Models;

use App\Domain\Platform\Services\Analytics\Experiments;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * **Un experimento** (`docs/specs/analitica.md` §4.4, T5a): una clave, sus variantes con peso y si está vivo.
 * Ver la migración `create_experiments_table` para lo que significa cada columna.
 *
 * ⚠️ Vive en `Platform` como `Setting`: es configuración del producto que leen el arranque del cajón y las
 * páginas SSR, y no mira a ningún módulo. La asignación NO está aquí: la calcula `Experiments` con el hash.
 *
 * ⚠️ Guardar o borrar una fila OLVIDA la caché de los vivos: lo que el panel apague deja de asignarse en la
 * petición siguiente, no en 60 s.
 *
 * @property int $id
 * @property string $key
 * @property string $name
 * @property ?array<int, mixed> $variants lo que guardó el panel: `weightedVariants()` es quien lo valida
 * @property bool $active
 * @property ?Carbon $started_at
 * @property ?Carbon $ended_at
 */
class Experiment extends Model
{
    protected $fillable = ['key', 'name', 'variants', 'active', 'started_at', 'ended_at'];

    protected $casts = [
        'variants' => 'array',
        'active' => 'boolean',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saved(static function (): void {
            Experiments::forget();
        });
        static::deleted(static function (): void {
            Experiments::forget();
        });
    }

    /** Activo y dentro de su ventana. Es lo único que decide si asigna. */
    public function isRunning(): bool
    {
        return $this->active
            && ($this->started_at === null || $this->started_at->lte(now()))
            && ($this->ended_at === null || $this->ended_at->gt(now()));
    }

    /**
     * Las variantes que valen, en el orden guardado: clave con la forma de `Experiments::VARIANT_RE` y peso entero
     * positivo. Lo que no case se ignora, y con menos de dos válidas el experimento no asigna (no hay nada que
     * comparar).
     *
     * @return array<string, int> variante → peso
     */
    public function weightedVariants(): array
    {
        $out = [];

        foreach ($this->variants ?? [] as $variant) {
            $key = is_array($variant) ? ($variant['key'] ?? null) : null;
            $weight = is_array($variant) ? ($variant['weight'] ?? null) : null;

            if (! is_string($key) || preg_match(Experiments::VARIANT_RE, $key) !== 1 || isset($out[$key])) {
                continue;
            }

            if (! is_numeric($weight) || (int) $weight <= 0) {
                continue;
            }

            $out[$key] = (int) $weight;
        }

        return $out;
    }
}
