<?php

namespace App\Models;

use App\Domain\Platform\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;

class Faq extends Model
{
    use HasTranslations;

    protected $guarded = [];

    protected $casts = [
        'question' => 'array',
        'answer' => 'array',
        'is_active' => 'boolean',
    ];
}
