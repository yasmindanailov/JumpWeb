<?php

namespace App\Http\Fiesta;

use App\Domain\Booking\Models\InvitationReply;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\PartyInvitation;
use App\Domain\Booking\Services\PartyInvitations;
use App\Domain\Content\Models\Attraction;
use App\Domain\Content\Services\CmsSocialProof;
use App\Domain\Identity\Models\LegalDocumentVersion;
use App\Domain\Platform\Services\DisplayTime;
use App\Domain\Platform\Services\SiteLocales;
use App\Domain\Platform\Services\Turnstile;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

/**
 * EL MODELO DE PÁGINA de la invitación digital y de su recibo (`specs/fiesta-sistema-nuevo.md` §4.1, T2; `#743`,
 * `#744`): lo que `InvitationPageController::show()` y `receipt()` calculan, con la forma de
 * `paginas/invitacion/datos.js` del diseño (`FIESTA`, `T`, `P`), para que las vistas lean SOLO `$m` y el banco pinte lo
 * mismo con los datos del diseño.
 *
 * ❗❗ Sigue siendo una HOJA EN BLANCO (spec hermana §7.2·R1): aquí no entra ni una respuesta, ni cuántas hay, ni si un
 * nombre contestó. El recibo enseña solo lo que escribió quien lo abre.
 *
 * ⚠️ Lo que el diseño pinta y hoy NO es dato (§1.4, `#743`): los grupos de la merienda con su icono (hoy, cada
 *    complemento marcado es un grupo, con su nombre y sus detalles, y un icono neutro), «Crear mi QR» y «Avísame de
 *    fechas». Entran una a una, con el owner. Desde F1 y F6a (26-09) SON dato o pieza: las palabras y las pistas
 *    (`family_words`, `gift_hints`), la merienda por grupos, «Ver el parque» y la firma dentro del recibo.
 * ⚠️ `#744`: tras contestar, el padre ve el RECIBO (no la invitación con un aviso); los textos nombran a quien organiza
 *    por el nombre de pila de la cuenta (sin él, «quien organiza la fiesta»); el «qué es la fiesta» es la descripción
 *    pública del pack.
 */
