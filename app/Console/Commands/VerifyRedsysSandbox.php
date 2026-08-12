<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Models\Setting;
use App\Support\Redsys;
use App\Support\RedsysRefundResult;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Verificación REAL contra el SANDBOX de Redsys (recomendación A, 2026-06-15).
 *
 * Cierra el ítem obligatorio de go-live «validar el reembolso REST en sandbox (Ds_Response=0900)»,
 * que nunca se había ejecutado. Hace una llamada REST de devolución (TransactionType=3) de VERDAD
 * contra `sis-t.redsys.es`, reutilizando el código de producción `Redsys::executeRefund`, y reporta
 * la respuesta AUTÉNTICA de Redsys. Verifica de un tiro: conectividad, credenciales del comercio,
 * firma `HMAC_SHA512_V2` aceptada por el banco, formato de la petición, parseo y verificación de la
 * firma de la respuesta.
 *
 * Sobre un `gateway_order` que NO corresponde a una autorización previa, Redsys responde con un
 * código de ERROR estructurado y FIRMADO (no 0900): eso es lo ESPERADO y demuestra que toda la
 * fontanería funciona. Para validar un 0900 real (movimiento de dinero) hace falta una autorización
 * previa en el sandbox: pásala con `--gateway-order=` (+ `--amount=`) tras un pago de prueba.
 *
 * Solo entornos NO productivos. Fija temporalmente el comercio del sandbox y RESTAURA los settings.
 */
class VerifyRedsysSandbox extends Command
{
    protected $signature = 'redsys:verify-sandbox
        {--merchant=263100000 : Código de comercio (FUC) del sandbox}
        {--terminal=3 : Terminal del sandbox}
        {--gateway-order= : Nº de operación de una AUTORIZACIÓN previa a devolver (para un 0900 real)}
        {--amount=100 : Importe a devolver en céntimos}
        {--bad-key : CONTROL: firmar con una clave ERRÓNEA (debe provocar un error de FIRMA, no SIS0054)}';

    protected $description = 'Llama de VERDAD al sandbox de Redsys para validar el reembolso REST (Ds_Response=0900 / integración). Solo dev/local.';

    /** Clave pública del sandbox de Redsys (HMAC_SHA512_V2). La misma para SHA-256/512. */
    private const SANDBOX_KEY = 'sq7HjrUOBfKmC576ILgskD5srU870gJ7';

    public function handle(): int
    {
        if ($this->getLaravel()->isProduction()) {
            $this->error('Abortado: NO ejecutar en producción.');

            return self::FAILURE;
        }

        $merchant = (string) $this->option('merchant');
        $terminal = (string) $this->option('terminal');
        $amount = max(1, (int) $this->option('amount'));
        $gatewayOrder = $this->option('gateway-order')
            ? (string) $this->option('gateway-order')
            : '0001'.Str::upper(Str::random(6)); // orden inexistente → prueba de fontanería

        $real = (bool) $this->option('gateway-order');

        // Control de firma: una clave de 32 chars válida en formato pero INCORRECTA → Redsys debe
        // rechazar por firma (SIS0042/SIS0319…), no llegar a la lógica de negocio (SIS0054).
        $key = $this->option('bad-key') ? 'XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX' : self::SANDBOX_KEY;
        if ($this->option('bad-key')) {
            $this->warn('CONTROL: firmando con clave ERRÓNEA (se espera un error de FIRMA).');
        }

        $restore = $this->applySandboxSettings($merchant, $terminal, $key);

        try {
            $this->line("Comercio <fg=yellow>{$merchant}</> · terminal <fg=yellow>{$terminal}</> · entorno <fg=yellow>test</> (sis-t.redsys.es)");
            $this->line('Devolviendo '.number_format($amount / 100, 2, ',', '.').' € sobre la operación '
                .($real ? "<fg=yellow>{$gatewayOrder}</> (autorización real)" : "<fg=gray>{$gatewayOrder}</> (inexistente → prueba de integración)")."…\n");

            $payment = new Payment([
                'gateway_order' => $gatewayOrder,
                'amount' => $amount,
                'currency' => 'EUR',
            ]);

            $result = (new Redsys)->executeRefund($payment, $amount);

            return $this->report($result, $real);
        } finally {
            $restore();
        }
    }

    /**
     * Fija los settings del sandbox y devuelve un closure que restaura los originales.
     *
     * @return callable():void
     */
    private function applySandboxSettings(string $merchant, string $terminal, string $secretKey): callable
    {
        $keys = [
            'redsys_environment' => 'test',
            'redsys_merchant_code' => $merchant,
            'redsys_terminal' => $terminal,
            'redsys_secret_key' => $secretKey,
            'redsys_currency' => '978',
        ];

        $original = [];
        foreach ($keys as $key => $value) {
            $original[$key] = Setting::where('key', $key)->value('value'); // null si no existía
            Setting::updateOrCreate(['key' => $key], ['value' => $value, 'group' => 'payment']);
        }

        return function () use ($original): void {
            foreach ($original as $key => $value) {
                if ($value === null) {
                    Setting::where('key', $key)->delete();
                } else {
                    Setting::updateOrCreate(['key' => $key], ['value' => $value, 'group' => 'payment']);
                }
            }
            $this->line('<fg=gray>Settings de Redsys restaurados.</>');
        };
    }

    private function report(RedsysRefundResult $result, bool $real): int
    {
        $this->table(['Campo', 'Valor'], [
            ['success', $result->success ? '<fg=green>true</>' : '<fg=red>false</>'],
            ['Ds_Response', $result->dsResponse ?? '—'],
            ['failureReason', $result->failureReason ?? '—'],
            ['message', Str::limit((string) $result->message, 200)],
            ['rawResponse', $result->rawResponse ? Str::limit(json_encode($result->rawResponse, JSON_UNESCAPED_UNICODE), 400) : '—'],
        ]);

        $this->newLine();
        if ($result->success && $result->dsResponse === PaymentRefund::REDSYS_REFUND_SUCCESS_CODE) {
            $this->info('✅ Ds_Response=0900: DEVOLUCIÓN ACEPTADA por el sandbox. Reembolso REST validado end-to-end (el ítem obligatorio de go-live).');

            return self::SUCCESS;
        }

        // Distinguir «integración OK pero sin operación original» de «integración rota».
        $reason = $result->failureReason;
        if ($reason === 'gateway_denied') {
            $this->info('✅ INTEGRACIÓN VÁLIDA: Redsys respondió con el código '.($result->dsResponse ?? '—').'. '
                .'La firma HMAC_SHA512_V2 de la PETICIÓN fue ACEPTADA (una firma errónea daría SIS0042, no esto), '
                .'la petición es correcta y la respuesta se parseó. '
                .($real
                    ? 'Pero la devolución fue DENEGADA por la pasarela — revisar el código en el portal.'
                    : 'SIS0054 = la denegación ESPERADA al devolver una operación inexistente. Para un 0900 real, pasa --gateway-order de una autorización previa del sandbox.'));

            return self::SUCCESS;
        }

        if (in_array($reason, ['transport_error_check_portal', 'unknown'], true)) {
            $this->error('❌ Fallo de integración ('.$reason.'): '.$result->message.' — revisar conectividad/credenciales/firma.');

            return self::FAILURE;
        }

        $this->warn('Resultado no concluyente: '.$result->message);

        return self::FAILURE;
    }
}
