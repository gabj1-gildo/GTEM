<div>
    <div class="page-heading"><div><span class="eyebrow">PAINEL DA SECRETARIA</span><h1>Visão geral</h1><p>A base para organizar o transporte escolar do município.</p></div><span class="date-pill"><x-icon name="calendar"/>{{ now()->format('d/m/Y') }}</span></div>
    <section class="welcome-banner"><div><span class="badge light">PRIMEIROS PASSOS</span><h2>Uma gestão conectada<br>começa com dados organizados.</h2><p>Prepare os cadastros escolares para as próximas<br class="desktop-only"> etapas de matrícula e operação do transporte.</p>
    @can('cadastros.editar')<a class="button white" href="{{ route('catalog','anos-letivos') }}">Organizar ano letivo <x-icon name="arrow"/></a>@endcan</div>
    <div class="banner-art" aria-hidden="true"><div class="art-halo"></div><div class="art-card"><x-icon name="school"/><span>ESCOLA</span></div><div class="art-path"></div><div class="art-bus"><x-icon name="bus"/></div><span class="art-caption">Planejar. Conectar. Transportar.</span></div>
    </section>
    <section class="stats-grid" aria-label="Indicadores">
    @foreach($stats as [$label,$count,$resource,$caption])<a href="{{ route('catalog',$resource) }}" class="stat-card"><div class="stat-label">{{ $label }}<x-icon :name="$resource === 'escolas' ? 'school' : ($resource === 'localidades' ? 'pin' : 'grid')"/></div><strong>{{ $count }}</strong><span>{{ $caption }}</span></a>@endforeach
    </section>
    @if($transportStats)<section class="stats-grid" aria-label="Indicadores do transporte">
    @foreach($transportStats as [$label,$count,$resource,$caption])<a href="{{ route('transport',$resource) }}" class="stat-card"><div class="stat-label">{{ $label }}<x-icon name="bus"/></div><strong>{{ $count }}</strong><span>{{ $caption }}</span></a>@endforeach
    </section>@endif
    <div class="dashboard-columns"><section class="panel">
        <div class="panel-heading"><div><h2>Anos letivos</h2><p>Ciclos cadastrados no sistema</p></div>@can('cadastros.visualizar')<a class="text-link" href="{{ route('catalog','anos-letivos') }}">Ver todos <x-icon name="arrow"/></a>@endcan</div>
        @forelse($years as $year)<div class="year-row"><span class="year-icon"><x-icon name="calendar"/></span><div><strong>Ano letivo {{ $year->year }}</strong><small>{{ $year->starts_on->format('d/m/Y') }} a {{ $year->ends_on->format('d/m/Y') }}</small></div><span class="badge {{ $year->status === 'ABERTO' ? 'green' : 'neutral' }}">{{ ['PLANEJAMENTO'=>'Planejamento','ABERTO'=>'Aberto','ENCERRADO'=>'Encerrado'][$year->status] }}</span></div>@empty<div class="empty-state"><x-icon name="calendar"/><h3>Nenhum ano letivo cadastrado</h3><p>Cadastre o primeiro ciclo para começar.</p></div>@endforelse
        <div class="panel-note"><x-icon name="shield"/> Os anos anteriores permanecem no histórico.</div>
    </section><section class="panel">
        <div class="panel-heading"><div><h2>Prepare sua base</h2><p>Uma sequência simples para começar</p></div></div>
        @foreach([['01','Ano letivo e períodos','Defina o ciclo e as janelas de inscrição.','anos-letivos'],['02','Escolas, séries e turnos','Organize as unidades e etapas de ensino.','escolas'],['03','Oferta escolar e localidades','Relacione os cadastros de atendimento.','ofertas']] as [$n,$label,$text,$resource])<div class="setup-row"><span class="step-number">{{ $n }}</span><div><strong>{{ $label }}</strong><small>{{ $text }}</small></div>@can('cadastros.visualizar')<a class="icon-button" href="{{ route('catalog',$resource) }}" aria-label="{{ $label }}"><x-icon name="arrow"/></a>@endcan</div>@endforeach
    </section></div>
    @can('auditoria.visualizar')<section class="panel recent-panel"><div class="panel-heading"><div><h2>Atividades recentes</h2><p>Rastreabilidade das ações administrativas</p></div><a class="text-link" href="{{ route('audit') }}">Abrir auditoria <x-icon name="arrow"/></a></div>
    @forelse($events as $event)<div class="activity-row"><span class="activity-dot"></span><div><strong>{{ str_replace('_',' ',ucfirst($event->action)) }}</strong><small>{{ $event->actor?->name ?? 'Sistema / comando' }} · {{ $event->entity }} #{{ $event->entity_id }}</small></div><time>{{ $event->created_at->format('d/m H:i') }}</time></div>@empty<div class="empty-inline">As ações registradas aparecerão aqui.</div>@endforelse</section>@endcan
</div>
