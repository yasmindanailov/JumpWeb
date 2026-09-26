@props(['id' => 'g', 'name' => '', 'age' => '', 'allergies' => '', 'state' => 'confirmado', 'viaInvite' => false, 'signed' => false, 'skipped' => false, 'dirty' => false, 'open' => false, 'autoFocus' => false, 'last' => false, 'editable' => true, 'birthday' => false, 'labels' => [], 'fallos' => [], 'campos' => null, 'quitar' => false, 'omitir' => false, 'deshacer' => false, 'volver' => false, 'volverValue' => null, 'omitirForm' => null, 'omitirValue' => null, 'chapa' => null, 'vacia' => false, 'oculto' => null])
@php
    /*
     * UN NIÑO de la lista de invitados (`invitados/GuestRow.jsx`): nombre, edad, alergias y su autorización, con la
     * chapa «por la invitación» si contestaron sus padres. Se toca entera y abre su ficha, de tres campos y ni uno
     * más. Quien cumple va primero, con su chapa, y no se quita. Un «No puede venir» se recupera con «Al final
     * viene» (`volver`); con `volverValue` (el id de la respuesta, F3c) es un botón de ENVÍO `rejoin[]`: sin
     * JavaScript guarda la lista y lo vuelve a contar, y con él la página lo intercepta. Lo que abre y cierra es JS de la página (`data-fila`); sin JavaScript la ficha sale abierta,
     * con sus campos, que es lo que viaja en el formulario (`campos`: los `name` de cada uno; por defecto, los del
     * diseño). Los estilos fijos van EN LÍNEA como el JSX; la fila, el chevron y la ficha, por `.fi-fila` en
     * `fiesta.css`, porque tienen estado.
     * ⚠️ Los errores de los campos se llaman `fallos`, no `errors`: ese nombre es el `ViewErrorBag` que Laravel comparte
     *    con TODA vista, y una prop con él pinta la página en 500 («Cannot use object of type ViewErrorBag as array»).
     */
    $L = array_merge([
        'invite' => __('fiesta.fila.invite'), 'signed' => __('fiesta.fila.signed'), 'missing' => __('fiesta.fila.missing'), 'noAge' => __('fiesta.fila.no_age'),
        'no' => __('fiesta.fila.no'), 'unanswered' => __('fiesta.fila.unanswered'), 'skipped' => __('fiesta.fila.skipped'), 'undo' => __('fiesta.fila.undo'),
        'skip' => __('fiesta.fila.skip'), 'remove' => __('fiesta.fila.remove'), 'name' => __('fiesta.fila.name'), 'age' => __('fiesta.fila.age'),
        'allergies' => __('fiesta.fila.allergies'), 'ageUnit' => __('fiesta.fila.age_unit'), 'noName' => __('fiesta.fila.no_name'), 'unsaved' => __('fiesta.fila.unsaved'),
        'birthday' => __('fiesta.fila.birthday'), 'rejoin' => __('fiesta.fila.rejoin'), 'done' => __('fiesta.fila.done'),
    ], $labels);
    $campos = $campos ?? ['name' => $id.'[nombre]', 'age' => $id.'[edad]', 'allergies' => $id.'[alergias]'];
    $no = $state === 'no';
    $pending = $state === 'sin-contestar';
    $canOpen = $editable && ! $no && ! $skipped;
    $regionId = $id.'-ficha';
    $initial = mb_strtoupper(mb_substr(trim((string) $name), 0, 1));
    $muted = $no || $skipped;
    $fondoInicial = $birthday ? 'var(--fiesta-baya-100)' : (($viaInvite && ! $muted) ? 'var(--notice-info-bg)' : 'var(--bg-muted)');
    $colorInicial = $birthday ? 'var(--fiesta-baya-700)' : ($muted ? 'var(--text-muted)' : 'var(--text-strong)');
    $colorNombre = $muted ? 'var(--text-muted)' : ($name !== '' ? 'var(--text-strong)' : 'var(--text-muted)');
    $ui = 'font-family: var(--font-ui);';
@endphp
<li @class(['fi-fila', 'fi-fila--last' => $last, 'fi-fila--open' => $open, 'fi-fila--vacia' => $vacia]) data-fila="{{ $id }}" {{ $attributes->except('class') }}>{{ $oculto }}
@php
    ob_start();
