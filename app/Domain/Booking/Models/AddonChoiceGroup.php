<?php

namespace App\Domain\Booking\Models;

use App\Domain\Platform\Concerns\HasTranslations;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Un GRUPO DE OPCIONES de un producto (`[DECIDIDO owner]` `DECISIONES #914`, `fiesta-sistema-nuevo.md` §4.21): «elige una»
 * entre los complementos del producto que llevan su clave en `product_addons.choice_group`.
 *
 * Lo que es del GRUPO vive aquí, una vez: su título traducible («¿Qué merienda?»), si hay que elegir y su orden. Lo que es
 * de cada OPCIÓN sigue en su enganche: incluida o de pago, por niño o fija, su fase. Repetir lo del grupo en cada opción
 * dejaba que dos opciones del mismo grupo dijeran cosas distintas (la alternativa descartada en `#914`).
 *
 * ⚠️ **En venta POSTERIOR la fila es obligatoria**: `ProductAddon::postFormProblem()` abre las reglas 2–4 de `#413`
 * (incluido, por niño, grupo excluyente) SOLO a las opciones de un grupo de esta tabla. Al reservar es opcional: sin fila,
 * el grupo de siempre (una marcada de serie, `AddonResolver::groupDefault()`).
 *
 * @property int $id
 * @property int $product_id
 * @property string $key
 * @property array<string, string>|null $title
 * @property bool $is_required
 * @property int $position
 * @property Carbon|null $created_at
 */
class AddonChoiceGroup extends Model
{
    use HasTranslations;

    /** El largo de la clave: el de `product_addons.choice_group`, que es donde la llevan sus opciones. */
    public const KEY_MAX = 50;

    protected $guarded = [];

    protected $casts = [
        'title' => 'array',
        'is_required' => 'boolean',
        'position' => 'integer',
    ];

    protected static function booted(): void
    {
        // La clave es la UNIÓN con sus opciones: se guarda recortada, como el panel la escribe en el enganche, o una de las
        // dos mitades no encontraría a la otra.
        static::saving(function (self $group): void {
            $group->key = trim((string) $group->key);
            if ($group->key === '' || mb_strlen($group->key) > self::KEY_MAX) {
                throw new \InvalidArgumentException('Un grupo de opciones necesita una clave de 1 a '.self::KEY_MAX.' caracteres.');
            }
        });

        // Borrar un grupo con opciones las dejaría huérfanas: en venta posterior, sin su fila, la regla 4 las cierra EN
        // SILENCIO y desaparecen de la lista. Primero se sacan del grupo; después se borra.
        static::deleting(function (self $group): void {
            if ($group->memberCount() > 0) {
                throw new \InvalidArgumentException('Este grupo tiene opciones: quítalas del grupo antes de borrarlo.');
            }
        });
    }

    /** @return BelongsTo<TicketType, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(TicketType::class, 'product_id');
    }

    /** Su título en el idioma activo, o su clave si el panel no lo puso: lo que se lee donde el grupo se NOMBRA. */
    public function displayTitle(): string
    {
        $title = trim((string) ($this->tr('title') ?? ''));

        return $title !== '' ? $title : $this->key;
    }

    /** Cuántos enganches de su producto llevan su clave. */
    public function memberCount(): int
    {
        return ProductAddon::query()
            ->where('product_id', $this->product_id)
            ->where('choice_group', $this->key)
            ->count();
    }

    /**
     * ¿Se le pide este grupo a una reserva vendida en `$soldAt`? Un grupo creado DESPUÉS de venderla, no (`[DECIDIDO owner]`
     * `#914`, «No se les pide»): lo vendido se rige por la configuración con la que se vendió —la merienda de las fiestas de
     * antes se eligió con el Menú al reservar— y lo lleva el parque. Sin fecha de un lado o del otro, se pide.
     */
    public function appliesToSaleAt(?DateTimeInterface $soldAt): bool
    {
        return $soldAt === null || $this->created_at === null || $this->created_at->lte($soldAt);
    }

    /**
     * Las claves de los grupos de estos productos, en UNA consulta: el cinturón de lectura (`AddonResolver::forStage()`) las
     * necesita para todas las opciones de una vez, y preguntar por cada una pagaría una consulta por complemento en la
     * página de todo cliente (la pendiente que vigila `GuestFormTest::test_the_page_pays_at_most_two_queries_per_addon`).
     *
     * @param  list<int>  $productIds
     * @return array<int, array<string, true>> producto => [clave => true]
     */
    public static function keysByProduct(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        $keys = [];
        foreach (self::query()->whereIn('product_id', $productIds)->get(['product_id', 'key']) as $group) {
            $keys[(int) $group->product_id][(string) $group->key] = true;
        }

        return $keys;
    }
}
