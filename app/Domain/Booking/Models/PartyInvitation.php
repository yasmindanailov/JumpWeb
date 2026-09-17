<?php

namespace App\Domain\Booking\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * **La INVITACIÓN DIGITAL de una reserva de cumpleaños**
 * (`docs/specs/celebracion-e-invitacion.md` §4.4, T4·1; `DECISIONES #573`).
 *
 * Una fila = **una reserva que se puede compartir**. El anfitrión pasa su enlace por donde quiera
 * —un grupo de clase, normalmente— y cada padre contesta desde ahí ({@see InvitationReply}).
 *
 * ⚠️ **Vive en Booking y cuelga de la RESERVA, no del pedido**, como el post-form y el justificante:
 * un pedido puede llevar dos visitas en dos días, y a una fiesta se invita, no a una compra.
 *
 * ⚠️ **Lo que se guarda aquí es lo que el ANFITRIÓN escribe**, y se publica bajo el dominio del
 * parque: por eso `honoree_name` y `host_line` son texto libre con tope corto y su saneo —rechazar
 * URLs y direcciones de correo, `SEC-07`— vive en el escritor, que es quien conoce el idioma del
 * cliente. El teléfono **no se copia aquí**: sale del de la cuenta, y `show_host_phone` solo dice si
 * se enseña (D13).
 *
 * ⚠️⚠️ **`$fillable` es una lista blanca a propósito** (`SEC-10`): esta fila la escribe una superficie
 * pública y su contenido se publica. `guarded = []` dejaría que un payload manipulado tocara el
 * token —que es la credencial del enlace— o el contador del aviso de la víspera.
 */
#[Fillable([
    'order_item_id',
    'token',
    'theme',
    'honoree_name',
    'honoree_age',
    'host_line',
    'show_host_phone',
])]
class PartyInvitation extends Model
{
    /**
     * **12 caracteres base62 ≈ 71 bits.** No es una firma temporal de Laravel (unos 200 caracteres,
     * imposible de teclear y confundible con la credencial del anfitrión) ni una ruta legible como
     * `/i/lucia-8`, que sería adivinable y publicaría el nombre y la edad de un menor en la URL.
     *
     * ⚠️ El enlace **se puede anular**: el operador rota el token desde el panel (T4·4). Por eso es
     * una columna y no algo derivado de la fila.
     */
    public const TOKEN_LENGTH = 12;

    public const HONOREE_NAME_MAX = 60;

    public const HOST_LINE_MAX = 80;

    /**
     * El tema visual, del que salen la banda, el confeti y el color de la chapa de edad — **hechos
     * con formas del sistema y los tokens de la instalación**, nunca con ilustración encargada, que
     * clavaría el mural de un parque concreto en el producto (§3.4, `[DECIDIDO owner]`).
     *
     * ⚠️ **Hoy la lista cerrada tiene UN valor, y eso es honesto, no provisional mal hecho**: los
     * **tres** temas definitivos los elige el owner **viéndolos renderizados en la T5** (§8), que es
     * cuando existen. Declarar aquí tres nombres inventados sería fijar una decisión suya por
     * adelantado; la columna ya es lo bastante ancha para los que elija.
     */
    public const THEME_DEFAULT = 'default';

    /** @var list<string> Lista CERRADA: es lo que permite sanear un valor desconocido (`#448`). */
    public const THEMES = [self::THEME_DEFAULT];

    protected $casts = [
        'order_item_id' => 'integer',
        'honoree_age' => 'integer',
        'show_host_phone' => 'boolean',
        'reminded_at' => 'datetime',
        'reminded_count' => 'integer',
    ];

    /**
     * Un token nuevo.
     *
     * ⚠️ **No comprueba si existe, y es deliberado**: el `UNIQUE` de la columna es la garantía real,
     * y una consulta previa solo añadiría una ventana entre mirar y escribir que daría falsa
     * seguridad. La aritmética: 62^12 ≈ 3,2 × 10^21 valores contra unas pocas miles de filas, así que
     * la probabilidad de choque es del orden de 10^-17 — y si ocurre, el índice lo para.
     */
    public static function freshToken(): string
    {
        return Str::random(self::TOKEN_LENGTH);
    }

    /** El tema saneado contra la lista cerrada. Desconocido o ausente → el del producto. */
    public function safeTheme(): string
    {
        return in_array($this->theme, self::THEMES, true) ? (string) $this->theme : self::THEME_DEFAULT;
    }

    /**
     * @return BelongsTo<OrderItem, $this>
     */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }

    /**
     * @return HasMany<InvitationReply, $this>
     */
    public function replies(): HasMany
    {
        return $this->hasMany(InvitationReply::class);
    }
}
