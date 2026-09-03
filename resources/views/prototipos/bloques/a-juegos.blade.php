{{-- 3 · ¿QUÉ HAY DENTRO? (forma A) --}}
<section class="section wrap pg-q">
    <h2 class="pg-q__ask pg-q__ask--pregunta">¿Qué hay dentro?</h2>
    <p class="pg-q__answer" x-text="'Las atracciones de la zona ' + ({{ \Illuminate\Support\Js::from($zones->mapWithKeys(fn ($z) => [$z->slug => $z->tr('name')])) }})[zone] + '. Cambia de zona arriba para ver la otra.'"></p>
    @include('prototipos.partials.juegos')
</section>
<hr class="pg-hairline wrap">
