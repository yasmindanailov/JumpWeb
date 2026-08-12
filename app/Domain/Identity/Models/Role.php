<?php

namespace App\Domain\Identity\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    /**
     * Lista blanca de asignación masiva (recomendación B, 2026-06-15). Antes `$guarded = []`.
     * Las ediciones reales viven en `RoleResource` (admin, vía `sync()` de IDs explícitos); esto
     * es defensa en profundidad. `name` se incluye por el seeder/factory, pero los 3 roles base
     * son inmutables por lógica de negocio.
     *
     * @var array<int,string>
     */
    protected $fillable = ['name', 'label'];

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    /**
     * @return BelongsToMany<Permission, $this>
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }
}