final class InvitacionPagina
{
    /**
     * @param  array<string, mixed>  $v  lo que calcula el controlador: `invitation`, `reservation`, `hostPhone`,
     *                                   `timeWindow`, `menu`, `repliesOpen`, `preview`, `calendarUrl`, `deadline`,
     *                                   `replyAction`, `status` y, en el recibo, `receipt`
     * @param  array<string, mixed>  $site
     * @return array<string, mixed>
     */
    public static function componer(array $v, array $site): array
    {
        /** @var PartyInvitation $inv */
        $inv = $v['invitation'];
        /** @var OrderItem $reservation */
        $reservation = $v['reservation'];

        $tema = $inv->safeTheme();
        $t = Temas::de($tema);
        $h = self::anfitrion($reservation);
        $cumple = [
            'nombre' => trim((string) $inv->honoree_name),
            'edad' => $inv->honoree_age === null ? '' : (string) ((int) $inv->honoree_age),
        ];

        $date = $reservation->slot?->date;
        $carbon = $date === null ? null : CarbonImmutable::instance($date)->locale(app()->getLocale());
        [$hora, $fin] = array_pad(explode('–', (string) ($v['timeWindow'] ?? ''), 2), 2, '');
        $hora = trim($hora);
        $fin = trim($fin);

        $telefono = trim((string) ($v['hostPhone'] ?? ''));
        $mapa = trim((string) ($site['maps'] ?? ''));
        $marca = Marca::logo($site);
        $nombreSitio = (string) ($site['name'] ?? config('app.name'));
        $deadline = $v['deadline'] ?? null;

        $recibo = isset($v['receipt']) && is_array($v['receipt'])
            ? self::recibo($v['receipt'], $inv, $h, $date, $hora)
            : null;

        $titulo = $recibo === null
            ? __('fiesta.invitacion_pagina.titulo_pagina')
            : ($recibo['si'] ? __('fiesta.recibo.titulo_si', ['n' => $recibo['nino']]) : __('fiesta.recibo.no'));

        return [
            'titulo_pagina' => $titulo.' · '.$nombreSitio,
            'marca' => $marca,
            'idiomas' => self::idiomas(),
            'tema' => ['clave' => $tema, 'tinte' => $t['tint'], 'acento' => $t['accent']],
            'cumple' => $cumple,
            'fecha' => $carbon === null ? '' : Str::ucfirst($carbon->isoFormat(__('fiesta.fecha.larga'))),
            'hora' => $hora === '' ? '' : ($fin === '' ? $hora : __('fiesta.lista.de_a', ['hora' => $hora, 'fin' => $fin])),
            'lugar' => trim($nombreSitio.((($site['city'] ?? '') !== '') ? ', '.$site['city'] : '')),
            'anfitrion' => [
                'linea' => trim((string) $inv->host_line),
                'nombre' => $h,
                'telefono' => $telefono,
                'tel' => $telefono === '' ? '' : 'tel:'.preg_replace('/\s+/', '', $telefono),
                // «Unas palabras de la familia» y «pistas para el regalo» (F1a): la tarjeta las pinta si las hay.
                'palabras' => trim((string) $inv->family_words),
                'pistas' => trim((string) $inv->gift_hints),
            ],
            'enlaces' => [
                'mapa' => $mapa === '#' ? '' : $mapa,
                'calendario' => (string) ($v['calendarUrl'] ?? ''),
                'ics' => self::icsNombre($cumple['nombre']),
            ],
            'texto' => self::parrafos($reservation),
            'parque' => self::parque($site, $nombreSitio, $recibo === null && (bool) ($v['repliesOpen'] ?? false)),
            'merienda' => self::merienda((array) ($v['menu'] ?? [])),
            'merienda_alergias' => __('fiesta.invitacion_pagina.merienda_alergias', ['h' => $h]),
            'respuestas' => [
                'abiertas' => (bool) ($v['repliesOpen'] ?? false),
                'plazo' => $deadline instanceof CarbonInterface
                    ? __('fiesta.invitacion_pagina.plazo', ['dia' => DisplayTime::dayInSentence($deadline), 'hora' => DisplayTime::format($deadline, 'H:i')])
                    : '',
                'accion' => (string) ($v['replyAction'] ?? ''),
                'cerrado' => __('fiesta.invitacion_pagina.cerrado', ['h' => $h]),
                'error' => (string) ($v['error'] ?? ''),
            ],
            'aviso' => self::aviso(is_string($v['status'] ?? null) ? $v['status'] : null, $h),
            'turnstile' => [
                'activo' => Turnstile::enabled(),
                'clave' => Turnstile::enabled() ? Turnstile::siteKey() : '',
                'rotulo' => __('invitation.antibot_label'),
            ],
            'og' => ['sitio' => $nombreSitio] + (array) ($v['preview'] ?? ['title' => '', 'description' => '', 'image' => null, 'width' => null, 'height' => null]),
            'privacidad' => [
                'texto' => __('fiesta.invitacion_pagina.privacidad', ['h' => $h]),
                'politica' => __('fiesta.invitacion_pagina.politica'),
                'enlace' => route('legal.privacidad'),
            ],
            'recibo' => $recibo,
        ];
    }

    /**
     * Quien organiza, por su nombre de pila (`#744`): el primer nombre del titular de la reserva, que es quien reparte
     * el enlace. Sin nombre, «quien organiza la fiesta».
     */
    private static function anfitrion(OrderItem $reservation): string
    {
        $nombre = trim((string) ($reservation->order->user->name ?? ''));
        $pila = trim(Str::before($nombre, ' '));

        return $pila !== '' ? $pila : __('fiesta.invitacion_pagina.quien_organiza');
    }

    /**
     * «Qué es la fiesta»: la descripción pública del pack (`#744`), en el idioma de la página, por párrafos. Vacía, sin
     * bloque.
     *
     * @return list<string>
     */
    private static function parrafos(OrderItem $reservation): array
    {
        $d = $reservation->ticketType?->tr('description');
        $texto = trim(strip_tags(is_string($d) ? $d : ''));

        if ($texto === '') {
            return [];
        }

        return array_values(array_filter(
            array_map('trim', preg_split('/\R{2,}/', $texto) ?: []),
            static fn (string $p): bool => $p !== '',
        ));
    }

