{{-- ZONA 1 · Estado y compartir. Las tres cifras son botones que filtran la lista (JS). Recién pagada no hay cifras:
     manda «Compartir por WhatsApp»; ya compartida, «Reenviar». La vista previa es la invitación de verdad; «Personalizar»
     abre el tema, quién cumple y su edad, «te invita» y el teléfono: escriben `party_invitations` con el mismo Guardar.
     Fuera de plazo, un aviso en lugar de compartir. --}}
@php($inv = $m['invitacion'])
@php($c = $m['cuentas'])
{{-- `id="gf-invite"` es el ANCLA con la que el correo de «Compartir la invitación» (`GuestFormRequest`) y la API
     (`invitation_url` de `/me/orders`) aterrizan aquí: es contrato en correos ya enviados y se conserva con su nombre. --}}
<section class="pli-zona pli-z1" id="gf-invite" data-zona="1" aria-labelledby="pli-h1">
    @if ($inv['compartida'])
        <div class="pli-estado">
            <div role="group" aria-label="{{ __('fiesta.lista.respuestas') }}" class="pli-tally">
                @foreach ([['confirmados', $c['confirmados'], __('fiesta.lista.confirmados'), 'var(--success-500)'], ['no', $c['no_pueden'], __('fiesta.lista.no_pueden'), 'var(--fiesta-tinta-400)'], ['sin', $c['sin_contestar'], __('fiesta.lista.sin_contestar'), 'var(--warn-500)']] as [$k, $n, $label, $dot])
                    <button type="button" class="pli-cuenta" aria-pressed="false" aria-label="{{ __('fiesta.lista.ver_en_lista', ['n' => $n, 'que' => $label]) }}" data-filtro="{{ $k }}"><span class="pli-cuenta-top"><span class="pli-cuenta-n" data-cuenta="{{ $k }}">{{ $n }}</span><span class="pli-dot" style="background: {{ $dot }};"></span></span><span class="pli-cuenta-l">{{ $label }}</span></button>
                @endforeach
            </div>
            <div class="pli-estado-bajo">
                @if ($inv['respuestas_abiertas'])
                    <x-pieza.boton variant="quiet" size="sm" :href="$inv['whatsapp']" target="_blank" rel="noopener noreferrer"><x-slot:izquierda><x-lucide name="message-circle" :size="17" /></x-slot:izquierda>{{ __('fiesta.lista.reenviar') }}</x-pieza.boton>
                @endif
                <span class="pli-vivo" role="status" aria-live="polite" data-aviso-vivo></span>
            </div>
        </div>
    @endif
    @if (! $inv['respuestas_abiertas'])
        <x-pieza.aviso tone="neutral" size="sm"><x-slot:icono><x-lucide name="lock" :size="17" /></x-slot:icono>{{ '' }}{{ __('fiesta.lista.invitacion.cerrada', ['plazo' => $inv['plazo']]) }}</x-pieza.aviso>
    @else
        <div class="pli-inv">
            {{-- LA VISTA PREVIA, EN VIVO (F2): lo que se teclea en «Personalizar» se ve en la tarjeta al momento (`lista.js`).
                 Una plantilla por tema con la tarjeta entera: cambiar de tema cambia la tarjeta y le vuelve a poner lo
                 tecleado. Sin JavaScript, la tarjeta es la de lo guardado, como siempre. --}}
            @php($deA = __('fiesta.lista.de_a', ['hora' => $m['reserva']['hora'], 'fin' => $m['reserva']['fin']]))
            <div class="pli-inv-vista">
                <x-fiesta.invitacion vivo :telefonoVisible="$inv['telefono']" :theme="$inv['tema']" :name="$m['cumple']['nombre']" :age="$m['cumple']['edad']" :date="$m['reserva']['dia']" :time="$deA" :place="$m['reserva']['lugar']" :host="$inv['invita']" :words="$inv['palabras']" :gifts="$inv['pistas']" :phone="$m['reserva']['anfitriona']" animate data-inv-vista />
                @foreach ($inv['temas'] as $tema)<template data-inv-plantilla="{{ $tema['value'] }}"><x-fiesta.invitacion vivo :telefonoVisible="$inv['telefono']" :theme="$tema['value']" :name="$m['cumple']['nombre']" :age="$m['cumple']['edad']" :date="$m['reserva']['dia']" :time="$deA" :place="$m['reserva']['lugar']" :host="$inv['invita']" :words="$inv['palabras']" :gifts="$inv['pistas']" :phone="$m['reserva']['anfitriona']" animate data-inv-vista /></template>@endforeach
            </div>
            <div class="pli-inv-acc">
                @if (! $inv['compartida'])
                    <x-pieza.boton variant="secondary" size="md" full :href="$inv['whatsapp']" target="_blank" rel="noopener noreferrer"><x-slot:izquierda><x-lucide name="message-circle" :size="19" /></x-slot:izquierda>{{ __('fiesta.lista.compartir') }}</x-pieza.boton>
                @endif
                <div class="pli-inv-fila">
                    <x-pieza.compartir :items="[['kind' => 'copy', 'label' => __('fiesta.lista.copiar')]]" :value="$inv['url']" :confirm="__('fiesta.lista.invitacion.copiado')" />
                    <x-pieza.boton variant="ghost" size="sm" aria-expanded="false" aria-controls="pli-pers" data-pers-abrir><x-slot:izquierda><x-lucide name="palette" :size="17" /></x-slot:izquierda>{{ __('fiesta.lista.personalizar') }}</x-pieza.boton>
                </div>
                <p class="pli-nota" hidden data-inv-nota><x-lucide name="info" :size="15" />{{ __('fiesta.lista.invitacion.cambios') }}</p>
                {{-- PERSONALIZAR: escribe SOLO `party_invitations`, con el Guardar de la página (`store()` lo reparte). Sin
                     JavaScript sale abierto; con él lo abre el botón. --}}
                <div id="pli-pers" class="pli-pers" data-pers>
                    <x-fiesta.tema vivo :label="__('fiesta.lista.pers.tema')" name="theme" :items="$inv['temas']" :value="$inv['tema']" :age="$m['cumple']['edad']" />
                    <div class="pli-pers-2">
                        <x-pieza.campo id="pli-quien" name="honoree_name" :label="__('fiesta.lista.pers.quien')" :value="$m['cumple']['nombre']" :maxlength="$inv['honoree_max']" autocomplete="off" data-inv-campo="name" />
                        <x-pieza.campo id="pli-su-edad" name="honoree_age" :label="__('fiesta.lista.pers.edad')" :value="$m['cumple']['edad']" inputmode="numeric" maxlength="2" data-inv-campo="age"><x-slot:sufijo>{{ __('fiesta.invitacion.unit') }}</x-slot:sufijo></x-pieza.campo>
                    </div>
                    <x-pieza.campo id="pli-invita" name="host_line" :label="__('fiesta.lista.pers.invita')" :value="$inv['invita']" :maxlength="$inv['host_max']" autocomplete="off" data-inv-campo="host" />
                    {{-- Las palabras y las pistas (F1a): opcionales, con el tope del diseño; texto libre publicado, como «te invita». --}}
                    <x-pieza.campo id="pli-palabras" name="family_words" :label="__('fiesta.lista.pers.palabras')" optional :value="$inv['palabras']" :maxlength="$inv['palabras_max']" autocomplete="off" data-inv-campo="words" />
                    <x-pieza.campo id="pli-pistas" name="gift_hints" :label="__('fiesta.lista.pers.pistas')" optional :value="$inv['pistas']" :maxlength="$inv['pistas_max']" autocomplete="off" data-inv-campo="gifts" />
                    {{-- ⚠️ El `hidden` de delante NO es decorativo: una casilla sin marcar no se envía, y para el dominio una clave
                         ausente es «no lo toques». Sin él, desmarcar «enseñar mi teléfono» no lo apagaría nunca. --}}
                    <input type="hidden" name="show_host_phone" value="0">
                    <x-pieza.casilla id="pli-tel" name="show_host_phone" value="1" :label="__('fiesta.lista.pers.telefono')" :description="__('fiesta.lista.invitacion.telefono', ['t' => $m['reserva']['anfitriona']])" :checked="$inv['telefono']" data-inv-campo="phone" />
                    <div><x-pieza.boton variant="quiet" size="sm" data-pers-cerrar>{{ __('fiesta.lista.invitacion.listo') }}</x-pieza.boton></div>
                </div>
            </div>
        </div>
    @endif
    @if ($inv['no_caben'] > 0)
        <x-pieza.aviso tone="warn" size="sm" role="status" :title="__('guestform.invite.overflow_title')"><x-slot:icono><x-lucide name="triangle-alert" :size="17" /></x-slot:icono>{{ '' }}{{ trans_choice('guestform.invite.overflow', $inv['no_caben'], ['count' => $inv['no_caben']]) }}</x-pieza.aviso>
    @endif
</section>
