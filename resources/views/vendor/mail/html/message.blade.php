@props(['preheader' => null])
<x-mail::layout :preheader="$preheader">
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="config('app.url')">
{{-- El NEGOCIO, no el producto. Hoy `html/header` IGNORA este slot y lee la BD él mismo,
     así que esto no se pinta — pero dejar aquí el nombre del PRODUCTO es una mina: el día
     que alguien haga que el componente use su slot, la fuga de `#500` vuelve en silencio. --}}
{{ \App\Domain\Platform\Models\Setting::businessName() }}
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer>
{{-- El NEGOCIO, no el producto: ver la nota de la firma en `vendor/notifications/email.blade.php`. --}}
© {{ date('Y') }} {{ \App\Domain\Platform\Models\Setting::businessName() }}. {{ __('All rights reserved.') }}
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
