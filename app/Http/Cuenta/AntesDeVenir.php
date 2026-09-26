<?php

namespace App\Http\Cuenta;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\PartyInvitation;
use App\Domain\Booking\Models\ProductAddon;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Services\CustomerReservationsReader;
use App\Domain\Booking\Services\GuestCountPolicy;
use App\Domain\Booking\Services\PartyInvitations;
use App\Domain\Booking\Services\PostFormAddons;
use App\Domain\Identity\Models\Dependent;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\DependentRegistry;
use App\Domain\Identity\Services\GuardianRoster;
use App\Domain\Identity\Services\LegalDocuments;
use App\Domain\Identity\Services\WaiverSettings;
use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\DisplayTime;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * **«ANTES DE VENIR»: lo que le queda por hacer a una reserva, con su plazo real** (T5c de
 * `specs/isla-y-landing-nueva.md` §4.13, `DECISIONES #776`). Lo pintan Mi cuenta (el bloque, su chip «Siguiente») y la
 * isla de las páginas (su punto, su nota y la situación «tarea»); los TEXTOS se componen aquí, una vez, para las dos.
 *
 * Vive en la capa HTTP y no en un módulo del dominio por la frontera (`ModuleBoundariesTest`): las autorizaciones son de
 * Identity y la reserva de Booking, y Booking no puede mirar a Identity. Es el mismo sitio que `OrderGuestMinorsController`
 * y `Fiesta\ListaDeInvitados`. **Aquí no se decide ninguna regla**: cada hecho lo da su servicio.
 *
 *   · **Formulario de invitados** (tarea): hecho cuando el post-form está COMPLETO (`guestFormStatus`, el mismo «8 de 8»
 *     de su pantalla); su plazo, el ÚNICO de la fiesta —la lista y el número, `GuestCountPolicy`, `#766`—.
 *   · **Invitación** (tarea, si el producto la ofrece y las respuestas siguen abiertas): hecha cuando los «sí» llegan al
 *     número de invitados (`#776`, el owner: «como el mockup») —sin quien cumple, si la reserva lleva su ficha (`#747`)—.
 *     Con la invitación lista, «Compartir por WhatsApp» con el
 *     MISMO mensaje que la lista de invitados (su prueba de paridad lo vigila); sin ella, «Crear la invitación».
 *   · **Extras** (opcional): los de venta posterior que siguen dentro de su plazo, cada uno con el suyo
 *     (`PostFormAddons`), y se pagan el día de la fiesta.
 *   · **Autorizaciones** (estado, si el producto las pide): las firmadas; con invitación, sobre los que han dicho que sí.
 *     Nunca un denominador inventado (`waiver-por-reserva.md` §4.10): sin respuestas, solo las firmadas.
 *
 *   · **Añade a tus hijos** (tarea de una ENTRADA, T5d, `#777`): si la instalación firma el descargo dentro; hecha con
 *     algún menor declarado.
 *
 * ⚠️ Una reserva sin pagar, cancelada o ya celebrada no tiene nada pendiente (la guarda de `PendingBeforeVisit`).
 */
final class AntesDeVenir
{
    public const TAREA = 'task';

    public const OPCIONAL = 'optional';

    public const ESTADO = 'status';

    public const FORMULARIO = 'guest_form';

    public const INVITACION = 'invitation';

    public const EXTRAS = 'extras';

    public const AUTORIZACIONES = 'authorizations';

    public const HIJOS = 'dependents';

    private const T = 'isla.mi_cuenta.antes.';

    /**
     * Hasta cuántos extras se NOMBRAN, como el mockup («tarta, combos para padres y cubos»). Con más, la frase dice cuántos
     * y cuándo cierra el primero: con los ocho del catálogo de prueba ocupaba cuatro líneas, lo contrario de «sin saturar».
     */
    private const EXTRAS_NOMBRADOS = 3;

    public function __construct(
        private GuestCountPolicy $plazos,
        private PartyInvitations $invitaciones,
        private PostFormAddons $extras,
        private GuardianRoster $firmas,
        private CustomerReservationsReader $reservas,
        private DependentRegistry $menores,
    ) {}

    /**
     * Las tareas de ESTA reserva, en el orden en que se hacen, con la forma del contrato (`BeforeVisitTask`).
     *
     * @return list<array{kind: string, type: string, done: bool, title: ?string, note: ?string, text: string, due: ?string, action: ?array{label: string, url: string, via: string}}>
     */
    public function tareasDe(OrderItem $reserva): array
    {
        return array_map(static function (array $t): array {
            unset($t['isla']);

            return $t;
        }, $this->componer($reserva));
    }

