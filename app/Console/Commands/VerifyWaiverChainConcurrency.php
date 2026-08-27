<?php

namespace App\Console\Commands;

use App\Domain\Identity\Models\Dependent;
use App\Domain\Identity\Models\LegalDocumentVersion;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\WaiverSignature;
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
 * N procesos reales (`pcntl_fork`) firman a la vez para el MISMO titular y el MISMO sujeto contra
 * MySQL, partiendo de CERO firmas de ese sujeto. El invariante desde `DECISIONES #197` (cadena por
 * (titular, sujeto)): **UNA sola fila** — la primera firma entra y las N-1 restantes la encuentran bajo
 * el lock y la devuelven (idempotencia por versión). Si el lock de `WaiverSigner` no serializa, todas
 * leen «no hay firma», todas insertan, y la cadena del sujeto nace BIFURCADA: N filas con el mismo
 * `prev_hash` nulo. No falla, no avisa, y una cadena bifurcada no prueba nada.
 *
 * ⚠️ Hasta `#197` la cadena era por titular y cada proceso firmaba como un menor distinto para medir
 * la linealidad de una cadena de N filas; con cadenas por sujeto eso serían N cadenas de una fila y
 * el instrumento no cazaría nada. La propiedad que el lock protege ahora es la idempotencia del mismo
 * sujeto, y es la que se mide. La sonda EN SERIE firma antes en nombre de un menor real (fila en
 * `dependents`, FK RESTRICT) para comprobar que el escenario firma y que la cadena del menor queda
 * aparte de la del titular.
 *
 * ⚠️ Un verde solo vale si el instrumento se ha visto FALLAR (`#147`): con el `lockForUpdate()`
 * retirado del firmador, este comando tiene que cazar la bifurcación. Solo dev/local.
 */
class VerifyWaiverChainConcurrency extends Command
{
    protected $signature = 'waiver:verify-chain
        {--workers=8 : Nº de firmas concurrentes (procesos) del MISMO titular y sujeto}
        {--keep : No borrar los datos de prueba al terminar}';

    protected $description = 'Verifica empíricamente (fork real + MySQL InnoDB) que N firmas simultáneas del mismo titular y sujeto producen UNA sola fila (idempotencia bajo el lock) y cadenas lineales por sujeto. Solo dev/local.';

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
        $driver = DB::connection()->getDriverName();
        if ($driver !== 'mysql') {
            $this->warn("⚠ Conexión '{$driver}': SQLite NO reproduce los locks de InnoDB. Ejecuta contra MySQL para una prueba VÁLIDA.");
        }

        $workers = max(2, (int) $this->option('workers'));
        $resultsDir = storage_path('app/waiver-chain-concurrency');
        File::ensureDirectoryExists($resultsDir);
        File::cleanDirectory($resultsDir);

        $seed = $this->seed();
        $this->line("Titular #{$seed['user']->getKey()} · menor a cargo #{$seed['dependent']->getKey()} · versión firmable v{$seed['version']->version}·{$seed['version']->locale} (#{$seed['version']->getKey()}).");

        // La guarda del instrumento: una firma EN SERIE tiene que funcionar. Si no, lo que fallara
        // abajo no sería la carrera, y el verificador estaría midiendo otra cosa. Se firma en nombre
        // del MENOR: así la cadena del titular parte de cero para la carrera, y la del menor queda
        // aparte para comprobar que las dos verifican por separado.
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

        $this->line("Disparando <fg=yellow>{$workers}</> firmas <options=bold>CONCURRENTES</> del mismo titular y sujeto sobre {$driver}…");

