{{-- JUEGOS (forma B) --}}
<section class="section wrap">
    <div class="rides__head"><div><h2 class="rides__title">Juegos</h2></div><p x-text="'Los de la zona ' + ({{ \Illuminate\Support\Js::from($zones->mapWithKeys(fn ($z) => [$z->slug => $z->tr('name')])) }})[zone] + '.'"></p></div>
    @include('prototipos.partials.juegos')
</section>
