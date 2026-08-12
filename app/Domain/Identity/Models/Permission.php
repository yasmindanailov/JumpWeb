<?php

namespace App\Domain\Identity\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    /**
     * Lista blanca de asignación masiva (recomendación B, 2026-06-15). Antes `$guarded = []`.
     * Los permisos los siembra `PermissionSeeder`; defensa en profundidad.
     *
     * @var array<int,string>
     */
    protected $fillable = ['name', 'label'];

    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }
}
