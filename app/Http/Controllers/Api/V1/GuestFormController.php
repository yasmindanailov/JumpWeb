<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Services\GuestCountAdjuster;
use App\Domain\Booking\Services\PartyInvitations;
use App\Domain\Booking\Services\PostFormAddonChanges;
use App\Domain\Booking\Services\PostFormAddons;
use App\Http\Api\ApiErrorCode;
use App\Http\Api\ApiErrorResponse;
use App\Http\Concerns\AuthorizesGuestForm;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\GuestFormResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Fase 3 · paso 5 — el POST-FORM de invitados por API (`docs/specs/api-v1.md` §4.4 y §4.6.5).
 *
 * Es el **segundo consumidor** de la API, y se eligió precisamente porque obliga a resolver algo que
 * ningún otro endpoint plantea: **cómo autentica la API a un portador de firma**. El resto de la
 * superficie es pública o va con sesión; esto es un enlace que viaja por correo semanas antes del
 * evento, sin cuenta detrás.
 *
 * **El canje** (lo que §4.6.5 dejó pendiente). La firma de Laravel cubre la URL EXACTA, así que la
 * del correo —que apunta a una ruta web— no autoriza un `PUT /api/v1/...`: reenviar su `signature`
 * a otro path no valida. La salida no es un almacén de credenciales nuevo, sino firmar también la
 * URL de la API con la MISMA caducidad y entregarla a quien ya demostró acceso
 * (`OrderItem::guestFormApiUrls()`): la página que abre el enlace del correo la recibe, y el propio
 * `GET` de aquí devuelve la de guardar. Mismo alcance —una reserva—, misma vida y misma prueba
 * (HMAC del servidor) que el modelo que la web ya usaba: no se relaja nada.
 *
 * **La reserva se resuelve A MANO, sin *route model binding*.** Con binding implícito, un
 * identificador inexistente daría 404 ANTES de comprobar la autorización, y ese 404 —frente al 403
 * de uno existente— le contaría a un desconocido qué reservas hay. La escalada 403 → 410 → 404 es
 * deliberada (spec §6.6) y solo se sostiene si nada responde antes que ella.
 *
 * **`no-store` va en la ruta, no heredado.** Este endpoint es accesible SIN sesión, así que el
 * `no-store` por defecto de la superficie autenticada no lo cubriría — y lo que devuelve son nombres
 * y alergias de menores (`RGPD-04`, dato de salud del art. 9).
 */
class GuestFormController extends Controller
{
    use AuthorizesGuestForm;

    /** El formulario: su esquema, lo ya rellenado, el progreso y a dónde guardar. */
    public function show(Request $request, int $reservation): GuestFormResource
    {
        $item = $this->resolve($reservation);

        $this->authorizeGuestFormAccess($request, $item);

        return new GuestFormResource($item);
    }

