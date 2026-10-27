@props(['title' => null])

@php
    $name = $branding->companyName();
    $pageTitle = filled($title) ? $title.' — '.$name : $name;
    $favicon = $branding->faviconUrl();
@endphp
<title>{{ $pageTitle }}</title>
<link rel="icon" href="{{ $favicon }}">
<link rel="apple-touch-icon" href="{{ $favicon }}">
<link rel="stylesheet" href="{{ asset('css/responsive.css') }}{{ file_exists(public_path('css/responsive.css')) ? '?v='.filemtime(public_path('css/responsive.css')) : '' }}">
<style>
    :root {
        --brand: {{ $branding->brandColor() }};
        --brand-dark: {{ $branding->brandColorDark() }};
        --brand-light: {{ $branding->brandColorLight() }};
    }

    /* Shared date / datetime fields */
    .date-field-wrap {
        position: relative;
        width: 100%;
        min-width: 0;
    }
    .date-field-wrap > .date-field-icon {
        position: absolute;
        left: 0.85rem;
        top: 50%;
        transform: translateY(-50%);
        width: 1.05rem;
        height: 1.05rem;
        color: var(--brand);
        pointer-events: none;
        z-index: 1;
    }
    input.date-field,
    input[type="date"],
    input[type="datetime-local"] {
        color-scheme: light;
        font-variant-numeric: tabular-nums;
        letter-spacing: -0.01em;
    }
    input.date-field {
        width: 100%;
        min-width: 0;
        height: 2.75rem;
        border-radius: 0.75rem;
        border: 1px solid #E5E7EB;
        background: #fff;
        padding: 0 0.75rem 0 2.55rem;
        font-size: 0.875rem;
        font-weight: 500;
        color: #111827;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
        transition: border-color 0.15s ease, box-shadow 0.15s ease, background-color 0.15s ease;
    }
    input.date-field:hover {
        border-color: color-mix(in srgb, var(--brand) 35%, #E5E7EB);
    }
    input.date-field:focus {
        outline: none;
        border-color: var(--brand);
        box-shadow: 0 0 0 3px color-mix(in srgb, var(--brand) 16%, transparent);
    }
    input.date-field:disabled {
        opacity: 0.55;
        cursor: not-allowed;
        background: #F8FAFC;
    }
    input.date-field.date-field--error {
        border-color: #DC2626;
    }
    input.date-field.date-field--sm {
        height: 2.5rem;
        border-radius: 0.65rem;
        font-size: 0.8125rem;
        padding-left: 2.35rem;
    }
    input.date-field::-webkit-calendar-picker-indicator {
        cursor: pointer;
        opacity: 0.45;
        margin-left: 0.25rem;
        padding: 0.2rem;
        border-radius: 0.35rem;
        transition: opacity 0.15s ease, background-color 0.15s ease;
    }
    input.date-field:hover::-webkit-calendar-picker-indicator,
    input.date-field:focus::-webkit-calendar-picker-indicator {
        opacity: 0.85;
        background-color: color-mix(in srgb, var(--brand) 10%, transparent);
    }
    input[type="datetime-local"].date-field {
        min-width: 0;
    }

    /* Split date + time fields (shared public + staff) */
    .dt-split-heading {
        margin: 0 0 0.45rem;
        font-size: 0.8125rem;
        font-weight: 600;
        color: #111827;
        line-height: 1.2;
    }
    .dt-split-row {
        display: grid;
        grid-template-columns: minmax(0, 1.45fr) minmax(5.25rem, 0.7fr);
        gap: 0.45rem;
        min-width: 0;
    }
    .dt-split-row--compact {
        grid-template-columns: minmax(0, 1.35fr) minmax(4.5rem, 0.75fr);
        gap: 0.35rem;
    }
    .dt-split-box {
        position: relative;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        width: 100%;
        min-width: 0;
        min-height: 3.1rem;
        padding: 0.65rem 0.8rem;
        border: 1px solid #D1D5DB;
        border-radius: 0.35rem;
        background: #fff;
        cursor: pointer;
        text-align: left;
        transition: border-color 0.15s ease, box-shadow 0.15s ease;
    }
    .dt-split-row--compact .dt-split-box {
        min-height: 2.55rem;
        padding: 0.45rem 0.65rem;
    }
    .dt-split-box:hover {
        border-color: color-mix(in srgb, var(--brand) 55%, #D1D5DB);
    }
    .dt-split-box:focus-within,
    .dt-split-box.is-active {
        border-color: var(--brand);
        box-shadow: 0 0 0 1px var(--brand);
        outline: none;
    }
    .dt-split-icon {
        width: 1.1rem;
        height: 1.1rem;
        flex-shrink: 0;
        color: var(--brand);
        pointer-events: none;
    }
    .dt-split-text {
        font-size: 0.9rem;
        font-weight: 500;
        color: #9CA3AF;
        line-height: 1.2;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        pointer-events: none;
    }
    .dt-split-row--compact .dt-split-text {
        font-size: 0.8125rem;
    }
    .dt-split-text.is-filled {
        color: #111827;
        font-weight: 600;
    }
    .dt-split-native {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
        opacity: 0;
        border: 0;
        pointer-events: none;
        color-scheme: light;
        font-size: 16px;
    }
</style>
