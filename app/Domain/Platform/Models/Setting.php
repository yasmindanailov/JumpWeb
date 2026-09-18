<?php

namespace App\Domain\Platform\Models;

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

    /**
     * El nombre del NEGOCIO — el que ve un cliente, no el del producto.
     *
     * ⚠️⚠️ **Existe porque las dos formas de escribirlo a mano NO son equivalentes, y estaban
     * las dos vivas** (medido el 2026-09-10, nueve copias):
     *   · `Setting::value('business.name', config('app.name'))` — el defecto de `value()` es
     *     `?? $default`, así que **solo actúa si la clave NO EXISTE**. Con la fila creada y el
     *     valor en blanco devuelve `''`, y seis correos firmaban con el nombre vacío.
     *   · `Setting::value('business.name') ?: config('app.name')` — ésta sí cae con el vacío.
     * Un operador que borre el campo en el panel producía dos conductas distintas según qué
     * pantalla lo leyera, y ninguna fallaba. Aquí hay una sola.
     *
     * ▶ El suelo es `config('app.name')` a propósito: es preferible que un correo diga el nombre
     * del producto a que diga «Un saludo,» y nada.
     */
    public static function businessName(): string
    {
        $name = trim((string) self::value('business.name', ''));

        return $name !== '' ? $name : (string) config('app.name');
    }

    /**
     * La dirección DESDE la que salen los correos — del panel, no del `.env`.
     *
     * `[DECIDIDO owner, 2026-09-10]`: el remitente sale de los ajustes. Hasta hoy lo ponía
     * `MAIL_FROM_ADDRESS` y en una instalación recién montada vale literalmente
     * `hello@example.com`, el placeholder de Laravel — o sea que **el hueco falla hacia
     * ridículo**, no hacia invisible: los correos salen, y salen mal.
     *
     * ⚠️ **Degrada, nunca lanza.** Si el ajuste está vacío o no es una dirección válida se
     * devuelve la de `config`, que es lo que había: *un ajuste mal puesto no puede impedir que
     * un correo salga*. La validación fuerte es del panel (`->email()`); esto es el cinturón.
     *
     * ▶ Devuelve `null` solo si TAMPOCO hay nada en config — y entonces quien llama no toca el
     * remitente, porque no hay con qué sustituirlo.
     */
    public static function mailFromAddress(): ?string
    {
        foreach ([self::value('mail.from_address', ''), config('mail.from.address')] as $candidato) {
            $dir = trim((string) $candidato);
            if ($dir !== '' && filter_var($dir, FILTER_VALIDATE_EMAIL) !== false) {
                return $dir;
            }
        }

        return null;
    }

    /**
     * **La rebaja que YA está aplicada al catálogo**, en tanto por ciento, para escribir el precio de
     * antes tachado en la web (`promo.percent`; sin ajuste o fuera de 1–99, `0` = no hay «antes»).
     *
     * ⚠️⚠️ **Chapuza declarada** (`[DECIDIDO owner, 2026-09-18]`: *«no quiero spec, ni sistema ni nada;
     * más adelante haremos un sistema de ofertas»*). Es un dato de la INSTALACIÓN que se escribe en
     * la tabla `settings` sin pantalla en el panel, junto a `promo.banner.{es,en,fr}` (el recuadro de
     * la sección de tarifas). **No toca ningún precio que se cobre**: la promo del 17-09 rebajó las
     * filas de `prices` como dato (`ENTORNOS.md` §6) y esto solo deshace esa rebaja para enseñarla.
     * Apagar la promo = borrar las filas; no hace falta desplegar.
     */
    public static function promoPercent(): int
    {
        $pct = (int) self::value('promo.percent', 0);

        return $pct > 0 && $pct < 100 ? $pct : 0;
    }
}
