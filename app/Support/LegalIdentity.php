<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Interpola los datos fiscales del titular (editables en `/admin/settings`, #205) dentro
 * del texto de las páginas legales (`pages.body`, sembrado en `LandingContentSeeder`).
 *
 * Las páginas legales se sembraron con marcadores `[PENDIENTE: razón social]`, `NIF
 * [PENDIENTE]`, etc. Este helper los sustituye **en el render** por los valores reales de
 * los settings (`business.legal_name` / `business.nif` / `business.address` /
 * `contact.email`), de modo que rellenar la configuración se refleja al instante en la
 * política de privacidad y el aviso legal — sin tocar la BD ni re-sembrar (#206).
 *
 * Reconoce dos formas del marcador, para cubrir tanto el contenido ya sembrado (literales)
 * como cualquier contenido futuro escrito con tokens limpios (`:legal_name`, …):
 *  - **Tokens**: `:legal_name`, `:legal_nif`, `:legal_address`, `:legal_email` (identidad fiscal),
 *    y además `:legal_phone` (teléfono de contacto), `:tax_rate` (IVA configurado, formateado
 *    «21 %») y `:site_domain` (dominio del sitio: ajuste `business.domain`, o el host de la
 *    petición si no se ha fijado). Todos editables desde el panel → el texto legal se rellena solo.
 *  - **Literales heredados** (es/en/fr), incluido el `[PENDIENTE]` desnudo desambiguado por
 *    su contexto (`NIF [PENDIENTE]` → NIF; `domicilio en [PENDIENTE]` → domicilio).
 *
 * Usa `strtr` (reemplazo de un solo paso, claves más largas primero) para que el marcador
 * con sufijo (`[PENDIENTE: razón social]`) gane al `[PENDIENTE]` desnudo y no haya
 * re-sustitución. Si un dato fiscal está vacío o aún es el placeholder sembrado, muestra
 * un `[pendiente]` neutro (no rompe la frase ni filtra el marcador crudo).
 */
class LegalIdentity
{
    public static function interpolate(?string $text): string
    {
        $text = (string) $text;
        if ($text === '') {
            return '';
        }

        $name = self::value('business.legal_name');
        $nif = self::value('business.nif');
        $address = self::value('business.address');
        $email = self::value('contact.email');

        return strtr($text, [
            // Tokens canónicos.
            ':legal_name' => $name,
            ':legal_nif' => $nif,
            ':legal_address' => $address,
            ':legal_email' => $email,
            // Tokens adicionales editables desde el panel (#220): teléfono, IVA y dominio.
            ':legal_phone' => self::value('contact.phone'),
            ':tax_rate' => self::taxRate(),
            ':site_domain' => self::siteDomain(),

            // Literales heredados — ES.
            '[PENDIENTE: razón social]' => $name,
            '[PENDIENTE: titular]' => $name,
            '[PENDIENTE: email]' => $email,
            'NIF [PENDIENTE]' => 'NIF '.$nif,
            'domicilio en [PENDIENTE]' => 'domicilio en '.$address,
            'domicilio [PENDIENTE]' => 'domicilio '.$address,

            // Literales heredados — EN.
            '[PENDING: legal name]' => $name,
            '[PENDING: owner]' => $name,
            '[PENDING: email]' => $email,
            'tax ID [PENDING]' => 'tax ID '.$nif,
            'registered at [PENDING]' => 'registered at '.$address,
            'address [PENDING]' => 'address '.$address,

            // Literales heredados — FR.
            '[À COMPLÉTER : raison sociale]' => $name,
            '[À COMPLÉTER : éditeur]' => $name,
            '[À COMPLÉTER : email]' => $email,
            'NIF [À COMPLÉTER]' => 'NIF '.$nif,
            'domicilié à [À COMPLÉTER]' => 'domicilié à '.$address,
            'adresse [À COMPLÉTER]' => 'adresse '.$address,
        ]);
    }

    /**
     * Valor de un dato fiscal listo para mostrar: el del setting, o un `[pendiente]` neutro
     * si está vacío o aún es el placeholder `[PENDIENTE…]` sembrado por defecto.
     */
    private static function value(string $key): string
    {
        $value = trim((string) (Setting::value($key) ?? ''));

        return ($value === '' || str_contains($value, '[PENDIENTE') || str_contains($value, '[PENDING'))
            ? '[pendiente]'
            : $value;
    }

    /**
     * Tipo de IVA configurado (`payment.tax_rate`) formateado «21 %». Defensivo: valida entero
     * 0–100 y cae al 21 % por defecto si el valor es inválido o aún es el placeholder.
     */
    private static function taxRate(): string
    {
        $n = filter_var(
            Setting::value('payment.tax_rate', '21'),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 0, 'max_range' => 100]],
        );

        return ($n !== false ? $n : 21).' %';
    }

    /**
     * Dominio del sitio para los textos legales: el ajuste `business.domain` si se ha fijado; si no,
     * el host de la petición actual (en producción = el dominio real, sin necesidad de configurarlo).
     */
    private static function siteDomain(): string
    {
        $domain = trim((string) (Setting::value('business.domain') ?? ''));
        if ($domain === '' || str_contains($domain, '[PENDIENTE') || str_contains($domain, '[PENDING')) {
            $domain = request()?->getHost() ?? '';
        }

        return $domain !== '' ? $domain : '[pendiente]';
    }
}
