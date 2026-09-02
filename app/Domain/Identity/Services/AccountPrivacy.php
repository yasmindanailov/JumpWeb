<?php

namespace App\Domain\Identity\Services;

use App\Domain\Booking\Contracts\CustomerOrderHistory;
use App\Domain\Booking\Contracts\CustomerReservations;
use App\Domain\Identity\Contracts\CredentialChangeResult;
use App\Domain\Identity\Exceptions\AccountHasUpcomingReservationsException;
use App\Domain\Identity\Models\Consent;
use App\Domain\Identity\Models\Dependent;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\UserIdentity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * **Los dos derechos RGPD del titular** (`specs/area-cliente.md` §9.5, tanda 2 · paso 8): borrar la
 * cuenta (art. 17) y descargarse sus datos (art. 20).
 *
 * Cierra la tanda por el mismo motivo que la abrieron {@see AccountCredentials} y
 * {@see AccountProfile}: la lógica vivía dentro de las superficies web —`Livewire\Account\
 * DeleteAccount` y `Http\Controllers\Account\AccountController::export`— y exponerla por API sin
 * bajarla al dominio la habría **duplicado**. Aquí no se reimplementa nada: la purga es
 * {@see User::anonymize()} (`RGPD-01`) y la reconfirmación de contraseña es
 * {@see AccountCredentials::verify()}, con su limitador.
 *
 * ⚠️ **Y ése es el efecto que justifica el paso, más allá de abrir dos rutas**: al pasar el borrado
 * por aquí, la web hereda el limitador de `current_password` **sin tocar la web**. Eran cuatro los
 * sitios que reconfirman contraseña y no lo tenían (`DECISIONES #120(o)`); con éste quedan **cero**.
 *
 * **Qué NO hace, a propósito** —igual que `AccountCredentials`—: tocar la sesión en curso ni decidir
 * a dónde va el titular después. Eso es de quien atiende la petición: la web redirige a la home con
 * su mensaje y la API responde `204`.
 */
class AccountPrivacy
{
    /**
     * La clave del aviso de despedida que el titular ve tras borrar su cuenta.
     *
     * ⚠️ **Vive aquí porque la usan las DOS superficies** —la web al redirigir y la API al dejarla en
     * la sesión nueva para que el cajón la encuentre al salir a la home— y el layout la traduce por
     * `account.status.*`. Escrita a mano en los dos sitios sería la clase de duplicación que se
     * descubre el día que alguien renombra una: el aviso simplemente dejaría de salir en una de las
     * dos, sin romper nada.
     */
    public const FAREWELL_STATUS = 'account-deleted';

    public function __construct(
        private readonly AccountCredentials $credentials,
        private readonly CustomerOrderHistory $orders,
        private readonly CustomerReservations $reservations,
        private readonly DependentAssigner $assigner,
    ) {}

    /**
     * **Ejerce el derecho de supresión** (art. 17), previa reconfirmación de la contraseña.
     *
     * ⚠️ **Se llama `anonymize()` y no `delete()`, y no es capricho.** Por un lado es lo que de
     * verdad ocurre —la fila de `users` sobrevive con datos neutros para que las facturas sigan
     * vinculadas (AEAT, conservación ≥4 años) y el email original queda libre—; por otro,
     * `ApiBoundariesTest` prohíbe `->delete()` en la capa HTTP y **no puede distinguir** un servicio
     * de un modelo de Eloquent. Es la misma lección que renombró `AccountProfile::update()` a
     * `apply()` (`DECISIONES #120(q)`): un nombre que provoca la confusión cada vez que alguien lo
     * llama desde un controlador se cambia, no se le declara una excepción a la guarda.
     *
     * ⚠️ **La reconfirmación es obligatoria aquí y esto es irreversible**: es la única de las cinco
     * gestiones que el titular no puede deshacer, así que la contraseña se pide siempre —no «solo
     * si cambia algo sensible», como en el perfil—.
     *
     * ⚠️⚠️ **Con una reserva POR CELEBRAR, la supresión NO se ejecuta** (T5 · D8, `#284`,
     * `cumple-mixto.md` §25.4): se lanza {@see AccountHasUpcomingReservationsException} y la
     * superficie se lo explica al titular (la API con un `409`). La puerta va DESPUÉS de
     * `verify()` —que la existencia de reservas no se filtre a quien no tiene la contraseña, y el
     * limitador siga mandando— y aquí y no en `User::anonymize()`: metería Booking en un modelo de
     * Identity y arrastraría el censo y la idempotencia de `AnonymizeCoversEveryUserColumnTest`.
     * El panel aplica la MISMA puerta por su lado (las tres vías, `[DECIDIDO owner]` §25.9 Q2);
     * una cuenta ya anónima no llega hasta aquí (su contraseña es inservible → `verify()` falla).
     *
     * @throws AccountHasUpcomingReservationsException
     */
    public function anonymize(User $user, string $currentPassword, string $ip): CredentialChangeResult
    {
        $verdict = $this->credentials->verify($user, $currentPassword, $ip);

        if ($verdict->failed()) {
            return $verdict;
        }

        if ($this->reservations->hasUpcomingFor((int) $user->id)) {
            Log::info('account.anonymize_blocked', ['user_id' => $user->id, 'reason' => 'upcoming_reservations']);

            throw new AccountHasUpcomingReservationsException;
        }

        $userId = $user->id;

        // `anonymize()` es la purga CENTRAL y completa (`RGPD-01`): atómica, alcanza la PII de
        // terceros de sus `order_items`, redacta los payloads legacy de `audit_logs`, borra el token
        // de reset y **revoca TODAS las credenciales** —sesiones y tokens— por `revokeAllAccess()`
        // (`RGPD-06`). No se reimplementa ni se completa desde fuera: si aparece PII nueva, se añade
        // allí, que es donde las dos vías (panel y self-service) la ven.
        $user->anonymize();

        Log::info('account.anonymized', ['user_id' => $userId]);

        return CredentialChangeResult::success();
    }

