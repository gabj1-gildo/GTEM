<?php
namespace App\Livewire;

use App\Models\TransportRevision;
use App\Services\TransportService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\{Component,WithPagination};

class TransportCatalog extends Component
{
    use WithPagination;
    #[Locked] public string $resource;
    #[Locked] public ?int $editingId=null;
    #[Locked] public ?int $version=null;
    #[Locked] public ?int $historyId=null;
    #[Locked] public bool $scheduleFixed=false;
    public array $form=[];
    public string $reason='';
    public string $search='';
    public string $status='';
    public bool $showForm=false;

    public function mount(string $resource): void
    {
        Gate::authorize('transporte.visualizar');
        abort_unless(array_key_exists($resource,config('transport.resources')),404);
        $this->resource=$resource;
    }
    private function definition(): array { return config('transport.resources.'.$this->resource); }
    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedStatus(): void { $this->resetPage(); }
    public function create(): void
    {
        Gate::authorize($this->definition()['edit']);
        $this->editingId=null; $this->version=null; $this->reason=''; $this->form=[]; $this->scheduleFixed=false;
        foreach ($this->definition()['fields'] as $key=>$field) $this->form[$key]=$field['default'] ?? '';
        $this->showForm=true; $this->resetValidation();
    }
    public function edit(int $id): void
    {
        Gate::authorize($this->definition()['edit']);
        $record=$this->definition()['model']::findOrFail($id);
        $this->editingId=$id; $this->version=$record->version; $this->reason='';
        $this->form=app(TransportService::class)->snapshot($this->resource,$record);
        $this->scheduleFixed=$this->resource==='operacoes' && $record->allocations()->exists();
        $this->showForm=true; $this->resetValidation();
    }
    public function cancel(): void { $this->showForm=false; $this->form=[]; $this->resetValidation(); }
    public function history(int $id): void
    {
        Gate::authorize('transporte.visualizar');
        $this->definition()['model']::findOrFail($id); $this->historyId=$id;
    }
    public function closeHistory(): void { $this->historyId=null; }
    public function save(): void
    {
        $this->resetValidation();
        try { app(TransportService::class)->save($this->resource,$this->form,$this->editingId,$this->version,$this->reason ?: null); }
        catch (ValidationException $e) {
            foreach ($e->errors() as $key=>$messages) foreach ($messages as $message)
                $this->addError(in_array($key,['reason','form'])?$key:'form.'.$key,$message);
            return;
        }
        $this->showForm=false; $this->form=[]; $this->resetPage();
        session()->flash('success','Cadastro salvo com histórico de alterações.');
    }
    public function render()
    {
        Gate::authorize('transporte.visualizar');
        $def=$this->definition(); $query=$def['model']::query(); $options=[];
        foreach ($def['fields'] as $key=>$field) {
            if (($field['type'] ?? '')==='relation') $options[$key]=$field['model']::orderBy($field['display'])->pluck($field['display'],'id')->all();
            if (($field['type'] ?? '')==='select') $options[$key]=$field['options'];
        }
        $search=mb_strtolower(trim(mb_substr($this->search,0,150)));
        if ($search!=='') {
            $columns=match($this->resource){'linhas'=>['name','code'],'veiculos'=>['plate','prefix','model'],default=>['name']};
            $query->where(function($q) use($columns,$search) { foreach($columns as $column) $q->orWhereRaw("LOWER($column) LIKE ?",['%'.$search.'%']); });
        }
        if ($this->resource==='linhas' && in_array($this->status,['active','inactive'])) $query->where('active',$this->status==='active');
        if ($this->resource!=='linhas' && isset($options['status'][$this->status])) $query->where('status',$this->status);
        $history=$this->historyId ? TransportRevision::with('actor')->where('entity',$this->resource)->where('entity_id',$this->historyId)->orderByDesc('id')->get() : collect();
        return view('livewire.transport-catalog',['definition'=>$def,'options'=>$options,'history'=>$history,'records'=>$query->orderByDesc('id')->paginate(12)])
            ->layout('components.layouts.app',['title'=>$def['title']]);
    }
}
