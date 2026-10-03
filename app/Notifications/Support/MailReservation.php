<?php

namespace App\Notifications\Support;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Services\Balance;
use App\Domain\Booking\Services\CancellationCutoffRule;
use App\Domain\Booking\Services\EmailBookBlock;
use App\Domain\Booking\Services\GuestCountPolicy;
use App\Domain\Booking\Services\Movement;
use App\Domain\Booking\Services\OrderBook;
use App\Domain\Booking\Services\PostFormAddons;
use App\Domain\Booking\Services\Settlement;
use App\Domain\Identity\Models\Dependent;
use App\Domain\Identity\Services\CardToken;
use App\Domain\Identity\Services\CustomerCards;
use App\Domain\Identity\Services\WaiverSettings;
use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\DisplayTime;
use App\Domain\Platform\Services\Money;
use App\Domain\Platform\Services\QrCode;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * **LO QUE LOS CORREOS DE LA RESERVA DICEN DE UNA RESERVA** (la R2b de `specs/correos-rediseno.md` §4.3): el resguardo, el
 * QR, «Antes de venir», los pasos de una fiesta y «Si cambian los planes», compuestos de los MISMOS datos que Mi cuenta —el
 * libro de cada reserva (`OrderBook::forReservation`), su plazo (`CancellationCutoffRule`), lo comprado con ella (el aviso
 * del complemento), el plazo de la lista (`GuestCountPolicy`)—, para que el correo y Mi cuenta no se contradigan. Lo usa la
 * confirmación (el 1, el 1b y el 2, sus tres CARAS) y lo usará la víspera (el 3).
 *
 * ⚠️ Solo COMPONE: el orden de los bloques es de cada correo, y los textos salen de `emails.reserva` (editables, R1·T).
 * ⚠️ El dinero, SOLO del libro (`LedgerSingleSourceTest`): ningún importe se recompone aquí.
 */
final class MailReservation
{
    /** Las tres caras de la confirmación: unas entradas (el 1), un grupo —un pack sin lista de invitados— (el 1b), una fiesta (el 2). */
    public const ENTRADAS = 'entradas';

    public const GRUPO = 'grupo';

    public const FIESTA = 'fiesta';

    /**
     * El icono de lo comprado en «Antes de venir», por el del producto (`ProductIcon`): los calcetines, sus huellas, como el
     * diseño; la hora extra, su reloj. Lo demás, el paquete (el de Mi cuenta).
     */
    private const ICONO_DE_COMPLEMENTO = ['socks' => 'footprints', 'clock-plus' => 'clock'];

    /** @var Collection<int, OrderItem> */
    private readonly Collection $reservas;

    /** @var array<int, OrderBook> el libro de cada reserva, compuesto una vez */
    private array $libros = [];

    public function __construct(public readonly Order $pedido)
    {
        $pedido->loadMissing(['items.ticketType', 'items.slot', 'adjustments', 'payments.refunds', 'user']);

        // Las reservas que el correo enseña: las PRINCIPALES vivas, con su franja, por día y hora. Cada una conoce su pedido
        // sin otra consulta (lo preguntan `acceptsGuestForm()` y el formulario firmado).
        $this->reservas = $pedido->items
            ->whereNull('parent_item_id')
            ->reject(static fn (OrderItem $i): bool => $i->isCancelled() || $i->slot === null)
            ->each(static fn (OrderItem $i) => $i->setRelation('order', $pedido))
            ->sortBy(static fn (OrderItem $i): string => $i->slot->date->toDateString().' '.$i->slot->start_time)
            ->values();
    }

    /** @return Collection<int, OrderItem> */
    public function reservas(): Collection
    {
        return $this->reservas;
    }

    /** La sola reserva del pedido, o `null` si tiene varias (o ninguna). */
    public function unica(): ?OrderItem
    {
        return $this->reservas->count() === 1 ? $this->reservas->first() : null;
    }

