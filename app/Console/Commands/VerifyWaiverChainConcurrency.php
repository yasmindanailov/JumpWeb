<?php

namespace App\Console\Commands;

use App\Domain\Identity\Models\Dependent;
use App\Domain\Identity\Models\GuardianAuthorization;
use App\Domain\Identity\Models\LegalDocumentVersion;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\WaiverSignature;
use App\Domain\Identity\Services\GuardianAuthorizationSigner;
use App\Domain\Identity\Services\LegalDocumentPublisher;
use App\Domain\Identity\Services\LegalDocuments;
use App\Domain\Identity\Services\WaiverChain;
use App\Domain\Identity\Services\WaiverSettings;
use App\Domain\Identity\Services\WaiverSignatureRequest;
use App\Domain\Identity\Services\WaiverSigner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Fase 6 · waiver — verificador de CONCURRENCIA de la cadena de hashes
 * (`docs/specs/waiver-probatorio.md` §8.5), hermano de `purchase:verify-oversell`.
 *
 * N procesos reales (`pcntl_fork`) firman a la vez contra MySQL. El invariante desde
 * `DECISIONES #197` (cadena por (titular, sujeto)): **UNA sola fila** — la primera firma entra y las
 * N-1 restantes la encuentran bajo el lock y la devuelven (idempotencia por versión). Si el lock de
 * `WaiverSigner` no serializa, todas leen «no hay firma», todas insertan, y la cadena del sujeto nace
 * BIFURCADA: N filas con el mismo `prev_hash` nulo. No falla, no avisa, y una cadena bifurcada no
 * prueba nada.
 *
 * ▶ **DOS escenarios, porque la propiedad que hay que forzar es distinta en cada uno** (`--scenario`):
 *
 *  - **`holder`** (por defecto): N firmas del MISMO titular como sujeto. Es el escenario histórico.
 *  - **`guest`**: N envíos del MISMO justificante de menor invitado
 *    (`specs/waiver-por-reserva.md` §6·5) — mismo pedido, mismo menor, por
 *    `GuardianAuthorizationSigner`. Fuerza DOS cosas a la vez: la idempotencia de la firma **y** la
 *    carrera contra el `UNIQUE (order_item_id, minor_key)` de la autorización, que sin el lock daría un
 *    error de clave duplicada en vez de encontrar la fila.
 *
 * ⚠️⚠️ **El escenario obvio para el sujeto nuevo NO MUERDE, y es la trampa que este fichero ya
 * documentaba en `#197` para el caso anterior**: N padres DISTINTOS del mismo pedido son N cadenas de
 * UNA fila, así que sin el lock no se bifurca nada y el instrumento saldría verde igual. Lo que el
 * lock protege es la idempotencia del MISMO sujeto, y es lo que se mide.
 *
 * ⚠️ Un verde solo vale si el instrumento se ha visto FALLAR (`#147`): con el `lockForUpdate()`
 * retirado del firmador, este comando tiene que cazar la bifurcación en los dos escenarios. Solo
 * dev/local.
 */
class VerifyWaiverChainConcurrency extends Command
{
    private const SCENARIOS = ['holder', 'guest'];

    protected $signature = 'waiver:verify-chain
        {--workers=8 : Nº de firmas concurrentes (procesos) del MISMO sujeto}
        {--scenario=holder : holder | guest — qué sujeto firman los procesos}
        {--keep : No borrar los datos de prueba al terminar}';

    protected $description = 'Verifica empíricamente (fork real + MySQL InnoDB) que N firmas simultáneas del mismo sujeto producen UNA sola fila (idempotencia bajo el lock) y cadenas lineales por sujeto. Dos escenarios: `holder` y `guest` (justificante de menor invitado). Solo dev/local.';

