<?php

namespace App\Http\Fiesta;

use App\Domain\Booking\Contracts\PostFormAddonView;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\PartyInvitation;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Services\GuestAgeMixReader;
use App\Domain\Booking\Services\PartyInvitations;
use App\Domain\Identity\Services\GuardianPlaces;
use App\Domain\Platform\Services\DisplayTime;
use App\Domain\Platform\Services\Money;
use App\Domain\Platform\Services\PersonNameKey;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * **EL MODELO DE PÁGINA de la lista de invitados** (`specs/fiesta-sistema-nuevo.md` §4.1, `#743`).
 *
 * Compone, desde lo que ya calcula `GuestFormController::show()` (sus claves de siempre), UN arreglo con la forma de
 * `paginas/lista-invitados/datos.js` del diseño: `reserva`, `cumple`, `invitacion`, `ninos[]`, `numero`, `extras`,
 * `guardar`, `plazos`… Las zonas Blade (`resources/views/fiesta/lista/*`) leen SOLO este arreglo, y el banco
 * (`scripts/banco-fiesta.php`) pinta las mismas zonas con el mismo arreglo mapeado desde los datos del diseño: por eso
 * la página y el banco no pueden divergir sin que el juez lo vea.
 *
 * ⚠️ **La lógica no cambia** (spec §0): el formulario sigue siendo POSICIONAL (`guests[i][columna]`, `adopt[]`,
 * `guest_count`, `expected_version`, `addons[i][…]`, `general[…]`) y lo que aquí se calcula es lo que ya calculaba la
 * vista de 1.279 líneas en Blade (`ficheStates`, el orden de la página, `declinedBySlot`…), bajado a PHP con su
 * test. Lo que el diseño añade y el producto no tiene (quien cumple como fila, la lista sin filas vacías, el número
 * que sube desde la lista…) NO está aquí: es lo que FALTA (spec §1.4, con la decisión del owner, `#743`).
 *
 * ⚠️ Las tres columnas de la ficha —nombre, edad, alergias— se resuelven por TIPO y por orden, como
 * `guestNameFieldKey()`: el nombre es la primera `text`; la edad, la primera `age`; las alergias, la siguiente `text`.
 * Las demás columnas del pack viajan ESCONDIDAS con su valor, para que un guardado no las borre (`submitGuestForm()`
 * sustituye la lista entera): con los packs a tres columnas (`#743`·6) dejan de existir.
 */
final class ListaDeInvitados
{
    /**
     * @param  array<string, mixed>  $v  lo que compone `GuestFormController::show()`
     * @param  array<string, mixed>  $site  el `$site` compartido (nombre, teléfono, dirección)
     * @return array<string, mixed>
     */
    public static function componer(array $v, array $site, ?string $status, ?string $reminderText): array
    {
        /** @var OrderItem $reservation */
        $reservation = $v['reservation'];
        /** @var TicketType $type */
        $type = $v['type'];
        $readonly = (bool) $v['readonly'];
        $rows = $v['rows'];
        $proposals = $v['proposals'];
        $guestFields = $v['guestFields'];
        $invitacion = $v['invitation'];
        $inv = $invitacion['invitation'] ?? null;

        $columnas = self::columnas($type, $guestFields);
        $firmas = $inv !== null ? app(GuardianPlaces::class)->signedMinorsIn((int) $reservation->getKey()) : [];
        $adoptadas = $inv !== null ? app(PartyInvitations::class)->adoptedYesKeysIn($reservation) : [];
        $declinadas = collect($invitacion['declined'] ?? [])->filter(fn (array $r): bool => $r['slot_index'] !== null)->keyBy('slot_index');

        $ninos = self::ninos($reservation, $type, $rows, $proposals, $columnas, $firmas, $adoptadas, $declinadas, $v);
        $cuentas = self::cuentas($ninos, $invitacion['summary'] ?? null);
        $reserva = self::reserva($reservation, $type, $site, $invitacion);
        $cumple = [
            'nombre' => $inv !== null ? trim((string) $inv->honoree_name) : '',
            'edad' => $inv !== null && $inv->honoree_age !== null ? (string) $inv->honoree_age : '',
        ];

        return [
            'accion' => $v['formAction'],
            'testigo' => $v['version'],
            'solo_lectura' => $readonly,
            'marca' => (string) ($site['name'] ?? config('app.name')),
            'logo' => self::logo($site),
            'reserva' => $reserva,
            'cumple' => $cumple,
            // La PRIMERA pantalla (spec §1.4, Z1): sin el nombre de quien cumple no hay invitación ni titular.
            'primero' => $inv !== null && ! $readonly && $cumple['nombre'] === '',
            'invitacion' => $inv === null ? null : self::invitacion($invitacion, $inv, $reservation, $cumple, $reserva, $status, $reminderText),
            'ninos' => $ninos,
            'columnas' => $columnas,
            'cuentas' => $cuentas,
            'numero' => self::numero($reservation, $v['guestCount'], $cuentas, $readonly),
            'extras' => self::extras($v['addons'], $v['extrasTotal'], $reservation, $readonly, $reserva),
            'generales' => self::generales($type, $v['generalFields'], $reservation, $readonly),
            'avisos' => self::avisos($v, $status, $type),
            'progreso' => $v['progress'],
            'guardar' => [
                'estado' => $status === 'guest-form-saved' ? 'saved' : 'clean',
            ],
            'plazos' => [
                'respuestas' => (bool) ($invitacion['replies_open'] ?? false),
                'numero' => (bool) $v['guestCount']['editable'] && ! $readonly,
                'extras' => collect($v['addons'])->contains(fn (PostFormAddonView $a): bool => ! $a->closed) && ! $readonly,
            ],
            'privacidad' => route('legal.privacidad'),
        ];
    }

