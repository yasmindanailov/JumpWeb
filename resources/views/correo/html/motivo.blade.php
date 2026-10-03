{{-- EL MOTIVO (el `motivo()` del diseño): el que da el banco, en la familia mono y aparte, sobre el sutil —se dicta igual al
     llamar al banco—. --}}
@php($t = $correo->tema)
<tr data-bloque="motivo"><td class="pjm-pad" style="padding:0 24px {{ $b['aire'] }}px;">
<table {!! $correo::TABLA !!} width="100%" class="pjm-subtle" bgcolor="{{ $t->claro('sutil') }}" style="border-collapse:separate;background:{{ $t->claro('sutil') }};border:1px solid {{ $t->claro('filete') }};border-radius:{{ $t->radio('md') }}px;"><tr><td style="padding:12px 16px;">
<p class="pjm-muted" style="margin:0;{{ $correo->ty('texto', 13, 20, 700, $t->claro('apagado')) }}">{{ $b['etiqueta'] }}</p>
<p class="pjm-strong" style="margin:0;{{ $correo->ty('mono', 14, 20, 500, $t->claro('fuerte')) }}">{{ $b['texto'] }}</p>
</td></tr></table>
</td></tr>
