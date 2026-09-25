<?php

namespace App\Http\Fiesta;

use App\Domain\Booking\Contracts\AuthorizableReservation;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\PartyInvitation;
use App\Domain\Identity\Models\LegalDocumentVersion;
use App\Domain\Platform\Services\DisplayTime;
use App\Domain\Platform\Services\Turnstile;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Illuminate\Support\ViewErrorBag;

/**
 * EL MODELO DE PÁGINA de la autorización de un menor invitado (`specs/fiesta-sistema-nuevo.md` §4.1, T3; `#743`,
 * `#745`): lo que `GuardianAuthorizationController::show()` calcula, con la forma de `paginas/autorizacion/datos.js`
 * del diseño, para que la vista lea SOLO `$m` y el banco pinte lo mismo con los datos del diseño.
 *
 * ❗❗ HOJA EN BLANCO (spec `waiver-por-reserva.md` §7·2): aquí no entra ni un dato de las autorizaciones ya firmadas, y
 * el niño nunca viene puesto (`#706`: lo que se escribió en la invitación se ENSEÑA en una nota, no se reparte).
 *
 * ⚠️ `#745`: se mantienen la fecha de nacimiento del menor (entra en la prueba firmada y da la edad a la puerta) y la
 *    relación con el menor (entra en la prueba); el adulto escribe «tu nombre y apellidos» en UNA casilla y el
 *    teléfono es obligatorio (el brief). El texto del descargo se presenta en el flujo (`waiver-probatorio.md` §4.4).
 *    Quien responde del menor sale en la tarjeta con su teléfono (§12.4 de la spec hermana, `[DECIDIDO owner]`).
 */
final class Autorizacion
{
    /**
     * @param  array<string, mixed>  $v  lo que calcula el controlador: `reservation`, `context`, `document`, `invitation`,
     *                                   `blocked`, `relationships`, `responsible`, `fromInvitation`, `prefill`,
     *                                   `dependents`, `formAction`, `status`, `minorName`, `signer`, `errors`, `old`
     * @param  array<string, mixed>  $site
     * @return array<string, mixed>
     */
    public static function componer(array $v, array $site): array
    {
        /** @var OrderItem $reservation */
        $reservation = $v['reservation'];
        /** @var AuthorizableReservation $context */
        $context = $v['context'];
        /** @var LegalDocumentVersion $document */
        $document = $v['document'];
        $inv = $v['invitation'] ?? null;
        $inv = $inv instanceof PartyInvitation ? $inv : null;

        $tema = $inv?->safeTheme() ?? 'confeti';
        $t = Temas::de($tema);
        $nombreSitio = (string) ($site['name'] ?? config('app.name'));
        $h = self::anfitrion($reservation);
        $responsable = (array) ($v['responsible'] ?? []);
        $telefono = trim((string) ($responsable['phone'] ?? ''));
        $cumple = trim((string) ($inv->honoree_name ?? ''));
        $status = is_string($v['status'] ?? null) ? $v['status'] : null;
        $bloqueado = is_string($v['blocked'] ?? null) ? $v['blocked'] : null;

        $titulo = $cumple !== '' ? __('fiesta.autorizacion.titular', ['n' => $cumple]) : __('fiesta.autorizacion.titular_visita');

        return [
            'titulo_pagina' => $titulo.' · '.$nombreSitio,
            'marca' => Marca::logo($site),
            'idiomas' => InvitacionPagina::idiomas(),
            'tema' => ['clave' => $tema, 'tinte' => $t['tint'], 'acento' => $t['accent']],
            'tarjeta' => [
                'edad' => $inv?->honoree_age === null ? '' : (string) ((int) $inv->honoree_age),
                'titulo' => $titulo,
                'linea' => self::linea($context, $nombreSitio, $site),
                'que' => __('fiesta.autorizacion.que', ['h' => $h]),
            ],
            'anfitrion' => [
                'etiqueta' => __('guardian.booking.responsible'),
                'linea' => trim((string) ($responsable['name'] ?? '')),
                'nombre' => $h,
                'telefono' => $telefono,
                'tel' => $telefono === '' ? '' : 'tel:'.preg_replace('/\s+/', '', $telefono),
            ],
            'bloqueado' => $bloqueado === null ? null : [
                'motivo' => $bloqueado,
                'titulo' => __('guardian.blocked.heading'),
                'texto' => __('guardian.blocked.'.$bloqueado),
            ],
            'listo' => $status !== 'signed' ? null : [
                'texto' => __('fiesta.firma.firmada'),
                'quien' => trim((string) ($v['minorName'] ?? '')),
                'firmante' => trim((string) ($v['signer'] ?? '')),
            ],
            'aviso' => self::aviso($status, trim((string) ($v['minorName'] ?? ''))),
            'formulario' => $bloqueado !== null || $status === 'signed' ? null : self::formulario($v, $document, $h),
            'privacidad' => [
                'texto' => __('fiesta.autorizacion.privacidad', ['h' => $h]),
                'datos' => __('guardian.notice'),
                'politica' => __('guardian.privacy_link'),
                'enlace' => route('legal.privacidad'),
            ],
            'turnstile' => [
                'activo' => Turnstile::enabled(),
                'clave' => Turnstile::enabled() ? Turnstile::siteKey() : '',
                'rotulo' => __('guardian.antibot_label'),
            ],
        ];
    }

