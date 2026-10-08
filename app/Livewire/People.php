<?php
namespace App\Livewire;

use App\Services\PeopleService;
use App\Support\Cpf;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\{Component, WithPagination};

class People extends Component
{
    use WithPagination;
    #[Locked] public string $resource;
    #[Locked] public ?int $editingId = null;
    #[Locked] public ?int $version = null;
    public array $form = [];
    public string $reason = '';
    public string $search = '';
    public string $status = 'active';
    public bool $showForm = false;

    public function mount(string $resource): void
    {
        Gate::authorize('pessoas.visualizar');
        abort_unless(array_key_exists($resource,config('people.resources')),404);
        $this->resource=$resource;
    }
    private function definition(): array { return config('people.resources.'.$this->resource); }
    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedStatus(): void { $this->resetPage(); }
    public function create(): void
    {
        Gate::authorize('pessoas.editar');
        $this->editingId=null; $this->version=null; $this->reason='';
        $this->form=['name'=>'','cpf'=>'','birth_date'=>'','active'=>true];
        if ($this->resource==='responsaveis') $this->form+=['phone'=>'','email'=>''];
        $this->showForm=true; $this->resetValidation();
    }
    public function edit(int $id): void
    {
        Gate::authorize('pessoas.editar');
        $person=$this->definition()['model']::findOrFail($id);
        $this->editingId=$id; $this->version=(int)$person->revisions()->max('id');
        $this->form=app(PeopleService::class)->snapshot($person);
        $this->form['cpf']=Cpf::formatted($person->cpf);
        $this->reason=''; $this->showForm=true; $this->resetValidation();
    }
    public function cancel(): void { $this->showForm=false; $this->form=[]; $this->resetValidation(); }
    public function save(): void
    {
        Gate::authorize('pessoas.editar');
        $this->resetValidation();
        try {
            app(PeopleService::class)->save($this->resource,$this->form,$this->editingId,$this->version,$this->reason ?: null);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $key=>$messages) foreach ($messages as $message)
                $this->addError(in_array($key,['reason','form']) ? $key : 'form.'.$key,$message);
            return;
        }
        $this->showForm=false; $this->form=[]; $this->resetPage();
        session()->flash('success','Cadastro salvo. O histórico foi preservado.');
    }
    public function render()
    {
        Gate::authorize('pessoas.visualizar');
        $def=$this->definition(); $query=$def['model']::query();
        $search=trim(mb_substr($this->search,0,150));
        if ($search!=='') {
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower($search).'%']);
                if (Cpf::valid($search)) $q->orWhere('cpf_hash',Cpf::fingerprint($search));
            });
        }
        if (in_array($this->status,['active','inactive'])) $query->where('active',$this->status==='active');
        $query->withCount(['links as current_links_count'=>fn ($q)=>$q->current()]);
        return view('livewire.people',[
            'definition'=>$def,'records'=>$query->orderBy('name')->orderBy('id')->paginate(12),
        ])->layout('components.layouts.app',['title'=>$def['title']]);
    }
}
