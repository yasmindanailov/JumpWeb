<?php

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Exceptions\DependentNotFoundException;
use App\Domain\Identity\Exceptions\DependentNotMinorException;
use App\Domain\Identity\Exceptions\GuardianAuthorizationNotFoundException;
use App\Domain\Identity\Exceptions\WaiverDocumentStaleException;
use App\Domain\Identity\Exceptions\WaiverEmailUnverifiedException;
use App\Domain\Identity\Models\Dependent;
use App\Domain\Identity\Models\GuardianAuthorization;
use App\Domain\Identity\Models\LegalDocumentVersion;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\WaiverSignature;
use App\Domain\Platform\Services\AuditLogger;
use App\Domain\Platform\Services\DisplayTime;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

/**
 * Fase 6 · waiver — el ÚNICO escritor de `waiver_signatures` (`specs/waiver-probatorio.md` §4.3,
 * §4.4, §8.5). Recibe la versión que el servidor SIRVIÓ (la capa de entrega es quien exige que la
 * petición traiga su identificador: sin eso, la firma no queda atada a ningún texto) y escribe:
 *
 *  1. la fila probatoria, con hash canónico y `prev_hash` de la firma anterior del MISMO SUJETO
 *     — la cadena es POR (TITULAR, SUJETO) desde `DECISIONES #197` (`menores-a-cargo.md` §9.5·1):
 *     el titular tiene la suya y cada menor a su cargo la suya, así que la poda de una nunca deja
 *     agujeros en otra. El punto de serialización de TODAS las cadenas de un titular sigue siendo
 *     el lock de su fila de `users`;
 *  2. si el sujeto es el titular: la fila de `consents` que él VE en su cuenta (art. 7.1) y el sello
 *     `users.waiver_accepted_at` que leen las superficies heredadas (infolist del pedido; la puerta
 *     en modo externo). La prueba es (1); (2) es presentación.
 *  3. si el sujeto es un MENOR a cargo (`menores-a-cargo.md` §4.3): la pertenencia se comprueba AQUÍ,
 *     bajo el lock —suyo, activo y menor— porque el `subject_id` llega del cliente (§4.9), y su
 *     identidad de ese momento se copia en la fila (v3), como la del titular desde `#161`.
 *  4. si el sujeto es un MENOR INVITADO (`specs/waiver-por-reserva.md` §4.1): la identidad del menor
 *     **y la de quien firma** se copian de su autorización (v4). ⚠️ Aquí `$holder` **no es quien
 *     firma**: es el RESPONSABLE de la reserva. Quien firma es un adulto sin cuenta, y por eso esta
 *     rama no exige correo verificado (§7·8).
 *  5. el rastro de auditoría, sin PII: `waiver.signed` o, si la declara un operador, `waiver.declared`.
 *
 * ⚠️⚠️ **La cadena a la que pertenece cada firma la decide `WaiverSignature::chainKey()`/`inChain()`,
 * nunca una condición escrita aquí.** Hasta la T1 del justificante por reserva esta clase acotaba con
 * `where('subject_id', …)` y un sujeto sin `subject_id` habría hecho que la idempotencia devolviera
 * la firma de otro menor, en silencio (`waiver-por-reserva.md` §11·B1).
 */
