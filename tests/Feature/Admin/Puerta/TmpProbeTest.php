<?php

namespace Tests\Feature\Admin\Puerta;

use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\Setting;
use App\Livewire\Admin\Puerta\ValidarRegistro;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TmpProbeTest extends TestCase
{
    use RefreshDatabase;

    public function test_probe(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        Setting::updateOrCreate(['key' => 'waiver.mode'], ['value' => 'interno', 'group' => 'waiver']);
        Setting::flushMemo();

        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', 'staff')->value('id')]);

        $html = Livewire::actingAs($u)->test(ValidarRegistro::class)->set('input', 'nadie@example.com')->call('search')->html();

        file_put_contents('/tmp/probe-notreg.html', $html);
        fwrite(STDERR, "\n--- not_registered ---\n");
        fwrite(STDERR, 'callout body class present: '.(str_contains($html, 'gate-callout__body') ? 'YES' : 'NO')."\n");
        fwrite(STDERR, 'query present: '.(str_contains($html, 'data-gate-query') ? 'YES' : 'NO')."\n");

        $html2 = Livewire::actingAs($u)->test(ValidarRegistro::class)->set('input', 'zzz')->call('search')->html();
        file_put_contents('/tmp/probe-invalid.html', $html2);
        fwrite(STDERR, "\n--- invalid_input ---\n");
        fwrite(STDERR, 'body class: '.(str_contains($html2, 'gate-callout__body') ? 'YES' : 'NO')."\n");
        fwrite(STDERR, 'invalid text: '.(str_contains($html2, (string) __('admin.puerta.validar.invalid_input')) ? 'YES' : 'NO')."\n");
        fwrite(STDERR, 'TEXT ES: '.__('admin.puerta.validar.invalid_input')."\n");
        fwrite(STDERR, substr(preg_replace('/\s+/', ' ', $html2), 0, 4000)."\n");

        $this->assertTrue(true);
    }
}
