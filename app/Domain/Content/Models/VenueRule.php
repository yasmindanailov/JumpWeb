<?php

namespace App\Domain\Content\Models;

use App\Domain\Platform\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;

class VenueRule extends Model
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
