<?php

namespace Tests\Feature\Fiesta;

use App\Domain\Booking\Services\PartyInvitations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MountsAParty;
use Tests\TestCase;

/**
 * EL CONTRATO DEL MODELO de página de la fiesta (`specs/fiesta-sistema-nuevo.md` §4.1, T1b): el banco
 * (`scripts/banco-fiesta/modelos.php`, los datos del diseño mapeados a mano) y el controlador (el presentador
 * `App\Http\Fiesta\*` con una fiesta de verdad) producen la MISMA forma: las mismas claves, anidadas igual, con el
 * mismo tipo. Si un presentador renombra una clave, o el banco pinta con una que el controlador no da, se dice aquí
 * antes de que el juez de píxeles compare dos páginas que no hablan de lo mismo.
 *
 * ⚠️ Lo que se compara es la FORMA, no los valores: el banco lleva la fiesta de Vera y el test la de `MountsAParty`.
 *    Un `null` a un lado vale por cualquier tipo al otro (`techo`, `reply_id`, `listo`…: son nulos por estado); una
 *    lista vacía a un lado no dice nada de la forma de sus elementos (los extras y los avisos de una fiesta recién
 *    montada); una lista con elementos se compara por su primero. Los MAPAS cuyas claves son DATO del pack (los
 *    rótulos de las columnas, las columnas extra de una fila) se comparan solo como mapas.
 */
class FiestaModeloTest extends TestCase
{
    use MountsAParty;
    use RefreshDatabase;

    /** Las rutas (por su cola) cuyas claves son dato, no forma. */
    private const MAPAS = ['.columnas.labels', '.ninos[0].extra'];

    public function test_the_list_model_of_the_bank_has_the_shape_the_controller_produces(): void
    {
        ['reservation' => $reservation, 'invitation' => $invitation, 'host' => $host] = $this->mountParty();
        $this->replyOf($invitation, $reservation, 'Hugo Ruiz');
        $modelos = $this->modelos();

        $pagina = $this->actingAs($host)->get(route('reservation.guests', ['reservation' => $reservation]))->assertOk()->viewData('m');

        $this->assertIsArray($pagina);
        $this->assertMismaForma($pagina, $modelos['lista']('guardado'), 'lista(guardado)');
        $this->assertMismaForma($pagina, $modelos['lista']('recien'), 'lista(recien)');
    }

    public function test_the_invitation_model_of_the_bank_has_the_shape_the_controller_produces(): void
    {
        ['reservation' => $reservation, 'invitation' => $invitation] = $this->mountParty();
        $this->replyOf($invitation, $reservation, 'Hugo Ruiz');
        $modelos = $this->modelos();

        $pagina = $this->get(route(PartyInvitations::PUBLIC_ROUTE, ['token' => $invitation->token]))->assertOk()->viewData('m');

        $this->assertIsArray($pagina);
        $this->assertMismaForma($pagina, $modelos['invitacion'](true), 'invitacion(viva)');
        $this->assertMismaForma($pagina, $modelos['invitacion'](false), 'invitacion(cerrada)');
    }

    public function test_the_authorization_model_of_the_bank_has_the_shape_the_controller_produces(): void
    {
        ['reservation' => $reservation, 'document' => $document] = $this->mountParty();
        $modelos = $this->modelos();

        // La ÚNICA clave que el banco añade y el controlador no: la marca de diagnóstico (la página viva nunca la lleva).
        $sinDiagnostico = static fn (array $m): array => array_diff_key($m, ['diagnostico' => true]);

        $enReposo = $this->get($reservation->guardianAuthorizationSignedUrl())->assertOk()->viewData('m');
        $this->assertIsArray($enReposo);
        $this->assertMismaForma($enReposo, $sinDiagnostico($modelos['autorizacion']('recibo', false)), 'autorizacion(recibo)');

        // Firmada, el Listo del brief: el modelo lleva `listo` y ningún formulario, como el banco en `firmada`.
        $vuelta = $this->post($this->signedAuthorizationStoreUrl($reservation), $this->authorizationPayload($document, ['guardian_surname' => '']));
        $vuelta->assertRedirect()->assertSessionHas('guardian_status', 'signed');
        $firmada = $this->get((string) $vuelta->headers->get('Location'))->assertOk()->viewData('m');
        $this->assertIsArray($firmada);
        $this->assertIsArray($firmada['listo'], 'firmada, la página lleva el Listo');
        $this->assertMismaForma($firmada, $sinDiagnostico($modelos['autorizacion']('firmada', false)), 'autorizacion(firmada)');
    }

    /** @return array{lista: callable(string): array<string, mixed>, invitacion: callable(bool): array<string, mixed>, autorizacion: callable(string, bool): array<string, mixed>} */
    private function modelos(): array
    {
        return require base_path('scripts/banco-fiesta/modelos.php');
    }

    /**
     * @param  array<string, mixed>  $pagina
     * @param  array<string, mixed>  $banco
     */
    private function assertMismaForma(array $pagina, array $banco, string $ruta): void
    {
        $diferencias = [];
        $this->compara($pagina, $banco, $ruta, $diferencias);

        $this->assertSame([], $diferencias, "el banco y el controlador no producen la misma forma en {$ruta}:\n - ".implode("\n - ", $diferencias));
    }

    /**
     * @param  array<string, mixed>  $pagina
     * @param  array<string, mixed>  $banco
     * @param  list<string>  $diferencias
     */
    private function compara(array $pagina, array $banco, string $ruta, array &$diferencias): void
    {
        foreach (array_diff(array_keys($pagina), array_keys($banco)) as $clave) {
            $diferencias[] = "{$ruta}.{$clave}: la da el controlador y el banco no";
        }
        foreach (array_diff(array_keys($banco), array_keys($pagina)) as $clave) {
            $diferencias[] = "{$ruta}.{$clave}: la pinta el banco y el controlador no la da";
        }
        foreach (array_intersect_key($pagina, $banco) as $clave => $p) {
            $b = $banco[$clave];
            $aqui = "{$ruta}.{$clave}";
            if ($p === null || $b === null) {
                continue;
            }
            if (is_array($p) !== is_array($b)) {
                $diferencias[] = "{$aqui}: ".gettype($p).' en el controlador, '.gettype($b).' en el banco';

                continue;
            }
            if (! is_array($p)) {
                if (gettype($p) !== gettype($b)) {
                    $diferencias[] = "{$aqui}: ".gettype($p).' en el controlador, '.gettype($b).' en el banco';
                }

                continue;
            }
            if ($p === [] || $b === []) {
                continue;
            }
            if (array_filter(self::MAPAS, static fn (string $cola): bool => str_ends_with($aqui, $cola)) !== []) {
                continue;
            }
            /** @var array<string, mixed> $b */
            if (array_is_list($p) && array_is_list($b)) {
                if (is_array($p[0]) && is_array($b[0])) {
                    $this->compara($p[0], $b[0], "{$aqui}[0]", $diferencias);
                } elseif (is_array($p[0]) !== is_array($b[0])) {
                    $diferencias[] = "{$aqui}[0]: ".gettype($p[0]).' en el controlador, '.gettype($b[0]).' en el banco';
                }

                continue;
            }
            if (array_is_list($p) !== array_is_list($b)) {
                $diferencias[] = "{$aqui}: lista en un lado y diccionario en el otro";

                continue;
            }
            $this->compara($p, $b, $aqui, $diferencias);
        }
    }
}
