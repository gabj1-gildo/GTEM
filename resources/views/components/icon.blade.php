@props(['name' => 'grid'])
@php
$paths = [
'grid' => '<rect x="3" y="3" width="7" height="7" rx="2"/><rect x="14" y="3" width="7" height="7" rx="2"/><rect x="3" y="14" width="7" height="7" rx="2"/><rect x="14" y="14" width="7" height="7" rx="2"/>',
'school' => '<path d="m3 10 9-7 9 7M5 9v12h14V9M9 21v-7h6v7M10 8h4"/>',
'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4M17 3v4M3 11h18M8 15h2M14 15h2"/>',
'users' => '<circle cx="9" cy="8" r="3"/><path d="M3 21v-2a6 6 0 0 1 12 0v2M16 5a3 3 0 0 1 0 6M21 21v-2a6 6 0 0 0-4-5"/>',
'shield' => '<path d="m12 3 8 3v5c0 6-8 10-8 10S4 17 4 11V6l8-3Z"/><path d="m8 12 3 3 5-6"/>',
'pin' => '<path d="M20 10c0 6-8 11-8 11S4 16 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="3"/>',
'list' => '<path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>',
'arrow' => '<path d="M5 12h14m-5-5 5 5-5 5"/>',
'plus' => '<path d="M12 5v14M5 12h14"/>',
'logout' => '<path d="M9 4H4v16h5M9 12h12m-5-5 5 5-5 5"/>',
'bus' => '<rect x="5" y="3" width="14" height="17" rx="3"/><path d="M5 11h14M9 3v8M5 7H3v5m16-5h2v5M8 20v2m8-2v2M8 16h1m6 0h1"/>',
'search' => '<circle cx="10" cy="10" r="6"/><path d="m15 15 6 6"/>',
'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
];
@endphp
<svg {{ $attributes->merge(['class' => 'icon']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $paths[$name] ?? $paths['grid'] !!}</svg>
