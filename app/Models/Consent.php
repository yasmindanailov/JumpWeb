<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Consent extends Model
{
    /**
     * Versión actual de los documentos legales aceptados. Se sube cuando
     * cambian los textos (entonces se vuelve a pedir aceptación). `[DECIDIDO]` 2026-05-23.
     */
    public const CURRENT_VERSION = '2026-05-23';

    protected $guarded = [];

    protected $casts = [
        'accepted_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