final class WaiverSigner
{
    public function sign(User $holder, LegalDocumentVersion $version, WaiverSignatureRequest $request): WaiverSignature
    {
        if ($version->slug !== WaiverSettings::SLUG) {
            throw new InvalidArgumentException("La versión #{$version->getKey()} es de «{$version->slug}», no del waiver.");
        }
        if (! in_array($request->channel, WaiverSignature::CHANNELS, true)) {
            throw new InvalidArgumentException("Canal de firma desconocido: «{$request->channel}».");
        }
        if ($request->subjectType === WaiverSignature::SUBJECT_DEPENDENT && $request->subjectId === null) {
            throw new InvalidArgumentException('La firma en nombre de un menor a cargo necesita su identificador.');
        }
        if ($request->subjectType === WaiverSignature::SUBJECT_GUEST_MINOR && $request->authorizationId === null) {
            throw new InvalidArgumentException('El justificante de un menor invitado necesita su autorización.');
        }
        if ($holder->isAnonymized()) {
            throw new LogicException('Una cuenta anonimizada no puede firmar el waiver.');
        }

        return DB::transaction(function () use ($holder, $version, $request): WaiverSignature {
            // ⚠️ PRIMERA sentencia de la transacción: el lock de la fila del titular es el punto de
            // serialización de TODAS sus cadenas (§8.5). Dos firmas simultáneas del mismo sujeto se
            // ordenan aquí y la segunda encuentra la primera (idempotencia); las de titulares
            // distintos no se estorban. Misma doctrina que `AFORO-01`, y el mismo instrumento para
            // verla fallar: `waiver:verify-chain` sobre MySQL real.
            $locked = User::query()->whereKey($holder->getKey())->lockForUpdate()->firstOrFail();

            // `[DECIDIDO owner, 2026-08-26]` (spec §7·5, `#179`): la firma del TITULAR exige el correo
            // verificado — es lo que prueba que quien acepta es dueño del buzón que la firma copia.
            // La firma DECLARADA en mostrador (§8.4) queda fuera: ahí la identidad la asegura el
            // operador, y el cliente de agenda puede no tener correo. Sobre la fila BLOQUEADA.
            //
            // ⚠️⚠️ Y el JUSTIFICANTE de un menor invitado también queda fuera
            // (`[DECIDIDO owner, 2026-09-01]`, `specs/waiver-por-reserva.md` §7·8): **aquí el buzón
            // del titular no es el de quien acepta**. Quien firma es un adulto sin cuenta y su
            // correo, que tampoco está verificado, viaja en `signer_email` con el PDF diciéndolo.
            // Exigirlo mataría el caso principal: un colegio se da de alta POR TELÉFONO y
            // `CustomerRegistrar` deja `email_verified_at` en `null` a propósito, así que ningún
            // padre podría firmar. Esto MATIZA la decisión del 26-08, no la contradice: aquélla es
            // sobre la firma del propio titular.
            $needsVerifiedEmail = $request->declaredBy === null
                && $request->subjectType !== WaiverSignature::SUBJECT_GUEST_MINOR;
            if ($needsVerifiedEmail && $locked->email_verified_at === null) {
                throw new WaiverEmailUnverifiedException;
            }

            // S-3 (`#181`): la vigencia se comprueba FUERA (`WaiverAcceptance::currentDocument()`) y una
            // publicación cruzada entre esa lectura y este `create` firmaría una versión superada sin 409.
            // Se re-comprueba aquí, bajo el lock, contra el máximo publicado.
            if ((int) $version->version !== (int) LegalDocuments::latestVersionNumber($version->slug)) {
                throw new WaiverDocumentStaleException;
            }

            // El SUJETO menor a cargo: suyo, activo y menor — decidido bajo el lock, y su identidad
            // copiada tal y como está ahora (`menores-a-cargo.md` §4.2, §4.9; `DECISIONES #197`).
            $subjectIdentity = ['subject_name' => null, 'subject_born_on' => null];
            $signerIdentity = ['signer_name' => null, 'signer_email' => null, 'signer_phone' => null, 'signer_relationship' => null];

            // El SUJETO menor INVITADO (`specs/waiver-por-reserva.md` §4.1): su identidad y la de quien
            // firma se copian de la autorización, que `GuardianAuthorizationSigner` acaba de crear (o
            // encontrar) DENTRO de esta misma transacción y bajo este mismo lock.
            //
            // ⚠️ Se comprueba AQUÍ, como la pertenencia de un menor a cargo, porque el identificador
            // llega de fuera: una autorización que no exista no puede firmarse.
            if ($request->subjectType === WaiverSignature::SUBJECT_GUEST_MINOR) {
                $authorization = GuardianAuthorization::query()->whereKey($request->authorizationId)->first();
                if ($authorization === null) {
                    throw new GuardianAuthorizationNotFoundException;
                }

                $subjectIdentity = [
                    'subject_name' => mb_substr($authorization->minorFullName(), 0, 255),
                    'subject_born_on' => $authorization->minor_born_on->toDateString(),
                ];
                $signerIdentity = [
                    'signer_name' => mb_substr($authorization->guardianFullName(), 0, 255),
                    'signer_email' => $authorization->guardian_email !== null ? mb_substr((string) $authorization->guardian_email, 0, 255) : null,
                    'signer_phone' => $authorization->guardian_phone !== null ? mb_substr((string) $authorization->guardian_phone, 0, 32) : null,
                    'signer_relationship' => (string) $authorization->guardian_relationship,
                ];
            }

            if ($request->subjectType === WaiverSignature::SUBJECT_DEPENDENT) {
                $dependent = Dependent::query()
                    ->whereKey($request->subjectId)
                    ->where('user_id', $locked->getKey())
                    ->active()
                    ->first();
                if ($dependent === null) {
                    throw new DependentNotFoundException;
                }
                if (! $dependent->isMinor()) {
                    throw new DependentNotMinorException;
                }
                // ▶ NOMBRE Y APELLIDOS desde `#236`, en la misma columna. Una firma es una prueba y
                // lo que prueba es a QUIÉN cubre: cuanto más identifica, mejor cumple su función.
                //
                // ⚠️ **Se guarda en `subject_name` y NO se añade columna**, y eso es deliberado: la
                // fila entra en una cadena de hashes por (titular, sujeto) y `computeHash()` cubre
                // estos campos. Cambiar el CONJUNTO de campos obligaría a subir
                // `CANONICAL_VERSION` y a que el verificador supiera de dos formas; cambiar el
                // VALOR no toca nada — las firmas anteriores conservan su hash, calculado con lo
                // que se guardó entonces, que es exactamente lo que una prueba debe hacer.
                //
                // El corte a `NAME_MAX` deja de bastar: se corta a lo que admite la columna.
                $subjectIdentity = [
                    'subject_name' => mb_substr($dependent->fullName(), 0, 255),
                    'subject_born_on' => $dependent->born_on->toDateString(),
                ];
            }

            // La última firma de ESTE sujeto: es a la vez la cabeza de su cadena (`prev_hash`) y lo que
            // decide la idempotencia por VERSIÓN (revisión `#169` §10.2·4): si este mismo sujeto ya
            // firmó esta versión —en cualquier idioma: el texto publicado es el mismo—, no se escribe
            // una segunda fila ni un segundo consentimiento: se devuelve la que hay. Va DENTRO del lock
            // a propósito: dos envíos simultáneos del mismo `document_id` pasan los dos la comprobación
            // de vigencia, y solo el lock de la fila del titular los pone en fila.
            // ⚠️⚠️ La cadena se acota con `inChain()`, NO con `where('subject_id', …)`. Hasta la T1 del
            // justificante por reserva esto estaba escrito a mano aquí, y con un sujeto cuyo
            // `subject_id` es `null` Laravel lo convierte en `is null`: **todas** las autorizaciones
            // del mismo responsable habrían compartido esta búsqueda y la idempotencia de abajo
            // habría devuelto la firma de OTRO menor —el segundo padre veía «hecho», recibía su
            // correo y su hijo se quedaba sin justificante—. La regla vive en `WaiverSignature`
            // (`chainKey()`/`scopeInChain()`) y la comparten el firmador y los dos verificadores.
            $previous = WaiverSignature::query()
                ->where('user_id', $locked->getKey())
                ->inChain($request->subjectType, $request->subjectId, $request->authorizationId)
                ->with('version')
                ->orderByDesc('id')
                ->first();

            if ($previous !== null && (int) $previous->version->version === (int) $version->version) {
                return $previous;
            }

            $now = now();
            $attributes = [
                'user_id' => (int) $locked->getKey(),
                'subject_type' => $request->subjectType,
                'subject_id' => $request->subjectId,
                'subject_authorization_id' => $request->authorizationId,
                'legal_document_version_id' => (int) $version->getKey(),
                'document_hash' => (string) $version->body_hash,
                'accepted_at' => $now,
                'accepted_tz' => DisplayTime::timezone(),
                'ip' => $request->ip !== null ? mb_substr($request->ip, 0, 45) : null,
                'user_agent' => $request->userAgent !== null ? mb_substr($request->userAgent, 0, 512) : null,
                'channel' => $request->channel,
                'declared_by_user_id' => $request->declaredBy?->getKey(),
                'prev_hash' => $previous?->hash,
                // La IDENTIDAD del firmante, tal y como está AHORA (`DECISIONES #161`, owner): tras
                // `anonymize()` la fila de `users` ya no identifica a nadie, y una prueba que apunte a
                // «Cliente eliminado» no prueba quién firmó. Entra en el hash (esquema v2).
                'holder_name' => $locked->name !== null ? mb_substr((string) $locked->name, 0, 255) : null,
                'holder_email' => $locked->email !== null ? mb_substr((string) $locked->email, 0, 255) : null,
                // Y la del SUJETO menor —a cargo o invitado—, por la misma razón (v3, `#197`).
                'subject_name' => $subjectIdentity['subject_name'],
                'subject_born_on' => $subjectIdentity['subject_born_on'],
                // Y la de QUIEN FIRMA cuando no es el titular (v4): en un justificante de menor
                // invitado, `holder_*` es el RESPONSABLE de la reserva y `signer_*` el padre o tutor.
                // Las TRES personas de la prueba viajan en la fila y sobreviven a `anonymize()`.
                'signer_name' => $signerIdentity['signer_name'],
                'signer_email' => $signerIdentity['signer_email'],
                'signer_phone' => $signerIdentity['signer_phone'],
                'signer_relationship' => $signerIdentity['signer_relationship'],
                'canonical_version' => WaiverSignature::CANONICAL_VERSION,
            ];
            $attributes['hash'] = WaiverSignature::computeHash($attributes);

            $signature = WaiverSignature::create($attributes);

            if ($request->subjectType === WaiverSignature::SUBJECT_HOLDER) {
                $locked->consents()->create([
                    'type' => 'waiver',
                    'accepted_at' => $now,
                    'ip' => $attributes['ip'],
                    'version' => $version->label(),
                ]);
                $locked->forceFill(['waiver_accepted_at' => $now])->save();
            }

            AuditLogger::log($request->declaredBy !== null ? 'waiver.declared' : 'waiver.signed', $locked, [
                'signature_id' => $signature->getKey(),
                'version' => $version->version,
                'locale' => $version->locale,
                'subject_type' => $request->subjectType,
                'subject_id' => $request->subjectId,
                'authorization_id' => $request->authorizationId,
                'channel' => $request->channel,
                'declared_by_user_id' => $request->declaredBy?->getKey(),
            ]);

            return $signature;
        });
    }
}
