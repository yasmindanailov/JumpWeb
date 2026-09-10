{{-- `:preheader` es LA LÍNEA DE ADELANTO (`#506`) — lo que el gestor de correo enseña detrás
     del asunto en la bandeja. Viaja como atributo hasta `layout`, que es el único sitio donde
     puede ir ANTES de la cabecera: metida en el cuerpo, lo primero que se lee sigue siendo el
     `alt` del logotipo. La notificación no la escribe: la deriva `hero()` de su grupo. --}}
<x-mail::message :preheader="$preheader ?? null">
{{-- LA CABECERA EN TINTA (`#503`) — chapa + titular + resguardo, la caja oscura que abre el correo.
     La notificación aporta DATOS (`viewData['hero']`), no HTML: componer marcado dentro de una
     notificación es cómo se acaba con veintitrés moldes en vez de uno.
     ❗ Y SUSTITUYE AL SALUDO, no se suma: el artboard no tiene «¡Hola!» en ninguno de los cuatro —
     lo primero que se lee es el estado y el titular. Un correo con hero Y saludo tendría dos
     aperturas. --}}
@isset($hero)
<x-mail::hero
    :chapa="$hero['chapa'] ?? null"
    :tono="$hero['tono'] ?? 'info'"
    :titulo="$hero['titulo']"
    :datos="$hero['datos'] ?? []" />
@else
{{-- Greeting --}}
@if (! empty($greeting))
# {{ $greeting }}
@else
@if ($level === 'error')
# @lang('Whoops!')
@else
# @lang('Hello!')
@endif
@endif
@endisset

{{-- Intro Lines --}}
@foreach ($introLines as $line)
{{ $line }}

@endforeach

{{-- EL AVISO (`#503`) — la caja de tinte con su punto: lo que hay que saber antes de venir, el
     motivo de un pago denegado, la frase de que un enlace se puede repartir. Va ENTRE el cuerpo y el
     botón, que es el orden del artboard: se lee antes de decidir. --}}
@isset($notice)
<x-mail::notice :titulo="$notice['titulo']" :tono="$notice['tono'] ?? 'info'">
{{ $notice['texto'] }}
</x-mail::notice>
@endisset

{{-- Action Button --}}
@isset($actionText)
<?php
    /**
     * ❗❗❗ EL MAPA DEL NARANJA, TAMBIÉN EN LOS CORREOS (`[DECIDIDO owner, 2026-09-10]`, `#503`).
     *
     * El naranja SOLO significa comprar. De los veintiuno que lee un cliente, únicamente DOS
     * venden —«Reintentar el pago» y «Hacer una nueva reserva»—; los otros diecinueve llevan a
     * mirar, a rellenar o a firmar, y van en RELLENO DE TINTA. Es la misma decisión que ya rige el
     * cajón y Mi Play Jump, aplicada al último sitio donde faltaba.
     *
     * ⚠️ Hasta hoy el defecto era `primary` para todos, así que «ver mis reservas» gritaba igual
     * que «reintentar el pago». Medido: **15 correos con botón y ninguno declaraba `level`**.
     *
     * ▶ `level` es la única palanca que Laravel ofrece aquí (`action()` no acepta color), así que
     * los dos que venden la declaran con `->level('sell')`. Lo vigila `MailButtonMapTest`: si un
     * tercer correo la usa, o si uno de esos dos deja de usarla, se pone rojo.
     */
    $color = match ($level) {
        'success', 'error' => $level,
        'sell' => 'accion',
        default => 'ink',
    };
?>
<x-mail::button :url="$actionUrl" :color="$color">
{{ $actionText }}
</x-mail::button>
@endisset

{{-- Outro Lines --}}
@foreach ($outroLines as $line)
{{ $line }}

@endforeach

{{-- Salutation --}}
{{-- ❗❗ EL NEGOCIO, NO EL PRODUCTO. Publicada del framework el 2026-09-10 (byte a byte idéntica)
     para cambiar EXACTAMENTE esta línea: firmaba con `config('app.name')`, o sea con el nombre
     del PRODUCTO. Medido: con `business.name = SaltoPark` el mismo correo decía «SaltoPark» en
     la cabecera y «JumpWeb» en la firma y en el pie. Es `DECISIONES #1` del revés — el repo es
     el producto «sin marca de ningún cliente», y aquí era la marca del producto colándose en la
     bandeja del cliente. Alcanza a **20 de los 21** correos que lee una persona: solo
     `GuardianAuthorizationSigned` pone `->salutation()` propia. --}}
@if (! empty($salutation))
{{ $salutation }}
@else
@lang('Regards,')<br>
{{ \App\Domain\Platform\Models\Setting::businessName() }}
@endif

{{-- Subcopy --}}
@isset($actionText)
<x-slot:subcopy>
@lang(
    "If you're having trouble clicking the \":actionText\" button, copy and paste the URL below\n".
    'into your web browser:',
    [
        'actionText' => $actionText,
    ]
) <span class="break-all">[{{ $displayableActionUrl }}]({{ $actionUrl }})</span>
</x-slot:subcopy>
@endisset
</x-mail::message>
