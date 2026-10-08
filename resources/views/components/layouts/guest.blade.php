<!DOCTYPE html>
<html lang="pt-BR">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{{ $title ?? 'Acesso' }} · GTEM</title><link rel="stylesheet" href="{{ asset('app.css') }}"></head>
<body class="auth-body">
<main class="auth-shell">
    <section class="auth-story"><a class="brand" href="{{ route('login') }}"><span class="brand-mark"><x-icon name="bus"/></span><span>GTEM<small>TRANSPORTE ESCOLAR</small></span></a>
    <div class="auth-story-copy"><span class="eyebrow">GESTÃO PÚBLICA, MAIS PRÓXIMA</span><h1>Cada trajeto começa<br>com uma boa gestão.</h1><p>Uma base organizada para conectar escolas, famílias e o transporte do município.</p><div class="route-art" aria-hidden="true"><span class="route-point"><x-icon name="pin"/></span><span class="route-line"></span><span class="route-point"><x-icon name="bus"/></span><span class="route-line"></span><span class="route-point"><x-icon name="school"/></span></div></div>
    <small>Gestão do Transporte Escolar Municipal</small></section>
    <section class="auth-content"><div class="auth-card">
    {{ $slot }}
    </div><small class="auth-footnote">Acesso restrito aos usuários autorizados pela Secretaria.</small></section>
</main>
</body></html>