    /**
     * **Dar o RETIRAR el consentimiento de marketing** (art. 7.3, `specs/auth-con-google.md` §9).
     *
     * ⚠️⚠️ **Cierra un incumplimiento que llevaba vivo desde el primer día, y no es de las cuentas de
     * Google**: `marketing_opt_in` se escribía en el alta y **ninguna ruta lo actualizaba**, así que
     * el consentimiento se daba con un clic y no se podía retirar por ninguna superficie. El art. 7.3
     * exige que retirarlo sea *tan fácil como darlo*.
     *
     * ⚠️ **Y no basta con apagar el booleano.** El art. 5.2 pide poder demostrar las dos cosas, así
     * que la retirada **sella la fila** (`revoked_at`) en vez de borrarla: la fila sigue probando que
     * en su día se aceptó —lo que justifica los envíos que se hicieron— y ahora dice además cuándo
     * dejó de valer. Sin esa marca, `GET /me/consents` enseñaría «marketing, aceptado el …» encima de
     * un interruptor apagado, que es exactamente la contradicción que la revisión de la spec señaló.
     *
     * ⚠️ **Es idempotente**: apagar lo ya apagado no escribe nada. Un segundo clic no puede crear una
     * segunda prueba de lo mismo ni mover la fecha de una retirada que ya ocurrió.
     *
     * @return bool si el estado ha CAMBIADO (lo usa la superficie para decidir si avisar de algo)
     */
    public function setMarketing(User $user, bool $wants, string $ip): bool
    {
        if ((bool) $user->marketing_opt_in === $wants) {
            return false;
        }

        DB::transaction(function () use ($user, $wants, $ip): void {
            $user->forceFill(['marketing_opt_in' => $wants])->save();

            if ($wants) {
                $user->consents()->create([
                    'type' => Consent::TYPE_MARKETING,
                    'accepted_at' => now(),
                    'ip' => $ip,
                    'version' => Consent::CURRENT_VERSION,
                ]);

                return;
            }

            // La retirada alcanza a TODAS las aceptaciones vivas de marketing, no solo a la última:
            // una cuenta antigua puede tener más de una fila (el alta y un consentimiento posterior),
            // y dejar una sin sellar sería dejar escrito que sigue aceptado.
            $user->consents()
                ->where('type', Consent::TYPE_MARKETING)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now(), 'revoked_ip' => $ip]);
        });

        Log::info('account.marketing_changed', ['user_id' => $user->id, 'accepted' => $wants]);

        return true;
    }

    /**
     * **El documento de portabilidad** (art. 20): copia legible por máquina de los datos personales
     * del titular.
     *
     * ⚠️ **Sin reconfirmar la contraseña, y es coherente con la web**: descargarse los propios datos
     * no destruye ni cede nada, y exigirla convertiría en fricción un derecho que la ley quiere
     * fácil de ejercer. Lo que sí es obligatorio es servirlo con `no-store` (`RGPD-04`): es la PII
     * más densa del producto —perfil, consentimientos con IP y `event_data` con nombre y ALERGIAS de
     * menores, art. 9—. En `/api/v1` lo lleva por defecto toda respuesta autenticada; la ruta web lo
     * declara con su alias.
     *
     * ⚠️ **Los pedidos los compone BOOKING**, no esta clase: son sus datos y los pide por contrato
     * ({@see CustomerOrderHistory}), como ya hace `CustomerAccountContext` con las reservas.
     *
     * @return array<string, mixed>
     */
    public function exportFor(User $user): array
    {
        $user->loadMissing(['consents', 'roles']);

        return [
            'exported_at' => now()->toIso8601String(),
            'profile' => [
                'name' => $user->name,
                'email' => $user->email,
                'pending_email' => $user->pending_email,
                'pending_email_sent_at' => $user->pending_email_sent_at?->toIso8601String(),
                'phone' => $user->phone,
                'locale' => $user->locale,
                'marketing_opt_in' => (bool) $user->marketing_opt_in,
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                'created_at' => $user->created_at?->toIso8601String(),
                'last_login_at' => $user->last_login_at?->toIso8601String(),
            ],
            'consents' => $user->consents->map(fn ($consent): array => [
                'type' => $consent->type,
                'accepted_at' => $consent->accepted_at?->toIso8601String(),
                // La RETIRADA viaja con la aceptación (art. 7.3 + art. 20): sin ella, el documento de
                // portabilidad diría que un consentimiento sigue vivo cuando el titular lo retiró.
                'revoked_at' => $consent->revoked_at?->toIso8601String(),
                'ip' => $consent->ip,
                'version' => $consent->version,
            ])->values()->all(),
            'roles' => $user->roles->pluck('name')->values()->all(),
            // Las IDENTIDADES EXTERNAS (`specs/auth-con-google.md` §11): con qué cuenta de un tercero
            // se entra a ésta, desde cuándo y por dónde se vinculó.
            //
            // ⚠️ **Se enumeran a mano, como todo lo de arriba, y ése es el riesgo de esta lista**: no
            // hay censo que obligue a que un dato nuevo aparezca aquí, así que un dato personal nuevo
            // se queda fuera del art. 20 **en silencio**. Lo que sí hay es el contrato
            // (`PersonalDataExport`, `additionalProperties: false` y todo en `required`), que rompe si
            // las dos mitades no cuadran — por eso esta clave y su esquema entran en el mismo commit.
            //
            // ⚠️ El `provider_id` VA INCLUIDO: es el identificador de esa persona en el proveedor, o
            // sea un dato personal suyo. No es una credencial —con él no se entra a ninguna parte— y
            // portabilidad quiere decir llevarse lo que hay, no un resumen.
            'identities' => $user->identities()->orderBy('id')->get()
                ->map(static fn (UserIdentity $identity): array => [
                    'provider' => (string) $identity->provider,
                    'provider_id' => (string) $identity->provider_id,
                    'email_at_link' => $identity->email_at_link,
                    'linked_via' => (string) $identity->linked_via,
                    'linked_at' => $identity->linked_at?->toIso8601String(),
                ])->values()->all(),
            // Fase 6 · menores a cargo (`specs/menores-a-cargo.md` §5, `RGPD-04`): las personas a cargo
            // ACTIVAS —las que declaró y no ha retirado—. Una retirada con firma detrás vive bajo el
            // régimen restringido del waiver, fuera del export del art. 20 como la propia firma.
            'dependents' => $user->dependents()->active()->orderBy('id')->get()
                ->map(static fn (Dependent $dependent): array => [
                    'name' => (string) $dependent->name,
                    'born_on' => $dependent->born_on->toDateString(),
                    'added_at' => $dependent->created_at?->toIso8601String(),
                ])->values()->all(),
            'orders' => $this->withAssignedDependents($this->orders->exportFor((int) $user->id)),
        ];
    }

    /**
     * Fase 6 · menores a cargo, tanda 4 (`specs/menores-a-cargo.md` §9.9.3 D6, `RGPD-04`): a cada
     * línea exportada se le añaden los NOMBRES de los menores para los que era, y se le retira el `id`
     * que Booking le puso solo para poder cruzar. El cruce es de Identity porque la asignación es suya.
     *
     * @param  list<array<string, mixed>>  $orders
     * @return list<array<string, mixed>>
     */
    private function withAssignedDependents(array $orders): array
    {
        $itemIds = [];
        foreach ($orders as $order) {
            foreach ($order['items'] ?? [] as $item) {
                $itemIds[] = (int) $item['id'];
            }
        }

        $byItem = $this->assigner->forOrderItems($itemIds);

        foreach ($orders as &$order) {
            foreach ($order['items'] as &$item) {
                $item['dependents'] = array_map(
                    static fn (Dependent $dependent): string => (string) $dependent->name,
                    $byItem[(int) $item['id']] ?? [],
                );
                unset($item['id']);
            }
            unset($item);
        }
        unset($order);

        return $orders;
    }
}