        try {
            $this->forkWorkers($seed, $workers, microtime(true) + 0.5, $resultsDir);

            DB::reconnect();
            $verdict = $this->evaluate($seed, $workers, $resultsDir);
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
     * Un titular desechable con un menor a cargo, y la versión vigente del waiver; si no hay ninguna
     * publicada, se publica una de prueba (y se retira al limpiar).
     *
     * @return array{user:User, dependent:Dependent, version:LegalDocumentVersion, created_version:bool}
     */
    private function seed(): array
    {
        return DB::transaction(function (): array {
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

            return ['user' => $user, 'dependent' => $dependent, 'version' => $version, 'created_version' => $created];
        });
    }

    /**
     * @param  array{user:User, version:LegalDocumentVersion}  $seed
     */
    private function forkWorkers(array $seed, int $workers, float $startAt, string $resultsDir): void
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
                    // Todos como el TITULAR, misma versión: la propiedad es que solo UNO escriba.
                    $signature = app(WaiverSigner::class)->sign($holder, $version, WaiverSignatureRequest::api('127.0.0.1', "waiver:verify-chain/{$i}"));
                    $outcome = 'signed:'.$signature->getKey();
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
     * @param  array{user:User, dependent:Dependent, version:LegalDocumentVersion}  $seed
     */
    private function evaluate(array $seed, int $workers, string $resultsDir): bool
    {
        $outcomes = collect(File::files($resultsDir))
            ->map(fn ($file): string => trim(File::get($file->getPathname())));
        $signed = $outcomes->filter(fn (string $o): bool => str_starts_with($o, 'signed:'));
        $errors = $outcomes->reject(fn (string $o): bool => str_starts_with($o, 'signed:'));
        $distinctIds = $signed->map(fn (string $o): string => mb_substr($o, 7))->unique();

        $rows = WaiverSignature::query()->where('user_id', $seed['user']->getKey())->orderBy('id')->get();
        $holderRows = $rows->where('subject_type', WaiverSignature::SUBJECT_HOLDER);
        $dependentRows = $rows->where('subject_type', WaiverSignature::SUBJECT_DEPENDENT);
        $chain = WaiverChain::verify(User::findOrFail($seed['user']->getKey()));
        // Dos filas del MISMO sujeto con el mismo prev_hash = bifurcación.
        $forks = $rows->groupBy(fn (WaiverSignature $r): string => $r->subject_type.':'.$r->subject_id)
            ->sum(fn ($group): int => $group->count() - $group->pluck('prev_hash')->unique()->count());

        $this->newLine();
        $this->line('<options=bold>Resultados de las firmas concurrentes:</>');
        $this->line('  '.$signed->count().'× devuelven una firma · ids distintos: '.$distinctIds->count());
        if ($errors->isNotEmpty()) {
            $this->line('  <fg=red>'.$errors->count().'× error inesperado</>');
            $errors->each(fn (string $e) => $this->line('     '.$e));
        }
        $this->line("  filas del titular: {$holderRows->count()} (esperada 1) · del menor: {$dependentRows->count()} (esperada 1) · cadenas: {$chain['chains']} (esperadas 2) · prev_hash repetidos por sujeto: {$forks} · verificación: ".($chain['ok'] ? 'OK' : 'ROTA'));
        foreach ($chain['problems'] as $problem) {
            $this->line('     <fg=red>'.$problem.'</>');
        }

        $ok = $signed->count() === $workers
            && $distinctIds->count() === 1
            && $holderRows->count() === 1
            && $dependentRows->count() === 1
            && $chain['chains'] === 2
            && $forks === 0
            && $chain['ok'];
        $this->newLine();
        if ($ok) {
            $this->info("✅ PASA: bajo {$workers} firmas concurrentes del mismo titular y sujeto hay UNA sola fila —idempotencia bajo el lock— y las dos cadenas (titular y menor) verifican. Verificado sobre InnoDB real.");
        } else {
            $this->error('❌ FALLA: firmas duplicadas o cadena rota. Revisar que el lockForUpdate() de la fila del titular sea la PRIMERA sentencia de la transacción de WaiverSigner.');
        }

        return $ok;
    }

    /**
     * Borrado por `DB::table`: las tablas son append-only (o rechazan borrar con referencias) y sus
     * modelos rechazan `delete()`. Esta limpieza y la de go-live (`PurgeCustomerData`) son las únicas
     * que lo hacen, y solo sobre datos que este propio comando creó. Orden: firmas → menor → titular.
     *
     * @param  array{user:User, dependent:Dependent, version:LegalDocumentVersion, created_version:bool}  $seed
     */
    private function cleanup(array $seed, string $resultsDir): void
    {
        $userId = $seed['user']->getKey();
        DB::table('waiver_signatures')->where('user_id', $userId)->delete();
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
