<?php

namespace App\Domain\Content\Enums;

/**
 * **Por qué se ocultó una reseña** (T2·5,
 * `docs/specs/google-business-profile.md` §4.3·7; `DECISIONES #524`, `#731`).
 *
 * ❗❗ **Tasado, y no un campo de texto libre.** La razón no es de orden: esta lista vive en la única
 * tabla que **no caduca a los 30 días**, así que lo que se escriba ahí se queda. Un campo libre
 * acaba, antes o después, con el nombre de alguien dentro —«la madre de Lucía pidió que…»— escrito
 * por quien no estaba pensando que eso era un dato personal. Con cuatro valores no hay dónde.
 *
 * ⚠️ **Los cuatro los fijó la revisión de privacidad** (§4.3·7) y no se amplían sin decisión: cada
 * motivo nuevo es una promesa nueva sobre qué se hace con él.
 */
enum GoogleReviewSuppressionReason: string
{
    /** Lo pidió quien la escribió. Es el caso que `RGPD-01` obliga a poder atender. */
    case AuthorRequest = 'author_request';

    /** Nombra a un menor o lo hace identificable. */
    case Minor = 'minor';

    /** Habla de la salud de alguien, o de un tercero que no escribió la reseña. */
    case HealthOrThirdParty = 'health_or_third_party';

    /** Cualquier otro motivo del parque. ⚠️ Sin explicación escrita: para eso está `audit_logs`. */
    case Other = 'other';

    /** El rótulo del panel. Vive aquí y no en `lang/` porque el panel es de la casa, no del cliente. */
    public function label(): string
    {
        return match ($this) {
            self::AuthorRequest => 'Lo ha pedido quien la escribió',
            self::Minor => 'Nombra a un menor',
            self::HealthOrThirdParty => 'Habla de la salud de alguien o de un tercero',
            self::Other => 'Otro motivo',
        };
    }
}
