@props(['paginator'])

{{-- Paginación sobria del sitio público (#179): prev/next + "Página X de Y".
     No usa la vista de paginación de Laravel (Tailwind) porque la web pública
     va con CSS propio (`site.css`). Solo se renderiza si hay más de una página. --}}
@if ($paginator->hasPages())
    <nav class="pagination" role="navigation" aria-label="{{ __('account.orders.pagination.label') }}">
        @if ($paginator->onFirstPage())
            <span class="pagination__btn pagination__btn--disabled" aria-disabled="true">{{ __('account.orders.pagination.prev') }}</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="pagination__btn">{{ __('account.orders.pagination.prev') }}</a>
        @endif

        <span class="pagination__info">
            {{ __('account.orders.pagination.page', ['current' => $paginator->currentPage(), 'last' => $paginator->lastPage()]) }}
        </span>

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="pagination__btn">{{ __('account.orders.pagination.next') }}</a>
        @else
            <span class="pagination__btn pagination__btn--disabled" aria-disabled="true">{{ __('account.orders.pagination.next') }}</span>
        @endif
    </nav>
@endif