    /**
     * Las tres columnas de la ficha por TIPO y orden, y las demás (que viajan escondidas).
     *
     * @param  list<array<string, mixed>>  $guestFields
     * @return array{name: ?string, age: ?string, allergies: ?string, extra: list<array<string, mixed>>, labels: array<string, string>}
     */
    private static function columnas(TicketType $type, array $guestFields): array
    {
        $name = $type->guestNameFieldKey();
        $age = $type->guestAgeFieldKey();
        $allergies = null;
        foreach ($guestFields as $field) {
            if ($field['type'] === TicketType::FIELD_TYPE_TEXT && $field['key'] !== $name) {
                $allergies = (string) $field['key'];
                break;
            }
        }
        $extra = array_values(array_filter($guestFields, fn (array $f): bool => ! in_array($f['key'], [$name, $age, $allergies], true)));
        $labels = [];
        foreach ($guestFields as $field) {
            $labels[(string) $field['key']] = $type->guestFieldLabel($field);
        }

        return ['name' => $name, 'age' => $age, 'allergies' => $allergies, 'extra' => $extra, 'labels' => $labels];
    }

    /**
     * Una fila por POSICIÓN (0…quantity−1), con lo que la vista calculaba en Blade: estado, «Falta …», régimen, la
     * propuesta pendiente (chapa «por la invitación», `adopt[]`, «respuesta repetida»), el «no» que empareja, la
     * firma por nombre y el orden de la página.
     *
     * @param  list<array<string, string>>  $rows
     * @param  array<int, array{id: int, repeated: bool}>  $proposals
     * @param  array{name: ?string, age: ?string, allergies: ?string, extra: list<array<string, mixed>>, labels: array<string, string>}  $columnas
     * @param  list<array{key: string, reply_id: int|null}>  $firmas
     * @param  list<string>  $adoptadas
     * @param  Collection<int, array{id: int, child_name: string, slot_index: int|null}>  $declinadas
     * @param  array<string, mixed>  $v
     * @return list<array<string, mixed>>
     */
    private static function ninos(OrderItem $reservation, TicketType $type, array $rows, array $proposals, array $columnas, array $firmas, array $adoptadas, $declinadas, array $v): array
    {
        $out = [];
        $quantity = max(0, (int) $reservation->quantity);
        $guestFields = $v['guestFields'];
        $regimes = $v['guestRegimes'];
        $noProduct = $v['noProductIndexes'];
        $readonly = (bool) $v['readonly'];

        for ($i = 0; $i < $quantity; $i++) {
            $row = $rows[$i] ?? [];
            $mark = $proposals[$i] ?? null;
            $nombre = $columnas['name'] === null ? '' : trim((string) ($row[$columnas['name']] ?? ''));
            $edad = $columnas['age'] === null ? '' : trim((string) ($row[$columnas['age']] ?? ''));
            $alergias = $columnas['allergies'] === null ? '' : trim((string) ($row[$columnas['allergies']] ?? ''));
            $key = $nombre === '' ? '' : PersonNameKey::for($nombre);
            $firmada = $key !== '' && collect($firmas)->contains(fn (array $f): bool => PersonNameKey::cardMatches($key, $f['key']));
            $adoptada = $key !== '' && in_array($key, $adoptadas, true);
            $declinada = $declinadas->has($i);
            $sinProducto = in_array($i, $noProduct, true);
            $primeraVacia = collect($guestFields)->first(fn (array $f): bool => (bool) $f['required'] && ! filled($row[$f['key']] ?? null));
            $conDatos = collect($guestFields)->contains(fn (array $f): bool => filled($row[$f['key']] ?? null));
            $regime = $regimes[$i] ?? null;

            $extra = [];
            foreach ($columnas['extra'] as $field) {
                $extra[(string) $field['key']] = (string) ($row[$field['key']] ?? '');
            }

            $out[] = [
                'id' => 'g'.$i,
                'indice' => $i,
                'nombre' => $nombre,
                'edad' => $edad,
                'alergias' => $alergias,
                'vacia' => ! $conDatos && $mark === null,
                'origen' => ($mark !== null || $adoptada) ? 'invitacion' : 'mano',
                'respuesta' => ($mark !== null || $adoptada) ? 'si' : ($declinada ? 'no' : null),
                'pendiente' => $mark !== null,
                'reply_id' => $mark['id'] ?? null,
                'repetida' => (bool) ($mark['repeated'] ?? false),
                'firmada' => $firmada,
                'completa' => ! $sinProducto && $primeraVacia === null,
                'falta' => $conDatos && $primeraVacia !== null ? $type->guestFieldLabel($primeraVacia) : null,
                'sin_producto' => $sinProducto,
                'regimen' => $regime !== null && $regime['state'] === GuestAgeMixReader::ROW_OK && ! $regime['own'] ? (string) $regime['name'] : null,
                'campos' => [
                    'name' => $columnas['name'] === null ? null : 'guests['.$i.']['.$columnas['name'].']',
                    'age' => $columnas['age'] === null ? null : 'guests['.$i.']['.$columnas['age'].']',
                    'allergies' => $columnas['allergies'] === null ? null : 'guests['.$i.']['.$columnas['allergies'].']',
                ],
                'extra' => $extra,
                'editable' => ! $readonly,
            ];
        }

        return $out;
    }

