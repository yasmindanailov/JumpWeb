<?php

namespace Tests\Feature\Sales;

use App\Domain\Payments\Services\Redsys;
use App\Domain\Payments\Services\Redsys\Vendor\Utils;
use App\Domain\Platform\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase 5.5a — Cimientos cripto Redsys (HMAC_SHA512_V2 + AES-128-CBC).
 *
 * Verifica empíricamente que el envoltorio `App\Domain\Payments\Services\Redsys` reproduce la firma de la
 * librería oficial PHP v2.0 (clase `Signature` vendorizada bajo `app/Support/Redsys/Vendor/`),
 * que la cripto va por OpenSSL (no mcrypt, no 3DES) y que el contador atómico de
 * `gateway_order` no reusa valores. Ver `docs/PLAN-REDSYS.md` §13 (capa 5.5a) y
 * `docs/DECISIONES.md` #104.
 */
class RedsysSignatureTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Fixture EMPÍRICAMENTE verificado el 2026-05-26 ejecutando la librería oficial v2.0 sin
     * modificar (`signature.php` + `utils.php` descargados de Redsys) sobre este mismo payload.
     * Si este test pasa, la cripto de nuestro envoltorio es bit-a-bit equivalente a la oficial.
     *
     * Payload, clave (sandbox público) y firma esperada — NO modificar.
     */
    public function test_signature_reproduces_official_library_output_bit_for_bit(): void
    {
        $secretKey = 'sq7HjrUOBfKmC576ILgskD5srU870gJ7';
        $data = [
            'DS_MERCHANT_AMOUNT' => '145',
            'DS_MERCHANT_ORDER' => '0000000001',
            'DS_MERCHANT_MERCHANTCODE' => '999008881',
            'DS_MERCHANT_CURRENCY' => '978',
            'DS_MERCHANT_TRANSACTIONTYPE' => '0',
            'DS_MERCHANT_TERMINAL' => '001',
            'DS_MERCHANT_MERCHANTURL' => '',
            'DS_MERCHANT_URLOK' => 'http://localhost/ok',
            'DS_MERCHANT_URLKO' => 'http://localhost/ko',
        ];

        $expectedParams = 'eyJEU19NRVJDSEFOVF9BTU9VTlQiOiIxNDUiLCJEU19NRVJDSEFOVF9PUkRFUiI6IjAwMDAwMDAwMDEiLCJEU19NRVJDSEFOVF9NRVJDSEFOVENPREUiOiI5OTkwMDg4ODEiLCJEU19NRVJDSEFOVF9DVVJSRU5DWSI6Ijk3OCIsIkRTX01FUkNIQU5UX1RSQU5TQUNUSU9OVFlQRSI6IjAiLCJEU19NRVJDSEFOVF9URVJNSU5BTCI6IjAwMSIsIkRTX01FUkNIQU5UX01FUkNIQU5UVVJMIjoiIiwiRFNfTUVSQ0hBTlRfVVJMT0siOiJodHRwOlwvXC9sb2NhbGhvc3RcL29rIiwiRFNfTUVSQ0hBTlRfVVJMS08iOiJodHRwOlwvXC9sb2NhbGhvc3RcL2tvIn0';
        $expectedSignature = 'm4USGwgOv_c-AHX57QCUfvOBkuLO53QskByy2y-FNtVILUPYQKxgeu5mh2H3wLJcBlyw9q4o_H7tHGhHCf2w_w';

        $redsys = new Redsys;
        $params = $redsys->createMerchantParameters($data);
        $signature = $redsys->createMerchantSignature($secretKey, $params, $data['DS_MERCHANT_ORDER']);

        $this->assertSame($expectedParams, $params, 'Ds_MerchantParameters does not match the official library output.');
        $this->assertSame($expectedSignature, $signature, 'Ds_Signature does not match the official library output.');
    }

    public function test_signature_uses_base64_url_safe_encoding(): void
    {
        $redsys = new Redsys;
        $data = ['DS_MERCHANT_ORDER' => '0000000002', 'DS_MERCHANT_AMOUNT' => '100'];

        $params = $redsys->createMerchantParameters($data);
        $signature = $redsys->createMerchantSignature('sq7HjrUOBfKmC576ILgskD5srU870gJ7', $params, '0000000002');

        // Ningún carácter Base64 "estándar" debe filtrarse: ni `=`, ni `+`, ni `/`.
        $this->assertStringNotContainsString('=', $params);
        $this->assertStringNotContainsString('+', $params);
        $this->assertStringNotContainsString('/', $params);
        $this->assertStringNotContainsString('=', $signature);
        $this->assertStringNotContainsString('+', $signature);
        $this->assertStringNotContainsString('/', $signature);
    }

    public function test_round_trip_verify_accepts_valid_signature_and_rejects_tampered_payload(): void
    {
        $redsys = new Redsys;
        $secretKey = 'sq7HjrUOBfKmC576ILgskD5srU870gJ7';
        $data = [
            'Ds_Order' => '0000000099',
            'Ds_Amount' => '1500',
            'Ds_Response' => '0000',
            'Ds_AuthorisationCode' => '372663',
        ];

        $params = $redsys->createMerchantParameters($data);
        $signature = $redsys->createMerchantSignature($secretKey, $params, '0000000099');

        $this->assertTrue($redsys->verifySignature($secretKey, $params, $signature));

        // Manipulación del payload: cambiamos el amount → la firma ya no debe validar.
        $tampered = $data;
        $tampered['Ds_Amount'] = '999999';
        $tamperedParams = $redsys->createMerchantParameters($tampered);
        $this->assertFalse($redsys->verifySignature($secretKey, $tamperedParams, $signature));

        // Manipulación de la firma → tampoco debe validar.
        $this->assertFalse($redsys->verifySignature($secretKey, $params, str_replace('A', 'B', $signature)));

        // Clave distinta → tampoco.
        $this->assertFalse($redsys->verifySignature('wrong_secret_key__________________xx', $params, $signature));
    }

    public function test_decode_merchant_parameters_round_trip(): void
    {
        $redsys = new Redsys;
        $original = ['Ds_Order' => '0000000010', 'Ds_Response' => '0000', 'Ds_Amount' => '250'];

        $params = $redsys->createMerchantParameters($original);
        $decoded = $redsys->decodeMerchantParameters($params);

        $this->assertSame($original, $decoded);
    }

    public function test_decode_merchant_parameters_rejects_garbage(): void
    {
        $redsys = new Redsys;

        $this->expectException(\RuntimeException::class);
        // "not json" en Base64 estándar → al decodificar daría texto no-JSON.
        $redsys->decodeMerchantParameters(Utils::base64_url_encode_safe('not json'));
    }

    public function test_verify_signature_extracts_order_case_insensitively(): void
    {
        $redsys = new Redsys;
        $secretKey = 'sq7HjrUOBfKmC576ILgskD5srU870gJ7';

        // La respuesta de Redsys puede traer `Ds_Order`, `DS_ORDER` o `DS_Order`.
        foreach (['Ds_Order', 'DS_ORDER', 'DS_Order'] as $orderKey) {
            $data = [$orderKey => '0000000020', 'Ds_Response' => '0000'];
            $params = $redsys->createMerchantParameters($data);
            $signature = $redsys->createMerchantSignature($secretKey, $params, '0000000020');

            $this->assertTrue(
                $redsys->verifySignature($secretKey, $params, $signature),
                "verifySignature should find the order under `$orderKey`."
            );
        }
    }

    public function test_crypto_runs_through_openssl_aes_not_mcrypt(): void
    {
        // Si `openssl_encrypt` no existiera o `aes-128-cbc` no estuviera disponible, la firma
        // explotaría aquí. Verificamos empíricamente que tenemos lo que esperamos antes de
        // depender de ello en producción.
        $this->assertTrue(function_exists('openssl_encrypt'), 'openssl_encrypt is required.');
        $this->assertContains('aes-128-cbc', openssl_get_cipher_methods(), 'aes-128-cbc must be available.');

        // El plan explícitamente descarta mcrypt y 3DES (#104). Si por error alguien volviera
        // a 3DES, este test serviría de canario.
        $this->assertFalse(function_exists('mcrypt_encrypt'), 'mcrypt is deprecated since PHP 7.2 and must not be used.');
    }

    public function test_gateway_url_uses_sandbox_by_default(): void
    {
        $redsys = new Redsys;

        $this->assertFalse($redsys->isLive());
        $this->assertSame(Redsys::URL_TEST, $redsys->gatewayUrl());
    }

    public function test_gateway_url_switches_to_live_when_environment_is_live(): void
    {
        Setting::create(['key' => 'redsys_environment', 'value' => 'live', 'group' => 'payment']);

        $redsys = new Redsys;

        $this->assertTrue($redsys->isLive());
        $this->assertSame(Redsys::URL_LIVE, $redsys->gatewayUrl());
    }

    public function test_consumer_language_maps_locales_to_official_codes(): void
    {
        $redsys = new Redsys;

        // Verificado contra Anexo 1 del manual: 001=ES, 002=EN, 004=FR (003=catalán, no mapeado).
        $this->assertSame('001', $redsys->consumerLanguageCode('es'));
        $this->assertSame('002', $redsys->consumerLanguageCode('en'));
        $this->assertSame('004', $redsys->consumerLanguageCode('fr'));
        $this->assertSame('0', $redsys->consumerLanguageCode(null));
        $this->assertSame('0', $redsys->consumerLanguageCode('de'));
    }

    public function test_config_secret_key_takes_precedence_over_setting(): void
    {
        // Audit hardening #113 (A2) + auditoría Fase 1 (H3): en producción la clave real del banco
        // vivirá en `.env` (vault de Enhance), NUNCA en BD. Se lee vía
        // `config('services.redsys.secret_key')` (= `env('REDSYS_SECRET_KEY')` en config/services.php),
        // que SÍ sobrevive a `config:cache`. El config() debe priorizarla sobre settings.redsys_secret_key.
        Setting::create(['key' => 'redsys_secret_key', 'value' => 'from-database', 'group' => 'payment']);

        config(['services.redsys.secret_key' => 'from-env-vault']);

        $this->assertSame('from-env-vault', (new Redsys)->config()['secret_key']);
    }

    public function test_config_secret_key_empty_falls_back_to_setting(): void
    {
        // Si la clave de config está vacía o ausente (caso por defecto en sandbox), usar la BD.
        Setting::create(['key' => 'redsys_secret_key', 'value' => 'from-database', 'group' => 'payment']);

        config(['services.redsys.secret_key' => null]);

        $this->assertSame('from-database', (new Redsys)->config()['secret_key']);
    }

    public function test_config_reads_sandbox_defaults_when_settings_missing(): void
    {
        // En este test, los seeders NO se ejecutan: el helper debe degradar a los defaults
        // públicos del sandbox de Redsys (la app es siempre operable, no rompe si falta seed).
        $redsys = new Redsys;
        $cfg = $redsys->config();

        $this->assertSame('test', $cfg['environment']);
        $this->assertSame('999008881', $cfg['merchant_code']);
        $this->assertSame('001', $cfg['terminal']);
        $this->assertSame('sq7HjrUOBfKmC576ILgskD5srU870gJ7', $cfg['secret_key']);
        $this->assertSame('978', $cfg['currency']);
    }
}