    /** Quien responde del menor, por su nombre de pila (`#744`): el titular de la reserva. Sin nombre, «quien organiza». */
    private static function anfitrion(OrderItem $reservation): string
    {
        $nombre = trim((string) ($reservation->order->user->name ?? ''));
        $pila = trim(Str::before($nombre, ' '));

        return $pila !== '' ? $pila : __('fiesta.invitacion_pagina.quien_organiza');
    }

    /**
     * «Sábado 26 de septiembre · 17:00 · Play Jump Park, Lorca · Nº R-5F2K8.»: cuándo, dónde y la referencia (como el
     * resguardo de la lista). Sin fecha, se dice.
     *
     * @param  array<string, mixed>  $site
     */
    private static function linea(AuthorizableReservation $context, string $nombreSitio, array $site): string
    {
        $lugar = trim($nombreSitio.((($site['city'] ?? '') !== '') ? ', '.$site['city'] : ''));
        $fecha = $context->date === null
            ? __('guardian.booking.no_date')
            : Str::ucfirst(CarbonImmutable::parse($context->date)->locale(app()->getLocale())->isoFormat(__('fiesta.fecha.larga')));
        [$hora] = array_pad(explode('–', (string) ($context->timeWindow ?? ''), 2), 1, '');
        $partes = array_values(array_filter([$fecha, trim($hora), $lugar, __('fiesta.autorizacion.numero', ['codigo' => (string) $context->orderCode])], static fn (string $p): bool => $p !== ''));

        return implode(' · ', $partes).'.';
    }

    /**
     * Los desenlaces que no son «firmada» (esa es el Listo): «ya firmada» (un niño, un papel), el texto que cambió, el
     * anti-robot y el rechazo del dominio bajo el lock. Un tono por desenlace y su título delante (spec hermana J-02).
     *
     * @return array{tono: string, titulo: string, texto: string, rol: string}|null
     */
    private static function aviso(?string $status, string $minor): ?array
    {
        return match ($status) {
            'already' => ['tono' => 'neutral', 'titulo' => __('guardian.done.already_title'), 'rol' => 'status',
                'texto' => $minor !== '' ? __('guardian.done.already', ['name' => $minor]) : __('guardian.done.already_generic')],
            'stale' => ['tono' => 'warn', 'titulo' => __('guardian.done.stale_title'), 'rol' => 'alert', 'texto' => __('guardian.done.stale')],
            'antibot' => ['tono' => 'danger', 'titulo' => __('guardian.done.refused_title'), 'rol' => 'alert', 'texto' => __('guardian.done.antibot')],
            'not_paid', 'closed', 'full' => ['tono' => 'danger', 'titulo' => __('guardian.done.refused_title'), 'rol' => 'alert', 'texto' => __('guardian.blocked.'.$status)],
            default => null,
        };
    }

