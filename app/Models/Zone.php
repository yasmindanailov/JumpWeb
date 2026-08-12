<?php

namespace App\Models;

use App\Domain\Content\Models\Attraction;
use App\Domain\Platform\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Zone extends Model
{
    use HasTranslations;

    protected $guarded = [];

    protected $casts = [
        'name' => 'array',
        'subtitle' => 'array',
        'description' => 'array',
        'age_label' => 'array',
        'age_range' => 'array',
        'is_active' => 'boolean',
        'show_in_landing' => 'boolean',
        // Cupo de packs POR ZONA (override del ajuste global; null = usa el global).
        'max_per_slot' => 'integer',
        'max_guests_per_slot' => 'integer',
        'prep_blocks_cupo' => 'boolean',
    ];

    public function attractions(): HasMany
    {
        return $this->hasMany(Attraction::class)->orderBy('position');
    }
}
