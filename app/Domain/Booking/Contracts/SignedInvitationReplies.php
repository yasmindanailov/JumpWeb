<?php

namespace App\Domain\Booking\Contracts;

/**
 * **¿La respuesta de esta invitación ya tiene justificante?** (T5·5 de
 * `specs/celebracion-e-invitacion.md` §10.6·C, `DECISIONES #704`).
 *
 * ⚠️⚠️ **Existe por una frontera, no por gusto.** Las respuestas de una invitación son de Booking y las
 * firmas son de Identity, y **Booking no puede mirar a Identity** (`ModuleBoundariesTest`). Es la misma
 * frontera —y el mismo sentido— que {@see ReservationPlacesTaken}: Booking declara lo que necesita
 * saber, Identity lo implementa y el binding vive en el composition root.
 *
 * ▶ **Para qué lo necesita Booking**: el RECIBO le ofrecía firmar a quien acababa de firmar. El owner
 * lo vio al probar la T5·3 y es lo que peor sienta en esta pantalla — el padre ya hizo lo que se le
 * pedía y la página se lo vuelve a pedir, así que duda de si le sirvió.
 *
 * ⚠️ **Se pregunta por la ATADURA, no por el nombre.** Una firma que nace desde el recibo queda atada a
 * esa respuesta (`invitation_reply_id`, `DECISIONES #576`), así que la pregunta tiene respuesta EXACTA.
 * Comparar nombres es lo que `#328` descartó, y aquí ni hace falta ni sería honesto: dos niños de la
 * misma clase pueden llamarse igual.
 *
 * ⚠️ **Falla por el lado seguro**: de un justificante firmado por OTRA vía —el enlace del correo, sin
 * pasar por la invitación— esto dice `false`, porque no hay atadura y no hay forma de saber que es el
 * mismo niño. El recibo vuelve a ofrecer firmar y el dominio para el duplicado con «un niño, un papel»
 * (`waiver-por-reserva.md` §4.8). Ofrecer de más es una molestia; esconder el botón a quien NO ha
 * firmado sería dejar a un niño sin justificante.
 */
interface SignedInvitationReplies
{
    /** ¿Hay un justificante atado a esa respuesta? */
    public function isReplySigned(int $replyId): bool;
}
