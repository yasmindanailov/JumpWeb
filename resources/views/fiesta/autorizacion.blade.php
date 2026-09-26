{{--
    LA AUTORIZACIÓN de un menor invitado, del sistema nuevo (`specs/fiesta-sistema-nuevo.md` §4.2, T3; `#743`, `#745`):
    el padre de un invitado firma la autorización y el descargo en dos minutos, sin cuenta, con el diseño
    `paginas/autorizacion.card.html`. La misma cabecera y la misma tarjeta que la invitación, con el titular de la
    autorización; debajo, la firma (`x-fiesta.firma`). Lee SOLO el modelo `$m` (`App\Http\Fiesta\Autorizacion`).

    ❗❗ HOJA EN BLANCO: no lista ni un dato de las autorizaciones ya firmadas y el niño nunca viene puesto.
    ⚠️ Sin JavaScript firma igual: el formulario es un POST normal; el JS añade el selector de menores a cargo (rellena,
       no envía), «Firmando» y el foco en el primer error.
    ⚠️ Lo que el diseño no dibuja (sin A): el bloqueo (no pagada, pasada, llena) con la forma del «enlace que no vale»;
       los desenlaces que no son el Listo; el texto del descargo en el flujo; el selector de menores a cargo.
    ⚠️ `$m['diagnostico']` lo pone SOLO el banco (`scripts/banco-fiesta.php`): omite lo que el producto pide y el brief no
       (nacimiento, relación, el descargo en el flujo, la frase de los datos no verificados) para probar que TODO lo
       demás es idéntico al mockup. La página viva nunca lo lleva.
--}}
@php($hojas = $hojas ?? [])
@php($diagnostico = (bool) ($m['diagnostico'] ?? false))
<x-pagina-enfocada :titulo="$m['titulo_pagina']" entrada="autorizacion" :hojas="$hojas">
    <div class="inv-fondo" style="background-color: {{ $m['tema']['tinte'] }}; --inv-accent: {{ $m['tema']['acento'] }}; --inv-tint: {{ $m['tema']['tinte'] }};" data-authorization-page data-theme="{{ $m['tema']['clave'] }}">
        <main class="inv">
            @include('fiesta.invitacion.cabecera')
            <div class="inv-card">
                <x-fiesta.invitacion :theme="$m['tema']['clave']" :age="$m['tarjeta']['edad']" :title="$m['tarjeta']['titulo']" titleTag="h1" :host="$m['anfitrion']['linea']" :phone="$m['anfitrion']['telefono']" :phoneHref="$m['anfitrion']['tel']" :labels="['host' => $m['anfitrion']['etiqueta']]" data-invitation-card :data-theme="$m['tema']['clave']">
                    <p class="inv-texto fuerte" data-guardian-what>{{ $m['tarjeta']['linea'] }}</p><p class="inv-texto">{{ $m['tarjeta']['que'] }}</p>
                </x-fiesta.invitacion>
            </div>
            @if ($m['bloqueado'] !== null)
                {{-- No se puede firmar: se dice POR QUÉ, con la forma del «enlace que no vale» del diseño, y sin formulario.
                     La puerta que manda sigue en el dominio; esto solo evita un envío inútil. --}}
                <section class="inv-sec aut-caducado" data-guardian-blocked="{{ $m['bloqueado']['motivo'] }}">
                    <span class="aut-caducado-ic" aria-hidden="true"><x-lucide name="lock" :size="22" /></span>
                    <h2 class="aut-fin-t">{{ $m['bloqueado']['titulo'] }}</h2>
                    <p class="inv-texto">{{ $m['bloqueado']['texto'] }}</p>
                    <x-pieza.enlace size="sm" underline="always" :href="$m['privacidad']['enlace']">{{ $m['privacidad']['politica'] }}</x-pieza.enlace>
                </section>
            @else
                @if ($m['aviso'] !== null)
                    <x-pieza.aviso :tone="$m['aviso']['tono']" :title="$m['aviso']['titulo']" :role="$m['aviso']['rol']" data-guardian-outcome="{{ $m['aviso']['tono'] === 'neutral' ? 'already' : ($m['aviso']['tono'] === 'warn' ? 'stale' : 'refused') }}">{{ $m['aviso']['texto'] }}</x-pieza.aviso>
                @endif
                @if ($m['listo'] !== null)
                    {{-- El Listo del brief: firmada, con el niño y quien firma. Sin formulario: un segundo hijo, con el enlace otra vez. --}}
                    <section class="inv-sec" aria-label="{{ __('fiesta.firma.firmar') }}">
                        <div class="aut-fin" id="aut-listo" tabindex="-1" role="status" data-guardian-outcome="signed">
                            <x-pieza.aviso tone="success" size="md"><x-slot:icono><x-lucide name="circle-check" :size="20" /></x-slot:icono>{{ '' }}{{ $m['listo']['texto'] }}</x-pieza.aviso>
                            @if ($m['listo']['quien'] !== '' || $m['listo']['firmante'] !== '')<p class="aut-quien">{{ $m['listo']['quien'] }}@if ($m['listo']['firmante'] !== '')<span>{{ $m['listo']['firmante'] }}</span>@endif</p>@endif
                        </div>
                    </section>
                @else
                    @php($f = $m['formulario'])
                    <section class="inv-sec" aria-label="{{ __('fiesta.firma.firmar') }}" data-guardian-form>
                        @if ($f['menores'] !== [])
                            {{-- Con sesión, el menor se ELIGE (§12.5): rellena los campos y no envía; «a mano» los vacía. --}}
                            <x-pieza.selector id="dependent_pick" :label="__('guardian.minor.pick')" :hint="__('guardian.minor.pick_help')" :options="[['value' => '', 'label' => __('guardian.minor.pick_manual')], ...$f['menores']]" data-guardian-pick-select />
                        @endif
                        @if ($f['desde_invitacion'] !== '')
                            {{-- Lo que escribió en la invitación se ENSEÑA, no se prerrellena (`#706`): lo reparte él. --}}
                            <p class="inv-nota" data-from-invitation>{{ __('guardian.minor.from_invitation', ['name' => $f['desde_invitacion']]) }}</p>
                        @endif
                        <x-fiesta.firma child idPrefix="aut" :action="$f['accion']" :valores="$f['valores']" :fallos="$f['fallos']" :labels="['box' => $f['casilla']]">
                            <x-slot:oculto>@if ($f['respuesta_id'] !== null)<input type="hidden" name="invitation_reply_id" value="{{ $f['respuesta_id'] }}">@endif<input type="hidden" name="document_id" value="{{ $f['documento_id'] }}"><div class="pz-sr" aria-hidden="true"><label for="contact_ref">Ref</label><input type="text" id="contact_ref" name="contact_ref" tabindex="-1" autocomplete="off"></div></x-slot:oculto>
                            @unless ($diagnostico)<x-slot:nino><x-pieza.campo id="aut-nacimiento" name="minor_born_on" type="date" :label="$f['nacimiento']['label']" :hint="$f['nacimiento']['hint']" :value="$f['nacimiento']['value']" :error="$f['nacimiento']['error']" data-campo-producto /></x-slot:nino>{{ '' }}@endunless
                            @unless ($diagnostico)<x-slot:adulto><x-pieza.selector id="aut-relacion" name="guardian_relationship" :label="$f['relacion']['label']" :options="$f['relacion']['opciones']" :value="$f['relacion']['value']" :error="$f['relacion']['error']" data-campo-producto /></x-slot:adulto>{{ '' }}@endunless
                            @unless ($diagnostico)<x-slot:descargo><div class="aut-descargo" id="descargo" data-guardian-waiver><h2 class="inv-h2">{{ $f['descargo']['titulo'] }}</h2><span class="inv-nota">{{ $f['descargo']['version'] }}</span>@foreach ($f['descargo']['cuerpo'] as $s)@if ($s['h'] !== '')<h3 class="aut-descargo-h">{{ $s['h'] }}</h3>@endif{{ '' }}@if ($s['p'] !== '')<p class="inv-texto">{{ $s['p'] }}</p>@endif{{ '' }}@endforeach</div></x-slot:descargo>{{ '' }}@endunless
                            <x-slot:legal><p class="inv-legal" data-guardian-privacy>{{ $m['privacidad']['texto'] }}@unless ($diagnostico) <span data-solo-producto>{{ $m['privacidad']['datos'] }}</span>@endunless <x-pieza.enlace size="sm" underline="always" :href="$m['privacidad']['enlace']">{{ $m['privacidad']['politica'] }}</x-pieza.enlace></p></x-slot:legal>
                            @if ($m['turnstile']['activo'])<x-slot:tercero><div class="inv-tercero"><span class="inv-tercero-rotulo">{{ $m['turnstile']['rotulo'] }}</span><div class="cf-turnstile" data-sitekey="{{ $m['turnstile']['clave'] }}"></div></div><script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script></x-slot:tercero>{{ '' }}@endif
                        </x-fiesta.firma>
                    </section>
                @endif
            @endif
            @include('fiesta.invitacion.idiomas')
        </main>
    </div>
</x-pagina-enfocada>
