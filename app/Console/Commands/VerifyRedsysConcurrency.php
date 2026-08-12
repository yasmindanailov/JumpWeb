<?php

namespace App\Console\Commands;

use App\Domain\Identity\Models\User;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Slot;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Support\Redsys;
use App\Support\RedsysReturnHandler;
use App\Support\RedsysReturnOutcome;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Verificación EMPÍRICA de concurrencia del retorno de Redsys (recomendación A, 2026-06-15).
 *
 * Cierra el punto ciego que la auditoría Fase 1 admitió por escrito (`AUDIT-FASE-1.md`): la suite
 * corre en SQLite `:memory:`, que NO reproduce los locks de InnoDB, y los tests de idempotencia
 * disparan los POST de forma SECUENCIAL, nunca solapada. El fix del doble-cobro (C1) y la
 * serialización por `lockForUpdate` quedaban «razonados para MySQL prod» pero sin verificar bajo
 * concurrencia real.
 *
 * Este comando lo verifica de VERDAD: siembra un pedido aislado, firma una notificación válida y
 * la procesa desde N procesos en PARALELO (`pcntl_fork`) contra MySQL. El invariante que debe
 * cumplirse: una notificación entregada N veces a la vez (réplica de Redsys + vuelta del navegador
 * llegando juntas) produce UN solo cobro, UN pedido pagado y exactamente `seats` tickets — nunca
 * duplicados. Firma del comportamiento correcto: exactamente 1 worker resuelve `Authorized` y los
 * (N-1) restantes `IdempotentPaid` (la prueba de que el lock serializó). Solo entornos NO
 * productivos; limpia siempre los datos que crea.
 */
class VerifyRedsysConcurrency extends Command
{
    protected $signature = 'redsys:verify-concurrency
        {--workers=8 : Nº de notificaciones concurrentes (procesos)}
        {--keep : No borrar los datos de prueba al terminar}';

    protected $description = 'Verifica empíricamente (fork real + MySQL InnoDB) que el retorno de Redsys es idempotente bajo notificaciones concurrentes: sin doble-cobro ni doble-emisión de tickets. Solo dev/local.';

    private const SEATS = 1;