    /**
     * LA FIRMA DENTRO DEL RECIBO (F6a; `AuthForm` en el recibo del diseño): el mismo formulario que la página de la
     * autorización (`Autorizacion::formularioDe`, una sola fuente), su desenlace si vuelve con uno, el bloqueo si la
     * reserva ya no admite firmas, y su aviso de privacidad (lo que se recoge al firmar se dice donde se recoge).
     * ⚠️ Pide lo mismo que la página (`#745` y `#236`): el nombre y los apellidos del niño —lo que escribió en la
     *    invitación se ENSEÑA en una nota (`#706`), no se reparte—, su fecha de nacimiento y la relación. El diseño no
     *    los dibuja en el recibo porque el niño ya se conoce; la prueba firmada los necesita por separado.
     *
     * @param  array<string, mixed>|null  $f  `document` y lo que compone `ComposesGuardianForm`
     * @return array<string, mixed>|null
     */
    private static function firma(?array $f, string $h): ?array
    {
        if ($f === null || ! ($f['document'] ?? null) instanceof LegalDocumentVersion) {
            return null;
        }
        $bloqueado = is_string($f['blocked'] ?? null) ? $f['blocked'] : null;
        $status = is_string($f['status'] ?? null) ? $f['status'] : null;

        return [
            'aviso' => Autorizacion::avisoDe($status, trim((string) ($f['minorName'] ?? ''))),
            'bloqueado' => $bloqueado === null ? null : ['motivo' => $bloqueado, 'texto' => __('guardian.blocked.'.$bloqueado)],
            'formulario' => $bloqueado !== null ? null : Autorizacion::formularioDe($f, $f['document'], $h),
            'privacidad' => ['texto' => __('fiesta.autorizacion.privacidad', ['h' => $h]), 'datos' => __('guardian.notice')],
            'turnstile' => [
                'activo' => Turnstile::enabled(),
                'clave' => Turnstile::enabled() ? Turnstile::siteKey() : '',
                'rotulo' => __('guardian.antibot_label'),
            ],
        ];
    }

    /**
     * «VER EL PARQUE» (F1c; `#743` §7·7, encendido con el vídeo de portada): la píldora de la cabecera con la foto, el
     * play y la nota de Google, y el visor a pantalla completa (`content/ClipViewer.jsx`) con UN clip —el vídeo de
     * portada de la instalación—, su nombre, su línea y, con las respuestas abiertas, «Vamos», que lleva a contestar.
     * Sin vídeo en los ajustes (`party.park_video`), sin píldora ni visor. La nota es la de Google COPIADA de la ficha
     * (`#771`, `CmsSocialProof::rating()`): sin ella, la píldora va sin cifra y el botón sin su coletilla.
     *
     * @param  array<string, mixed>  $site
     * @return array{video: string, poster: string, nombre: string, linea: string, ver: string, cerrar: string, rotulo: string, nota: ?array{valor: string, texto: string, aria: string}, accion: ?array{label: string, href: string}}
     */
    private static function parque(array $site, string $nombreSitio, bool $contestar): array
    {
        $video = (string) ($site['park_video'] ?? '');
        $ciudad = trim((string) ($site['city'] ?? ''));
        $atracciones = $video === '' ? 0 : Attraction::query()->where('is_active', true)->count();
        $rating = $video === '' ? null : app(CmsSocialProof::class)->rating();
        $valor = $rating === null ? '' : number_format($rating->value, 1, ',', '');

        return [
            'video' => $video,
            'poster' => (string) ($site['park_video_poster'] ?? ''),
            'nombre' => $nombreSitio,
            'linea' => $atracciones > 0
                ? trim(($ciudad !== '' ? $ciudad.' · ' : '').trans_choice('fiesta.invitacion_pagina.video_linea', $atracciones, ['n' => $atracciones]))
                : $ciudad,
            'ver' => __('fiesta.invitacion_pagina.ver_parque'),
            'cerrar' => __('fiesta.invitacion_pagina.cerrar'),
            'rotulo' => __('fiesta.invitacion_pagina.video_rotulo', ['nombre' => $nombreSitio]),
            'nota' => $rating === null ? null : [
                'valor' => $valor,
                'texto' => trans_choice('fiesta.invitacion_pagina.prueba', $rating->count, ['nota' => $valor, 'n' => $rating->count]),
                'aria' => __('fiesta.invitacion_pagina.nota_aria', ['nota' => $valor]),
            ],
            'accion' => $contestar ? ['label' => __('fiesta.invitacion_pagina.si'), 'href' => '#rsvp-nino'] : null,
        ];
    }

