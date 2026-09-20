<?php

namespace App\Domain\Content\Models;

use App\Domain\Content\Exceptions\ForeignImageUrlException;
use App\Domain\Content\Services\GoogleReviewImages;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;

/**
 * **Una reseña de la ficha de Google, guardada el tiempo justo** (T2·1,
 * `docs/specs/google-business-profile.md` §4.3·2 → §4.3·5; `DECISIONES #524`, `#727`).
 *
 * Solo llegan aquí las **candidatas** (§4.3·2): con texto, con el mínimo de estrellas y no ocultas. Y
 * llegan **saneadas**, no crudas — el nombre de una anónima ya entra a `null`—, porque la regla de la
 * casa es que el dato nace limpio y no se limpia al pintarlo.
 *
 * ❗❗❗ **EL PLAZO SE APLICA AL LEER, no al purgar** (§4.3·4). Es la diferencia entre una promesa y una
 * garantía: una purga programada es un comando que puede no haber corrido —el worker caído, la cola
 * atascada, el despliegue a medias—, y ese día la portada pintaría el nombre y la cara de alguien
 * pasados los 30 días que la política concede. Con el filtro en la consulta, **no hay ningún camino
 * que lea una fila caducada**, corra la purga o no. {@see scopeWithinRetention()}.
 *
 * ❗❗ **Y hay un segundo plazo, más corto, sobre los datos PERSONALES** (§4.3·4): si pasan
 * {@see IDENTIFIED_DAYS} días sin que una pasada vuelva a ver la reseña, **el nombre y la foto dejan
 * de pintarse** aunque el texto siga. El caso que lo justifica no es teórico: el autor borra su reseña
 * en Google, o Google la retira, y la sincronización lleva rota desde el martes. Sin esto, su nombre y
 * su cara siguen en la portada del parque hasta el día 30 porque **a nosotros** se nos rompió algo.
 * ⚠️ Se mide contra el `fetched_at` de LA FILA y no contra el de la última pasada, y es más fino: una
 * reseña que deja de venir mientras las demás siguen llegando **envejece sola**, que es justo el caso
 * de la que borró su autor. {@see identified()}.
 *
 * ⚠️ **Sin ninguna FK, a propósito** (§4.3·11): cruzar esta tabla con clientes o pedidos está
 * prohibido, y la forma de que no se escriba por accidente es que no haya por dónde.
 */
class GoogleBusinessReview extends Model
{
    use Prunable;

    /**
     * **Cuántos días atrás llega la lectura.** Uno menos que el tope que concede la política, para que
     * el borde no dependa de a qué hora del día corra la pasada ni de cuántas horas lleve parada.
     */
    public const FRESH_DAYS = 29;

    /**
     * **Cuántos días sobrevive el NOMBRE y la FOTO sin una pasada que confirme la reseña** (§4.3·4).
     *
     * ⚠️ Tres, y no treinta, porque lo que caduca aquí no es la reseña: es la certeza de que sigue
     * publicada. El texto se puede sostener —lo dijo quien lo dijo— pero la identidad de un tercero no
     * se enseña *«porque todavía no nos hemos enterado de que la retiró»*.
     */
    public const IDENTIFIED_DAYS = 3;

    /**
     * **El tope que concede la política**, y el que aplica la purga: *«limited amounts of Content»* por
     * un máximo de 30 días. ⚠️ Es mayor que {@see FRESH_DAYS} a propósito — la purga es limpieza, y la
     * garantía la da la lectura. Si fueran el mismo número, una purga que se retrasara una hora
     * parecería un fallo del plazo sin serlo.
     */
    public const RETENTION_DAYS = 30;

    /**
     * Lo que delata a una URL en una columna que solo admite rutas nuestras: un esquema (`https:`,
     * `data:`, `javascript:`) o las dos barras de una URL sin esquema, que el navegador completa con
     * el de la página y que por eso es una URL de tercero disfrazada de ruta.
     */
    private const URL_SHAPE = '#^(?:[a-z][a-z0-9+.\-]*:|//)#i';

    protected $guarded = [];

    protected $casts = [
        'anonymous' => 'boolean',
        'star_rating' => 'integer',
        'text_ambiguous' => 'boolean',
        'photos' => 'array',
        'reply_at' => 'datetime',
        'review_created_at' => 'datetime',
        'review_updated_at' => 'datetime',
        'fetched_at' => 'datetime',
    ];

