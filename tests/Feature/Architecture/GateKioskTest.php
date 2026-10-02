<?php

namespace Tests\Feature\Architecture;

use Tests\TestCase;

/**
 * **La pantalla de PUERTA es un KIOSCO** (`DECISIONES #232`: tablet propia, fija en un soporte y en horizontal) y, desde la
 * P1 de la Puerta nueva (`docs/specs/puerta-nueva.md` §4.4), con la maqueta del mockup del zip (6): el buscador arriba, el
 * cuerpo en dos columnas que se desplazan solas y, con una búsqueda en pantalla, el pie. Su hoja es propia
 * (`resources/css/filament/admin/puerta.css`, prefijo `ppu-`, con los tokens del panel) y la importa el tema del panel.
 *
 * ❗ **Lo que esta guarda NO puede ver.** Que la pantalla «se vea bien», que veredicto y tareas quepan sin desplazar en
 * 1080 × 810 o que un botón mida de verdad 44 px lo mide el NAVEGADOR (la sonda de la P1). Aquí se fijan las
 * DECISIONES, para que nadie las deshaga sin enterarse — y una de ellas es de privacidad, no de estética.
 */
class GateKioskTest extends TestCase
{
    private const HOJA = 'resources/css/filament/admin/puerta.css';

    private const TEMA = 'resources/css/filament/admin/theme.css';

    private const VISTA = 'resources/views/livewire/admin/puerta/validar.blade.php';

    private function fichero(string $ruta): string
    {
        return (string) file_get_contents(base_path($ruta));
    }

    /** El cuerpo de la PRIMERA regla cuyo selector es exactamente `$selector` (a nivel raíz de la hoja). */
    private function regla(string $selector): string
    {
        $this->assertMatchesRegularExpression('/^'.preg_quote($selector, '/').' \{\n(.*?)\n\}/ms', $this->fichero(self::HOJA), "Falta la regla «{$selector}» de la Puerta.");
        preg_match('/^'.preg_quote($selector, '/').' \{\n(.*?)\n\}/ms', $this->fichero(self::HOJA), $m);

        return $m[1];
    }

    /**
     * La regla `$selector` lleva la declaración `$declaracion` como LÍNEA ENTERA. ⚠️ Una subcadena no basta:
     * «min-height: 100dvh;» CONTIENE «height: 100dvh;», y así sobrevivió el mutante que hacía desplazarse la pantalla.
     */
    private function declara(string $selector, string $declaracion, string $porque = ''): void
    {
        $this->assertMatchesRegularExpression('/^\s*'.preg_quote($declaracion, '/').'$/m', $this->regla($selector), $porque !== '' ? $porque : "«{$selector}» ya no declara «{$declaracion}».");
    }

    public function test_the_panel_theme_imports_the_gate_sheet(): void
    {
        $this->assertStringContainsString("@import './puerta.css';", $this->fichero(self::TEMA), 'La Puerta se quedó sin hoja: el tema del panel ya no la importa.');
        $this->assertStringNotContainsString('.gate-shell {', $this->fichero(self::TEMA), 'La hoja vieja de la puerta volvió al tema del panel.');
    }

    /** La pantalla ENTERA es el kiosco: tres alturas (buscador, cuerpo, pie) y la página no se desplaza; lo hacen sus columnas. */
    public function test_the_screen_fills_the_tablet_and_the_page_never_scrolls(): void
    {
        $this->declara('.gate-shell', 'height: 100dvh;', 'la pantalla dejó de medir la tablet: se desplaza como un documento');

        $this->declara('.ppu', 'container-type: size;', 'la pieza se mide contra su CAJA (el mockup): sin esto no hay vertical ni escritorio');
        $this->declara('.ppu', 'grid-template-rows: auto minmax(0, 1fr) auto;');
        $this->declara('.ppu', 'overflow: hidden;');
    }

    /**
     * ▶ **Entre dos clientes, ningún gesto** (`#234`): tras cada búsqueda válida el campo se vacía y RECUPERA el foco, así
     * que el siguiente escaneo entra solo. Son dos mitades: el componente avisa y la vista escucha.
     */
    public function test_the_field_clears_and_takes_focus_back_after_a_search(): void
    {
        $component = $this->fichero('app/Livewire/Admin/Puerta/ValidarRegistro.php');

        $this->assertStringContainsString("\$this->input = '';", $component, '`search()` ya no vacía el campo.');
        $this->assertStringContainsString("\$this->dispatch('gate-input-cleared')", $component, 'Falta el aviso al navegador: el cursor no vuelve.');
        $this->assertStringContainsString('x-on:gate-input-cleared.window="$el.focus()"', $this->fichero(self::VISTA), 'El campo ya no escucha el aviso.');
    }

