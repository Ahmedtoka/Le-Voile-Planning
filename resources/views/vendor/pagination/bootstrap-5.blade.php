@if ($paginator->hasPages())
    <nav class="d-flex align-items-center justify-content-between flex-wrap gap-2" aria-label="التنقل بين الصفحات">
        {{-- الموبايل: السابق / التالي بس --}}
        <div class="d-flex justify-content-between flex-fill d-sm-none">
            <ul class="pagination pagination-sm mb-0">
                {{-- Previous Page Link --}}
                @if ($paginator->onFirstPage())
                    <li class="page-item disabled" aria-disabled="true">
                        <span class="page-link"><i class="bi bi-chevron-right" aria-hidden="true"></i> السابق</span>
                    </li>
                @else
                    <li class="page-item">
                        <a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev"><i class="bi bi-chevron-right" aria-hidden="true"></i> السابق</a>
                    </li>
                @endif
            </ul>
            <span class="hint align-self-center num">{{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</span>
            <ul class="pagination pagination-sm mb-0">
                {{-- Next Page Link --}}
                @if ($paginator->hasMorePages())
                    <li class="page-item">
                        <a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next">التالي <i class="bi bi-chevron-left" aria-hidden="true"></i></a>
                    </li>
                @else
                    <li class="page-item disabled" aria-disabled="true">
                        <span class="page-link">التالي <i class="bi bi-chevron-left" aria-hidden="true"></i></span>
                    </li>
                @endif
            </ul>
        </div>

        {{-- الشاشة الواسعة: «عرض ١–٢٥ من ٧٨» + أرقام الصفحات --}}
        <div class="d-none flex-sm-fill d-sm-flex align-items-sm-center justify-content-sm-between gap-2">
            <div>
                <p class="hint mb-0">
                    عرض <span class="fw-semibold num">{{ $paginator->firstItem() }}–{{ $paginator->lastItem() }}</span>
                    من <span class="fw-semibold num">{{ number_format($paginator->total()) }}</span>
                </p>
            </div>

            <div>
                <ul class="pagination pagination-sm mb-0">
                    {{-- Previous Page Link --}}
                    @if ($paginator->onFirstPage())
                        <li class="page-item disabled" aria-disabled="true" aria-label="السابق">
                            <span class="page-link" aria-hidden="true"><i class="bi bi-chevron-right"></i></span>
                        </li>
                    @else
                        <li class="page-item">
                            <a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="السابق"><i class="bi bi-chevron-right" aria-hidden="true"></i></a>
                        </li>
                    @endif

                    {{-- Pagination Elements --}}
                    @foreach ($elements as $element)
                        {{-- "Three Dots" Separator --}}
                        @if (is_string($element))
                            <li class="page-item disabled" aria-disabled="true"><span class="page-link">{{ $element }}</span></li>
                        @endif

                        {{-- Array Of Links --}}
                        @if (is_array($element))
                            @foreach ($element as $page => $url)
                                @if ($page == $paginator->currentPage())
                                    <li class="page-item active" aria-current="page"><span class="page-link num">{{ $page }}</span></li>
                                @else
                                    <li class="page-item"><a class="page-link num" href="{{ $url }}" aria-label="صفحة {{ $page }}">{{ $page }}</a></li>
                                @endif
                            @endforeach
                        @endif
                    @endforeach

                    {{-- Next Page Link --}}
                    @if ($paginator->hasMorePages())
                        <li class="page-item">
                            <a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="التالي"><i class="bi bi-chevron-left" aria-hidden="true"></i></a>
                        </li>
                    @else
                        <li class="page-item disabled" aria-disabled="true" aria-label="التالي">
                            <span class="page-link" aria-hidden="true"><i class="bi bi-chevron-left"></i></span>
                        </li>
                    @endif
                </ul>
            </div>
        </div>
    </nav>
    <style>
    /* ترقيم مضغوط: كل زرار 34px، أيقونات للسابق/التالي */
    .pagination .page-link{min-width:34px;height:34px;padding:0 8px;display:inline-flex;align-items:center;justify-content:center;gap:4px;
      font-size:var(--fs-sm);font-weight:600;line-height:1}
    .pagination .page-link .bi{font-size:.8rem}
    .pagination .page-item.disabled .page-link{color:var(--lv-faint);background:var(--lv-white)}
    </style>
@endif
