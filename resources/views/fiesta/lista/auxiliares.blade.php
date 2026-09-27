{{-- Los formularios que escriben OTRA cosa que la lista, VACÍOS y fuera del principal (un `form` dentro de otro no es
     HTML válido): «No lo apuntes» (`invitation_replies`, por `form=` desde cada fila) y el recordatorio. --}}
@if ($m['invitacion'] !== null && ! $m['solo_lectura'])
    <form method="POST" action="{{ $m['invitacion']['descartar'] }}" id="fiesta-descartar" class="pz-sr">@csrf</form>
    <form method="POST" action="{{ $m['invitacion']['recordatorio'] }}" id="fiesta-recordatorio" class="pz-sr">@csrf</form>
@endif
{{-- El descargo de QUIEN CUMPLE por el camino de la cuenta (F7b, `#752`): sus campos, bajo su fila, van aquí por `form=`. --}}
@if (($m['firma_cumple'] ?? null) !== null && $m['firma_cumple']['sesion'])
    <form method="POST" action="{{ $m['firma_cumple']['accion'] }}" id="fiesta-cumple" class="pz-sr">@csrf</form>
@endif
