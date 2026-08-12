@props([
    'url',
    'color' => 'primary',
    'align' => 'center',
])
<table class="action" align="{{ $align }}" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="{{ $align }}">
<table width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="{{ $align }}">
<table border="0" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td>
{{-- El botón "primary" sigue el color de marca global (white-label, #7.10): se inyecta inline
     (fondo + bordes, que en email hacen de padding) y gana al `.button-primary` estático del
     tema al inlinear el CSS. Los botones semánticos (success/error) conservan su color. --}}
@php($jjBrand = \App\Domain\Content\Services\ThemeSettings::brand())
<a href="{{ $url }}" class="button button-{{ $color }}" target="_blank" rel="noopener"
@if ($color === 'primary') style="background-color: {{ $jjBrand }}; border-color: {{ $jjBrand }}; color: {{ \App\Domain\Content\Services\ThemeSettings::onBrand($jjBrand) }};" @endif>{!! $slot !!}</a>
</td>
</tr>
</table>
</td>
</tr>
</table>
</td>
</tr>
</table>
