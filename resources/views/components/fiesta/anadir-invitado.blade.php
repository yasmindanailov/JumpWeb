@props(['id' => 'nuevo', 'defaultAge' => '', 'labels' => [], 'autoFocus' => true])
@php
    /*
     * AÑADIR INVITADOS A MANO, uno detrás de otro (`invitados/GuestComposer.jsx`): la misma ficha de tres campos con su
     * botón «Añadir». Intro añade, el niño pasa a la lista con su fila, y la ficha se vacía con el foco en el nombre
     * para el siguiente (JS de la página, `data-anadir`). Añadir no escribe: la lista se guarda con el único Guardar.
     * ⚠️ Sus campos van SIN `name`: solo existen con JavaScript y nunca viajan en el formulario.
     */
    $L = array_merge([
        'group' => __('fiesta.anadir.group'), 'name' => __('fiesta.fila.name'), 'age' => __('fiesta.fila.age'), 'allergies' => __('fiesta.fila.allergies'),
        'ageUnit' => __('fiesta.fila.age_unit'), 'add' => __('fiesta.anadir.add'), 'close' => __('fiesta.anadir.close'), 'error' => __('fiesta.anadir.error'),
    ], $labels);
@endphp
<div role="group" aria-label="{{ $L['group'] }}" {{ $attributes->class('fi-anadir') }} data-anadir data-error="{{ $L['error'] }}" data-anadido="{{ __('fiesta.anadir.added', ['name' => ':name']) }}">
<x-pieza.campo :id="$id.'-nombre'" :label="$L['name']" :autofocus="$autoFocus" autocomplete="off" autocapitalize="words" enterkeyhint="done" data-campo="name" />
<div class="fi-anadir__campos"><x-pieza.campo :id="$id.'-edad'" :label="$L['age']" :value="$defaultAge" inputmode="numeric" pattern="[0-9]*" maxlength="2" autocomplete="off" enterkeyhint="done" data-campo="age"><x-slot:sufijo>{{ $L['ageUnit'] }}</x-slot:sufijo></x-pieza.campo><x-pieza.campo :id="$id.'-alergias'" :label="$L['allergies']" optional autocomplete="off" enterkeyhint="done" data-campo="allergies" /></div>
<div class="fi-anadir__acciones"><x-pieza.boton variant="secondary" size="sm" data-act="anadir"><x-slot:izquierda><x-lucide name="user-round-plus" :size="17" /></x-slot:izquierda>{{ $L['add'] }}</x-pieza.boton><x-pieza.boton variant="ghost" size="sm" data-act="cerrar">{{ $L['close'] }}</x-pieza.boton><span role="status" aria-live="polite" class="fi-anadir__estado" data-anadir-estado></span></div>
</div>