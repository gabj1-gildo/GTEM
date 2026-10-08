<div>
<div class="page-heading"><div><span class="eyebrow">TRANSPORTE</span><h1>{{ $definition['title'] }}</h1><p>{{ $definition['description'] }}</p></div>@can($definition['edit'])<button class="button primary" wire:click="create"><x-icon name="plus"/> Novo cadastro</button>@endcan</div>
@if($showForm)
<section class="panel form-panel"><div class="panel-heading"><h2>{{ $editingId ? 'Editar cadastro' : 'Novo cadastro' }}</h2><button class="button subtle" wire:click="cancel">Fechar</button></div>
@if($scheduleFixed)<p class="notice">Esta operação possui histórico de alocação. Para mudar a programação, cadastre uma nova operação.</p>@endif
<form wire:submit="save"><div class="form-grid">
@foreach($definition['fields'] as $key=>$field)
@php($type=$field['type'] ?? 'text')
@php($fixed=$scheduleFixed && ($field['fixed'] ?? false))
<div class="field {{ in_array($type,['textarea','weekdays']) ? 'full-width' : '' }}" wire:key="transport-field-{{ $key }}">
@if($type==='checkbox')
<label class="checkbox-label"><input type="checkbox" wire:model="form.{{ $key }}"> {{ $field['label'] }}</label>
@elseif($type==='weekdays')
<fieldset class="weekday-fieldset"><legend>{{ $field['label'] }}</legend><div class="weekday-options">@foreach(config('transport.weekdays') as $day=>$label)<label><input type="checkbox" value="{{ $day }}" wire:model="form.weekdays" @disabled($fixed)> {{ $label }}</label>@endforeach</div></fieldset>
@else
<label for="transport-{{ $key }}">{{ $field['label'] }}</label>
@if(in_array($type,['select','relation']))
<select id="transport-{{ $key }}" wire:model="form.{{ $key }}" @disabled($fixed)><option value="">Selecione</option>@foreach($options[$key] as $value=>$label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select>
@elseif($type==='textarea')<textarea id="transport-{{ $key }}" wire:model="form.{{ $key }}" rows="3"></textarea>
@else<input id="transport-{{ $key }}" type="{{ $type }}" wire:model="form.{{ $key }}" @disabled($fixed)>
@endif
@endif
@error('form.'.$key)<span class="field-error" role="alert">{{ $message }}</span>@enderror
@if($key==='weekdays')@error('form.weekdays.*')<span class="field-error" role="alert">{{ $message }}</span>@enderror @endif
</div>
@endforeach
@if($editingId)<div class="field full-width"><label for="reason">Justificativa da alteração</label><textarea id="reason" wire:model="reason" rows="2" required minlength="10" maxlength="1000"></textarea>@error('reason')<span class="field-error" role="alert">{{ $message }}</span>@enderror</div>@endif
</div>
@error('form')<div class="notice error" role="alert">{{ $message }}</div>@enderror
<div class="form-actions"><button type="button" class="button secondary" wire:click="cancel">Cancelar</button><button class="button primary" wire:loading.attr="disabled" wire:target="save">Salvar cadastro</button></div></form>
</section>
@endif
@if($historyId)
<section class="panel form-panel"><div class="panel-heading"><h2>Histórico do cadastro #{{ $historyId }}</h2><button class="button subtle" wire:click="closeHistory">Fechar histórico</button></div>
@foreach($history as $revision)
<details class="transport-revision"><summary>{{ $revision->created_at->format('d/m/Y H:i') }} · {{ $revision->actor->name }} · {{ $revision->before ? 'Alteração' : 'Cadastro inicial' }}</summary>
<p>{{ $revision->reason ?: 'Cadastro inicial.' }}</p>
<div class="table-scroll"><table><thead><tr><th>Campo</th><th>Anterior</th><th>Registrado</th></tr></thead><tbody>
@foreach($definition['fields'] as $key=>$field)
@php($before=$revision->before[$key] ?? null) @php($after=$revision->after[$key] ?? null)
@if($before!==$after)
<tr><td>{{ $field['label'] }}</td>
@foreach([$before,$after] as $value)<td>
@if(is_array($value)){{ collect($value)->map(fn($d)=>config('transport.weekdays.'.$d))->join(', ') }}
@elseif(is_bool($value)){{ $value ? 'Sim':'Não' }}
@else{{ $options[$key][$value ?? ''] ?? ($value ?? '—') }}@endif
</td>@endforeach</tr>
@endif
@endforeach
</tbody></table></div></details>
@endforeach
</section>
@endif
<section class="panel"><div class="table-toolbar"><div class="table-title"><h2>Cadastros</h2><span class="count-pill">{{ $records->total() }}</span></div><div class="filters">
<div class="search-input"><x-icon name="search"/><input type="search" wire:model.live.debounce.300ms="search" placeholder="{{ $resource==='veiculos' ? 'Placa, prefixo ou modelo...' : 'Buscar por nome...' }}" aria-label="Buscar cadastro"></div>
<select wire:model.live="status" aria-label="Filtrar situação"><option value="">Todas as situações</option>@foreach($resource==='linhas' ? ['active'=>'Ativas','inactive'=>'Inativas'] : $options['status'] as $value=>$label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select>
</div></div>
<div class="table-scroll"><table><thead><tr>@if($resource==='operacoes')<th>Operação / linha</th><th>Ano / turno</th><th>Programação</th><th>Vigência</th><th>Situação</th>@else @foreach($definition['fields'] as $key=>$field)@if($field['list'] ?? true)<th>{{ $field['label'] }}</th>@endif @endforeach @endif<th class="align-right">Ações</th></tr></thead><tbody>
@forelse($records as $record)<tr wire:key="transport-record-{{ $record->id }}">
@if($resource==='operacoes')
<td><a class="text-link" href="{{ route('transport.operation',$record->id) }}">{{ $record->name }}</a><br><small>{{ $options['route_line_id'][$record->route_line_id] }}</small></td>
<td>{{ $options['academic_year_id'][$record->academic_year_id] }}<br><small>{{ $options['shift_id'][$record->shift_id] }}</small></td>
<td>{{ substr($record->starts_at,0,5) }} – {{ substr($record->ends_at,0,5) }}<br><small>{{ collect($record->weekdays)->map(fn($d)=>mb_substr(config('transport.weekdays.'.$d),0,3))->join(', ') }}</small></td>
<td>{{ $record->starts_on->format('d/m/Y') }}<br><small>até {{ $record->ends_on->format('d/m/Y') }}</small></td>
<td><span class="badge {{ $record->status==='ATIVA'?'green':'neutral' }}">{{ $options['status'][$record->status] }}</span></td>
@else
@foreach($definition['fields'] as $key=>$field)@if($field['list'] ?? true)<td>
@if(($field['type'] ?? '')==='checkbox')<span class="badge {{ $record->$key ? 'green':'neutral' }}">{{ $record->$key ? 'Ativa':'Inativa' }}</span>
@elseif(($field['type'] ?? '')==='date'){{ $record->$key?->format('d/m/Y') }}
@elseif(($field['type'] ?? '')==='time'){{ substr($record->$key,0,5) }}
@elseif(($field['type'] ?? '')==='weekdays'){{ collect($record->$key)->map(fn($d)=>mb_substr(config('transport.weekdays.'.$d),0,3))->join(', ') }}
@elseif(isset($options[$key])){{ $options[$key][$record->$key] ?? '—' }}
@else{{ $record->$key ?? '—' }}@endif
</td>@endif @endforeach
@endif
<td class="align-right"><div class="transport-actions">
@if($resource==='operacoes')<a class="text-link" href="{{ route('transport.operation',$record->id) }}">Abrir operação</a>@endif
@can($definition['edit'])<button class="text-link" wire:click="edit({{ $record->id }})">Editar</button>@endcan
<button class="text-link" wire:click="history({{ $record->id }})">Histórico</button></div></td>
</tr>@empty<tr><td colspan="{{ count($definition['fields'])+1 }}"><div class="empty-state"><x-icon name="bus"/><h3>Nenhum cadastro encontrado</h3><p>Cadastre uma linha e um veículo para começar a organizar as operações.</p></div></td></tr>@endforelse
</tbody></table></div>@include('components.pagination',['paginator'=>$records])</section>
<p class="footnote"><x-icon name="shield"/> Alterações exigem justificativa e preservam o histórico.</p>
</div>
