@php
    /**
     * Branded checkbox that renders a check and dash (indeterminate) via
     * inline SVG backgrounds — no peer-nesting tricks, no wrapper span.
     *
     * Props:
     *   - $name       (string|null)
     *   - $value      (string|int|null)
     *   - $checked    (bool, default false)
     *   - $ariaLabel  (string|null)
     *   - $attributes (string, verbatim extra attributes)
     *   - $size       ('sm'|'md', default 'md')
     */
    $size = $size ?? 'md';
    $box = $size === 'sm' ? 'h-4 w-4' : 'h-[18px] w-[18px]';
    $ariaLabel = $ariaLabel ?? null;
    $name = $name ?? null;
    $value = $value ?? null;
    $checked = $checked ?? false;
    $attributes = $attributes ?? '';
@endphp
<input type="checkbox"
       class="jm-checkbox {{ $box }} shrink-0 cursor-pointer appearance-none rounded-[5px] border border-border bg-card align-middle transition-colors hover:border-primary/70 checked:border-primary checked:bg-primary checked:bg-center checked:bg-no-repeat focus:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1 focus-visible:ring-offset-background disabled:cursor-not-allowed disabled:opacity-50 indeterminate:border-primary indeterminate:bg-primary indeterminate:bg-center indeterminate:bg-no-repeat"
       @if($name) name="{{ $name }}" @endif
       @if($value !== null) value="{{ $value }}" @endif
       @if($checked) checked @endif
       @if($ariaLabel) aria-label="{{ $ariaLabel }}" @endif
       {!! $attributes !!}>
