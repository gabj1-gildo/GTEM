<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Painel' }} · GTEM</title>
    <link rel="stylesheet" href="{{ asset('app.css') }}">
    <link rel="stylesheet" href="{{ asset('people.css') }}">
    <link rel="stylesheet" href="{{ asset('transport.css') }}">
    @livewireStyles
</head>
<body x-data="{ menu: false }">
<a class="skip-link" href="#main">Ir para o conteúdo</a>
<div class="app-shell">
    <div x-show="menu" x-cloak class="sidebar-overlay" @click="menu = false"></div>
    <aside class="sidebar" :class="{ 'is-open': menu }">
        <a href="{{ route('dashboard') }}" class="brand"><span class="brand-mark"><x-icon name="bus"/></span><span>GTEM<small>TRANSPORTE ESCOLAR</small></span></a>
        <div class="workspace-tag"><span class="status-dot"></span> Secretaria de Educação</div>
        <nav aria-label="Navegação principal">
            <p class="nav-label">PRINCIPAL</p>
            @can('painel.visualizar')<a class="nav-link {{ request()->routeIs('dashboard') ? 'selected' : '' }}" href="{{ route('dashboard') }}"><x-icon name="grid"/> Visão geral</a>@endcan
            @can('pessoas.visualizar')
            <p class="nav-label">ALUNOS E FAMÍLIAS</p>
            <a class="nav-link {{ request()->is('pessoas/alunos*') ? 'selected' : '' }}" href="{{ route('people','alunos') }}"><x-icon name="users"/> Alunos</a>
            <a class="nav-link {{ request()->is('pessoas/responsaveis*') ? 'selected' : '' }}" href="{{ route('people','responsaveis') }}"><x-icon name="shield"/> Responsáveis</a>
            @endcan
            @can('transporte.visualizar')
            <p class="nav-label">TRANSPORTE</p>
            @foreach(['linhas'=>['pin','Linhas'],'operacoes'=>['clock','Operações das linhas'],'veiculos'=>['bus','Veículos']] as $key=>[$icon,$label])
            <a class="nav-link {{ request()->is('transporte/'.$key.'*') ? 'selected' : '' }}" href="{{ route('transport',$key) }}"><x-icon :name="$icon"/> {{ $label }}</a>
            @endforeach
            @endcan
            @can('cadastros.visualizar')
            <p class="nav-label">BASE ESCOLAR</p>
            @foreach(['anos-letivos' => ['calendar','Anos letivos'], 'periodos' => ['clock','Períodos de inscrição'], 'escolas' => ['school','Escolas'], 'series' => ['list','Séries / anos escolares'], 'turnos' => ['clock','Turnos'], 'localidades' => ['pin','Localidades'], 'ofertas' => ['grid','Oferta escolar']] as $key => [$icon,$label])
                <a class="nav-link {{ request()->is('cadastros/'.$key) ? 'selected' : '' }}" href="{{ route('catalog',$key) }}"><x-icon :name="$icon"/> {{ $label }}</a>
            @endforeach
            @endcan
            <p class="nav-label">ADMINISTRAÇÃO</p>
            @can('usuarios.gerenciar')<a class="nav-link {{ request()->routeIs('users') ? 'selected' : '' }}" href="{{ route('users') }}"><x-icon name="users"/> Usuários</a>@endcan
            @can('perfis.gerenciar')<a class="nav-link {{ request()->routeIs('roles') ? 'selected' : '' }}" href="{{ route('roles') }}"><x-icon name="shield"/> Perfis e permissões</a>@endcan
            @can('auditoria.visualizar')<a class="nav-link {{ request()->routeIs('audit') ? 'selected' : '' }}" href="{{ route('audit') }}"><x-icon name="list"/> Auditoria</a>@endcan
        </nav>
        <div class="sidebar-bottom"><span class="version">GTEM · Desenvolvimento inicial</span><small>Organização para ir mais longe.</small></div>
    </aside>
    <div class="main-shell">
        <header class="topbar">
            <div class="breadcrumb"><button type="button" class="mobile-menu" @click="menu = !menu" aria-label="Abrir menu"><x-icon name="list"/></button><span>Ambiente administrativo</span><span class="breadcrumb-divider">/</span><strong>{{ $title ?? 'Painel' }}</strong></div>
            <div class="user-area"><span class="avatar">{{ mb_substr(auth()->user()->name,0,1) }}</span><span class="user-info">{{ auth()->user()->name }}<small>{{ auth()->user()->role->name }}</small></span><form method="post" action="{{ route('logout') }}">@csrf<button class="icon-button" aria-label="Sair"><x-icon name="logout"/></button></form></div>
        </header>
        <main id="main" class="page">
            @if(session('success'))<div class="notice success" role="status">{{ session('success') }}</div>@endif
            {{ $slot }}
        </main>
        <footer class="page-footer"><span>Gestão do Transporte Escolar Municipal</span><span>GTEM · v0.3.1</span></footer>
    </div>
</div>
@livewireScripts
</body>
</html>