    /**
     * La CARA del correo: una FIESTA si alguna reserva lleva lista de invitados (la misma regla que «¡Fiesta reservada!» de la
     * isla, `guest_form_url`); un GRUPO si alguna es un pack sin ella (una excursión, T6c·3); si no, unas ENTRADAS.
     */
    public function cara(): string
    {
        return match (true) {
            $this->reservas->contains(static fn (OrderItem $r): bool => $r->isGuestFormReservation()) => self::FIESTA,
            $this->reservas->contains(static fn (OrderItem $r): bool => $r->ticketType?->isPack() === true) => self::GRUPO,
            default => self::ENTRADAS,
        };
    }

    /** La hora de inicio de una reserva («17:00»). */
    public static function hora(OrderItem $r): string
    {
        return substr((string) $r->slot?->start_time, 0, 5);
    }

    /**
     * EL RESGUARDO de una reserva, como lo pide `BrandedMailMessage::slip()`: la hoja del día, la fecha, la hora, qué y
     * cuántos, el precio y el número; las filas de dinero, del LIBRO de la reserva —con señal, «Señal pagada» y lo del día en
     * negrita (`shows_deposit_note` de la API: cobrado, con señal y saldo en el parque); pagada entera, «24 € pagados»—, y los
     * enlaces claros: «Cómo llegar» (el mapa del panel) y «Añadir al calendario» (el `.ics` firmado, con duración).
     *
     * @return array{dia: array{dow: string, n: string, month: string}, fecha: string, hora: string, que: string, precio: ?string, codigo: string, filas: list<array{0: string, 1: string, 2?: bool}>, enlaces: list<array{0: string, 1: string, 2: string}>}
     */
    public function resguardo(OrderItem $r): array
    {
        [$precio, $filas] = $this->dinero($r);
        $mapa = self::mapa();

        return [
            'dia' => DisplayTime::calendarSheet($r->slot->date),
            'fecha' => Str::ucfirst(DisplayTime::dayAndMonth($r->slot->date)),
            'hora' => self::hora($r),
            'que' => implode(' · ', array_filter([$r->displayProductName(), $r->displayQuantityLabel()])),
            'precio' => $precio,
            'codigo' => (string) __('emails.reserva.number_label', ['code' => (string) $this->pedido->code]),
            'filas' => $filas,
            'enlaces' => array_values(array_filter([
                $mapa !== null ? [(string) __('emails.reserva.directions_label'), $mapa, 'map-pin'] : null,
                $r->visitWindow() !== null
                    ? [(string) __('emails.reserva.calendar_label'), URL::signedRoute('reserva.calendario', ['reserva' => $r]), 'calendar-plus']
                    : null,
            ])),
        ];
    }

    /**
     * El dinero del resguardo: el precio (la línea bajo «qué») y las filas. Un libro que NO CIERRA no afirma ningún importe:
     * solo su frase («en revisión»), como Mi cuenta (`#132`).
     *
     * @return array{0: ?string, 1: list<array{0: string, 1: string, 2?: bool}>}
     */
    private function dinero(OrderItem $r): array
    {
        $libro = $this->libroDe($r);
        $euros = static fn (int $cents): string => Money::showcaseWithSymbol($cents, $libro->currency);

        if (! $libro->isConsistent) {
            return [$libro->note, []];
        }
        if ($this->conSenal($libro)) {
            $fiesta = $r->isGuestFormReservation();

            return [
                // En una fiesta el precio por cabeza no dice nada (la paga quien la reserva): el diseño no lo pone.
                $fiesta ? null : (string) __('emails.reserva.per_person_label', ['amount' => $euros((int) $r->unit_price)]),
                [
                    [(string) __('emails.reserva.deposit_label'), $euros($libro->paidCents)],
                    [(string) __($fiesta ? 'emails.reserva.rest_party_label' : 'emails.reserva.rest_label'), $euros(abs($libro->balance->cents)), true],
                ],
            ];
        }
        if ($libro->balance->kind === Balance::KIND_SETTLED && $libro->paidCents > 0) {
            return [(string) __('emails.reserva.paid_label', ['amount' => $euros($libro->paidCents)]), []];
        }

        return [null, []];
    }

    /** «Señal pagada · el resto en el parque»: las TRES condiciones de `shows_deposit_note` (`OrderItemResource`), compuestas. */
    private function conSenal(OrderBook $libro): bool
    {
        return $this->pedido->paid_at !== null && $libro->hasDeposit && $libro->balance->kind === Balance::KIND_PAY_AT_PARK;
    }

