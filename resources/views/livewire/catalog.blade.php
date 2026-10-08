<div>
<div class="page-heading"><div><span class="eyebrow">BASE ESCOLAR</span><h1>{{ $definition['title'] }}</h1><p>{{ $definition['description'] }}</p></div>@can('cadastros.editar')<button class="button primary" wire:click="create"><x-icon name="plus"/> Novo cadastro</button>@endcan</div>
@if($showForm)
<section class="panel form-panel" aria-label="Formulário de cadastro"><div class="panel-heading"><h2>{{ $editingId ? 'Editar cadastro' : 'Novo cadastro' }}</h2><button class="button subtle" wire:click="cancel">Fechar</button></div>
<form wire:submit="save">
    <div class="form-grid">
    @foreach($definition['fields'] as $key => $field)
        <div class="field" wire:key="field-{{ $key }}">
        @if(($field['type'] ?? '') === 'checkbox')
            <label class="checkbox-label"><input type="checkbox" wire:model="form.{{ $key }}"> {{ $field['label'] }}</label>
        @else
            <label for="field-{{ $key }}">{{ $field['label'] }}</label>
            @if(in_array($field['type'] ?? '',['select','relation']))
                <select id="field-{{ $key }}" wire:model="form.{{ $key }}"><option value="">Selecione</option>@foreach($options[$key] as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select>
            @else
                <input id="field-{{ $key }}" type="{{ $field['type'] ?? 'text' }}" wire:model="form.{{ $key }}">
            @endif
        @endif
        @error('form.'.$key)<span class="field-error" role="alert">{{ $message }}</span>@enderror
        </div>
    @endforeach
    @if($editingId)<div class="field full-width"><label for="reason">Justificativa da alteração</label><textarea id="reason" wire:model="reason" rows="2" placeholder="Obrigatória para inativar um cadastro ou encerrar um ano letivo."></textarea>@error('reason')<span class="field-error" role="alert">{{ $message }}</span>@enderror</div>@endif
    </div>
    @error('form')<div class="notice error" role="alert">{{ $message }}</div>@enderror
    <div class="form-actions"><button type="button" class="button secondary" wire:click="cancel">Cancelar</button><button class="button primary" wire:loading.attr="disabled" wire:target="save">Salvar cadastro</button></div>
</form></section>
@endif
<section class="panel">
<div class="table-toolbar"><div class="table-title"><h2>Cadastros</h2><span class="count-pill">{{ $records->total() }}</span></div><div class="filters">
@if(isset($definition['search']))<div class="search-input"><x-icon name="search"/><input type="search" wire:model.live.debounce.300ms="search" placeholder="Buscar cadastro..." aria-label="Buscar cadastro"></div>@endif
@if(isset($definition['fields']['active']))<select wire:model.live="status" aria-label="Filtrar situação"><option value="">Todas as situações</option><option value="active">Ativos</option><option value="inactive">Inativos</option></select>@endif
</div></div>
<div class="table-scroll"><table><thead><tr>@foreach($definition['fields'] as $key => $field)<th>{{ $field['label'] }}</th>@endforeach @can('cadastros.editar')<th class="align-right">Ações</th>@endcan</tr></thead>
<tbody>@forelse($records as $record)<tr wire:key="record-{{ $record->id }}">
@foreach($definition['fields'] as $key => $field)<td>
@if(($field['type'] ?? '') === 'checkbox')<span class="badge {{ $record->$key ? 'green' : 'neutral' }}">{{ $record->$key ? 'Ativo' : 'Inativo' }}</span>
@elseif(($field['type'] ?? '') === 'date'){{ $record->$key?->format('d/m/Y') }}
@elseif(isset($options[$key])){{ $options[$key][$record->$key] ?? '—' }}
@else{{ $record->$key ?: '—' }}@endif
</td>@endforeach
@can('cadastros.editar')<td class="align-right"><button class="text-link" wire:click="edit({{ $record->id }})">Editar</button></td>@endcan
</tr>@empty<tr><td colspan="{{ count($definition['fields']) + 1 }}"><div class="empty-state"><x-icon name="list"/><h3>Nenhum cadastro encontrado</h3><p>{{ $search ? 'Tente outro termo de busca.' : 'Os registros aparecerão aqui quando forem cadastrados.' }}</p></div></td></tr>@endforelse</tbody></table></div>
@include('components.pagination',['paginator' => $records])
</section><p class="footnote"><x-icon name="shield"/> Registros são inativados, preservando as informações e o histórico de alterações.</p>
</div>