    /**
     * Las cifras de la zona 1 y de la 3: «confirmados» son los «sí» (pendientes o adoptados) SIN contar dos veces
     * a un niño; «sin contestar», las fichas con nombre que no llegaron por la invitación; «no pueden», los «no».
     *
     * @param  list<array<string, mixed>>  $ninos
     * @param  array{yes: int, no: int, pending: int}|null  $summary
     * @return array{confirmados: int, no_pueden: int, sin_contestar: int, en_lista: int}
     */
    private static function cuentas(array $ninos, ?array $summary): array
    {
        $confirmados = 0;
        $sin = 0;
        $enLista = 0;
        foreach ($ninos as $n) {
            if ($n['vacia']) {
                continue;
            }
            $enLista++;
            if ($n['respuesta'] === 'si') {
                $confirmados++;
            } elseif ($n['respuesta'] === null) {
                $sin++;
            }
        }

        return [
            'confirmados' => $confirmados,
            'no_pueden' => (int) ($summary['no'] ?? 0),
            'sin_contestar' => $sin,
            'en_lista' => $enLista,
        ];
    }

    /**
     * El resguardo: día, hora de inicio y de fin (la duración EFECTIVA, `displayTimeWindow()`), el pack, el código.
     *
     * @param  array<string, mixed>  $site
     * @param  array<string, mixed>|null  $invitacion
     * @return array<string, string|int>
     */
    private static function reserva(OrderItem $reservation, TicketType $type, array $site, ?array $invitacion): array
    {
        $slot = $reservation->slot;
        $date = $slot?->date;
        $ventana = (string) $reservation->displayTimeWindow();
        [$hora, $fin] = array_pad(explode('–', $ventana, 2), 2, '');
        $carbon = $date === null ? null : CarbonImmutable::instance($date)->locale(app()->getLocale());
        $enlace = (string) ($invitacion['url'] ?? '');

        return [
            'codigo' => (string) ($reservation->order->code ?? ''),
            'dia' => $carbon === null ? '' : Str::ucfirst($carbon->isoFormat(__('fiesta.fecha.larga'))),
            'dia_corto' => $date === null ? '' : DisplayTime::dayInSentence($date),
            'corto' => $date === null ? '' : str_replace('.', '', DisplayTime::dayLabel($date)),
            'hora' => trim($hora),
            'fin' => trim($fin),
            'pack' => $type->tr('name'),
            'reservados' => (int) $reservation->quantity,
            'telefono' => (string) ($site['phone'] ?? ''),
            'tel' => (string) ($site['phone_tel'] ?? ''),
            'lugar' => trim((string) ($site['name'] ?? '').(($site['city'] ?? '') !== '' ? ', '.$site['city'] : '')),
            'enlace' => $enlace,
            'enlace_corto' => preg_replace('#^https?://#', '', $enlace) ?? $enlace,
            'anfitriona' => (string) ($reservation->order->user->phone ?? ''),
        ];
    }