@endphp
<span aria-hidden="true" style="position: relative; display: inline-flex; align-items: center; justify-content: center; flex: 0 0 auto; width: 36px; height: 36px; border-radius: 50%; background: {{ $fondoInicial }}; color: {{ $colorInicial }}; {{ $ui }} font-weight: 700; font-size: 15px;">@if ($initial !== ''){{ $initial }}@else<x-lucide name="user-round" :size="17" />@endif{{ '' }}@if ($dirty)<span data-fila-punto style="position: absolute; top: -1px; right: -1px; width: 10px; height: 10px; border-radius: 50%; background: var(--icon-accent); box-shadow: 0 0 0 2px var(--surface-card, #fff);"></span>@endif</span><span style="display: grid; gap: 3px; min-width: 0; flex: 1;"><span style="display: flex; flex-wrap: wrap; align-items: center; gap: 4px 8px; min-width: 0;"><span data-fila-nombre style="{{ $ui }} font-size: var(--fs-body); font-weight: var(--fw-semibold); color: {{ $colorNombre }}; text-decoration: {{ $skipped ? 'line-through' : 'none' }}; overflow-wrap: anywhere;">{{ $name !== '' ? $name : $L['noName'] }}</span>@if ($birthday)<x-pieza.chapa tone="berry" size="sm">{{ $L['birthday'] }}</x-pieza.chapa>@endif{{ '' }}@if ($viaInvite && ! $birthday)<x-pieza.chapa tone="aqua" size="sm">{{ $L['invite'] }}</x-pieza.chapa>@endif{{ '' }}@if ($pending)<x-pieza.chapa tone="neutral" variant="outline" size="sm">{{ $L['unanswered'] }}</x-pieza.chapa>@endif{{ $chapa }}@if ($dirty)<span class="pz-sr">{{ $L['unsaved'] }}</span>@endif</span><span style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 2px 12px;"><span data-fila-meta style="display: flex; flex-wrap: wrap; gap: 2px 6px; min-width: 0; {{ $ui }} font-size: var(--fs-body-sm); line-height: 1.4; color: var(--text-muted);">@if ($no)<span>{{ $L['no'] }}</span>@elseif ($skipped)<span>{{ $L['skipped'] }}</span>@else{{ '' }}@if ($age !== '' && $age !== null)<span class="pj-num">{{ __('fiesta.fila.years', ['n' => $age]) }}</span>@else<span style="color: var(--text-low); font-weight: var(--fw-semibold);">{{ $L['noAge'] }}</span>@endif{{ '' }}@if ($allergies !== '')<span aria-hidden="true">·</span><span style="min-width: 0; overflow-wrap: anywhere;">{{ $allergies }}</span>@endif{{ '' }}@endif</span>@unless ($muted)<span style="display: inline-flex; align-items: center; gap: 5px; flex: 0 0 auto; margin-left: auto; {{ $ui }} font-size: var(--fs-caption); font-weight: var(--fw-bold); color: {{ $signed ? 'var(--text-positive)' : 'var(--text-muted)' }};"><x-lucide :name="$signed ? 'circle-check' : 'circle-dashed'" :size="16" />{{ $signed ? $L['signed'] : $L['missing'] }}</span>@endunless</span></span>@if ($canOpen)<span aria-hidden="true" class="fi-fila__chevron"><x-lucide name="chevron-down" :size="18" /></span>@endif
@php
    $resumen = trim(ob_get_clean());
@endphp
@if ($canOpen)<button type="button" aria-expanded="{{ $open ? 'true' : 'false' }}" aria-controls="{{ $regionId }}" class="fi-fila__cabecera" data-fila-abrir>{!! $resumen !!}</button>@else<div class="fi-fila__cabecera">{!! $resumen !!}@if ($skipped && $deshacer)<x-pieza.enlace size="sm" underline="always" data-act="deshacer">{{ $L['undo'] }}</x-pieza.enlace>@endif{{ '' }}@if ($no && $volver)<x-pieza.enlace size="sm" underline="always" style="flex: 0 0 auto;" data-act="volver" :type="$volverValue !== null ? 'submit' : null" :name="$volverValue !== null ? 'rejoin[]' : null" :value="$volverValue" :data-rejoin="$volverValue"><x-slot:icono><x-lucide name="user-round-check" :size="15" /></x-slot:icono>{{ $L['rejoin'] }}</x-pieza.enlace>@endif</div>@endif
@if ($canOpen)
<div id="{{ $regionId }}" role="group" aria-label="{{ __('fiesta.fila.sheet', ['name' => $name !== '' ? $name : $L['noName']]) }}" class="fi-fila__ficha" data-fila-ficha>
<x-pieza.campo :id="$id.'-nombre'" :name="$campos['name']" :label="$L['name']" :value="$name" :error="$fallos['name'] ?? ''" autocomplete="off" autocapitalize="words" enterkeyhint="next" :autofocus="$autoFocus === true || $autoFocus === 'name'" data-campo="name" />
<div class="fi-fila__campos"><x-pieza.campo :id="$id.'-edad'" :name="$campos['age']" :label="$L['age']" :value="$age" :error="$fallos['age'] ?? ''" inputmode="numeric" pattern="[0-9]*" maxlength="2" autocomplete="off" enterkeyhint="next" :autofocus="$autoFocus === 'age'" data-campo="age"><x-slot:sufijo>{{ $L['ageUnit'] }}</x-slot:sufijo></x-pieza.campo><x-pieza.campo :id="$id.'-alergias'" :name="$campos['allergies']" :label="$L['allergies']" optional :value="$allergies" autocomplete="off" enterkeyhint="done" data-campo="allergies" /></div>
<div class="fi-fila__acciones"><x-pieza.boton variant="quiet" size="sm" data-act="listo"><x-slot:izquierda><x-lucide name="check" :size="17" /></x-slot:izquierda>{{ $L['done'] }}</x-pieza.boton>@if ($omitir && $omitirForm !== null)<x-pieza.boton variant="ghost" size="sm" type="submit" form="{{ $omitirForm }}" name="reply" :value="$omitirValue" data-act="omitir"><x-slot:izquierda><x-lucide name="user-x" :size="17" /></x-slot:izquierda>{{ $L['skip'] }}</x-pieza.boton>@elseif ($omitir)<x-pieza.boton variant="ghost" size="sm" data-act="omitir"><x-slot:izquierda><x-lucide name="user-x" :size="17" /></x-slot:izquierda>{{ $L['skip'] }}</x-pieza.boton>@endif{{ '' }}@if ($quitar)<x-pieza.boton variant="ghost" size="sm" data-act="quitar"><x-slot:izquierda><x-lucide name="trash-2" :size="17" /></x-slot:izquierda>{{ $L['remove'] }}</x-pieza.boton>@endif</div>
</div>
@endif
</li>