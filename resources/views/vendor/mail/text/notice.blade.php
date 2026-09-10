@props(['titulo', 'tono' => 'info'])
{{-- EL AVISO, en texto plano (`#503`). El gemelo obligatorio: ver la nota de `text/hero`. --}}
{{ $titulo }}
{{ $slot }}