    /**
     * El bloque de la invitación (zona 1): tema, quién cumple, «te invita», el teléfono, compartir, el plazo, el
     * resumen, los avisos («no caben», «no vienen»), el recordatorio y las rutas de sus gestos.
     *
     * @param  array<string, mixed>  $vista
     * @param  array{nombre: string, edad: string}  $cumple
     * @param  array<string, string|int>  $reserva
     * @return array<string, mixed>
     */
    private static function invitacion(array $vista, PartyInvitation $inv, OrderItem $reservation, array $cumple, array $reserva, ?string $status, ?string $reminderText): array
    {
        $temas = array_map(fn (string $t): array => [
            'value' => $t,
            'label' => __('guestform.invite.theme_'.$t),
            'note' => $t === PartyInvitation::THEME_DEFAULT ? __('fiesta.lista.invitacion.defecto') : null,
        ], $vista['themes']);
        $titular = $cumple['nombre'].($cumple['edad'] !== ''
            ? __('fiesta.invitacion.rest', ['age' => $cumple['edad']])
            : __('fiesta.invitacion.rest_sin_edad'));
        $cuando = __('fiesta.lista.cuando', ['dia' => $reserva['dia'], 'hora' => $reserva['hora'], 'fin' => $reserva['fin'], 'lugar' => $reserva['lugar']]);
        $mensaje = __('fiesta.lista.invitacion.mensaje', ['titular' => $titular, 'cuando' => $cuando, 'enlace' => $reserva['enlace']]);
        $resumen = $vista['summary'];
        $compartida = ((int) ($resumen['yes'] ?? 0) + (int) ($resumen['no'] ?? 0) + (int) ($resumen['pending'] ?? 0)) > 0 || (int) $inv->reminded_count > 0;

        return [
            'tema' => $inv->safeTheme(),
            'temas' => $temas,
            'invita' => (string) $inv->host_line,
            'palabras' => '',
            'pistas' => '',
            'telefono' => (bool) $inv->show_host_phone,
            'url' => (string) ($vista['url'] ?? ''),
            'compartible' => (bool) $vista['shareable'],
            'respuestas_abiertas' => (bool) $vista['replies_open'],
            'plazo' => (string) $vista['deadline'],
            'compartida' => $compartida,
            'whatsapp' => 'https://wa.me/?text='.rawurlencode($mensaje),
            'mensaje' => $mensaje,
            'accion' => (string) $vista['action'],
            'descartar' => (string) $vista['dismiss'],
            'recordatorio' => (string) $vista['remind'],
            'faltan' => (int) $vista['awaiting'],
            'recordado_el' => (string) $vista['reminded_on'],
            'recordado_veces' => (int) $inv->reminded_count,
            'texto_recordatorio' => $reminderText,
            'no_caben' => (int) $vista['unplaced'],
            'no_vienen' => array_values(array_map(fn (array $r): array => ['id' => (int) $r['id'], 'nombre' => (string) $r['child_name'], 'indice' => $r['slot_index']], $vista['declined'])),
            'texto_rechazado' => $status === 'invitation-text-rejected',
            'descartada' => $status === 'invitation-dismissed',
            'guardada' => $status === 'invitation-saved',
            'honoree_max' => PartyInvitation::HONOREE_NAME_MAX,
            'host_max' => PartyInvitation::HOST_LINE_MAX,
        ];
    }

