<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    /**
     * Lista blanca de asignación masiva (recomendación B, 2026-06-15). Antes `$guarded = []`.
     * Las escrituras reales (`Settings`/`Maintenance` del panel) usan `updateOrCreate` con claves
     * de una constante de código, no de la petición; defensa en profundidad.
     *
     * @var array<int,string>
     */
    protected $fillable = ['key', 'value', 'group'];

    /**
     * Memo POR PETICIÓN de la tabla completa: un request lee `Setting::value()` varias veces
     * (CSP de `SecurityHeaders`, color de marca de `ThemeSettings`, flags de `MaintenanceSettings`…);
     * las colapsamos en UNA sola lectura. El estado estático se reinicia entre peticiones (el SAPI
     * hace request-shutdown) y lo invalidamos al guardar/borrar un ajuste, así una escritura del
     * panel se refleja en el mismo request. Independiente del pluck del composer (`AppServiceProvider`).
     *
     * @var array<string,mixed>|null
     */
    private static ?array $memo = null;

    protected static function booted(): void
    {
        static::saved(static fn () => self::flushMemo());
        static::deleted(static fn () => self::flushMemo());
    }

    /**
     * Vacía el memo. Necesario cuando la BD cambia SIN pasar por los eventos de arriba:
     * el rollback de `RefreshDatabase` entre tests revierte la tabla pero el estático
     * sobrevive en el mismo proceso PHPUnit → `tests/TestCase.php::setUp()` lo llama
     * para que ningún test herede ajustes de otro.
     */
    public static function flushMemo(): void
    {
        self::$memo = null;
    }

    /** Lee un ajuste por clave (con valor por defecto). */
    public static function value(string $key, mixed $default = null): mixed
    {
        self::$memo ??= static::query()->pluck('value', 'key')->all();

        return self::$memo[$key] ?? $default;
    }
}