    private function libroDe(OrderItem $r): OrderBook
    {
        return $this->libros[(int) $r->getKey()] ??= OrderBook::forReservation($this->pedido, $r);
    }

    /** El enlace del mapa del panel («Cómo llegar», el de la web), o `null` si no hay uno que se pueda abrir. */
    public static function mapa(): ?string
    {
        $url = trim((string) Setting::value('address.maps_url', ''));

        return preg_match('#^https?://#i', $url) === 1 && filter_var($url, FILTER_VALIDATE_URL) !== false ? $url : null;
    }

    /**
     * EL QR del titular, como lo pide `BrandedMailMessage::qr()` (sin `secundario`, que es de cada correo): la imagen —los
     * MISMOS bytes que el adjunto y que Mi cuenta—, el token en grupos para dictarlo y «Abrir Mi QR». El carné nace aquí si
     * el titular no tiene; si su clave rotó y no se puede pintar (§8.1 de `identidad-qr-puerta.md`), `null`: sin QR.
     * ▶ Sus tres textos, los de la confirmación salvo que el correo dé los SUYOS (la víspera, la R2d): cada correo se edita en
     * su página, y un texto compartido cambiaría otro correo sin que el parque lo viera.
     *
     * @return array{png: string, codigo: string, texto: string, dicta: string, boton: string, url: string}|null
     */
    public function qr(?string $texto = null, ?string $dicta = null, ?string $boton = null): ?array
    {
        $titular = $this->pedido->user;
        $token = $titular !== null ? app(CustomerCards::class)->ensureFor($titular)->plainToken() : null;

        if ($token === null) {
            return null;
        }

        return [
            'png' => QrCode::png($token),
            'codigo' => CardToken::grouped($token),
            'texto' => $texto ?? (string) __('emails.reserva.qr_title'),
            'dicta' => $dicta ?? (string) __('emails.reserva.qr_dictate_label'),
            'boton' => $boton ?? (string) __('emails.reserva.action_qr'),
            'url' => route('account'),
        ];
    }

    /**
     * ¿Le falta al titular AÑADIR a sus menores? (`#875`): si la instalación firma el descargo dentro y aún no tiene ninguno
     * ACTIVO (uno desvinculado no cuenta). Lo que dicen la tarea de la confirmación y el aviso de la víspera.
     */
    public function faltanMenores(): bool
    {
        $titular = $this->pedido->user;

        // La relación ENTERA y filtrada aquí, no `->active()->exists()`: la vista previa la pone en memoria (R1·T2).
        return WaiverSettings::isInternal() && $titular !== null
            && $titular->dependents->every(static fn (Dependent $d): bool => $d->removed_at !== null);
    }

    /**
     * Lo COMPRADO con una reserva, como lo dice Mi cuenta: el aviso de cada complemento (`reservationNote()`) o su nombre y
     * cantidad, con su icono (los calcetines, sus huellas).
     *
     * @return list<array{texto: string, icono: string}>
     */
    public function loComprado(OrderItem $r): array
    {
        return $this->complementos($r)->map(static fn (OrderItem $c): array => [
            'texto' => $c->ticketType?->reservationNote((int) $c->quantity)
                ?? (string) __('isla.mi_cuenta.proxima.complemento', ['nombre' => $c->displayProductName(), 'cantidad' => $c->displayQuantityLabel()]),
            'icono' => self::ICONO_DE_COMPLEMENTO[$c->ticketType?->iconKey() ?? ''] ?? 'package',
        ])->values()->all();
    }

    /**
     * Lo de un PRODUCTO en «Antes de venir» (`beforeVisitLines()`, lo escribe el panel: «Todos los profesores entran gratis»).
     *
     * @return list<array{texto: string, icono: string}>
     */
    public static function delProducto(?TicketType $producto): array
    {
        return array_map(static fn (string $linea): array => ['texto' => $linea, 'icono' => 'info'], $producto?->beforeVisitLines() ?? []);
    }