    /**
     * El número final (zona 3): lo que hay, sus límites y su plazo, y cuántos hay en la lista.
     *
     * @param  array{editable: bool, min: int, max: ?int, locked_reason: ?string, hint: string}  $control
     * @param  array{confirmados: int, no_pueden: int, sin_contestar: int, en_lista: int}  $cuentas
     * @return array<string, mixed>
     */
    private static function numero(OrderItem $reservation, array $control, array $cuentas, bool $readonly): array
    {
        $valor = (int) $reservation->quantity;
        $enLista = $cuentas['en_lista'];

        return [
            'valor' => $valor,
            'suelo' => (int) $control['min'],
            'techo' => $control['max'],
            'editable' => (bool) $control['editable'] && ! $readonly,
            'motivo' => $control['locked_reason'],
            'pista' => (string) $control['hint'],
            'en_lista' => $enLista,
            'libres' => max(0, $valor - $enLista),
            'lleno' => $enLista >= $valor,
        ];
    }

    /**
     * Los complementos de venta posterior (zona 4), uno a uno con su plazo escrito y su estado.
     *
     * @param  list<PostFormAddonView>  $addons
     * @param  array<string, string|int>  $reserva
     * @return array{lista: list<array<string, mixed>>, total: string, elegidos: int, alguno_abierto: bool}
     */
    private static function extras(array $addons, string $total, OrderItem $reservation, bool $readonly, array $reserva): array
    {
        $lista = [];
        $i = 0;
        foreach (collect($addons)->sortBy(fn (PostFormAddonView $a): int => $a->closed ? 1 : 0) as $addon) {
            $cierra = $addon->closesAt === null ? null : CarbonImmutable::parse($addon->closesAt)->setTimezone(DisplayTime::timezone());
            $plazo = $cierra === null ? '' : __('fiesta.lista.extras.hasta', ['cuando' => self::plazoEscrito($cierra)]);
            $lista[] = [
                'indice' => $i,
                'id' => $addon->productId,
                'nombre' => $addon->productName,
                'linea' => $addon->features[0] ?? '',
                'que_lleva' => $addon->features,
                'regalos' => $addon->gifts,
                'precio' => $addon->note,
                'precio_unidad' => $addon->unitPriceCents,
                'plazo' => $plazo,
                'cambia' => $cierra === null ? '' : __('fiesta.lista.extras.cambia', ['cuando' => self::plazoEscrito($cierra)]),
                'cantidad' => $addon->quantity,
                'tope' => $addon->maxQuantity,
                'cerrado' => $addon->closed || $readonly,
                'motivo' => $addon->closed
                    ? ($addon->closedReason === PostFormAddonView::REASON_SOLD_AT_BOOKING ? __('guestform.extras_closed_sold') : __('guestform.extras_closed_cutoff'))
                    : '',
                'total' => $addon->quantity > 0 ? __('fiesta.lista.extras.total', ['x' => Money::format($addon->chargedCents, $reservation->order->currency ?? 'EUR')]) : '',
            ];
            $i++;
        }

        return [
            'lista' => $lista,
            'total' => $total,
            'elegidos' => collect($addons)->filter(fn (PostFormAddonView $a): bool => $a->quantity > 0)->count(),
            'alguno_abierto' => collect($addons)->contains(fn (PostFormAddonView $a): bool => ! $a->closed) && ! $readonly,
            'telefono' => $reserva['telefono'],
            'tel' => $reserva['tel'],
        ];
    }

    /** «hoy a las 17:00», «mañana a las 17:00» o «el jueves 24 a las 17:00», como lo lee quien lo lee. */
    private static function plazoEscrito(CarbonImmutable $cierra): string
    {
        $hoy = DisplayTime::today();
        $hora = $cierra->format('H:i');
        if ($cierra->isSameDay($hoy)) {
            return __('fiesta.lista.extras.hoy', ['hora' => $hora]);
        }
        if ($cierra->isSameDay($hoy->copy()->addDay())) {
            return __('fiesta.lista.extras.manana', ['hora' => $hora]);
        }

        return __('fiesta.lista.extras.el_dia', ['dia' => DisplayTime::dayInSentence($cierra), 'hora' => $hora]);
    }

    /**
     * Los datos GENERALES del formulario (`event_fields` de la fase `postform`): el diseño no los dibuja; se pintan
     * con las piezas del sistema y se juzgan sin A (spec §1.4).
     *
     * @param  list<array<string, mixed>>  $fields
     * @return list<array<string, mixed>>
     */
    private static function generales(TicketType $type, array $fields, OrderItem $reservation, bool $readonly): array
    {
        $out = [];
        foreach ($fields as $field) {
            $out[] = [
                'key' => (string) $field['key'],
                'name' => 'general['.$field['key'].']',
                'label' => $type->eventFieldLabel($field),
                'required' => (bool) $field['required'],
                'textarea' => $field['type'] === TicketType::FIELD_TYPE_TEXTAREA,
                'numeric' => TicketType::isNumericFieldType($field['type']),
                'value' => (string) ($reservation->event_data[$field['key']] ?? ''),
                'editable' => ! $readonly,
            ];
        }

        return $out;
    }

