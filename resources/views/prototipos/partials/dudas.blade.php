@props(['faqs'])

{{-- DUDAS — la FAQ del panel con el marcado real; la interacción va en TINTA (el defecto del producto
     para D1), que `guion.css` impone sobre `.faq__q`. --}}
<div class="faq__list">
    @foreach ($faqs as $i => $faq)
        <div class="faq__item" :class="faqOpen==={{ $i }} && 'open'">
            <button type="button" class="faq__q" data-tap @click="faqOpen = faqOpen==={{ $i }} ? -1 : {{ $i }}"
                    :aria-expanded="faqOpen==={{ $i }} ? 'true' : 'false'" aria-controls="faq-answer-{{ $i }}">{{ $faq->tr('question') }}<span class="ico" aria-hidden="true"><x-icons.plus :width="14" :height="14" /></span></button>
            <div class="faq__a" id="faq-answer-{{ $i }}">{{ $faq->tr('answer') }}</div>
        </div>
    @endforeach
</div>
<x-site.faq-json-ld :faqs="$faqs" />
