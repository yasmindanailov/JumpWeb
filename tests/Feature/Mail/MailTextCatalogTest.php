<?php

namespace Tests\Feature\Mail;

use App\Domain\Content\Services\MailTextRules;
use App\Domain\Platform\Services\Analytics\EmailUtm;
use App\Notifications\Support\MailSituations;
use App\Notifications\Support\MailTextCatalog;
use Illuminate\Support\Facades\Lang;
use Tests\TestCase;

/**
 * EL CATÁLOGO DE LO EDITABLE (R1·T de `specs/correos-rediseno.md` §4.2.1, `#802`): que diga la verdad sobre los correos.
 * Una clave editable que el correo no pinta sería un bloque fantasma en el panel; una que también usa la web cambiaría la
 * web desde un correo; un correo nuevo al cliente fuera del catálogo quedaría sin editar sin que nadie lo decidiera.
 */
class MailTextCatalogTest extends TestCase
{
    /** Los correos al EQUIPO, fuera del catálogo a propósito (no los lee un cliente). */
    private const DEL_EQUIPO = ['google_business_location_changed', 'panel_password_link'];

    public function test_each_mail_is_known_by_the_key_of_its_sent_emails(): void
    {
        foreach (MailTextCatalog::CORREOS as $correo => [$clase, $tipo, $tramos]) {
            $this->assertSame(EmailUtm::keyOf($clase), $correo, 'la clave del catálogo es la de «Correos enviados»');
            $this->assertContains($tipo, MailTextCatalog::TIPOS);
            $this->assertNotSame([], MailTextCatalog::claves($correo), "{$correo}: sin nada que editar");
        }
    }

    public function test_every_notification_to_a_customer_is_in_the_catalog_or_declared_for_the_team(): void
    {
        foreach (EmailUtm::keys() as $clave) {
            $this->assertTrue(
                MailTextCatalog::existe($clave) || in_array($clave, self::DEL_EQUIPO, true),
                "«{$clave}» es un correo nuevo: al catálogo (sus textos, editables) o a DEL_EQUIPO, decidido y no por olvido",
            );
        }
    }

    public function test_every_editable_text_is_painted_by_its_mail_and_by_nothing_else(): void
    {
        $fuentes = $this->fuentesDelProducto();
        // El catálogo NOMBRA sus tramos (y un tramo puede ser un texto), y `MailSituations` la condición de cada texto (R1·T2):
        // declararla no es usarla.
        unset($fuentes[(string) realpath((string) (new \ReflectionClass(MailTextCatalog::class))->getFileName())]);
        unset($fuentes[(string) realpath((string) (new \ReflectionClass(MailSituations::class))->getFileName())]);
        $fantasmas = [];
        $compartidas = [];
        $total = 0;
        foreach (array_keys(MailTextCatalog::CORREOS) as $correo) {
            // Lo SUYO: su notificación y los compositores que pintan parte de sus textos por ella (`MailReservation`, la R2b).
            $suyas = array_map(static fn (string $clase): string => (string) realpath((string) (new \ReflectionClass($clase))->getFileName()), MailTextCatalog::compositores($correo));
            $propia = implode("\n", array_map(static fn (string $f): string => $fuentes[$f] ?? '', $suyas));
            foreach (MailTextCatalog::claves($correo) as $clave) {
                $total++;
                $ultimo = substr($clave, (int) strrpos($clave, '.') + 1);
                // La cabecera deriva `.badge`, `.headline` —y sus variantes, `headline_grupo`— y `.preheader` de su grupo
                // (`BrandedMailMessage::hero`); que de verdad se pinten lo mide el censo (`MailPreviewsTest`).
                $deCabecera = ($ultimo === 'badge' || $ultimo === 'preheader' || str_starts_with($ultimo, 'headline')) && str_contains($propia, 'hero(');
                if (! str_contains($propia, "'{$clave}'") && ! $deCabecera) {
                    $fantasmas[] = $clave;
                }
                foreach ($fuentes as $fichero => $codigo) {
                    if (! in_array($fichero, $suyas, true) && str_contains($codigo, "'{$clave}'")) {
                        $compartidas[] = "{$clave} (también en {$fichero})";
                    }
                }
            }
        }

        $this->assertGreaterThan(250, $total, 'CONTROL: el catálogo tiene lo que se mide');
        $this->assertSame([], $fantasmas, 'claves que su correo no pinta —bloques fantasma en el panel— (sin uso: a MailTextCatalog::NUNCA)');
        $this->assertSame([], $compartidas, 'claves que también usa otro sitio: editarlas desde el correo lo cambiaría');
    }