    /**
     * Lo que se DICE arriba: el guardado, los rechazos del número y de los extras, la fiesta mixta, las edades
     * congeladas y las edades sin producto. Cada uno con su tono; el texto es el de siempre (`guestform.php`).
     *
     * @param  array<string, mixed>  $v
     * @return list<array{tono: string, titulo: string, lineas: list<string>, rol: string}>
     */
    private static function avisos(array $v, ?string $status, TicketType $type): array
    {
        $out = [];
        $aviso = static function (string $tono, string $titulo, array $lineas, string $rol = 'status') use (&$out): void {
            $out[] = ['tono' => $tono, 'titulo' => $titulo, 'lineas' => array_values(array_filter($lineas, static fn (string $l): bool => $l !== '')), 'rol' => $rol];
        };

        if ($status === 'guest-form-saved') {
            $aviso('success', '', [__('guestform.saved')]);
        }
        if (is_string($status) && str_starts_with($status, 'guest-count-')) {
            $aviso('danger', __('guestform.count_error_title'), [__('guestform.count_error_'.substr($status, strlen('guest-count-')))], 'alert');
        }
        if (in_array($status, ['guest-form-extras-blocked', 'guest-form-stale'], true)) {
            $aviso('danger', '', [$status === 'guest-form-stale' ? __('guestform.extras_stale') : __('guestform.extras_blocked')], 'alert');
        }

        $surcharge = $v['ageSurcharge'];
        $mix = $v['ageMix'];
        if ($surcharge['charge_cents'] > 0 || $surcharge['credit_cents'] > 0) {
            $lineas = [];
            foreach ($surcharge['lines'] as $line) {
                $lineas[] = __('guestform.mixed_line_written', ['count' => $line['count'], 'target' => $line['name'], 'unit' => Money::format($line['unit'])]);
            }
            if ($surcharge['credit'] !== null) {
                $lineas[] = $surcharge['credit']['label'].': −'.Money::format($surcharge['credit']['cents']);
            }
            $lineas[] = $surcharge['cents'] > 0
                ? __('guestform.mixed_surcharge', ['amount' => Money::format($surcharge['cents'])])
                : ($surcharge['cents'] < 0 ? __('guestform.mixed_discount_total', ['amount' => Money::format(-$surcharge['cents'])]) : __('guestform.mixed_net_zero'));
            $aviso('info', __('guestform.mixed_title'), $lineas);
        } elseif ($mix->mixed) {
            $lineas = [];
            foreach ($mix->upgrades as $up) {
                $lineas[] = __('guestform.mixed_line', [
                    'count' => $up['count'], 'target' => $up['name'],
                    'target_price' => $up['target_price_cents'] === null ? '—' : Money::format($up['target_price_cents']),
                    'booked' => $type->tr('name'),
                    'booked_price' => $mix->basePriceCents === null ? '—' : Money::format($mix->basePriceCents),
                ]);
            }
            $lineas[] = $mix->hasSavings() ? __('guestform.mixed_savings_pending', ['amount' => Money::format($mix->savingsCents)]) : __('guestform.mixed_no_difference');
            $aviso('info', __('guestform.mixed_title'), $lineas);
        }
        if ($v['frozenMissingAges'] > 0) {
            $aviso('warn', '', [trans_choice('guestform.frozen_missing_ages', $v['frozenMissingAges'], ['count' => $v['frozenMissingAges']])]);
        }
        if ($v['noProductNotices'] !== []) {
            $aviso('danger', __('guestform.no_product_title'), $v['noProductNotices'], 'alert');
        }

        return $out;
    }

    /**
     * El logotipo, por el MISMO hueco que el resto de la web (`DECISIONES #143`): el fichero es del cliente y no se
     * versiona; sin él, el nombre en la fuente de rótulo.
     *
     * @param  array<string, mixed>  $site
     * @return array{src: ?string, alt: string}
     */
    private static function logo(array $site): array
    {
        return Marca::logo($site);
    }
}
