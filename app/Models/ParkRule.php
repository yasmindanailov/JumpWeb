<?php

namespace App\Models;

use App\Domain\Platform\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;

class ParkRule extends Model
{
    use HasTranslations;

    protected $table = 'park_rules';

    protected $guarded = [];

    protected $casts = [
        'name' => 'array',
        'description' => 'array',
        'is_active' => 'boolean',
    ];
}