    /**
     * «ANTES DE VENIR» (no en una fiesta: su trabajo son los pasos), en el orden del diseño:
     *  · QUIÉN FIRMA (`#875`, la regla de «¡Reservado!»: en una compra de ENTRADAS si la instalación firma dentro): «Menores a
     *    tu cargo…» como TAREA si el titular aún no tiene ninguno, y «Otros adultos…» siempre;
     *  · lo COMPRADO con cada reserva: el aviso de su complemento (`reservationNote()`, el de Mi cuenta) o su nombre y cantidad;
     *  · lo de cada PRODUCTO (`beforeVisitLines()`, lo escribe el panel: «Todos los profesores entran gratis»);
     *  · la HORA, con una sola reserva (con varias, cada resguardo dice la suya).
     *
     * @return list<array{texto: string, icono: string, tarea?: bool}>
     */
    public function antesDeVenir(): array
    {
        $cara = $this->cara();
        if ($cara === self::FIESTA) {
            return [];
        }

        $lineas = [];
        if ($cara === self::ENTRADAS && WaiverSettings::isInternal()) {
            if ($this->faltanMenores()) {
                $lineas[] = ['texto' => (string) __('emails.reserva.minors'), 'icono' => 'user-round-plus', 'tarea' => true];
            }
            $lineas[] = ['texto' => (string) __('emails.reserva.adults'), 'icono' => 'users'];
        }

        foreach ($this->reservas as $r) {
            array_push($lineas, ...$this->loComprado($r));
        }

        $productos = $this->reservas->map(static fn (OrderItem $r): ?TicketType => $r->ticketType)->filter()->unique('id');
        foreach ($productos as $producto) {
            array_push($lineas, ...self::delProducto($producto));
        }

        if (($r = $this->unica()) !== null) {
            $lineas[] = [
                'texto' => (string) __($cara === self::GRUPO ? 'emails.reserva.arrival_group' : 'emails.reserva.arrival', ['time' => self::hora($r)]),
                'icono' => 'clock',
            ];
        }

        return $lineas;
    }

    /**
     * Lo comprado con una reserva que sigue en pie: sus complementos vivos, sin los FANTASMA (cancelados sin cobro ni
     * devolución, `isVoidedLeftoverItem()`, como Mi cuenta).
     *
     * @return Collection<int, OrderItem>
     */
    private function complementos(OrderItem $r): Collection
    {
        return $this->pedido->items
            ->where('parent_item_id', $r->getKey())
            ->reject(fn (OrderItem $c): bool => $c->isCancelled() || $this->pedido->isVoidedLeftoverItem($c))
            ->values();
    }

    /**
     * LOS PASOS de una fiesta (el 2): el formulario de invitados, hasta su plazo (el de `GuestCountPolicy`, el mismo que
     * publica la lista) y, si el producto la ofrece, la invitación —en el mismo formulario, su bloque `#gf-invite`—. Con el
     * enlace FIRMADO del formulario: se abre sin sesión, como el del correo que hasta hoy salía aparte. `null` si la reserva
     * no lleva lista.
     *
     * @return array{titulo: string, pasos: list<array{texto: string, boton: string, url: string}>}|null
     */
    public function pasos(OrderItem $r): ?array
    {
        if (! $r->acceptsGuestForm()) {
            return null;
        }
        $url = $r->guestFormSignedUrl();
        $limite = app(GuestCountPolicy::class)->deadlineFor($r);
        $invita = $r->ticketType?->offersGuestInvitation() === true;

        $pasos = [[
            'texto' => $limite !== null && Carbon::now()->lessThan($limite)
                ? (string) __('emails.reserva.step_form', ['day' => DisplayTime::dayInSentence($limite)])
                : (string) __('emails.reserva.step_form_open'),
            'boton' => (string) __('emails.reserva.action_form'),
            'url' => $url,
        ]];
        if ($invita) {
            $pasos[] = ['texto' => (string) __('emails.reserva.step_invite'), 'boton' => (string) __('emails.reserva.action_invite'), 'url' => $url.'#gf-invite'];
        }

        return ['titulo' => (string) __($invita ? 'emails.reserva.steps_title' : 'emails.reserva.steps_one_title'), 'pasos' => $pasos];
    }