    /**
     * El formulario: lo que la pieza `x-fiesta.firma` pinta, los valores que vuelven tras un error, los fallos por
     * campo con palabras, los campos del producto (nacimiento, relación), el texto del descargo y, con sesión, los
     * menores a cargo para elegir (§12.5 de la spec hermana: rellena, no envía; no enlaza la cuenta con la firma).
     *
     * @param  array<string, mixed>  $v
     * @return array<string, mixed>
     */
    private static function formulario(array $v, LegalDocumentVersion $document, string $h): array
    {
        $errores = $v['errors'] ?? null;
        $e = static fn (string $campo): string => $errores instanceof ViewErrorBag ? (string) $errores->first($campo) : '';
        $old = (array) ($v['old'] ?? []);
        $prefill = (array) ($v['prefill'] ?? []);
        $o = static fn (string $campo, ?string $defecto = null): string => (string) ($old[$campo] ?? $defecto ?? '');
        $desde = (array) ($v['fromInvitation'] ?? []);

        $menores = [];
        foreach ((array) ($v['dependents'] ?? []) as $d) {
            $menores[] = [
                'value' => (string) $d['id'],
                'label' => (string) $d['label'],
                'attrs' => [
                    'data-name' => (string) $d['name'], 'data-surname' => (string) $d['surname'],
                    'data-born-on' => (string) ($d['born_on'] ?? ''), 'data-relationship' => (string) $d['relationship'],
                ],
            ];
        }

        $relaciones = [['value' => '', 'label' => __('guardian.guardian.relationship_placeholder')]];
        foreach ((array) ($v['relationships'] ?? []) as $r) {
            $relaciones[] = ['value' => (string) $r, 'label' => __('guardian.relationships.'.$r)];
        }

        return [
            'accion' => (string) ($v['formAction'] ?? ''),
            'documento_id' => (int) $document->getKey(),
            'respuesta_id' => isset($desde['reply_id']) ? (int) $desde['reply_id'] : null,
            'desde_invitacion' => trim((string) ($desde['minor'] ?? '')),
            'menores' => $menores,
            'valores' => [
                'ninoNombre' => $o('minor_name'), 'ninoApellidos' => $o('minor_surname'),
                'nombre' => $o('guardian_name', $prefill['guardian_name'] ?? null),
                'telefono' => $o('guardian_phone', $prefill['guardian_phone'] ?? null),
                'correo' => $o('guardian_email', $prefill['guardian_email'] ?? null),
                'casilla' => $o('accept_waiver'),
            ],
            'fallos' => [
                'ninoNombre' => $e('minor_name'), 'ninoApellidos' => $e('minor_surname'), 'nombre' => $e('guardian_name'),
                'telefono' => $e('guardian_phone'), 'correo' => $e('guardian_email'), 'casilla' => $e('accept_waiver'),
            ],
            'nacimiento' => ['label' => __('fiesta.firma.nacimiento'), 'hint' => __('guardian.minor.born_on_help'), 'value' => $o('minor_born_on'), 'error' => $e('minor_born_on')],
            'relacion' => ['label' => __('fiesta.firma.relacion'), 'opciones' => $relaciones, 'value' => $o('guardian_relationship'), 'error' => $e('guardian_relationship')],
            'descargo' => [
                'titulo' => (string) $document->title,
                'version' => __('guardian.waiver.version', ['version' => (string) $document->version, 'date' => DisplayTime::format($document->published_at, 'd/m/Y')]),
                'cuerpo' => array_values(array_map(static fn (array $s): array => ['h' => (string) ($s['h'] ?? ''), 'p' => (string) ($s['p'] ?? '')], (array) $document->body)),
            ],
            'casilla' => __('fiesta.firma.casilla', ['h' => $h]),
        ];
    }
}