    /** ▶ **Volver desde el TPV y poder escanear sin tocar nada** (`#234`): el caso que `autofocus` no cubre. */
    public function test_the_field_takes_focus_back_when_the_window_returns(): void
    {
        $vista = $this->fichero(self::VISTA);

        $this->assertStringContainsString('x-on:focus.window="$el.focus({ preventScroll: true })"', $vista);
        $this->assertStringContainsString('x-on:visibilitychange.document', $vista, 'Falta el caso de volver a la PESTAÑA.');
        $this->assertStringContainsString('x-init="$el.focus()"', $vista, 'El foco al abrir vuelve a depender solo de `autofocus`.');
    }

    /** ⚠️ El buscador pegado arriba se RETIRÓ (`#234`); en la Puerta nueva el buscador es su propia fila de la rejilla. */
    public function test_nothing_is_sticky(): void
    {
        $this->assertStringNotContainsString('position: sticky', $this->fichero(self::HOJA));
    }

    /** Dos columnas que se desplazan SOLAS (el mockup): a la izquierda lo que se hace, a la derecha el contexto. */
    public function test_the_body_is_two_columns_that_scroll_on_their_own(): void
    {
        $this->declara('.ppu-cuerpo', 'grid-template-columns: minmax(0, 1.15fr) minmax(0, 1fr);');
        $this->declara('.ppu-col', 'overflow-y: auto;');

        $vista = $this->fichero(self::VISTA);
        $this->assertStringContainsString('data-gate-col="main"', $vista);
        $this->assertStringContainsString('data-gate-col="side"', $vista);
    }

    /**
     * ⚠️⚠️ **«Nueva búsqueda» es un control de PRIVACIDAD, no una comodidad** (`#817`, el owner: se queda aunque el mockup
     * la quite): además de vaciar el campo, quita de la pantalla la ficha del anterior, que la cola ve. Va en el pie de
     * CUALQUIER búsqueda en pantalla y nada lo esconde.
     */
    public function test_the_privacy_reset_is_never_hidden(): void
    {
        $vista = $this->fichero(self::VISTA);

        $this->assertMatchesRegularExpression('/@if \(\$verdict !== null\)\s*<footer class="ppu-pie gate-foot">/', $vista, 'El pie ya no sale con cualquier búsqueda en pantalla.');
        $this->assertMatchesRegularExpression('/<button type="button" wire:click="clear" class="ppu-boton gate-foot__btn">/', $vista);

        foreach (['.ppu-pie', '.ppu-boton', '.ppu-pie > .ppu-boton'] as $selector) {
            $this->assertStringNotContainsString('display: none', $this->regla($selector), "«{$selector}» esconde «Nueva búsqueda».");
        }
    }

    /**
     * ▶ **Todo lo que se toca mide 44 px o más, a CUALQUIER ancho** (el brief; el mínimo de Apple): la regla vive fuera de
     * toda consulta de caja o de pantalla, porque un dedo es un dedo.
     */
    public function test_touch_targets_are_at_least_44px_at_every_width(): void
    {
        $this->declara('.ppu-boton', 'min-height: 44px;');
        $this->declara('.ppu-boton--buscar', 'min-height: 58px;');
        $this->declara('.ppu-op', 'min-height: 48px;');
        $this->declara('.ppu-ahora', 'min-height: 44px;');
        $this->declara('.gate-q__text', 'min-height: 44px;');
        $this->declara('.ppu-campo__input', 'height: 58px;');
    }

    /**
     * ▶ **Un toque de la encuesta no rehace la ficha** (la P1b; el owner, 02-10: «no quiero que se recargue la página»). La
     * clave de la ficha y la del veredicto llevan la LECTURA, nunca `expires_at`: cada toque renueva el reloj, y con él en
     * la clave la ficha entraba entera otra vez —su entrada, el salto del veredicto y el pitido—. La identidad la mide el
     * navegador (la sonda de la P1); aquí se fija la decisión.
     */
    public function test_a_tap_inside_the_sheet_does_not_rebuild_it(): void
    {
        $vista = $this->fichero(self::VISTA);

        $this->assertStringContainsString('wire:key="ficha-{{ $lectura }}-{{ $profile[\'user_id\'] }}"', $vista);
        $this->assertStringContainsString('wire:key="veredicto-{{ $lectura }}-{{ $verdict[\'tone\'] }}"', $vista);
        $this->assertDoesNotMatchRegularExpression('/wire:key="[^"]*(expires_at|uniqid)/', $vista, 'Una clave que cambia sin una lectura nueva rehace la ficha en cada toque.');
        $this->assertStringNotContainsString('wire:model="surveyAnswers', $vista, 'Lo contestado viaja con el toque, no en un modelo a medias.');
    }

    /** ▶ **La doble lectura** del lector (el mockup): el mismo código en menos de 3 s se corta ANTES que el `wire:submit`. */
    public function test_a_double_read_is_cut_before_livewire_sees_it(): void
    {
        $vista = $this->fichero(self::VISTA);

        $this->assertStringContainsString('x-on:submit.capture="if (doble($refs.campo.value))', $vista);
        $this->assertStringContainsString('$event.stopImmediatePropagation()', $vista);
        $this->assertStringContainsString('ahora - this.previo.t < 3000', $this->fichero('resources/views/layouts/puerta.blade.php'));
    }
}
