<?php

namespace Tests\Feature\Puerta;

use App\Domain\Identity\Models\CustomerCard;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\CardToken;
use App\Domain\Identity\Services\CustomerCards;
use App\Domain\Platform\Models\AuditLog;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Encryption\Encrypter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase 6 · subsistema A — el CARNÉ QR (`docs/specs/identidad-qr-puerta.md` §4.1–§4.5, §8.1, §8.2,
 * §9.2 A·1/A·2): su forma, su emisión única, su rotación, su entrada en `revokeAllAccess()` (`RGPD-06`)
 * y su degradación con la clave rotada.
 */
class CustomerCardTest extends TestCase
{
    use RefreshDatabase;

    private function cards(): CustomerCards
    {
        return app(CustomerCards::class);
    }

    // ─── La forma (§4.3, §8.2, `#208`) ────────────────────────────────────────

    public function test_a_token_has_twenty_crockford_characters_with_prefix_and_check_char(): void
    {
        $seen = [];
        for ($i = 0; $i < 500; $i++) {
            $token = CardToken::generate();
            $this->assertSame(20, strlen($token));
            $this->assertStringStartsWith('JW', $token);
            $this->assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{20}$/', $token, 'Crockford: sin I, L, O ni U');
            $this->assertTrue(CardToken::isWellFormed($token));
            $seen[$token] = true;
        }
        $this->assertCount(500, $seen, 'quinientos carnés, quinientos distintos');
    }

    public function test_the_check_char_catches_a_typo_and_a_transposition(): void
    {
        $token = CardToken::generate();
        $body = substr($token, 0, 19);

        // Un carácter cambiado.
        $pos = 7;
        $other = $token[$pos] === 'A' ? 'B' : 'A';
        $typo = substr_replace($token, $other, $pos, 1);
        $this->assertFalse(CardToken::isWellFormed($typo));

        // Dos caracteres adyacentes distintos, intercambiados.
        for ($i = 2; $i < 18; $i++) {
            if ($body[$i] !== $body[$i + 1]) {
                $swapped = $body;
                $swapped[$i] = $body[$i + 1];
                $swapped[$i + 1] = $body[$i];
                $this->assertFalse(CardToken::isWellFormed($swapped.$token[19]), "transposición en {$i} no detectada");
                break;
            }
        }

        $this->assertFalse(CardToken::isWellFormed('JW'.str_repeat('A', 18)), 'sin control válido');
        $this->assertFalse(CardToken::isWellFormed('XX'.substr($token, 2)), 'sin el prefijo');
        $this->assertFalse(CardToken::isWellFormed(substr($token, 0, 19)), 'corto');
    }

    public function test_normalization_forgives_what_crockford_forgives(): void
    {
        $token = CardToken::generate();
        $dictated = strtolower(' '.substr($token, 0, 4).'-'.substr($token, 4, 8).' '.substr($token, 12).' ');
        $this->assertSame($token, CardToken::normalize($dictated));

        // I y L se leen como 1; O como 0 — solo si el token real llevaba 1 y 0 ahí.
        $withDigits = 'JW10'.substr(CardToken::generate(), 4, 15);
        $withDigits = substr($withDigits, 0, 19).CardToken::checksum(substr($withDigits, 0, 19));
        $this->assertTrue(CardToken::isWellFormed($withDigits));
        $this->assertSame($withDigits, CardToken::normalize(str_replace(['1', '0'], ['l', 'O'], substr($withDigits, 0, 4)).substr($withDigits, 4)));
    }

    // ─── Emisión y rotación (§4.5, A·1, A·8) ──────────────────────────────────

    public function test_ensure_issues_one_active_card_and_then_returns_the_same_one(): void
    {
        $user = User::factory()->create();

        $first = $this->cards()->ensureFor($user);
        $again = $this->cards()->ensureFor($user);

        $this->assertTrue($first->is($again));
        $this->assertSame(1, CustomerCard::count());
        $this->assertNull($first->revoked_at);
        $this->assertSame(1, AuditLog::where('action', 'cards.issued')->count());
        $this->assertSame(CardToken::hash((string) $first->plainToken()), $first->token_hash);
        $this->assertTrue(CardToken::isWellFormed((string) $first->plainToken()));
        $this->assertStringNotContainsString((string) $first->plainToken(), json_encode(AuditLog::all()), 'RGPD-02: el token no se audita');
    }

