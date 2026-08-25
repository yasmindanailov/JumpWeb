<?php

namespace App\Console\Commands;

use App\Domain\Booking\Models\Order;
use App\Domain\Identity\Models\User;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Models\PaymentRefund;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Limpieza de datos de CLIENTES para el go-live (#266 / despliegue). Deja una «pizarra limpia»:
 * borra TODOS los pedidos/reservas/pagos/reembolsos y TODOS los usuarios salvo los `--keep`
 * (cuentas de acceso: dueña/operador/dev). CONSERVA todo el contenido (landing, zonas, atracciones,
 * productos/tarifas, FAQs, normas, franjas, ajustes del panel) y las definiciones de roles/permisos.
 *
 * SEGURIDAD (es un borrado irreversible en producción):
 *  - `--keep=` OBLIGATORIO; si algún email a conservar NO existe (typo) → ABORTA sin borrar nada.
 *  - Exige ≥1 admin entre los conservados (anti-lockout del panel).
 *  - DRY-RUN por defecto (muestra conteos); solo borra con `--force` (SIN confirmación interactiva,
 *    para poder ejecutarse por SSH no-interactivo en prod; ver `handle()`).
 *  - Todo el borrado va en UNA transacción, en orden FK-seguro:
 *      reembolsos → pagos (morph, sin cascada) → pedidos (cascada items/tickets/ajustes)
 *      → tokens de reset + sesiones (sin FK) → usuarios (cascada consents/role_user; nullOnDelete resto).
 *
 * Ejecutar SIEMPRE con backup fresco. Idempotente: re-ejecutar deja igual (0 a borrar).
 */
class PurgeCustomerData extends Command
{
    protected $signature = 'app:purge-customers
        {--keep=* : Email(s) de las cuentas a CONSERVAR (obligatorio). Repetir --keep por cada uno.}
        {--force : Ejecutar el borrado de verdad. Sin esta opción es DRY-RUN.}';

    protected $description = 'Pizarra limpia para go-live: borra pedidos/usuarios (salvo --keep) conservando el contenido.';

    public function handle(): int
    {
        $keep = collect((array) $this->option('keep'))
            ->map(fn ($e) => mb_strtolower(trim((string) $e)))
            ->filter()
            ->unique()
            ->values();

        if ($keep->isEmpty()) {
            $this->error('Indica al menos un email a conservar con --keep=email@dominio (nunca se borran TODOS los usuarios).');

            return self::FAILURE;
        }

        // Resolver cuentas a conservar (case-insensitive). Abortar si alguna no existe (typo → riesgo).
        $keptUsers = User::whereIn(DB::raw('LOWER(email)'), $keep->all())->get();
        $found = $keptUsers->map(fn (User $u) => mb_strtolower((string) $u->email));
        $missing = $keep->diff($found);

        if ($missing->isNotEmpty()) {
            $this->error('Estos emails a conservar NO existen en la BD (¿typo?): '.$missing->implode(', '));
            $this->warn('ABORTO por seguridad: no se borra nada hasta que TODOS los --keep resuelvan a una cuenta real.');

            return self::FAILURE;
        }

        // Anti-lockout: al menos una cuenta conservada debe ser admin.
        if (! $keptUsers->contains(fn (User $u) => $u->hasRole('admin'))) {
            $this->error('Ninguna cuenta conservada tiene rol admin → te quedarías sin acceso al panel. ABORTO.');

            return self::FAILURE;
        }

        $keptIds = $keptUsers->pluck('id')->all();
        $orderMorph = (new Order)->getMorphClass();

        $paymentIds = Payment::where('payable_type', $orderMorph)->pluck('id');
        $deleteUserIds = User::whereNotIn('id', $keptIds)->pluck('id');

        $this->newLine();
        $this->table(['Concepto', 'Cantidad'], [
            ['Pedidos a borrar (TODOS)', Order::count()],
            ['Pagos a borrar', $paymentIds->count()],
            ['Reembolsos a borrar', PaymentRefund::whereIn('payment_id', $paymentIds)->count()],
            ['Usuarios a borrar', $deleteUserIds->count()],
            ['Usuarios conservados', count($keptIds)],
        ]);
        $this->info('Cuentas conservadas: '.$keptUsers->pluck('email')->implode(', '));

        if (! $this->option('force')) {
            $this->newLine();
            $this->warn('DRY-RUN: no se ha borrado nada. Revisa los conteos y vuelve a ejecutar con --force.');

            return self::SUCCESS;
        }

        // Sin confirmación interactiva (debe poder ejecutarse por SSH no-interactivo en prod). La
        // seguridad está en: DRY-RUN por defecto + `--force` explícito + guardas (--keep obligatorio,
        // aborta ante typo, exige admin) + backup fresco previo (runbook). Aviso claro antes de borrar.
        $this->newLine();
        $this->warn('Ejecutando borrado IRREVERSIBLE (--force). Asegúrate de tener un backup fresco.');

        DB::transaction(function () use ($keptIds): void {
            $orderMorph = (new Order)->getMorphClass();
            $paymentIds = Payment::where('payable_type', $orderMorph)->pluck('id')->all();

            // 1. Reembolsos (RESTRICT sobre payment_id y requested_by) — antes que pagos y usuarios.
            PaymentRefund::whereIn('payment_id', $paymentIds)->delete();
            // 2. Pagos (morph: sin cascada al borrar el pedido).
            Payment::whereIn('id', $paymentIds)->delete();
            // 3. Pedidos → cascada order_items, tickets, order_adjustments.
            Order::query()->delete();
            // 4. Adyacentes de usuario SIN FK: tokens de reset (por email), sesiones y tokens de API.
            //    Los `personal_access_tokens` son una tabla MORPH y no tienen clave foránea, así que
            //    borrar la fila de `users` los dejaría huérfanos apuntando a un id que ya no existe
            //    (verificado: 0 FKs en la tabla). Se añaden en Fase 3 · paso 3a, cuando Sanctum entró
            //    en el proyecto después de escribirse este comando.
            $purgedIds = User::whereNotIn('id', $keptIds)->pluck('id')->all();
            $emails = User::whereNotIn('id', $keptIds)->whereNotNull('email')->pluck('email')->all();
            DB::table('password_reset_tokens')->whereIn('email', $emails)->delete();
            DB::table('sessions')->whereNotIn('user_id', $keptIds)->delete();
            DB::table('personal_access_tokens')
                ->where('tokenable_type', (new User)->getMorphClass())
                ->whereIn('tokenable_id', $purgedIds)
                ->delete();
            // 4.bis Fase 6 · waiver: `waiver_signatures.user_id` es RESTRICT a propósito (la prueba
            //    sobrevive al titular, `specs/waiver-probatorio.md` §8.6), así que va ANTES que los
            //    usuarios. Esta limpieza de go-live es —con el verificador de cadena— la única que
            //    borra firmas, y lo hace por `DB::table` porque el modelo es append-only y rechaza
            //    `delete()`. `legal_document_versions.published_by` es nullOnDelete: las versiones quedan.
            DB::table('waiver_signatures')->whereIn('user_id', $purgedIds)->delete();
            // 5. Usuarios → cascada consents/role_user; nullOnDelete audit_logs/cookie_consent_logs/tickets/order_items.
            User::whereNotIn('id', $keptIds)->delete();
        });

        $this->newLine();
        $this->info('✓ Limpieza completada. Pedidos: '.Order::count().' · Usuarios: '.User::count().' (conservados).');

        return self::SUCCESS;
    }
}
