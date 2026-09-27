{{-- EL DESCARGO DE QUIEN CUMPLE (F7b de `fiesta-sistema-nuevo.md` §4.13, `[DECIDIDO owner]` `#752`), bajo su fila y SOLO
     cuando falta. Un `<details>`: sin JavaScript se abre y se cierra igual. «¿Firmas tú por él?» con las dos respuestas a la
     vista, sin pasos escondidos:
       · «Sí, soy su padre, madre o tutor»: con la sesión del titular, su hijo a cargo (o uno nuevo) y la casilla — los
         campos van al formulario `fiesta-cumple` (en `auxiliares`, fuera del principal: un `form` dentro de otro no es
         HTML válido) por `form=`, así la barra de Guardar no los cuenta —; sin ella, entrar en la cuenta o su justificante.
       · «No, que lo firme su familia»: su justificante, para pasárselo a su padre o madre.
     En pantalla, «descargo» y nunca «exención» (`#339`). --}}
@php
    $doc = $fc['documento'];
    $pre = collect($fc['hijos'])->first(fn (array $h): bool => $h['pre']);
    $opcionesHijo = array_merge(
        array_map(fn (array $h): array => ['value' => (string) $h['id'], 'label' => $h['nombre']], $fc['hijos']),
        [['value' => 'nuevo', 'label' => __('fiesta.lista.cumple_firma.otro')]],
    );
    $elegido = $pre !== null ? (string) $pre['id'] : 'nuevo';
@endphp
<details class="pli-cumple-firma" id="pli-cumple-firma" data-firma-cumple @if ($fc['abierto']) open @endif>
    <summary class="pli-cumple-firma-s"><x-lucide name="pen-line" :size="17" /><span>{{ $fc['viejo'] ? __('fiesta.lista.cumple_firma.boton_viejo') : __('fiesta.lista.cumple_firma.boton') }}</span><x-lucide name="chevron-down" :size="17" class="pli-cumple-firma-v" /></summary>
    <div class="pli-cumple-firma-c">
        @if ($fc['aviso'] !== null)
            <x-pieza.aviso :tone="$fc['aviso']['tono']" size="sm" role="alert" data-cumple-aviso>{{ $fc['aviso']['texto'] }}</x-pieza.aviso>
        @endif
        <p class="pli-cumple-firma-p">{{ __('fiesta.lista.cumple_firma.pregunta', ['n' => $fc['nombre']]) }}</p>

        <section class="pli-cumple-via" data-cumple-via="si" aria-labelledby="pli-cumple-si">
            <h4 class="pli-h4" id="pli-cumple-si">{{ __('fiesta.lista.cumple_firma.si') }}</h4>
            @if ($fc['sesion'] && $doc !== null)
                <input type="hidden" name="document_id" value="{{ $doc['id'] }}" form="fiesta-cumple">
                @if ($fc['hijos'] !== [])
                    <x-pieza.selector id="pli-cumple-hijo" name="hijo" form="fiesta-cumple" :label="__('fiesta.lista.cumple_firma.cual')" :options="$opcionesHijo" :value="$elegido" data-cumple-hijo />
                @else
                    <input type="hidden" name="hijo" value="nuevo" form="fiesta-cumple">
                @endif
                {{-- Un hijo que aún no está en su cuenta: se añade y se firma en el mismo paso (como «Añade a tus hijos»). --}}
                <div class="pli-cumple-nuevo" data-cumple-nuevo>
                    <div class="pli-cumple-2">
                        <x-pieza.campo id="pli-cumple-nombre" name="minor_name" form="fiesta-cumple" :label="__('fiesta.autorizacion.nino_nombre')" :value="$fc['nombre']" autocomplete="off" />
                        <x-pieza.campo id="pli-cumple-apellidos" name="minor_surname" form="fiesta-cumple" :label="__('fiesta.autorizacion.nino_apellidos')" autocomplete="off" />
                    </div>
                    <div class="pli-cumple-2">
                        <x-pieza.campo id="pli-cumple-nacimiento" name="minor_born_on" type="date" form="fiesta-cumple" :label="__('fiesta.firma.nacimiento')" />
                        <x-pieza.selector id="pli-cumple-relacion" name="relationship" form="fiesta-cumple" :label="__('fiesta.firma.relacion')" :options="$fc['relaciones']" />
                    </div>
                </div>
                <details class="pli-cumple-descargo"><summary>{{ __('fiesta.firma.leer_descargo') }}</summary><div class="aut-descargo"><h2 class="inv-h2">{{ $doc['descargo']['titulo'] }}</h2><span class="inv-nota">{{ $doc['descargo']['version'] }}</span>@foreach ($doc['descargo']['cuerpo'] as $s)@if ($s['h'] !== '')<h3 class="aut-descargo-h">{{ $s['h'] }}</h3>@endif{{ '' }}@if ($s['p'] !== '')<p class="inv-texto">{{ $s['p'] }}</p>@endif{{ '' }}@endforeach</div></details>
                <x-pieza.casilla id="pli-cumple-casilla" name="accept_waiver" value="1" form="fiesta-cumple" :label="__('fiesta.firma.casilla_cumple')" />
                <div class="pli-acciones"><x-pieza.boton type="submit" variant="primary" size="sm" form="fiesta-cumple" data-cumple-firmar>{{ __('fiesta.lista.cumple_firma.firmar') }}</x-pieza.boton></div>
            @else
                <p class="pli-sub">{{ __('fiesta.lista.cumple_firma.sin_sesion') }}</p>
                <div class="pli-acciones">
                    <x-pieza.boton variant="secondary" size="sm" :href="$fc['entrar']" data-cumple-entrar>{{ __('fiesta.lista.cumple_firma.entrar') }}</x-pieza.boton>
                    <x-pieza.boton variant="quiet" size="sm" :href="$fc['aqui']" data-cumple-aqui>{{ __('fiesta.lista.cumple_firma.aqui') }}</x-pieza.boton>
                </div>
            @endif
        </section>

        <section class="pli-cumple-via" data-cumple-via="no" aria-labelledby="pli-cumple-no">
            <h4 class="pli-h4" id="pli-cumple-no">{{ __('fiesta.lista.cumple_firma.no') }}</h4>
            <p class="pli-sub">{{ __('fiesta.lista.cumple_firma.no_texto', ['n' => $fc['nombre']]) }}</p>
            <x-pieza.compartir :items="[['kind' => 'copy', 'label' => __('fiesta.lista.cumple_firma.copiar')], ['kind' => 'whatsapp', 'label' => 'WhatsApp', 'href' => $fc['whatsapp']]]" :value="$fc['aqui']" :confirm="__('fiesta.lista.cumple_firma.copiado')" data-cumple-compartir />
        </section>
    </div>
</details>