    public function test_rotating_kills_the_old_card_in_the_act_and_issues_a_new_one(): void
    {
        $user = User::factory()->create();
        $old = $this->cards()->ensureFor($user);
        $oldToken = (string) $old->plainToken();

        $new = $this->cards()->rotate($user);

        $this->assertFalse($old->is($new));
        $this->assertTrue($old->fresh()->isRevoked());
        $this->assertSame(CustomerCard::REASON_ROTATED, $old->fresh()->revoked_reason);
        $this->assertTrue($this->cards()->activeFor($user)->is($new));
        $this->assertSame(1, CustomerCard::active()->where('user_id', $user->id)->count(), 'uno ACTIVO por titular');

        // El viejo SIGUE ENCONTRÁNDOSE, pero revocado: la puerta puede decir «carné caducado».
        $found = $this->cards()->findByToken($oldToken);
        $this->assertNotNull($found);
        $this->assertTrue($found->isRevoked());
        $this->assertSame($user->id, $found->user->id);
        $this->assertSame(1, AuditLog::where('action', 'cards.rotated')->count());
        $this->assertSame(2, AuditLog::where('action', 'cards.issued')->count());
    }

    public function test_find_by_token_normalizes_and_never_queries_for_garbage(): void
    {
        $user = User::factory()->create();
        $card = $this->cards()->ensureFor($user);
        $token = (string) $card->plainToken();

        $this->assertTrue($card->is($this->cards()->findByToken(strtolower($token))));
        $this->assertTrue($card->is($this->cards()->findByToken(substr($token, 0, 10).' '.substr($token, 10))));
        $this->assertNull($this->cards()->findByToken('cliente@example.com'));
        $this->assertNull($this->cards()->findByToken('JW'.str_repeat('7', 18)), 'forma de carné, control incorrecto');
        $this->assertNull($this->cards()->findByToken(CardToken::generate()), 'bien formado pero inexistente');
    }

    public function test_the_card_never_serializes_its_token(): void
    {
        $card = $this->cards()->ensureFor(User::factory()->create());

        $array = $card->toArray();
        $this->assertArrayNotHasKey('token', $array);
        $this->assertArrayNotHasKey('token_hash', $array);
        $this->assertStringNotContainsString((string) $card->plainToken(), $card->toJson());
    }

    // ─── RGPD-06: la revocación tiene UN sitio (§4.4, A·2) ────────────────────

    public function test_revoke_all_access_revokes_the_active_card(): void
    {
        $user = User::factory()->create();
        $card = $this->cards()->ensureFor($user);

        $user->revokeAllAccess();

        $this->assertTrue($card->fresh()->isRevoked());
        $this->assertSame(CustomerCard::REASON_REVOKED, $card->fresh()->revoked_reason);
        $this->assertNull($this->cards()->activeFor($user));
        $revoked = AuditLog::where('action', 'cards.revoked')->sole();
        $this->assertSame(['count' => 1, 'reason' => 'revoked'], $revoked->payload);

        // Sin carné activo no hay nada que auditar.
        $user->revokeAllAccess();
        $this->assertSame(1, AuditLog::where('action', 'cards.revoked')->count());
    }

    public function test_anonymizing_revokes_the_card_with_its_own_reason(): void
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->roles()->sync([Role::where('name', 'customer')->value('id')]);
        $card = $this->cards()->ensureFor($user);

        $this->assertTrue($user->anonymize());

        $this->assertTrue($card->fresh()->isRevoked());
        $this->assertSame(CustomerCard::REASON_ANONYMIZED, $card->fresh()->revoked_reason);
    }

    public function test_revoking_other_access_keeps_the_card(): void
    {
        $user = User::factory()->create();
        $card = $this->cards()->ensureFor($user);

        $user->revokeOtherAccess();

        $this->assertFalse($card->fresh()->isRevoked(), 'cambiar la contraseña no mata el carné impreso en casa');
    }

    // ─── §8.1: la clave rotada degrada, no rompe ──────────────────────────────

    public function test_plain_token_is_null_after_an_app_key_rotation_and_the_lookup_still_works(): void
    {
        $user = User::factory()->create();
        $card = $this->cards()->ensureFor($user);
        $token = (string) $card->plainToken();

        Model::encryptUsing(new Encrypter(Encrypter::generateKey('AES-256-CBC'), 'AES-256-CBC'));
        try {
            $fresh = CustomerCard::query()->findOrFail($card->id);
            $this->assertNull($fresh->plainToken(), 'con otra clave no se puede repintar — y no se lanza');
            $this->assertSame([], array_intersect_key($fresh->toArray(), ['token' => 1]), 'ni al serializar');
            $this->assertTrue($card->is($this->cards()->findByToken($token)), 'el ESCANEO sigue funcionando: busca por el hash');
        } finally {
            Model::encryptUsing(null);
        }
    }
}