    /**
     * Guarda el formulario. **`PUT` y no `PATCH`**: el envío sustituye la lista de invitados entera
     * —el cliente manda las N fichas que hay ahora—, no la parchea ficha a ficha.
     *
     * Lo que se persiste lo decide el dominio (`OrderItem::submitGuestForm()`), que es el mismo que
     * usa la web: saneado contra el esquema y contra la cantidad ACTUAL de invitados, mezcla que
     * preserva los datos de la fase de reserva, sello de completado y rastro sin PII.
     */
    public function update(Request $request, int $reservation): GuestFormResource|JsonResponse
    {
        $item = $this->resolve($reservation);

        $this->authorizeGuestFormAccess($request, $item);

        // El post-form sirve para PREPARAR la fiesta: pasada, se consulta pero no se edita. Un 409 y
        // no un 403 — el permiso no ha cambiado, ha cambiado el momento—, y se comprueba en servidor
        // aunque la interfaz ya no ofrezca editar: una pestaña vieja o un cliente propio no lo saben.
        if ($item->isFinishedInPractice()) {
            return ApiErrorResponse::make(ApiErrorCode::GuestFormClosed, 409);
        }

        $validated = $request->validate([
            // Las CLAVES de cada ficha son data-driven (`guest_fields` del pack), así que aquí solo
            // se valida la FORMA; qué campos existen y cuáles son obligatorios lo sabe el esquema, y
            // el saneado del dominio descarta lo que no reconozca.
            'guests' => ['sometimes', 'array', 'max:'.self::MAX_GUESTS],
            'guests.*' => ['array'],
            'general' => ['sometimes', 'array'],
            // Los EXTRAS de venta posterior (T3 de `#413`). Aquí solo la FORMA: qué se ofrece, con
            // qué tope y hasta cuándo lo decide el dominio bajo el lock (`PAY-12`).
            'addons' => ['sometimes', 'array', 'max:'.self::MAX_ADDONS],
            'addons.*.product_id' => ['required', 'integer', 'min:1'],
            'addons.*.quantity' => ['required', 'integer', 'min:0'],
            'expected_version' => ['sometimes', 'string', 'max:32'],
            // Los INVITADOS (`specs/invitados-en-post-form.md`, `#444`). Aquí solo la FORMA: el
            // techo, los DOS suelos, el plazo y el aforo los decide el dominio bajo el lock — igual
            // que con los extras, y por el mismo motivo (`SEC-04`: la autoridad no es la petición).
            'guest_count' => ['sometimes', 'integer', 'min:1', 'max:'.self::MAX_GUESTS],
            // La ADOPCIÓN de respuestas de la invitación (T4·6). ⚠️ Va FUERA de `guests` y no dentro
            // de cada fila: una fila es un mapa ABIERTO de respuestas por clave de campo, y esas
            // claves las inventa cada instalación desde su panel — una marca metida ahí chocaría el
            // día que alguien llamara a una columna igual (§1.3·13, §7.2·R3).
            'adopt' => ['sometimes', 'array', 'max:'.self::MAX_GUESTS],
            'adopt.*' => ['integer', 'min:1'],
            // «AL FINAL VIENE» (F3c de `fiesta-sistema-nuevo.md` §4.8, `#747`, contrato 1.36.0): los «no» que el anfitrión
            // vuelve a contar. Fuera de `guests` por la misma razón que `adopt`.
            'rejoin' => ['sometimes', 'array', 'max:'.self::MAX_GUESTS],
            'rejoin.*' => ['integer', 'min:1'],
        ]);

        // El estado ANTES de nuestra propia escritura ({@see addonsExpectedVersion}): `submitGuestForm()`
        // mueve `updated_at`, así que comparar el testigo del cliente contra el de después haría
        // que un `PUT` normal —fichas y extras a la vez— nunca comprara nada.
        $before = PostFormAddons::versionOf($item);

        // ❗❗ **La cantidad va DESPUÉS de `$before` y ANTES del saneo de las fichas, y con la reserva
        // re-leída en medio** (`specs/invitados-en-post-form.md` §7.1·A2/A3): capturarla antes deja el
        // testigo de los extras fuera de sitio, hacerlo después recortaría las fichas contra la
        // cantidad VIEJA —el hueco original dentro de su propio arreglo— y sin re-leer, el saneo mira
        // la instancia en memoria. La web hace exactamente esto, así que las dos no pueden divergir.
        $countChange = null;
        $desiredCount = $this->submittedGuestCount($request, $validated);
        if ($desiredCount !== null) {
            $countChange = app(GuestCountAdjuster::class)->adjust(
                $item,
                $desiredCount,
                $this->guestFormVia($request),
                $validated['expected_version'] ?? null,
            );
            $item = $item->fresh(['ticketType', 'slot', 'order', 'children']) ?? $item;
        }

        // ⚠️⚠️ La ausencia de una clave significa «no la toques», NUNCA «vacíala». El `?? []` que
        // había aquí hacía que un `PUT` con solo `general` **borrara las fichas de los menores**
        // (medido: 8 filas vaciadas, respuesta 200) — y el propio contrato lo documentaba como una
        // virtud, «se guarda a trozos». Guardar a trozos borraba el otro trozo.
        $item->submitGuestForm(
            $this->submittedGuestFormArray($request, 'guests', $validated),
            $this->submittedGuestFormArray($request, 'general', $validated),
            $this->guestFormVia($request),
        );

        // ── La ADOPCIÓN de respuestas de la invitación (T4·6, §4.7; `DECISIONES #578`) ──────────
        //
        // ⚠️⚠️ **Va DESPUÉS de guardar las fichas, y el orden es la regla.** Adoptar marca una
        // respuesta con la clave del nombre que el anfitrión acaba de escribir, así que antes de
        // escribirlo no hay contra qué emparejarla. Y la reconciliación va la última porque compara
        // contra las fichas que han quedado guardadas: una respuesta adoptada cuyo nombre ya no está
        // es una que **el anfitrión quitó** — dejarla adoptada la escondería para siempre y encima
        // seguiría ocupando su plaza en el suelo de `#444`.
        //
        // ⚠️ Solo se reconcilia si vinieron `guests`: sin ellas las fichas no se han tocado, y
        // recorrerlas igual descartaría respuestas por un `PUT` que solo cambiaba las observaciones.
        if (isset($validated['adopt']) && is_array($validated['adopt'])) {
            app(PartyInvitations::class)->adopt($item, array_map(intval(...), $validated['adopt']));
        }
        if ($this->submittedGuestFormArray($request, 'guests', $validated) !== null) {
            app(PartyInvitations::class)->reconcileAdopted($item->fresh(['ticketType']) ?? $item);
        }
        // La vuelta de los «no» (F3c), DESPUÉS de reconciliar, como en la web: adopta en su ficha, y la reconciliación con
        // las fichas de antes la habría descartado. Sin ficha libre no entra; la app lo ve en la respuesta (sigue «no»).
        if (isset($validated['rejoin']) && is_array($validated['rejoin'])) {
            app(PartyInvitations::class)->rejoin($item->fresh(['ticketType', 'order']) ?? $item, array_map(intval(...), $validated['rejoin']));
        }

        // Los extras van DESPUÉS y en su propia transacción (§4.5.3): un hueco de tarifas o un id que
        // dejó de ofrecerse no puede tumbar el guardado de los nombres y las alergias, que es la
        // razón de ser de este formulario. La no-atomicidad es deliberada y se DICE.
        $desired = $this->submittedGuestFormArray($request, 'addons', $validated);
        if ($desired !== null) {
            $fresh = $item->fresh(['ticketType.addons', 'order', 'slot', 'children']);
            $changes = app(PostFormAddons::class)->reconcile(
                $fresh,
                self::desiredQuantities($desired),
                $this->guestFormVia($request),
                null,
                $this->addonsExpectedVersion($validated['expected_version'] ?? null, $before, $fresh),
            );

            // Un envío hecho con la pantalla vieja no se aplica a medias: se rechaza entero, y con un
            // 409 —el permiso no ha cambiado, ha cambiado el estado— para que el cliente relea.
            if ($this->isStale($changes)) {
                return ApiErrorResponse::make(ApiErrorCode::GuestFormStale, 409);
            }
        }

        return new GuestFormResource($item->fresh(['ticketType.addons', 'order', 'slot', 'children']));
    }