    /**
     * La merienda, por grupos (`inv-merienda` del diseño, F1b): los TRES grupos del diseño con su icono —«para
     * beber», «para comer», «y para terminar»— se llenan con lo que cada complemento marcado y comprado reparte en
     * ellos (`TicketType::invitationMenuGroups()`, dato del panel), sin repetir una cosa que traigan dos platos; un
     * complemento sin reparto sigue siendo su propio grupo, con su nombre y sus ventajas y el icono neutro, detrás.
     *
     * @param  list<array{name: string, features: list<string>, groups?: array{drink: list<string>, food: list<string>, sweet: list<string>}}>  $menu
     * @return list<array{icono: string, rotulo: string, cosas: list<string>}>
     */
    private static function merienda(array $menu): array
    {
        $iconos = ['drink' => 'cup-soda', 'food' => 'sandwich', 'sweet' => 'candy'];
        $grupos = ['drink' => [], 'food' => [], 'sweet' => []];
        $sueltos = [];
        foreach ($menu as $plato) {
            $repartido = false;
            foreach ($grupos as $clave => $cosas) {
                $suyas = $plato['groups'][$clave] ?? [];
                if ($suyas !== []) {
                    $grupos[$clave] = array_merge($cosas, $suyas);
                    $repartido = true;
                }
            }
            if (! $repartido) {
                $sueltos[] = [
                    'icono' => 'utensils',
                    'rotulo' => (string) $plato['name'],
                    'cosas' => array_values(array_filter(array_map('trim', $plato['features']), static fn (string $x): bool => $x !== '')),
                ];
            }
        }

        $out = [];
        foreach ($grupos as $clave => $cosas) {
            if ($cosas !== []) {
                $out[] = ['icono' => $iconos[$clave], 'rotulo' => __('fiesta.invitacion_pagina.merienda_grupos.'.$clave), 'cosas' => array_values(array_unique($cosas))];
            }
        }

        return array_merge($out, $sueltos);
    }

    /**
     * El aviso de la barra: lo que pasó con lo que se acaba de contestar (los rechazos de la spec hermana, todos iguales
     * para todo el mundo: ninguno depende del nombre) o el recibo caducado (`caducado`, §4.6 del diseño).
     *
     * @return array{texto: string, rol: string}|null
     */
    private static function aviso(?string $status, string $h): ?array
    {
        return match ($status) {
            'antibot' => ['texto' => __('invitation.done.antibot'), 'rol' => 'alert'],
            'closed', 'cutoff', 'no_name', 'too_many' => ['texto' => __('invitation.refused.'.$status), 'rol' => 'alert'],
            'expired' => ['texto' => __('fiesta.recibo.despues', ['h' => $h]), 'rol' => 'status'],
            'yes' => ['texto' => __('invitation.done.yes_generic'), 'rol' => 'status'],
            'no' => ['texto' => __('invitation.done.no'), 'rol' => 'status'],
            default => null,
        };
    }

    /**
     * El idioma, abajo y en texto (`#748`): los del sitio (`SiteLocales`), con su nombre en su propio idioma, y el enlace
     * que lo cambia (`lang.switch`, que vuelve a la página desde la sesión: esta página no manda `Referer`). Cuál se lee
     * lo decide `SetLocale`, como en la web: la elección de antes, la cuenta o el navegador.
     *
     * @return array{actual: string, lista: list<array{clave: string, nombre: string, enlace: string}>}
     */
    public static function idiomas(): array
    {
        // Un idioma nuevo en `SiteLocales::SUPPORTED` es una entrada más aquí (su nombre, en su propio idioma).
        $nombres = ['es' => 'Español', 'en' => 'English', 'fr' => 'Français'];
        $actual = app()->getLocale();
        $lista = [];
        foreach (SiteLocales::SUPPORTED as $clave) {
            $lista[] = [
                'clave' => $clave,
                'nombre' => $nombres[$clave],
                'enlace' => route('lang.switch', ['locale' => $clave]),
            ];
        }

        return ['actual' => $actual, 'lista' => $lista];
    }

