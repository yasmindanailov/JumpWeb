@props(['chapa' => null, 'tono' => 'info', 'titulo', 'datos' => []])
{{-- LA CABECERA, en TEXTO PLANO (`#503`).

⚠️⚠️ ESTE GEMELO NO ES OPCIONAL, Y SU AUSENCIA NO SE VE AL RENDERIZAR. Laravel compone el correo
en las DOS versiones —HTML y texto—, y busca cada componente bajo `mail::` en su propia carpeta.
Sin este fichero, `->render()` sale perfecto y **el envío revienta** con «View [hero] not found»:
el HTML no pasa por aquí, así que el defecto solo aparece cuando el correo sale de verdad.
▶ La regla: **todo componente de correo nace por partida doble.**

La versión de texto no dibuja la caja: transcribe lo que dice, que es lo que un lector de texto
necesita — el estado, el titular y el resguardo como pares «rótulo: valor». --}}
@if ($chapa)
[{{ mb_strtoupper($chapa) }}]
@endif

{{ $titulo }}
@if (! empty($datos))

@foreach ($datos as $etiqueta => $valor)
{{ $etiqueta }}: {{ $valor }}
@endforeach
@endif