    /**
     * «Y si quieres…» de una fiesta: los extras que su formulario ofrece AHORA (`PostFormAddons::offerableFor()`, dentro de su
     * plazo), por su familia («Cubos») o su nombre, una vez cada uno. `null` si no ofrece ninguno.
     */
    public function extras(): ?string
    {
        $nombres = $this->reservas
            ->filter(static fn (OrderItem $r): bool => $r->isGuestFormReservation())
            ->flatMap(static fn (OrderItem $r): Collection => app(PostFormAddons::class)->offerableFor($r)->values())
            ->map(static fn (TicketType $a): string => trim((string) $a->tr('family')) !== '' ? trim((string) $a->tr('family')) : trim((string) $a->tr('name')))
            ->filter()
            ->unique()
            ->values();

        return $nombres->isEmpty() ? null : (string) __('emails.reserva.extras', ['extras' => $nombres->implode(', ')]);
    }

    /**
     * «SI CAMBIAN LOS PLANES»: lo que se puede hacer, con el teléfono del parque y sus enlaces (`whatsapp`, `tel`). Con una
     * reserva, su plazo (`CancellationCutoffRule`, el de Mi cuenta) y si devuelve la señal (`deposit_refundable_in_time`, solo
     * si nació con ella); pasado el plazo, que ya no se puede; sin plazo publicado, o con varias reservas, la frase sin fecha.
     * Lo INFORMA: los cambios los hace el personal. Sin teléfono en el panel, sin sección.
     */
    public function cambios(): ?string
    {
        $telefono = MailPie::current()->telefono;
        if ($telefono === null) {
            return null;
        }
        $r = $this->unica();
        $limite = $r !== null ? app(CancellationCutoffRule::class)->deadlineFor($r) : null;

        if ($r === null || $limite === null) {
            return (string) __('emails.reserva.changes_open', ['phone' => $telefono]);
        }
        if (! Carbon::now()->lessThan($limite)) {
            return (string) __('emails.reserva.changes_late', ['phone' => $telefono]);
        }
        $devuelve = $r->ticketType?->deposit_refundable_in_time === true && $this->libroDe($r)->hasDeposit;

        return (string) __($devuelve ? 'emails.reserva.changes_refund' : 'emails.reserva.changes', [
            'day' => DisplayTime::dayInSentence($limite),
            'time' => $limite->format('H:i'),
            'phone' => $telefono,
        ]);
    }

    /**
     * Los ENLACES por nombre que nombran los textos de la reserva: WhatsApp —con el mensaje de cambio ya escrito, el de Mi
     * cuenta, si es una reserva—, el teléfono y la pantalla de los menores a cargo. Uno que el parque no tiene, no se ofrece
     * (su texto sale sin enlace).
     *
     * @return array<string, ?string>
     */
    public function enlaces(): array
    {
        $pie = MailPie::current();
        $r = $this->unica();
        $mensaje = $r !== null ? (string) __('isla.mi_cuenta.cambiar.mensaje', [
            'code' => (string) $this->pedido->code,
            'dia' => DisplayTime::dayInSentence($r->slot->date),
            'hora' => self::hora($r),
        ]) : null;

        return [
            'whatsapp' => $pie->whatsapp !== null ? 'https://wa.me/'.$pie->whatsapp.($mensaje !== null ? '?text='.rawurlencode($mensaje) : '') : null,
            'tel' => $pie->tel !== null ? 'tel:'.$pie->tel : null,
            'menores' => route('account.dependents'),
        ];
    }

    /**
     * EL LIBRO entero del pedido («Tu pedido, a día de hoy», `EmailBookBlock`), solo si al pedido le ha PASADO algo después
     * de nacer —un cambio, una cortesía, una cancelación, una devolución, lo liquidado en el parque—: el correo se REENVÍA
     * desde el panel, y entonces el resguardo solo no cuenta la historia. Recién pagado, el resguardo ya lo dice todo (el
     * diseño no lo lleva).
     */
    public function libro(): ?Htmlable
    {
        $libro = OrderBook::forOrder($this->pedido);
        $historia = collect($libro->movements)->contains(static fn (Movement $m): bool => $m->kind !== Movement::KIND_BOOKING)
            || collect($libro->settlements)->contains(static fn (Settlement $s): bool => $s->kind !== Settlement::KIND_PAYMENT);

        return $historia ? EmailBookBlock::forOrder($this->pedido) : null;
    }
}