    public function test_nothing_legal_no_data_and_no_plural_is_editable(): void
    {
        foreach ([
            'fiesta.cumple_mail.porque', 'emails.comercial.porque', 'surveys.mail.optout', // lo legal de un comercial
            'account.social_link_mail.providers.google', 'emails.confirmation_code.actions.change_email', // datos y fragmentos
            // Fragmentos sueltos: el nombre de reserva de un producto borrado (va también al ASUNTO) y los importes rotulados
            'emails.order_item_cancelled.product_fallback', 'emails.mixed_party_surcharge.amount_discount',
            'emails.mixed_party_surcharge.amount_surcharge',
            'emails.order_cancelled.greeting', // lo que no se pinta
            'emails.verify_pending_email.action', // el botón del correo nuevo, que se fue con su enlace (A5, `#869`)
        ] as $clave) {
            $this->assertFalse(MailTextCatalog::esEditable($clave), $clave);
        }
        // CONTROL: lo de al lado, sí (la frase que recibe los importes; el «si no fuiste tú» que el correo nuevo conserva).
        $this->assertTrue(MailTextCatalog::esEditable('fiesta.cumple_mail.linea'));
        $this->assertTrue(MailTextCatalog::esEditable('surveys.mail.line1'));
        $this->assertTrue(MailTextCatalog::esEditable('emails.mixed_party_surcharge.changed_direction'));
        $this->assertTrue(MailTextCatalog::esEditable('emails.verify_pending_email.ignore'));
        $this->assertTrue(MailTextCatalog::esEditable('emails.verify_pending_email_code.headline'));
        // Ningún texto editable es un plural ni ninguno de fábrica cambia de variables entre idiomas.
        $mal = [];
        foreach (array_keys(MailTextCatalog::CORREOS) as $correo) {
            foreach (MailTextCatalog::claves($correo) as $clave) {
                $es = (string) MailTextCatalog::fabrica($clave, 'es');
                if (str_contains($es, '|')) {
                    $mal[] = "{$clave}: un plural no se edita en una frase";
                }
                foreach (['en', 'fr'] as $locale) {
                    if (MailTextRules::variablesDeFabrica($es) !== MailTextRules::variablesDeFabrica((string) MailTextCatalog::fabrica($clave, $locale))) {
                        $mal[] = "{$clave} [{$locale}]: sus variables no son las del español; el panel exigiría otras";
                    }
                }
            }
        }
        $this->assertSame([], $mal);
    }

    public function test_no_two_blocks_of_a_mail_share_a_name(): void
    {
        // Dos «Asunto» en un correo: ¿cuál sale? (el del correo nuevo ofrecía también los de la versión sin código, 30-09).
        $repetidos = [];
        foreach (array_keys(MailTextCatalog::CORREOS) as $correo) {
            $nombres = array_map(static fn (string $clave): string => MailTextCatalog::etiqueta($clave), MailTextCatalog::claves($correo));
            foreach (array_keys(array_filter(array_count_values($nombres), static fn (int $n): bool => $n > 1)) as $nombre) {
                $repetidos[] = "{$correo}: {$nombre}";
            }
        }

        $this->assertSame([], $repetidos);
    }

    public function test_every_block_and_every_variable_has_its_name_in_both_panel_languages(): void
    {
        $faltan = [];
        foreach (array_keys(MailTextCatalog::CORREOS) as $correo) {
            $claves = ['admin.email_sends.mails.'.$correo, 'admin.mail_texts.descripciones.'.$correo];
            foreach (MailTextCatalog::claves($correo) as $clave) {
                $claves[] = 'admin.mail_texts.bloques.'.substr($clave, (int) strrpos($clave, '.') + 1);
                foreach (MailTextRules::variablesDeFabrica((string) MailTextCatalog::fabrica($clave, 'es')) as $variable) {
                    $claves[] = 'admin.mail_texts.variables.'.$variable;
                }
            }
            foreach (array_unique($claves) as $rotulo) {
                foreach (['es', 'zh_CN'] as $panel) {
                    if (! Lang::has($rotulo, $panel, false)) {
                        $faltan[] = "{$rotulo} [{$panel}]";
                    }
                }
            }
        }

        $this->assertSame([], array_values(array_unique($faltan)), 'nombres del panel que faltan (el bloque saldría con su clave técnica)');
    }

    /** @return array<string, string> ruta → código de lo que puede usar un texto: la aplicación y sus vistas */
    private function fuentesDelProducto(): array
    {
        $fuentes = [];
        foreach (['app', 'resources/views', 'resources/js', 'routes'] as $raiz) {
            $iterador = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(base_path($raiz), \FilesystemIterator::SKIP_DOTS));
            foreach ($iterador as $f) {
                if ($f->isFile() && preg_match('/\.(php|js|vue)$/', $f->getFilename()) === 1) {
                    $fuentes[(string) $f->getRealPath()] = (string) file_get_contents((string) $f->getRealPath());
                }
            }
        }

        return $fuentes;
    }
}
