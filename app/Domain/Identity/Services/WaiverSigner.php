<?php

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Exceptions\DependentNotFoundException;
use App\Domain\Identity\Exceptions\DependentNotMinorException;
use App\Domain\Identity\Exceptions\WaiverDocumentStaleException;
use App\Domain\Identity\Exceptions\WaiverEmailUnverifiedException;
use App\Domain\Identity\Models\Dependent;
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
 *  4. el rastro de auditoría, sin PII: `waiver.signed` o, si la declara un operador, `waiver.declared`.
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
            if ($request->declaredBy === null && $locked->email_verified_at === null) {
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
                $subjectIdentity = [
                    'subject_name' => mb_substr((string) $dependent->name, 0, Dependent::NAME_MAX),
                    'subject_born_on' => $dependent->born_on->toDateString(),
                ];
            }

            // La última firma de ESTE sujeto: es a la vez la cabeza de su cadena (`prev_hash`) y lo que
            // decide la idempotencia por VERSIÓN (revisión `#169` §10.2·4): si este mismo sujeto ya
            // firmó esta versión —en cualquier idioma: el texto publicado es el mismo—, no se escribe
            // una segunda fila ni un segundo consentimiento: se devuelve la que hay. Va DENTRO del lock
            // a propósito: dos envíos simultáneos del mismo `document_id` pasan los dos la comprobación
            // de vigencia, y solo el lock de la fila del titular los pone en fila.
            $previous = WaiverSignature::query()
                ->where('user_id', $locked->getKey())
                ->where('subject_type', $request->subjectType)
                ->where('subject_id', $request->subjectId)
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
                // Y la del SUJETO menor a cargo, por la misma razón (esquema v3, `#197`).
                'subject_name' => $subjectIdentity['subject_name'],
                'subject_born_on' => $subjectIdentity['subject_born_on'],
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
                'channel' => $request->channel,
                'declared_by_user_id' => $request->declaredBy?->getKey(),
            ]);

            return $signature;
        });
    }
}
