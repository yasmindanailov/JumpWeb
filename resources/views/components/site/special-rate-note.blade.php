{{-- **LOS DÍAS DE LA TARIFA ESPECIAL, UNA VEZ POR SECCIÓN.**

     `DECISIONES #479`. Es la otra mitad de la regla que sacó los días de cada chip: *«la tarifa
     especial se nombra igual en todas las superficies, con su precio entero, y los días —viernes,
     findes, festivos y vísperas— escritos una vez por sección»*.

     ⚠️⚠️ **Sin esta nota, «en tarifa especial» no significa nada.** Al retirar los días del chip la
     cifra se queda con un nombre que el visitante no ha aprendido en ninguna parte: el término hay
     que definirlo una vez, y en el sitio donde se usa. Que la nota exista es lo que hace honesta la
     poda del chip.

     ⚠️ **El texto sale del PANEL** (`rate_types.label`), no de aquí: hoy dice «Viernes, findes y
     festivos» donde el mockup escribe «finde». Acortarlo en el producto sería escribir el copy de
     este cliente dentro del código.
     ⚠️ **Sin tarifas especiales activas no se pinta nada**: vacío es una respuesta. --}}
{{-- ⚠️ La búsqueda va MEMOIZADA (`RateType::firstSpecial()`) porque esta nota la pinta cada zona de
     la sección de tarifas y también la banda de cumpleaños: sin memo serían tres o cuatro consultas
     idénticas por página para leer una tabla de dos filas. --}}
@php($rate = \App\Domain\Booking\Models\RateType::firstSpecial())

@if ($rate && $rate->tr('label'))
    <p class="rates__note">{{ __('landing.rates.special_note', ['label' => $rate->tr('label')]) }}</p>
@endif
