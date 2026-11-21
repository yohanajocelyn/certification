@php
    $baseClass = 'min-w-9 h-9 px-3 inline-flex items-center justify-center rounded-lg text-sm transition-colors';
    $linkClass = $baseClass.' font-semibold text-primary-800 bg-white border border-primary-200 shadow-2xs hover:bg-primary-100';
    $currentClass = $baseClass.' font-bold text-white bg-primary';
    $disabledClass = $baseClass.' text-primary-300';
@endphp

@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Navigasi halaman" class="mt-6 flex flex-col sm:flex-row items-center justify-between gap-3">
        <p class="text-sm text-primary-600">
            Menampilkan {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} dari {{ $paginator->total() }}
        </p>

        <div class="flex flex-wrap items-center gap-1">
            @if ($paginator->onFirstPage())
                <span class="{{ $disabledClass }}" aria-hidden="true">&laquo;</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="{{ $linkClass }}" aria-label="Halaman sebelumnya">&laquo;</a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="{{ $disabledClass }}">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page === $paginator->currentPage())
                            <span class="{{ $currentClass }}" aria-current="page">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" class="{{ $linkClass }}" aria-label="Halaman {{ $page }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="{{ $linkClass }}" aria-label="Halaman berikutnya">&raquo;</a>
            @else
                <span class="{{ $disabledClass }}" aria-hidden="true">&raquo;</span>
            @endif
        </div>
    </nav>
@endif
