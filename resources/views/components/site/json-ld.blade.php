@props(['site' => []])
{{-- Datos estructurados JSON-LD (marca + negocio local): habilitan los resultados enriquecidos de
     Google (horario, dirección, teléfono, panel de marca). INVISIBLE para el visitante. La salida la
     sanea `StructuredData::toJson` (JSON_HEX_TAG) → un valor editable no puede romper el <script>. --}}
<script type="application/ld+json">{!! \App\Domain\Content\Services\StructuredData::toJson(\App\Domain\Content\Services\StructuredData::businessGraph($site)) !!}</script>
