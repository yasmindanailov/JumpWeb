@props(['idPrefix' => 'aut', 'action' => '', 'child' => false, 'labels' => [], 'fallos' => [], 'valores' => [], 'autoFocus' => false, 'legal' => null, 'nino' => null, 'adulto' => null, 'oculto' => null, 'descargo' => null, 'tercero' => null])
@php
    /*
     * LA FIRMA de la autorización y el descargo de un invitado, sin cuenta (`invitados/AuthForm.jsx`), con sus estilos EN
     * LÍNEA como el JSX: la misma pieza en la página de la autorización (`child`: quien firma escribe el nombre del niño,
     * que NUNCA viene puesto) y, en T3+, en el recibo de la invitación. Los `name` son los del controlador de siempre
     * (`minor_name`, `guardian_name`…); los errores llegan del servidor (`fallos`, por campo) y se dicen con palabras.
     * ⚠️ Lo que el producto pide y el brief no (`#745`): la fecha de nacimiento del niño va en la ranura `nino` (la
     *    tercera celda de su fila) y la relación con el menor en `adulto` (entre el nombre y el teléfono). El texto del
     *    descargo se PRESENTA en el flujo (`waiver-probatorio.md` §4.4), en la ranura `descargo`, antes de la casilla;
     *    «Leer el descargo» es un ancla a él. `oculto`: los campos escondidos (documento, respuesta atada, honeypot).
     * ⚠️ El botón dice «Firmando» mientras el formulario viaja (el JS de la página); sin JavaScript firma igual.
     */
    $L = array_merge([
        'childName' => __('fiesta.autorizacion.nino_nombre'), 'childSurname' => __('fiesta.autorizacion.nino_apellidos'),
        'name' => __('fiesta.firma.tu_nombre'), 'phone' => __('fiesta.firma.tu_telefono'), 'box' => __('fiesta.firma.casilla'),
        'readWaiver' => __('fiesta.firma.leer_descargo'), 'email' => __('fiesta.firma.correo'), 'emailHint' => __('fiesta.firma.correo_ayuda'),
        'commit' => __('fiesta.firma.compromiso'), 'sign' => __('fiesta.firma.firmar'), 'signing' => __('fiesta.firma.firmando'),
    ], $labels);
    $id = static fn (string $k): string => $idPrefix.'-'.$k;
    $f = static fn (string $k): string => (string) ($fallos[$k] ?? '');
    $v = static fn (string $k): string => (string) ($valores[$k] ?? '');
@endphp
<form method="post" action="{{ $action }}" novalidate {{ $attributes->class(['fi-firma']) }} style="display: grid; gap: 14px;" data-firma data-firmando="{{ $L['signing'] }}">@csrf{{ '' }}{{ $oculto }}{{ '' }}@if ($child)<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 160px), 1fr)); gap: 14px 12px; align-items: start;"><x-pieza.campo :id="$id('ninoNombre')" name="minor_name" :label="$L['childName']" :value="$v('ninoNombre')" :error="$f('ninoNombre')" autocomplete="off" autocapitalize="words" :autofocus="$autoFocus ? true : null" /><x-pieza.campo :id="$id('ninoApellidos')" name="minor_surname" :label="$L['childSurname']" :value="$v('ninoApellidos')" :error="$f('ninoApellidos')" autocomplete="off" autocapitalize="words" />{{ $nino }}</div>@endif{{ '' }}<x-pieza.campo :id="$id('nombre')" name="guardian_name" :label="$L['name']" :value="$v('nombre')" :error="$f('nombre')" autocomplete="name" autocapitalize="words" :autofocus="$autoFocus && ! $child ? true : null" />{{ $adulto }}<x-pieza.campo :id="$id('telefono')" name="guardian_phone" :label="$L['phone']" type="tel" :value="$v('telefono')" :error="$f('telefono')" autocomplete="tel" inputmode="tel" />{{ $descargo }}<x-pieza.casilla :id="$id('casilla')" name="accept_waiver" :label="$L['box']" :checked="$v('casilla') === '1'" :error="$f('casilla')"><x-pieza.enlace size="sm" underline="always" href="#descargo">{{ $L['readWaiver'] }}</x-pieza.enlace></x-pieza.casilla><x-pieza.campo :id="$id('correo')" name="guardian_email" :label="$L['email']" optional :hint="$L['emailHint']" type="email" :value="$v('correo')" :error="$f('correo')" autocomplete="email" inputmode="email" />{{ '' }}@if ($L['commit'] !== '')<p style="margin: 0; display: flex; align-items: flex-start; gap: 8px; padding: 10px 12px; border-radius: var(--r-md); background: var(--bg-subtle); font-family: var(--font-ui); font-size: var(--fs-body-sm); font-weight: var(--fw-semibold); line-height: 1.45; color: var(--fiesta-tinta-900);"><x-lucide name="circle-check" :size="17" color="var(--text-positive)" style="flex: 0 0 auto; margin-top: 1px;" /><span>{{ $L['commit'] }}</span></p>@endif{{ '' }}{{ $legal }}{{ $tercero }}<div style="display: grid;"><x-pieza.boton type="submit" variant="primary" size="md" full data-firma-boton>{{ $L['sign'] }}</x-pieza.boton></div></form>
