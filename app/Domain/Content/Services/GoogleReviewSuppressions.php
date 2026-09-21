<?php

namespace App\Domain\Content\Services;

use App\Domain\Content\Enums\GoogleReviewSuppressionReason;
use App\Domain\Content\Models\GoogleBusinessReview;
use App\Domain\Content\Models\GoogleBusinessReviewSuppression;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * **Ocultar una reseña, y que siga oculta** (T2·5,
 * `docs/specs/google-business-profile.md` §4.3·7; `DECISIONES #524`, `#731`).
 *
 * ❗❗❗ **Ocultar son DOS cosas, y la segunda es la que importa.** Borrar la fila se lleva en el acto
 * el nombre, la foto, las fotos y el texto —también la respuesta del parque— porque el fichero se va
 * con la fila (T2·4). Pero la reseña **sigue publicada en Google**: nosotros no la podemos retirar de
 * ahí, y la pasada de mañana la traería otra vez. Por eso se apunta su hash, y por eso ese apunte
 * **no caduca**. Sin la lista, «ocultar» sería «ocultar hasta las 04:40».
 *
 * ⚠️⚠️ **No toca la media ni el total** (§4.3·7). Son de Google, contados sobre TODAS las reseñas, y
 * el parque no puede cambiar la nota de su ficha decidiendo qué se enseña en su web. Que «ocultar»
 * bajara el recuento sería exactamente el dato engañoso que la Ómnibus persigue.
 *
 * ⚠️ **El rastro va a `audit_logs` con el hash y el motivo, y nada más** (§4.3·7). Quién y cuándo ya
 * los pone el propio registro; el texto de la reseña, el nombre y la foto **no pueden estar ahí**
 * (`RGPD-02`) — y menos en un registro que sobrevive a la reseña que lo causó.
 */
final class GoogleReviewSuppressions
{
    public const ACTION_HIDDEN = 'google_business.review_hidden';

    public const ACTION_UNHIDDEN = 'google_business.review_unhidden';

    /**
     * El identificador con el que se reconoce una reseña sin guardar nada de ella.
     *
     * ⚠️ Es `sha256` del nombre de RECURSO, que es lo único estable que Google da: el texto se puede
     * editar y el autor puede cambiarse el nombre, así que ninguno de los dos sirve para reconocerla.
     */
    public static function hash(string $reviewName): string
    {
        return hash('sha256', $reviewName);
    }

    /**
     * **Oculta una reseña**: la apunta y la borra, en una transacción.
     *
     * ⚠️ Las dos cosas o ninguna. Con el apunte sin el borrado, la reseña sigue en la portada hasta
     * mañana; con el borrado sin el apunte, vuelve mañana. Los dos desenlaces parecen «funcionó».
     */
    public function hide(GoogleBusinessReview $review, GoogleReviewSuppressionReason $reason): string
    {
        $hash = self::hash($review->review_name);

        DB::transaction(function () use ($review, $reason, $hash): void {
            GoogleBusinessReviewSuppression::query()->updateOrCreate(
                ['review_hash' => $hash],
                ['reason' => $reason],
            );

            // ⚠️ Por el MODELO: su evento `deleting` es el que se lleva los ficheros (T2·4).
            $review->delete();
        });

        return $hash;
    }

    /**
     * **Deja de ocultar.** Devuelve `true` si había algo que retirar.
     *
     * ▶ **No estaba en la spec y se añade a sabiendas** (`#731`): sin esto, un clic equivocado es
     * irreversible para siempre, porque la lista no caduca. Y es seguro por cómo está hecho: aquí no
     * se restaura nada —el texto y las imágenes se borraron— sino que se deja de tapar. La reseña
     * reaparece **solo si sigue publicada en Google**, y la trae la pasada como cualquier otra.
     */
    public function unhide(string $hash): bool
    {
        return GoogleBusinessReviewSuppression::query()->where('review_hash', $hash)->delete() > 0;
    }

    /**
     * Los hashes ocultos, para que la pasada no los traiga.
     *
     * @return list<string>
     */
    public function hashes(): array
    {
        return GoogleBusinessReviewSuppression::query()->pluck('review_hash')->all();
    }

    /** Lo que enseña el panel: qué hay oculto y desde cuándo. */
    public function all(): Collection
    {
        return GoogleBusinessReviewSuppression::query()->latest('created_at')->get();
    }
}