    /**
     * Lo que la isla de una página necesita saber de la cuenta, sin pedir nada: si la PRÓXIMA es hoy («Hoy a las
     * 17:00», con «Ver mi QR») y su primera tarea pendiente (el punto del menú, «Siguiente: …» y la situación «tarea»).
     * `null` sin sesión.
     *
     * @return array{pending: bool, pendingText: ?string, task: ?array{text: string, product: ?string, action: array{label: string, href: string, zone?: string}}, bookingToday: ?array{text: string}}|null
     */
    public function paraLaIsla(?User $titular): ?array
    {
        if ($titular === null) {
            return null;
        }

        $reserva = $this->reservas->nextItemFor((int) $titular->getKey());
        $primera = null;
        foreach ($reserva === null ? [] : $this->componer($reserva) as $t) {
            if ($t['type'] === self::TAREA && ! $t['done']) {
                $primera = $t;
                break;
            }
        }

        $hoy = $reserva !== null && $reserva->slot?->date?->toDateString() === DisplayTime::today()->toDateString();

        return [
            'pending' => $primera !== null,
            'pendingText' => $primera === null ? null : __(self::T.'siguiente_chip', ['n' => $primera['title']]),
            'task' => $primera === null ? null : $primera['isla'],
            'bookingToday' => $hoy ? ['text' => __(self::T.'hoy', ['hora' => substr((string) $reserva->slot->start_time, 0, 5)])] : null,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function componer(OrderItem $reserva): array
    {
        $tipo = $reserva->ticketType;
        $viva = $tipo !== null && $reserva->parent_item_id === null && ! $reserva->isCancelled()
            && $reserva->order?->status === Order::STATUS_PAID && ! $reserva->isFinishedInPractice();
        if (! $viva) {
            return [];
        }
        if ($reserva->acceptsGuestForm()) {
            return $this->deLaFiesta($reserva, $tipo);
        }

        // Una ENTRADA: «Añade a tus hijos» (T5d). Un pack sin formulario no tiene nada que pedir.
        $hijos = $tipo->isPack() ? null : $this->hijos($reserva, $tipo);

        return $hijos === null ? [] : [$hijos];
    }

    /**
     * **«Añade a tus hijos»** (T5d, `#777`): en una entrada, si la instalación firma el descargo DENTRO y tiene texto
     * publicado —la condición del alta de un menor (`#441`) y la de «Listo» de la compra, que la ofrece igual—. Hecha
     * cuando la cuenta tiene algún menor declarado (el mockup: «la de los hijos se da por hecha en cuanto hay hijos»).
     * Su acción abre la pantalla de alta: en Mi cuenta, en el sitio (`via: account`); fuera, su puerta.
     *
     * @return array<string, mixed>|null
     */
    private function hijos(OrderItem $reserva, TicketType $tipo): ?array
    {
        $titular = $reserva->order?->user;
        if ($titular === null || ! WaiverSettings::isInternal() || LegalDocuments::latestVersionNumber(WaiverSettings::SLUG) === null) {
            return null;
        }

        $hoy = DisplayTime::today();
        $t = self::T.'hijos.';
        $puerta = route('account.dependents');

        return [
            'kind' => self::HIJOS, 'type' => self::TAREA,
            'done' => $this->menores->activeFor($titular)->contains(fn (Dependent $d): bool => $d->isMinorOn($hoy)),
            'title' => __($t.'titulo'), 'note' => __($t.'nota'), 'text' => __($t.'texto'),
            'due' => __(self::T.'para_el', ['dia' => DisplayTime::dayInSentence($reserva->slot->date)]),
            'action' => ['label' => __($t.'boton'), 'url' => $puerta, 'via' => 'account'],
            // En la isla de una página: se abre la cuenta en su ZONA (el enlace del motor), en la página de lo reservado.
            'isla' => ['text' => __($t.'linea'), 'product' => $tipo->zone?->slug, 'action' => ['label' => __($t.'boton_isla'), 'href' => $puerta, 'zone' => 'dependents']],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function deLaFiesta(OrderItem $reserva, TicketType $tipo): array
    {
        $plazo = $this->plazos->deadlineFor($reserva);
        // «Hasta el jueves 24» solo mientras sea verdad: pasado el plazo, la tarea sigue pero sin prometer una fecha.
        $dia = $plazo !== null && $this->plazos->isWithinWindow($reserva) ? DisplayTime::dayInSentence($plazo) : null;
        $invitacion = $tipo->offersGuestInvitation();
        $si = $invitacion ? (int) $this->invitaciones->summaryFor($reserva)['yes'] : 0;
        $lista = route('reservation.guests', ['reservation' => $reserva]);

        $tareas = [$this->formulario($reserva, $dia, $lista)];
        if ($invitacion && $this->invitaciones->repliesOpenFor($reserva)) {
            $tareas[] = $this->invitacion($reserva, $dia, $si, $lista);
        }
        $extras = $this->extrasDe($reserva, $lista);
        if ($extras !== null) {
            $tareas[] = $extras;
        }
        if ($tipo->guardianMode() !== TicketType::GUARDIAN_NONE) {
            $tareas[] = $this->autorizaciones($reserva, $invitacion ? $si : 0, $lista);
        }

        return $tareas;
    }

    /** @return array<string, mixed> */
    private function formulario(OrderItem $reserva, ?string $dia, string $lista): array
    {
        $hecha = $reserva->guestFormStatus() === 'ok';
        $t = self::T.'formulario.';

        return [
            'kind' => self::FORMULARIO, 'type' => self::TAREA, 'done' => $hecha,
            'title' => __($t.'titulo'),
            'note' => $dia === null ? null : __($t.'nota', ['dia' => $dia]),
            'text' => $dia === null ? __($t.'texto_sin_plazo') : __($t.'texto', ['dia' => $dia]),
            'due' => null,
            'action' => ['label' => __($t.($hecha ? 'boton_hecho' : 'boton')), 'url' => $lista, 'via' => 'link'],
            'isla' => [
                'text' => $dia === null ? __($t.'linea_sin_plazo') : __($t.'linea', ['dia' => $dia]),
                'product' => null,
                'action' => ['label' => __($t.'boton_isla'), 'href' => $lista],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function invitacion(OrderItem $reserva, ?string $dia, int $si, string $lista): array
    {
        // Los que tienen que contestar: los INVITADOS. Con la ficha de quien cumple (`#747`, el sello de la reserva), una
        // reserva de 10 es quien cumple y 9 invitados, y quien cumple no contesta a su propia invitación.
        $total = (int) $reserva->quantity - ($reserva->hasHonoreeRow() ? 1 : 0);
        $t = self::T.'invitacion.';
        $cifras = ['si' => $si, 'total' => $total];
        // El ancla de la invitación en la lista: la misma que publica `OrderItem.invitation_url` y usa su correo.
        $enLaLista = $lista.'#gf-invite';

        $inv = $this->invitaciones->existingFor($reserva);
        $enlace = $inv !== null && $this->invitaciones->isShareable($reserva, $inv) ? $this->invitaciones->shareUrlFor($inv) : null;
        $accion = $inv !== null && $enlace !== null
            ? ['label' => __($t.'boton'), 'url' => 'https://wa.me/?text='.rawurlencode($this->mensaje($reserva, $inv, $enlace)), 'via' => 'whatsapp']
            : ['label' => __($t.'crear'), 'url' => $enLaLista, 'via' => 'link'];

        return [
            'kind' => self::INVITACION, 'type' => self::TAREA, 'done' => $si >= $total,
            'title' => __($t.'titulo'),
            'note' => __($t.'nota', $cifras),
            'text' => __($t.'texto', $cifras),
            'due' => $dia === null ? null : __(self::T.'para_el', ['dia' => $dia]),
            'action' => $accion,
            // En la isla de una página, sin el enlace de la invitación en el HTML: a su sitio en la lista, como el correo.
            'isla' => ['text' => __($t.'linea', $cifras), 'product' => null, 'action' => ['label' => __($t.'boton_isla'), 'href' => $enLaLista]],
        ];
    }

    /**
     * «Tarta, hasta el jueves 24; Combo y Cubo, hasta el mismo día»: los extras que siguen en plazo, agrupados por el
     * día en que cierran (el de la fiesta se dice «el mismo día»). Con más de {@see EXTRAS_NOMBRADOS}, cuántos y cuándo
     * cierra el primero. Sin ninguno en plazo, no hay línea.
     *
     * @return array<string, mixed>|null
     */
    private function extrasDe(OrderItem $reserva, string $lista): ?array
    {
        $ofrecidos = $this->extras->offerableFor($reserva);
        if ($ofrecidos->isEmpty()) {
            return null;
        }

        $t = self::T.'extras.';
        $fiesta = $reserva->slot?->date?->toDateString();
        $grupos = [];
        foreach ($ofrecidos as $extra) {
            /** @var ProductAddon $enganche el de ESTE pack: su plazo en horas (`postform_cutoff_hours`) */
            $enganche = $extra->getRelation('pivot');
            $cierra = PostFormAddons::deadlineFor($reserva, $enganche);
            $clave = $cierra === null || $cierra->toDateString() === $fiesta ? 'mismo' : $cierra->toDateString();
            $grupos[$clave] ??= ['cierra' => $cierra, 'nombres' => []];
            $grupos[$clave]['nombres'][] = (string) $extra->tr('name');
        }
        // Primero lo que cierra antes; «el mismo día», al final.
        uksort($grupos, static fn (string $a, string $b): int => $a === 'mismo' ? 1 : ($b === 'mismo' ? -1 : strcmp($a, $b)));

        if ($ofrecidos->count() > self::EXTRAS_NOMBRADOS) {
            // «El mismo día» va siempre al final: si es el primero, es el único plazo.
            $primero = (string) array_key_first($grupos);
            $clave = match (true) {
                $primero === 'mismo' => 'muchos_mismo_dia',
                count($grupos) === 1 => 'muchos',
                default => 'muchos_plazos',
            };
            $dia = $primero === 'mismo' ? '' : DisplayTime::dayInSentence($grupos[$primero]['cierra']);
            $texto = __($t.$clave, ['n' => $ofrecidos->count(), 'dia' => $dia]);
        } else {
            $partes = array_map(fn (string $clave, array $g): string => $clave === 'mismo'
                ? __($t.'mismo_dia', ['nombres' => $this->enumerar($g['nombres'])])
                : __($t.'hasta', ['nombres' => $this->enumerar($g['nombres']), 'dia' => DisplayTime::dayInSentence($g['cierra'])]),
                array_keys($grupos), $grupos);
            $texto = __($t.'texto', ['lista' => implode('; ', $partes)]);
        }

        return [
            'kind' => self::EXTRAS, 'type' => self::OPCIONAL, 'done' => false,
            'title' => __($t.'titulo'), 'note' => null,
            'text' => $texto,
            'due' => null,
            'action' => ['label' => __($t.'boton'), 'url' => $lista, 'via' => 'link'],
            'isla' => null,
        ];
    }

    /** @return array<string, mixed> */
    private function autorizaciones(OrderItem $reserva, int $si, string $lista): array
    {
        $firmadas = $this->firmas->countFor((int) $reserva->getKey());
        $t = self::T.'autorizaciones.';
        $texto = match (true) {
            $firmadas === 0 => __($t.'texto_ninguna'),
            $si >= $firmadas => __($t.'texto', ['firmadas' => $firmadas, 'total' => $si]),
            default => trans_choice($t.'texto_sin_total', $firmadas, ['firmadas' => $firmadas]),
        };

        return [
            'kind' => self::AUTORIZACIONES, 'type' => self::ESTADO, 'done' => false,
            'title' => null, 'note' => null, 'text' => $texto, 'due' => null,
            'action' => ['label' => __($t.'boton'), 'url' => $lista, 'via' => 'link'],
            'isla' => null,
        ];
    }

    /**
     * El mensaje de la invitación, **el mismo que compone la lista de invitados** (`Fiesta\ListaDeInvitados::invitacion`):
     * quién cumple y su edad, cuándo y dónde, y el enlace. `AntesDeVenirTest` lo compara con el de la página.
     */
    private function mensaje(OrderItem $reserva, PartyInvitation $inv, string $enlace): string
    {
        $edad = $inv->honoree_age !== null ? (string) $inv->honoree_age : '';
        $titular = trim((string) $inv->honoree_name).($edad !== ''
            ? __('fiesta.invitacion.rest', ['age' => $edad])
            : __('fiesta.invitacion.rest_sin_edad'));
        $fecha = $reserva->slot?->date;
        [$hora, $fin] = array_pad(explode('–', (string) $reserva->displayTimeWindow(), 2), 2, '');
        $ciudad = (string) Setting::value('business.city', '');
        $cuando = __('fiesta.lista.cuando', [
            'dia' => $fecha === null ? '' : Str::ucfirst(CarbonImmutable::instance($fecha)->locale(app()->getLocale())->isoFormat(__('fiesta.fecha.larga'))),
            'hora' => trim($hora),
            'fin' => trim($fin),
            'lugar' => trim((string) Setting::value('business.name', config('app.name')).($ciudad !== '' ? ', '.$ciudad : '')),
        ]);

        return __('fiesta.lista.invitacion.mensaje', ['titular' => $titular, 'cuando' => $cuando, 'enlace' => $enlace]);
    }

    /** «A, B y C», con la conjunción del idioma. */
    private function enumerar(array $nombres): string
    {
        $ultimo = array_pop($nombres);

        return $nombres === [] ? (string) $ultimo : implode(', ', $nombres).__(self::T.'extras.y').$ultimo;
    }
}