    public function handle(): int
    {
        if ($this->getLaravel()->isProduction()) {
            $this->error('Abortado: NO ejecutar en producción (crea y borra datos).');

            return self::FAILURE;
        }
        if (! \extension_loaded('pcntl')) {
            $this->error('Falta la extensión pcntl: no se puede forkar para concurrencia real.');

            return self::FAILURE;
        }
        $scenario = (string) $this->option('scenario');
        if (! in_array($scenario, self::SCENARIOS, true)) {
            $this->error('Escenario desconocido: «'.$scenario.'». Usa: '.implode(' | ', self::SCENARIOS).'.');

            return self::FAILURE;
        }
        $driver = DB::connection()->getDriverName();
        if ($driver !== 'mysql') {
            $this->warn("⚠ Conexión '{$driver}': SQLite NO reproduce los locks de InnoDB. Ejecuta contra MySQL para una prueba VÁLIDA.");
        }

        $workers = max(2, (int) $this->option('workers'));
        $resultsDir = storage_path('app/waiver-chain-concurrency');
        File::ensureDirectoryExists($resultsDir);
        File::cleanDirectory($resultsDir);

        $seed = $this->seed($scenario, $workers);
        $this->line("Escenario <fg=yellow>{$scenario}</> · titular #{$seed['user']->getKey()} · menor a cargo #{$seed['dependent']->getKey()}"
            .($seed['order_item_id'] !== null ? " · reserva #{$seed['order_item_id']}" : '')
            ." · versión firmable v{$seed['version']->version}·{$seed['version']->locale} (#{$seed['version']->getKey()}).");

        // La guarda del instrumento: una firma EN SERIE tiene que funcionar. Si no, lo que fallara
        // abajo no sería la carrera, y el verificador estaría midiendo otra cosa. Se firma en nombre
        // del MENOR A CARGO: así la cadena del sujeto de la carrera parte de cero, y la del menor
        // queda aparte para comprobar que las cadenas verifican por separado.
        try {
            app(WaiverSigner::class)->sign(
                $seed['user'],
                $seed['version'],
                WaiverSignatureRequest::api('127.0.0.1', 'waiver:verify-chain/probe')->forDependent((int) $seed['dependent']->getKey()),
            );
        } catch (\Throwable $e) {
            $this->error('El escenario NO permite firmar ni una vez: '.$e->getMessage());
            $this->cleanup($seed, $resultsDir);

            return self::FAILURE;
        }

        $this->line("Disparando <fg=yellow>{$workers}</> firmas <options=bold>CONCURRENTES</> del mismo sujeto sobre {$driver}…");

        try {
            $this->forkWorkers($seed, $scenario, $workers, microtime(true) + 0.5, $resultsDir);

            DB::reconnect();
            $verdict = $this->evaluate($seed, $scenario, $workers, $resultsDir);
        } finally {
            DB::reconnect();
            if ($this->option('keep')) {
                $this->warn("--keep: datos de prueba NO borrados (titular #{$seed['user']->getKey()}).");
            } else {
                $this->cleanup($seed, $resultsDir);
            }
        }

        return $verdict ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Un titular desechable con un menor a cargo —y, en el escenario `guest`, un pedido suyo— más la
     * versión vigente del waiver; si no hay ninguna publicada, se publica una de prueba (y se retira
     * al limpiar).
     *
     * ⚠️ El pedido se inserta por `DB::table` a propósito: solo hace falta una fila que satisfaga la
     * FK RESTRICT de `guardian_authorizations`, y construir un pedido de verdad metería aquí aforo,
     * catálogo y dinero, que no es lo que este instrumento mide.
     *
     * @return array{user:User, dependent:Dependent, version:LegalDocumentVersion, created_version:bool, order_item_id:?int}
     */
    private function seed(string $scenario, int $workers): array
    {
        return DB::transaction(function () use ($scenario, $workers): array {
            $user = User::create([
                'name' => 'Verificador de cadena',
                'email' => 'waiver-chain-'.Str::lower(Str::random(8)).'@verify.local',
                'password' => Str::random(32),
                'locale' => 'es',
                'marketing_opt_in' => false,
            ]);
            // Desde `#179` el titular firma solo con el correo verificado.
            $user->forceFill(['email_verified_at' => now()])->save();

            $dependent = Dependent::create([
                'user_id' => (int) $user->getKey(),
                'name' => 'Menor del verificador',
                'born_on' => now()->subYears(9)->toDateString(),
            ]);

            $reservationId = null;
            if ($scenario === 'guest') {
                // ⚠️ La RESERVA tiene que ser LEGAL para el subsistema, no solo existir: su pedido
                // `paid` y ella principal y viva, porque el tope sale de ahí
                // (`AuthorizableReservationsReader`). Una línea de cantidad 0 daría cero plazas y este
                // verificador mediría el rechazo del cupo en vez de la carrera — verde por el motivo
                // equivocado.
                $ticketTypeId = DB::table('ticket_types')->where('is_active', true)->value('id');
                if ($ticketTypeId === null) {
                    throw new \RuntimeException('No hay ningún producto activo en el catálogo: el escenario `guest` no puede montar un pedido legal.');
                }

                $orderId = (int) DB::table('orders')->insertGetId([
                    'user_id' => (int) $user->getKey(),
                    'code' => 'WVCHAIN-'.Str::upper(Str::random(8)),
                    'status' => 'paid',
                    'paid_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                // Sin franja: `visitFinished` es `false` cuando NINGUNA línea tiene fecha, así que la
                // ventana está abierta y la carrera se mide sin depender del calendario.
                $reservationId = (int) DB::table('order_items')->insertGetId([
                    'order_id' => $orderId,
                    'ticket_type_id' => $ticketTypeId,
                    'quantity' => max(2, $workers),
                    'unit_price' => 0,
                    'seats' => max(2, $workers),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $version = LegalDocuments::current(WaiverSettings::SLUG, 'es');
            $created = false;
            if ($version === null) {
                $version = app(LegalDocumentPublisher::class)->publish(WaiverSettings::SLUG, [
                    'es' => [
                        'title' => 'Waiver (verificador de cadena)',
                        'body' => [['h' => 'Prueba', 'p' => 'Texto del verificador de concurrencia de la cadena.']],
                    ],
                ])->first();
                $created = true;
            }

            return ['user' => $user, 'dependent' => $dependent, 'version' => $version, 'created_version' => $created, 'order_item_id' => $reservationId];
        });
    }

    /**
     * @param  array{user:User, version:LegalDocumentVersion, order_item_id:?int}  $seed
     */
    private function forkWorkers(array $seed, string $scenario, int $workers, float $startAt, string $resultsDir): void
    {
        DB::disconnect(); // el socket MySQL del padre NO debe compartirse entre forks

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
                    $holder = User::findOrFail($seed['user']->getKey());
                    $version = LegalDocumentVersion::findOrFail($seed['version']->getKey());
                    $request = WaiverSignatureRequest::web('127.0.0.1', "waiver:verify-chain/{$i}");

                    if ($scenario === 'guest') {
                        // El MISMO menor y el MISMO adulto en todos los procesos: la propiedad es que
                        // solo se cree UNA autorización y se escriba UNA firma. Sin el lock, unos se
                        // estrellan contra el UNIQUE y otros bifurcan la cadena.
                        $result = app(GuardianAuthorizationSigner::class)->sign(
                            $holder,
                            (int) $seed['order_item_id'],
                            $version,
                            [
                                'minor_name' => 'Ana',
                                'minor_surname' => 'Del Verificador',
                                'minor_born_on' => now()->subYears(8)->toDateString(),
                                'guardian_name' => 'Padre',
                                'guardian_surname' => 'Del Verificador',
                                'guardian_relationship' => 'father',
                                'guardian_email' => null,
                                'guardian_phone' => null,
                            ],
                            $request,
                        );
                        $outcome = 'signed:'.$result['signature']->getKey();
                    } else {
                        // Todos como el TITULAR, misma versión: la propiedad es que solo UNO escriba.
                        $signature = app(WaiverSigner::class)->sign($holder, $version, $request);
                        $outcome = 'signed:'.$signature->getKey();
                    }
                } catch (\Throwable $e) {
                    $outcome = 'EXCEPTION: '.$e->getMessage();
                }
                File::put($resultsDir.'/'.$i.'.txt', $outcome);
                exit(0);
            }
            $pids[] = $pid;
        }

        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
        }
    }

    /**
     * @param  array{user:User, dependent:Dependent, version:LegalDocumentVersion, order_item_id:?int}  $seed
     */
    private function evaluate(array $seed, string $scenario, int $workers, string $resultsDir): bool
    {
        $outcomes = collect(File::files($resultsDir))
            ->map(fn ($file): string => trim(File::get($file->getPathname())));
        $signed = $outcomes->filter(fn (string $o): bool => str_starts_with($o, 'signed:'));
        $errors = $outcomes->reject(fn (string $o): bool => str_starts_with($o, 'signed:'));
        $distinctIds = $signed->map(fn (string $o): string => mb_substr($o, 7))->unique();

        $rows = WaiverSignature::query()->where('user_id', $seed['user']->getKey())->orderBy('id')->get();
        $dependentRows = $rows->where('subject_type', WaiverSignature::SUBJECT_DEPENDENT);
        $racedRows = $scenario === 'guest'
            ? $rows->where('subject_type', WaiverSignature::SUBJECT_GUEST_MINOR)
            : $rows->where('subject_type', WaiverSignature::SUBJECT_HOLDER);
        $chain = WaiverChain::verify(User::findOrFail($seed['user']->getKey()));
        // Dos filas del MISMO sujeto con el mismo prev_hash = bifurcación. ⚠️ La clave es la del
        // modelo, no una compuesta aquí: con el literal duplicado, el sujeto nuevo caía todo en la
        // misma cesta y este contador dejaba de medir nada.
        $forks = $rows->groupBy(fn (WaiverSignature $r): string => $r->chainKey())
            ->sum(fn ($group): int => $group->count() - $group->pluck('prev_hash')->unique()->count());

        // Las cadenas ESPERADAS son la del menor a cargo (la sonda en serie) + la del sujeto de la
        // carrera. Sale del escenario y no de un literal: con `chains === 2` clavado, cualquier
        // escenario nuevo mediría otra cosa sin que nadie lo notara.
        $expectedChains = 2;
        $authorizations = $scenario === 'guest'
            ? GuardianAuthorization::query()->where('order_item_id', $seed['order_item_id'])->count()
            : null;

        $this->newLine();
        $this->line('<options=bold>Resultados de las firmas concurrentes:</>');
        $this->line('  '.$signed->count().'× devuelven una firma · ids distintos: '.$distinctIds->count());
        if ($errors->isNotEmpty()) {
            $this->line('  <fg=red>'.$errors->count().'× error inesperado</>');
            $errors->each(fn (string $e) => $this->line('     '.$e));
        }
        $this->line("  filas del sujeto en carrera ({$scenario}): {$racedRows->count()} (esperada 1) · del menor a cargo: {$dependentRows->count()} (esperada 1)"
            .($authorizations !== null ? " · autorizaciones: {$authorizations} (esperada 1)" : '')
            ." · cadenas: {$chain['chains']} (esperadas {$expectedChains}) · prev_hash repetidos por sujeto: {$forks} · verificación: ".($chain['ok'] ? 'OK' : 'ROTA'));
        foreach ($chain['problems'] as $problem) {
            $this->line('     <fg=red>'.$problem.'</>');
        }

        $ok = $signed->count() === $workers
            && $distinctIds->count() === 1
            && $racedRows->count() === 1
            && $dependentRows->count() === 1
            && ($authorizations === null || $authorizations === 1)
            && $chain['chains'] === $expectedChains
            && $forks === 0
            && $chain['ok'];
        $this->newLine();
        if ($ok) {
            $this->info("✅ PASA ({$scenario}): bajo {$workers} firmas concurrentes del mismo sujeto hay UNA sola fila —idempotencia bajo el lock— y las {$expectedChains} cadenas verifican. Verificado sobre InnoDB real.");
        } else {
            $this->error('❌ FALLA: firmas duplicadas, autorización duplicada o cadena rota. Revisar que el lockForUpdate() de la fila del titular sea la PRIMERA sentencia de la transacción de WaiverSigner (y de GuardianAuthorizationSigner).');
        }

        return $ok;
    }

    /**
     * Borrado por `DB::table`: las tablas son append-only (o rechazan borrar con referencias) y sus
     * modelos rechazan `delete()`. Esta limpieza y la de go-live (`PurgeCustomerData`) son las únicas
     * que lo hacen, y solo sobre datos que este propio comando creó.
     *
     * ⚠️ El orden lo mandan las FK RESTRICT: firmas → autorizaciones → pedido → menor → titular.
     *
     * @param  array{user:User, dependent:Dependent, version:LegalDocumentVersion, created_version:bool, order_item_id:?int}  $seed
     */
    private function cleanup(array $seed, string $resultsDir): void
    {
        $userId = $seed['user']->getKey();
        DB::table('waiver_signatures')->where('user_id', $userId)->delete();
        if ($seed['order_item_id'] !== null) {
            DB::table('guardian_authorizations')->where('order_item_id', $seed['order_item_id'])->delete();
            // ⚠️ El PEDIDO se resuelve desde la línea: el `seed` guarda la reserva desde `#343`, y
            // borrar `orders.id = <id de línea>` habría borrado el pedido EQUIVOCADO (o ninguno).
            $orderId = DB::table('order_items')->where('id', $seed['order_item_id'])->value('order_id');
            // `order_items.order_id` es CASCADE (verificado en `information_schema`): la línea cae sola.
            if ($orderId !== null) {
                DB::table('orders')->where('id', $orderId)->delete();
            }
        }
        DB::table('dependents')->where('user_id', $userId)->delete();
        DB::table('consents')->where('user_id', $userId)->delete();
        DB::table('audit_logs')->where('target_type', (new User)->getMorphClass())->where('target_id', $userId)->delete();
        if ($seed['created_version']) {
            DB::table('audit_logs')
                ->where('target_type', (new LegalDocumentVersion)->getMorphClass())
                ->where('target_id', $seed['version']->getKey())
                ->delete();
            DB::table('legal_document_versions')->where('id', $seed['version']->getKey())->delete();
        }
        DB::table('users')->where('id', $userId)->delete();
        File::deleteDirectory($resultsDir);
    }
}
