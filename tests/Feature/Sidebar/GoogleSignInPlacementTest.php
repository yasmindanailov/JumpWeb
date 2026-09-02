<?php

namespace Tests\Feature\Sidebar;

use Tests\TestCase;

/**
 * **DÓNDE VA EL BOTÓN DE GOOGLE, Y QUE SU «O» NO PUEDA QUEDARSE SOLO**
 * (`specs/auth-con-google.md` §21.4.4, T8·d, `#350`).
 *
 * `[DECIDIDO owner, 2026-09-02]`, sobre las dos opciones: **encima del formulario, con un separador
 * «o»**. Hasta hoy iba debajo, con su motivo escrito en `LoginZone.vue` («quien ya tiene contraseña
 * la teclea, y quien no, lee hasta abajo»); revertirlo es deliberado.
 *
 * ⚠️⚠️ **Esto existe porque el contrato de árbol NO ve este botón, y está medido en `#345`**: el
 * manifiesto congelado de `SidebarDomContractTest` tiene **cero** ocurrencias de «google» —sus
 * fixtures no pasan la URL y el componente es un `v-if="href"`—, así que **mover esta pieza de sitio
 * no pone en rojo absolutamente nada**. `GoogleButtonBrandingTest` cubre su marca y su piel; su
 * COLOCACIÓN no la cubría nadie.
 *
 * ⚠️ **Y hay un segundo modo de fallo, más silencioso todavía**: sacar el separador del componente
 * para escribirlo en los dos formularios. Funciona igual hasta que alguien toca uno solo — y entonces
 * una instalación **sin claves de Google** se queda con una raya y un «o» en medio de la pantalla,
 * separando el formulario de nada. Por eso el separador vive dentro del `v-if` del botón y este
 * fichero lo fija.
 */
class GoogleSignInPlacementTest extends TestCase
{
    private const BUTTON = 'resources/js/sidebar/steps/GoogleButton.vue';

    /** Los dos formularios que ofrecen el camino alternativo: entrar y crear cuenta. */
    private const FORMS = [
        'resources/js/sidebar/steps/LoginForm.vue',
        'resources/js/sidebar/steps/RegisterForm.vue',
    ];

    /**
     * **Guarda de la guarda.** Sin esto, un renombrado deja todo lo de abajo buscando anclas en
     * cadenas vacías y pasando en verde — que es como este repo perdió `#113`.
     */
    public function test_the_scan_reads_its_sources_and_finds_its_anchors(): void
    {
        foreach ([self::BUTTON, ...self::FORMS] as $path) {
            $this->assertFileExists(base_path($path));
            $this->assertNotSame('', trim($this->read($path)), "`{$path}` se lee vacío.");
        }

        foreach (self::FORMS as $path) {
            $template = $this->template($path);

            $this->assertStringContainsString('auth__head', $template, "`{$path}` ya no tiene cabecera.");
            $this->assertStringContainsString('<form', $template, "`{$path}` ya no tiene formulario.");
            $this->assertStringContainsString('<GoogleButton', $template, "`{$path}` ya no ofrece Google.");
        }
    }

    /**
     * **Entre la cabecera y los campos**, que es lo que «encima del formulario» significa aquí.
     *
     * ⚠️ Se comprueban las DOS fronteras y no solo una: «después de `.auth__head`» lo cumple también
     * un botón puesto al final del fichero, y «antes de `<form`» lo cumpliría uno colgado encima del
     * título. La decisión es que esté **en medio**.
     */
    public function test_the_button_sits_between_the_heading_and_the_form(): void
    {
        foreach (self::FORMS as $path) {
            $template = $this->template($path);

            $head = strpos($template, 'auth__head');
            $button = strpos($template, '<GoogleButton');
            $form = strpos($template, '<form');

            $this->assertGreaterThan(
                $head, $button,
                "En `{$path}` el botón de Google está por ENCIMA del título.\n".
                'El camino alternativo se ofrece después de saber en qué pantalla estás.'
            );

            $this->assertLessThan(
                $form, $button,
                "En `{$path}` el botón de Google ha vuelto DEBAJO del formulario.\n".
                "▶ `[DECIDIDO owner, 2026-09-02]` (T8·d): va encima, con un separador «o». Iba debajo\n".
                '  hasta `#350` y se cambió a propósito, sobre las dos opciones.'
            );
        }
    }

    /** Y los dos lo piden con su separador: un botón suelto encima del formulario no separa nada. */
    public function test_both_forms_ask_for_the_separator(): void
    {
        foreach (self::FORMS as $path) {
            $this->assertMatchesRegularExpression(
                '~<GoogleButton\b[^>]*:separator="~s', $this->template($path),
                "`{$path}` pinta el botón SIN separador: la raya con el «o» es lo que dice que hay dos\n".
                'caminos, y sin ella el botón se lee como un paso más del formulario.'
            );
        }
    }

    /**
     * ⚠️⚠️ **LA de este fichero**: el separador **no puede existir sin el botón**.
     *
     * Sin las dos condiciones en el mismo `v-if`, una instalación sin claves de Google —donde `href`
     * llega vacío y el botón no se pinta— se queda con una raya y un «o» separando el formulario de
     * nada. No falla ningún test, no lo enseña ninguna captura de nuestra instalación (que sí tiene
     * claves) y solo se ve en la del cliente que no lo usa.
     */
    public function test_the_separator_cannot_be_painted_without_the_button(): void
    {
        $template = $this->template(self::BUTTON);

        $this->assertMatchesRegularExpression(
            '~<p\b[^>]*\bv-if="href && separator"[^>]*\bclass="auth__or"~s', $template,
            "El separador ha dejado de depender de `href`.\n".
            "▶ Sin esa condición, una instalación SIN claves de Google pinta la raya y el «o» sin\n".
            "  botón encima: un separador que no separa nada, en la pantalla de entrar.\n".
            '▶ Y tiene que vivir AQUÍ, no en los dos formularios: dos copias de la condición divergen.'
        );
    }

    private function read(string $path): string
    {
        return (string) file_get_contents(base_path($path));
    }

    /**
     * El `<template>` del componente, sin el `<script setup>` — que cita `auth__or` y `href` al
     * explicar el porqué, y acusaría a su propia documentación.
     */
    private function template(string $path): string
    {
        $source = $this->read($path);
        $at = strpos($source, '<template>');

        $this->assertNotFalse($at, "`{$path}` ya no tiene `<template>`.");

        return substr($source, $at);
    }
}