    /**
     * ❗❗❗ **LA GUARDA DEL §4.3·9: aquí dentro una imagen es una RUTA NUESTRA o `null`.**
     *
     * Toda la T2 existe para que la portada **deje de pedirle nada a Google** (`RGPD-05`, `SEC-01`):
     * las fotos se descargan, se sirven desde nuestro servidor y `img-src` deja de nombrar a
     * `lh3.googleusercontent.com`. Guardar aquí un `https://lh3.googleusercontent.com/…` —porque la
     * descarga falló y «total, la URL la tenemos»— **deshace la tanda entera y no rompe ni un test de
     * los que miran la pantalla**: la foto se vería igual de bien, y el visitante volvería a estar
     * pidiéndole su cara a Google sin haberlo consentido.
     *
     * Por eso la guarda vive en `saving()` y **lanza**, en vez de sanear callando: quien escriba eso
     * está cometiendo un error de diseño, y un valor que se corrige solo le deja creer que funcionó.
     * §4.3·6 ya dice qué hacer cuando la descarga falla — *«la inicial, jamás la URL de Google»*.
     */
    protected static function booted(): void
    {
        static::saving(function (self $review): void {
            $review->guardAgainstForeignImage($review->author_photo_path, 'author_photo_path');

            foreach ($review->photos ?? [] as $photo) {
                $review->guardAgainstForeignImage(is_string($photo) ? $photo : null, 'photos');
            }
        });

        // ❗❗ **El fichero se va con la fila, en la misma operación** (§4.3·6). Da igual por dónde
        // se borre —la purga del plazo, el reemplazo de una pasada, «Ocultar», desconectar—: todas
        // pasan por aquí, y por eso la pasada borra fila a fila POR EL MODELO y este modelo es
        // `Prunable` y no `MassPrunable`. Un borrado en masa no instancia nada y dejaría en el
        // disco la cara de alguien cuya reseña ya no existe.
        static::deleting(function (self $review): void {
            app(GoogleReviewImages::class)->forget($review->imagePaths());
        });
    }

    /**
     * Todas las rutas de imagen de esta reseña, la del autor y las suyas.
     *
     * @return list<string|null>
     */
    public function imagePaths(): array
    {
        $rutas = [$this->author_photo_path];

        foreach ($this->photos ?? [] as $foto) {
            $rutas[] = is_string($foto) ? $foto : null;
        }

        return $rutas;
    }

    /**
     * @throws ForeignImageUrlException
     */
    private function guardAgainstForeignImage(?string $value, string $column): void
    {
        if ($value !== null && preg_match(self::URL_SHAPE, $value) === 1) {
            throw ForeignImageUrlException::in($column);
        }
    }

    /**
     * **Las que el plazo deja leer.** Toda consulta que acabe en la portada pasa por aquí.
     *
     * ⚠️ El nombre no es `fresh`: `fresh()` ya existe en Eloquent —recarga el modelo de la base— y un
     * ámbito con ese nombre se invoca por la misma vía. Dos cosas distintas con el mismo nombre en el
     * mismo objeto es una trampa que se cobra sola.
     *
     * @param  Builder<GoogleBusinessReview>  $query
     * @return Builder<GoogleBusinessReview>
     */
    public function scopeWithinRetention(Builder $query): Builder
    {
        return $query->where('fetched_at', '>=', now()->subDays(self::FRESH_DAYS));
    }

    /**
     * **¿Se puede pintar todavía el nombre y la foto de quien la firmó?** (§4.3·4).
     *
     * `false` no oculta la reseña: la deja anónima, como las que lo son de origen.
     */
    public function identified(): bool
    {
        // ⚠️ Sin comprobar `null`, y medido: `fetched_at` es NOT NULL en la tabla, así que toda fila
        // persistida la tiene y Larastan lo sabe (`notIdentical.alwaysTrue`). Escribir la guarda de
        // todos modos haría que el código dijera que puede pasar algo que el esquema impide, y la
        // línea base de la casa solo encoge: se arregla el código, no la lista.
        return $this->fetched_at->greaterThanOrEqualTo(now()->subDays(self::IDENTIFIED_DAYS));
    }

    /**
     * **El nombre que se puede pintar**, o `null`.
     *
     * Reúne las dos razones por las que una reseña sale sin autor —anónima de origen (§4.3·5) o sin
     * confirmar desde hace {@see IDENTIFIED_DAYS} días— para que la vista no tenga que conocer
     * ninguna de las dos. Quien pinta pregunta «¿hay nombre?», no «¿por qué no lo hay?».
     */
    public function publishableAuthor(): ?string
    {
        return $this->identified() ? $this->author_name : null;
    }

    /**
     * **La ruta de la foto que se puede servir**, o `null`. Misma regla que el nombre: una cara es
     * dato personal igual que un nombre, y caducan juntos.
     */
    public function publishablePhotoPath(): ?string
    {
        return $this->identified() ? $this->author_photo_path : null;
    }

    /**
     * Filas a podar: las que pasaron del tope de la política.
     *
     * ⚠️ **Prunable y NO MassPrunable** (§4.3·4), aunque aquí haya una docena de filas: la T2·4 cuelga
     * de `pruning()` el borrado de los ficheros de imagen, y `MassPrunable` borra por consulta **sin
     * instanciar el modelo**, así que las fotos se quedarían huérfanas en el disco. Es una elección de
     * forma tomada antes de que exista el problema, no una optimización olvidada.
     *
     * ⚠️ El tipo declarado es `Builder<static>` y no `Builder<GoogleBusinessReview>`: `static::query()`
     * devuelve lo primero, el parámetro de plantilla de `Builder` **no es covariante**, y los cuatro
     * `prunable()` que ya existen en el repo pagaron esa diferencia con una entrada en la línea base.
     * La base solo encoge, así que aquí se escribe el tipo que el código de verdad devuelve.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where('fetched_at', '<', now()->subDays(self::RETENTION_DAYS));
    }
}