    /** `cumple-vera.ics`: un nombre de fichero que se reconozca en la carpeta de descargas, sin acentos ni token. */
    private static function icsNombre(string $nombre): string
    {
        $slug = Str::slug($nombre);

        return ($slug === '' ? 'invitacion' : 'cumple-'.$slug).'.ics';
    }

    /**
     * El RECIBO (§4.5·6 de la spec hermana, con el diseño del 24-09): tras «Vamos», la tarjeta con su titular, «Su
     * ficha» (las columnas del pack menos el nombre, todas opcionales), la autorización como oferta (firmada, su
     * Listo con quién firmó; si no, desde F6a, la firma DENTRO, atada a la respuesta), la línea de después; tras «No
     * podemos», solo la tarjeta y la línea.
     *
     * @param  array<string, mixed>  $r
     * @return array<string, mixed>
     */
    private static function recibo(array $r, PartyInvitation $inv, string $h, mixed $date, string $hora): array
    {
        /** @var InvitationReply $reply */
        $reply = $r['reply'];
        $si = (bool) $reply->attending;
        $firmada = (bool) ($r['signed'] ?? false);
        $firma = self::firma(is_array($r['firma'] ?? null) ? $r['firma'] : null, $h);
        $datos = (array) ($r['data'] ?? []);

        $campos = [];
        foreach ((array) ($r['fields'] ?? []) as $campo) {
            $clave = (string) ($campo['key'] ?? '');
            if ($clave === '') {
                continue;
            }
            $edad = $clave === 'age';
            $campos[] = [
                'clave' => $clave,
                'name' => 'guest_data['.$clave.']',
                'id' => 'inv-'.Str::slug($clave),
                'label' => (string) ($r['labels'][$clave] ?? $clave),
                'valor' => (string) old('guest_data.'.$clave, (string) ($datos[$clave] ?? '')),
                'tipo' => ($campo['type'] ?? 'text') === 'number' ? 'number' : 'text',
                'sufijo' => $edad ? __('fiesta.invitacion.unit') : null,
                'inputmode' => $edad ? 'numeric' : null,
                'maxlength' => $edad ? 2 : 2000,
            ];
        }

        return [
            'si' => $si,
            'nino' => trim((string) $reply->child_name),
            'titulo' => $si ? __('fiesta.recibo.si') : __('fiesta.recibo.no'),
            'texto' => $si
                ? ($date === null || $hora === ''
                    ? __('fiesta.recibo.si_texto_sin_fecha')
                    : __('fiesta.recibo.si_texto', ['dia' => DisplayTime::dayInSentence($date), 'hora' => $hora]))
                : __('fiesta.recibo.no_texto', ['h' => $h]),
            'ficha' => [
                'abierta' => (bool) ($r['open'] ?? false),
                'accion' => (string) ($r['action'] ?? ''),
                'campos' => $campos,
                'ayuda' => __('fiesta.recibo.ayuda', ['h' => $h]),
                'estado' => is_string($r['status'] ?? null) ? $r['status'] : null,
            ],
            'otro' => route(PartyInvitations::PUBLIC_ROUTE, ['token' => (string) $inv->token]).'#rsvp-nino',
            // ❗ Sin nada que firmar aquí (fuera del modo interno, sin texto del descargo publicado o sin autorización en
            // el pack) la sección NO se pinta, ni la línea de «sin firma»: hasta F6a el recibo ofrecía «Firmar» y ese
            // enlace llevaba a un 404 en los tres casos (la página de la autorización no existe fuera de ellos).
            'autorizacion' => $firmada || $firma !== null ? [
                'firmada' => $firmada,
                // Recién firmada desde aquí: nombre · teléfono de quien firmó, bajo «Firmada» (el Listo del diseño).
                'firmante' => trim((string) ($r['signer'] ?? '')),
                'firma' => $firmada ? null : $firma,
            ] : null,
            'despues' => __('fiesta.recibo.despues', ['h' => $h]).($si && ! $firmada && $firma !== null ? ' '.__('fiesta.recibo.sin_firma') : ''),
        ];
    }
}
