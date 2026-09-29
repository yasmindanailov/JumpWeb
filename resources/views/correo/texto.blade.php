{{-- LA VERSIÓN DE TEXTO de todos los correos del molde: cada bloque por su gemela (`correo/texto/<tipo>`), separados
     por una línea en blanco (`MailDocument::texto()`). Sin la línea de adelanto: en texto no hay bandeja a la que
     adelantarse y repetiría la primera frase. --}}
{!! $correo->texto() !!}
