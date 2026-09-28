<?php

namespace App\Domain\Platform\Models;

use App\Domain\Platform\Services\Analytics\AnalyticsGoals;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * **Un objetivo del mes** (T3c·2 de `docs/specs/analitica-para-decidir.md` §4.13, `#759`): cuánto quiere el parque que valga
 * UNA cifra del cuadro en UN mes. Ver la migración `create_analytics_goals_table` para lo que significa cada columna.
 *
 * ⚠️ Vive en `Platform` como `Experiment`: es configuración del producto, sin datos personales. Su único escritor es
 * {@see AnalyticsGoals::save()}, que deja el rastro y olvida la caché del mes.
 *
 * @property int $id
 * @property string $metric_key
 * @property CarbonImmutable $month
 * @property int $target
 * @property ?int $set_by
 */
class AnalyticsGoal extends Model
{
    protected $fillable = ['metric_key', 'month', 'target', 'set_by'];

    protected $casts = [
        'month' => 'immutable_date',
        'target' => 'integer',
        'set_by' => 'integer',
    ];
}
