{{-- La gemela de texto de la cabecera. ⚠️ `{!! !!}` en TODAS las gemelas: la versión de texto se pinta tal cual, y
     `{{ }}` escribiría `&amp;` donde el cliente lee «&» (`CorreoTextoTest`). --}}
{!! $b['chapa'] !== null ? $b['chapa']."\n" : '' !!}{!! $b['titulo'] !!}
