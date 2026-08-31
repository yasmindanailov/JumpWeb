<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\TicketType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Fase 3 · paso 5 — el post-form de una reserva: su esquema, lo rellenado y a dónde guardar.
 *
 * **Lleva el ESQUEMA, no solo los datos.** Los campos por invitado son data-driven por pack
 * (`guest_fields`): cada instalación decide sus columnas desde el panel, y un cliente que las
 * llevara quemadas dejaría de funcionar en cuanto alguien añadiera una. Por eso viajan las
 * etiquetas ya resueltas al idioma activo —los textos viven en BD, no en `lang/`— y el `required`
 * de cada una.
 *
 * **`save_url` es el canje** (§4.6.5): una URL de la API firmada con la misma caducidad que el
 * enlace del correo. Quien llega con una firma válida no puede construirse otra, así que recibir
 * aquí la de guardar es lo que le permite completar el flujo sin cuenta. Es el equivalente exacto
 * del `formAction` firmado que la página web ya emitía.
 *
 * ⚠️ **Devuelve datos personales de MENORES** (nombres y, en el sector de origen, alergias — dato de
 * salud del art. 9). Es su razón de ser: el cliente edita lo que ya escribió. Lo que lo acota es que
 * la ruta va con `no-store` explícito (`RGPD-04`) y que el acceso exige firma o titularidad.
 *
 * @property-read OrderItem $resource
 */
class GuestFormResource extends JsonResource
{
    /** El recurso va en la raíz (spec §4.3). */
    public static $wrap = null;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $item = $this->resource;
        $type = $item->ticketType;
        $progress = $item->guestFormProgress();

        return [
            'reservation_id' => (int) $item->id,
            'order_code' => (string) $item->order?->code,
            'product_name' => (string) $type?->tr('name'),
            'date' => $item->slot?->date?->toDateString(),
            'time_window' => $item->displayTimeWindow(),
            // Cuántas fichas hay que rellenar: es la cantidad ACTUAL de invitados, y puede cambiar
            // si el operador la ajusta. El estado se deriva de ella, no de un flag congelado.
            'guest_count' => (int) $item->quantity,
            'status' => $item->guestFormStatus(),
            'progress' => ['done' => $progress['done'], 'total' => $progress['total']],
            // Pasado el evento, el formulario se consulta pero no se edita: guardar responde 409.
            'readonly' => $item->isFinishedInPractice(),
            'guest_fields' => $this->schema($type?->guestFields() ?? [], $type),
            'general_fields' => $this->schema($type?->eventFields(TicketType::EVENT_STAGE_POSTFORM) ?? [], $type),
            // ⚠️ `(object)` a propósito: el contrato declara OBJETOS (`GuestForm.guests[]` y
            // `GuestForm.general`), y un array PHP vacío se serializa como `[]` — una lista. Lo
            // destapó una fiesta sin campos generales de post-form (`specs/cumple-mixto.md` §22.9):
            // la respuesta violaba `openapi/v1.yaml` y ningún caso lo veía porque el fixture de
            // siempre tenía un campo general. Una ficha sin respuestas es el mismo caso.
            'guests' => array_map(static fn (array $row): object => (object) $row, $item->guestData()),
            'general' => (object) $this->generalAnswers($item, $type),
            'save_url' => $item->guestFormApiUrls()['save'],
        ];
    }

    /**
     * Esquema de campos con la etiqueta ya resuelta al idioma activo.
     *
     * @param  array<int, array<string, mixed>>  $fields
     * @return list<array{key:string, label:string, type:string, required:bool}>
     */
    private function schema(array $fields, ?TicketType $type): array
    {
        return array_map(static fn (array $field): array => [
            'key' => (string) $field['key'],
            'label' => $type?->guestFieldLabel($field) ?? (string) $field['key'],
            'type' => (string) ($field['type'] ?? 'text'),
            'required' => (bool) ($field['required'] ?? false),
        ], array_values($fields));
    }

    /**
     * Respuestas de los campos GENERALES, acotadas a los de la fase `postform`.
     *
     * `event_data` guarda juntas las respuestas de las dos fases —las de la reserva y las del
     * post-form— y aquí solo interesan las segundas: devolver las primeras expondría datos que este
     * formulario ni edita ni debería reenviar.
     *
     * @return array<string, string>
     */
    private function generalAnswers(OrderItem $item, ?TicketType $type): array
    {
        $keys = array_column($type?->eventFields(TicketType::EVENT_STAGE_POSTFORM) ?? [], 'key');
        $answers = $item->event_data ?? [];

        $out = [];
        foreach ($keys as $key) {
            $out[$key] = (string) ($answers[$key] ?? '');
        }

        return $out;
    }
}
