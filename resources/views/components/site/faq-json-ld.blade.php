@props(['faqs' => []])
{{-- FAQPage (schema.org): permite que Google muestre las preguntas como desplegable enriquecido en
     el resultado. INVISIBLE para el visitante. Solo se emite si hay FAQs utilizables. --}}
@php($faqSchema = \App\Domain\Content\Services\StructuredData::faqPage($faqs))
@if ($faqSchema)
<script type="application/ld+json">{!! \App\Domain\Content\Services\StructuredData::toJson($faqSchema) !!}</script>
@endif