    /**
     * Tope de fichas del cuerpo. El dominio recorta a la cantidad real de invitados de la reserva,
     * así que esto no es la regla: es lo que impide que un cuerpo desmedido llegue a recorrerse.
     */
    private const MAX_GUESTS = 200;

    /** Lo mismo para los extras: el catálogo de una instalación no tiene cientos de complementos. */
    private const MAX_ADDONS = 50;

    /**
     * `[{product_id, quantity}]` → `[productId => quantity]`. Si un id viene repetido gana el ÚLTIMO:
     * es lo que un formulario HTML produce al re-enviar un campo, y sumar dos valores del mismo
     * complemento convertiría un cuerpo torpe en una compra que nadie pidió.
     *
     * @param  array<mixed>  $rows
     * @return array<int,int>
     */
    private static function desiredQuantities(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (is_array($row) && isset($row['product_id'], $row['quantity'])) {
                $out[(int) $row['product_id']] = (int) $row['quantity'];
            }
        }

        return $out;
    }

    /** ¿El reconciliador rechazó TODO por testigo obsoleto? (§4.8·5.) */
    private static function isStale(PostFormAddonChanges $changes): bool
    {
        return ! $changes->changed()
            && $changes->blocked !== []
            && collect($changes->blocked)->every(fn (array $b): bool => $b['reason'] === 'stale');
    }

    /**
     * Reserva por id, o `null`. Sin `firstOrFail`: el «no existe» lo decide la escalada, no esto.
     *
     * ▶ El cuerpo subió al trait en la T4·6 (`#578`), donde ya vive la escalada que lo explica y
     * desde donde lo comparten los tres controladores que entran por esta puerta.
     */
    private function resolve(int $reservation): ?OrderItem
    {
        return $this->resolveGuestFormReservation($reservation);
    }
}