    public function handle(): int
    {
        if ($this->getLaravel()->isProduction()) {
            $this->error('Abortado: NO ejecutar en producción (crea y borra datos de prueba).');

            return self::FAILURE;
        }
        if (! \extension_loaded('pcntl')) {
            $this->error('Falta la extensión pcntl: no se puede forkar para concurrencia real.');

            return self::FAILURE;
        }
        $driver = DB::connection()->getDriverName();
        if ($driver !== 'mysql') {
            $this->warn("⚠ Conexión '{$driver}': SQLite NO reproduce los locks de InnoDB. Ejecuta contra MySQL para una prueba VÁLIDA (la dev de Sail es MySQL).");
        }

        $workers = max(2, (int) $this->option('workers'));
        $resultsDir = storage_path('app/redsys-concurrency');
        File::ensureDirectoryExists($resultsDir);
        File::cleanDirectory($resultsDir);

        $this->line('Sembrando un pedido de prueba aislado (1 plaza)…');
        $seed = $this->seedScenario();
        $payload = $this->signedNotification($seed['payment']);

        $this->line("Disparando <fg=yellow>{$workers}</> notificaciones <options=bold>CONCURRENTES</> (fork) para el pago {$seed['payment']->gateway_order} sobre {$driver}…");

        try {
            $this->forkWorkers($workers, microtime(true) + 0.5, $payload, $resultsDir);

            DB::reconnect();
            $verdict = $this->evaluate($seed, $workers, $resultsDir);
        } finally {
            DB::reconnect();
            if ($this->option('keep')) {
                $this->warn("--keep: datos de prueba NO borrados (order #{$seed['order']->id}).");
            } else {
                $this->cleanup($seed);
                File::deleteDirectory($resultsDir);
            }
        }

        return $verdict ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Crea un pedido + pago `pending` aislados (usuario, franja, item desechables) listos para
     * recibir la notificación. Todo etiquetado para limpieza.
     *
     * @return array{user:User, slot:Slot, order:Order, item:OrderItem, payment:Payment}
     */
    private function seedScenario(): array
    {
        return DB::transaction(function (): array {
            $type = TicketType::where('is_sellable', true)->firstOrFail();

            $user = User::forceCreate([
                'name' => 'Concurrency Probe',
                'email' => 'concurrency-probe+'.Str::random(8).'@deleted.local',
                'password' => bcrypt(Str::random(32)),
            ]);

            $slot = Slot::create([
                'zone_id' => $type->zone_id,
                'date' => now()->addYears(5)->toDateString(),
                'start_time' => '10:00:00',
                'end_time' => '11:00:00',
                'capacity' => 50,
                'online_capacity' => 50,
            ]);

            $order = Order::create([
                'user_id' => $user->id,
                'code' => 'CONC-'.Str::upper(Str::random(4)),
                'status' => Order::STATUS_PENDING,
                'subtotal' => 1000,
                'total' => 1000,
                'currency' => 'EUR',
                'expires_at' => now()->addMinutes(30),
            ]);

            $item = OrderItem::create([
                'order_id' => $order->id,
                'ticket_type_id' => $type->id,
                'slot_id' => $slot->id,
                'quantity' => self::SEATS,
                'seats' => self::SEATS,
                'unit_price' => 1000,
            ]);

            $payment = Payment::create([
                'payable_type' => (new Order)->getMorphClass(),
                'payable_id' => $order->id,
                'provider' => 'redsys',
                'amount' => 1000,
                'currency' => 'EUR',
                'status' => Payment::STATUS_PENDING,
                'gateway_order' => 'C'.str_pad((string) $order->id, 11, '0', STR_PAD_LEFT),
            ]);

            return compact('user', 'slot', 'order', 'item', 'payment');
        });
    }

    /** Notificación Redsys AUTORIZADA, firmada con la clave del entorno (la que verifica el handler). */
    private function signedNotification(Payment $payment): array
    {
        $redsys = new Redsys;
        $data = [
            'Ds_Date' => now()->format('d/m/Y'),
            'Ds_Hour' => now()->format('H:i'),
            'Ds_Amount' => (string) $payment->amount,
            'Ds_Currency' => '978',
            'Ds_Order' => $payment->gateway_order,
            'Ds_MerchantCode' => $redsys->config()['merchant_code'],
            'Ds_Terminal' => $redsys->config()['terminal'],
            'Ds_Response' => '0000',
            'Ds_TransactionType' => '0',
            'Ds_AuthorisationCode' => '999999',
        ];
        $params = $redsys->createMerchantParameters($data);
        $signature = $redsys->createMerchantSignature($redsys->config()['secret_key'], $params, $payment->gateway_order);

        return [
            'Ds_SignatureVersion' => Redsys::SIGNATURE_VERSION,
            'Ds_MerchantParameters' => $params,
            'Ds_Signature' => $signature,
        ];
    }

    /**
     * Forka `$workers` procesos que procesan la MISMA notificación en paralelo, con arranque
     * sincronizado para maximizar la contención del lock. Cada hijo escribe su outcome a un fichero.
     */
    private function forkWorkers(int $workers, float $startAt, array $payload, string $resultsDir): void
    {
        // Cerrar la conexión del padre ANTES de forkar: el socket MySQL no debe compartirse entre
        // procesos (corrupción). Cada hijo abre la suya fresca.
        DB::disconnect();

        $pids = [];
        for ($i = 0; $i < $workers; $i++) {
            $pid = pcntl_fork();
            if ($pid === -1) {
                $this->error('pcntl_fork falló.');
                break;
            }
            if ($pid === 0) {
                // ---- HIJO ----
                DB::reconnect();
                $wait = (int) (($startAt - microtime(true)) * 1_000_000);
                if ($wait > 0) {
                    usleep($wait);
                }
                $outcome = 'ERROR';
                try {
                    $result = (new RedsysReturnHandler(new Redsys))->process($payload, 'notification');
                    $outcome = $result['outcome']?->value ?? 'null';
                } catch (\Throwable $e) {
                    $outcome = 'EXCEPTION: '.$e->getMessage();
                }
                File::put($resultsDir.'/'.$i.'.txt', $outcome);
                // Salir sin disparar el shutdown de Laravel del hijo (evita efectos colaterales).
                exit(0);
            }
            $pids[] = $pid;
        }

        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
        }
    }

