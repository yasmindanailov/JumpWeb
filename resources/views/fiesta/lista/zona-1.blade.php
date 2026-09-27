{{-- ZONA 1 · Estado y envío (F8, `#753`: una acción por tarea). Enviada la invitación, las tres cifras son un RESUMEN
     (no filtran: hasta 50 niños, el orden de la lista ya agrupa) y, si alguien de la lista no ha contestado, «Recordárselo»
     a WhatsApp en un toque. La vista previa es la invitación de verdad, con «Enviar por WhatsApp» —el MISMO rótulo antes y
     después— y «Copiar el enlace»; «Personalizar» abre el tema, quién cumple y su edad, «te invita» y el teléfono: escriben
     `party_invitations` con el mismo Guardar. Fuera de plazo, un aviso en lugar de enviar. --}}
@php($inv = $m['invitacion'])
@php($c = $m['cuentas'])
{{-- `id="gf-invite"` es el ANCLA con la que el correo de «Compartir la invitación» (`GuestFormRequest`) y la API
     (`invitation_url` de `/me/orders`) aterrizan aquí: es contrato en correos ya enviados y se conserva con su nombre. --}}
<section class="pli-zona pli-z1" id="gf-invite" data-zona="1" aria-labelledby="pli-h1">
    @if ($inv['compartida'])
        <div class="pli-estado">
            <p aria-label="{{ __('fiesta.lista.respuestas') }}" class="pli-tally">
                @foreach ([['confirmados', $c['confirmados'], __('fiesta.lista.confirmados'), 'var(--success-500)'], ['no', $c['no_pueden'], __('fiesta.lista.no_pueden'), 'var(--fiesta-tinta-400)'], ['sin', $c['sin_contestar'], __('fiesta.lista.sin_contestar'), 'var(--warn-500)']] as [$k, $n, $label, $dot])
                    <span class="pli-cuenta"><span class="pli-cuenta-top"><span class="pli-cuenta-n" data-cuenta="{{ $k }}">{{ $n }}</span><span class="pli-dot" style="background: {{ $dot }};"></span></span><span class="pli-cuenta-l">{{ $label }}</span></span>
                @endforeach
            </p>
            {{-- RECORDAR (F8): su formulario (`fiesta-recordatorio`) abre una pestaña nueva y el servidor, tras apuntarlo,
                 la manda a WhatsApp con el mensaje escrito. Sin JavaScript, igual. Nombrar, opcional y desmarcado. --}}
            @if ($inv['respuestas_abiertas'] && $inv['faltan'] > 0)
                <div class="pli-rec" data-recordatorio>
                    {{-- De contorno: el ÚNICO principal de la zona es «Enviar por WhatsApp». --}}
                    <x-pieza.boton variant="outline" size="sm" type="submit" form="fiesta-recordatorio" data-recordatorio-escribir><x-slot:izquierda><x-lucide name="message-circle" :size="17" /></x-slot:izquierda>{{ trans_choice('fiesta.lista.recordar', $inv['faltan'], ['count' => $inv['faltan']]) }}</x-pieza.boton>
                    <x-pieza.casilla id="pli-rec-nombres" name="with_names" value="1" form="fiesta-recordatorio" :label="trans_choice('guestform.invite.remind_names', $inv['faltan'], ['count' => $inv['faltan']])" />
                    @if ($inv['recordado_veces'] > 0 && $inv['recordado_el'] !== '')
                        <p class="pli-sub">{{ trans_choice('guestform.invite.remind_last', $inv['recordado_veces'], ['count' => $inv['recordado_veces'], 'when' => $inv['recordado_el']]) }}</p>
                    @endif
                </div>
            @endif
            <span class="pli-vivo" role="status" aria-live="polite" data-aviso-vivo></span>
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
                {{-- ENVIAR (F8): el único primario, siempre el mismo. El enlace a WhatsApp va DIRECTO; `data-envio` lo apunta
                     al pulsarse (`lista.js`, por su POST firmado), y el enlace que viaja dice su canal (`?c=wa`). --}}
                <x-pieza.boton variant="secondary" size="md" full :href="$inv['whatsapp']" target="_blank" rel="noopener noreferrer" data-envio="whatsapp" data-envio-donde="invitation"><x-slot:izquierda><x-lucide name="message-circle" :size="19" /></x-slot:izquierda>{{ __('fiesta.lista.enviar') }}</x-pieza.boton>
                <div class="pli-inv-fila">
                    <x-pieza.compartir :items="[['kind' => 'copy', 'label' => __('fiesta.lista.copiar')]]" :value="$inv['url_copia']" :confirm="__('fiesta.lista.invitacion.copiado')" data-envio="copy" data-envio-donde="invitation" />
                    <x-pieza.boton variant="ghost" size="sm" aria-expanded="false" aria-controls="pli-pers" data-pers-abrir><x-slot:izquierda><x-lucide name="palette" :size="17" /></x-slot:izquierda>{{ __('fiesta.lista.personalizar') }}</x-pieza.boton>
                </div>
                <p class="pli-nota" hidden data-inv-nota><x-lucide name="info" :size="15" />{{ __('fiesta.lista.invitacion.cambios') }}</p>
                {{-- PERSONALIZAR: escribe SOLO `party_invitations`, con el Guardar de la página (`store()` lo reparte). Sin
                     JavaScript sale abierto; con él lo abre el botón. --}}
                <div id="pli-pers" class="pli-pers" data-pers>
                    <x-fiesta.tema vivo :label="__('fiesta.lista.pers.tema')" name="theme" :items="$inv['temas']" :value="$inv['tema']" :age="$m['cumple']['edad']" />
                    {{-- ⚠️ Con la fila de quien cumple (F3a, `#747`) estos dos son un ESPEJO de su fila en la lista («se escriben
                         una vez»): sin `name`, no viajan; `lista.js` los ata a los campos de la ficha 0, que son los que se
                         guardan. Sin ella (las reservas de antes), escriben la invitación como siempre. --}}
                    @php($espejo = $m['cumple']['fila'] ? collect($m['ninos'])->first(fn (array $n): bool => $n['origen'] === 'cumple') : null)
                    <div class="pli-pers-2">
                        <x-pieza.campo id="pli-quien" :name="$espejo === null ? 'honoree_name' : null" :label="__('fiesta.lista.pers.quien')" :value="$espejo['nombre'] ?? $m['cumple']['nombre']" :maxlength="$inv['honoree_max']" autocomplete="off" data-inv-campo="name" :data-cumple-espejo="$espejo === null ? null : 'name'" />
                        <x-pieza.campo id="pli-su-edad" :name="$espejo === null ? 'honoree_age' : null" :label="__('fiesta.lista.pers.edad')" :value="$espejo['edad'] ?? $m['cumple']['edad']" inputmode="numeric" maxlength="2" data-inv-campo="age" :data-cumple-espejo="$espejo === null ? null : 'age'"><x-slot:sufijo>{{ __('fiesta.invitacion.unit') }}</x-slot:sufijo></x-pieza.campo>
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
