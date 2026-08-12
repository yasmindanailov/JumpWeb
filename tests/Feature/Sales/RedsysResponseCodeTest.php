<?php

namespace Tests\Feature\Sales;

use App\Support\RedsysResponseCode;
use Tests\TestCase;

/**
 * Audit #114 (2026-05-28) — Mapeo `Ds_Response` → motivo i18n al cliente.
 *
 * Estos tests blindan que:
 *  - Códigos comunes del Anexo 2 (manual Redsys) tienen su clave esperada.
 *  - Códigos NO mapeados caen a `default` (nunca exponemos número crudo al cliente).
 *  - `null`/string vacío → `default` (no rompe Blade).
 *  - El helper de conveniencia `reasonText()` devuelve un string traducido NO vacío.
 */
class RedsysResponseCodeTest extends TestCase
{
    public function test_known_card_codes_map_to_specific_reasons(): void
    {
        $this->assertSame('card_expired', RedsysResponseCode::reasonKey('0101'));
        $this->assertSame('card_expired', RedsysResponseCode::reasonKey('0191'));
        $this->assertSame('card_invalid', RedsysResponseCode::reasonKey('0125'));
        $this->assertSame('cvv_wrong', RedsysResponseCode::reasonKey('0129'));
        $this->assertSame('card_unsupported', RedsysResponseCode::reasonKey('0180'));
    }

    public function test_known_auth_and_bank_codes_map_correctly(): void
    {
        $this->assertSame('auth_failed', RedsysResponseCode::reasonKey('0184'));
        $this->assertSame('bank_denied', RedsysResponseCode::reasonKey('0190'));
        $this->assertSame('fraud_suspicion', RedsysResponseCode::reasonKey('0102'));
        $this->assertSame('fraud_suspicion', RedsysResponseCode::reasonKey('0202'));
        $this->assertSame('pin_attempts_exceeded', RedsysResponseCode::reasonKey('0106'));
    }

    public function test_user_cancelled_code_maps_to_user_cancelled(): void
    {
        $this->assertSame('user_cancelled', RedsysResponseCode::reasonKey('9915'));
    }

    public function test_system_codes_map_to_system_error(): void
    {
        $this->assertSame('system_error', RedsysResponseCode::reasonKey('0904'));
        $this->assertSame('system_error', RedsysResponseCode::reasonKey('0909'));
        $this->assertSame('system_error', RedsysResponseCode::reasonKey('0913'));
        $this->assertSame('system_error', RedsysResponseCode::reasonKey('0944'));
    }

    public function test_unknown_codes_fall_back_to_default(): void
    {
        // Cualquier código no listado en REASON_MAP debe degradar a 'default' — JAMÁS
        // exponer un número crudo al cliente (sería ruido sin valor).
        $this->assertSame('default', RedsysResponseCode::reasonKey('9999'));
        $this->assertSame('default', RedsysResponseCode::reasonKey('0500'));
        $this->assertSame('default', RedsysResponseCode::reasonKey('ABCD'));
    }

    public function test_null_or_empty_falls_back_to_default(): void
    {
        $this->assertSame('default', RedsysResponseCode::reasonKey(null));
        $this->assertSame('default', RedsysResponseCode::reasonKey(''));
    }

    public function test_reason_text_returns_translated_non_empty_string(): void
    {
        $text = RedsysResponseCode::reasonText('0101');
        $this->assertNotEmpty($text);
        $this->assertStringNotContainsString('payment_failed.reasons.', $text, 'Si sale el path i18n significa que falta la clave');

        // Default también debe estar traducido.
        $defaultText = RedsysResponseCode::reasonText(null);
        $this->assertNotEmpty($defaultText);
        $this->assertStringNotContainsString('payment_failed.reasons.', $defaultText);
    }
}