    /**
     * @param  array{user:User, slot:Slot, order:Order, item:OrderItem, payment:Payment}  $seed
     */
    private function evaluate(array $seed, int $workers, string $resultsDir): bool
    {
        $order = $seed['order']->fresh();
        $paidPayments = Payment::where('id', $seed['payment']->id)->where('status', Payment::STATUS_PAID)->count();
        $tickets = Ticket::where('order_id', $seed['order']->id)->count();

        $outcomes = collect(File::files($resultsDir))
            ->map(fn ($f): string => trim(File::get($f->getPathname())))
            ->countBy(fn (string $o): string => $o)
            ->sortDesc();

        $this->newLine();
        $this->line('<options=bold>Resultados de los workers concurrentes:</>');
        foreach ($outcomes as $outcome => $count) {
            $this->line(sprintf('  %3d× %s', $count, $outcome));
        }

        $authorized = (int) ($outcomes[RedsysReturnOutcome::Authorized->value] ?? 0);
        $idempotent = (int) ($outcomes[RedsysReturnOutcome::IdempotentPaid->value] ?? 0);
        $exceptions = $outcomes->keys()->filter(fn ($k) => str_starts_with((string) $k, 'EXCEPTION') || $k === 'ERROR')->sum(fn ($k) => $outcomes[$k]);

        $this->newLine();
        $this->table(
            ['Invariante', 'Esperado', 'Real', 'OK'],
            [
                ['Pagos en estado paid', '1', (string) $paidPayments, $this->ok($paidPayments === 1)],
                ['Estado del pedido', 'paid', (string) $order->status, $this->ok($order->status === Order::STATUS_PAID)],
                ['Tickets emitidos', (string) self::SEATS, (string) $tickets, $this->ok($tickets === self::SEATS)],
                ['Workers "Authorized" (1 solo cumple)', '1', (string) $authorized, $this->ok($authorized === 1)],
                ['Workers "IdempotentPaid"', (string) ($workers - 1), (string) $idempotent, $this->ok($idempotent === $workers - 1)],
                ['Excepciones', '0', (string) $exceptions, $this->ok($exceptions === 0)],
            ]
        );

        $pass = $paidPayments === 1
            && $order->status === Order::STATUS_PAID
            && $tickets === self::SEATS
            && $authorized === 1
            && $idempotent === $workers - 1
            && $exceptions === 0;

        $this->newLine();
        if ($pass) {
            $this->info('✅ PASA: bajo '.$workers.' notificaciones concurrentes el lockForUpdate serializó correctamente — sin doble-cobro ni tickets duplicados. Invariante anti-C1 verificado sobre InnoDB real.');
        } else {
            $this->error('❌ FALLA: el invariante de concurrencia NO se cumple. Revisar el lockForUpdate / la guarda $canFulfil del handler.');
        }

        return $pass;
    }

    private function ok(bool $b): string
    {
        return $b ? '<fg=green>✓</>' : '<fg=red>✗</>';
    }

    /**
     * @param  array{user:User, slot:Slot, order:Order, item:OrderItem, payment:Payment}  $seed
     */
    private function cleanup(array $seed): void
    {
        Ticket::where('order_id', $seed['order']->id)->delete();
        Payment::where('payable_type', (new Order)->getMorphClass())->where('payable_id', $seed['order']->id)->delete();
        OrderItem::where('order_id', $seed['order']->id)->delete();
        Order::where('id', $seed['order']->id)->delete();
        Slot::where('id', $seed['slot']->id)->delete();
        User::where('id', $seed['user']->id)->delete();
        $this->line('<fg=gray>Datos de prueba borrados.</>');
    }
}
