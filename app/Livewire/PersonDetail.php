<?php
namespace App\Livewire;

use App\Models\{Guardian, GuardianStudentLink};
use App\Services\GuardianLinkService;
use App\Support\Cpf;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\{Component, WithPagination};

class PersonDetail extends Component
{
    use WithPagination;
    #[Locked] public string $resource;
    #[Locked] public int $personId;
    #[Locked] public ?int $editingLinkId=null;
    #[Locked] public ?int $endingLinkId=null;
    public $guardianId='';
    public string $guardianSearch='';
    public string $relationship='RESPONSAVEL_LEGAL';
    public bool $is_primary=false;
    public bool $can_request=false;
    public string $reason='';
    public string $endReason='';
    public bool $showLinkForm=false;
    public string $tab='current';

    public function mount(string $resource, int $personId): void
    {
        Gate::authorize('pessoas.visualizar');
        abort_unless(array_key_exists($resource,config('people.resources')),404);
        $this->resource=$resource; $this->personId=$personId;
        config('people.resources.'.$resource.'.model')::findOrFail($personId);
    }
    public function updatedTab(): void { $this->resetPage(); }
    public function addLink(): void
    {
        Gate::authorize('vinculos.gerenciar'); abort_unless($this->resource==='alunos',403);
        $this->reset(['editingLinkId','endingLinkId','guardianId','guardianSearch','relationship','is_primary','can_request','reason']);
        $this->showLinkForm=true; $this->resetValidation();
    }
    public function editLink(int $id): void
    {
        Gate::authorize('vinculos.gerenciar'); abort_unless($this->resource==='alunos',403);
        $link=GuardianStudentLink::where('student_id',$this->personId)->current()->findOrFail($id);
        $this->editingLinkId=$id; $this->guardianId=$link->guardian_id;
        $this->relationship=$link->relationship; $this->is_primary=$link->is_primary; $this->can_request=$link->can_request;
        $this->reason=''; $this->endingLinkId=null; $this->showLinkForm=true; $this->resetValidation();
    }
    public function cancelLink(): void { $this->showLinkForm=false; $this->endingLinkId=null; $this->resetValidation(); }
    public function saveLink(): void
    {
        Gate::authorize('vinculos.gerenciar'); abort_unless($this->resource==='alunos',403);
        $this->validate(['guardianId'=>'required|integer|exists:guardians,id']);
        app(GuardianLinkService::class)->save($this->personId,(int)$this->guardianId,[
            'relationship'=>$this->relationship,'is_primary'=>$this->is_primary,
            'can_request'=>$this->can_request,'reason'=>$this->reason,
        ],$this->editingLinkId);
        $this->showLinkForm=false; $this->tab='current'; $this->resetPage();
        session()->flash('success','Vínculo salvo. As versões anteriores continuam no histórico.');
    }
    public function confirmEnd(int $id): void
    {
        Gate::authorize('vinculos.gerenciar'); abort_unless($this->resource==='alunos',403);
        GuardianStudentLink::where('student_id',$this->personId)->current()->findOrFail($id);
        $this->endingLinkId=$id; $this->endReason=''; $this->showLinkForm=false; $this->resetValidation();
    }
    public function endLink(): void
    {
        Gate::authorize('vinculos.gerenciar'); abort_unless($this->resource==='alunos' && $this->endingLinkId!==null,403);
        $this->validate(['endReason'=>'required|string|min:10|max:1000']);
        app(GuardianLinkService::class)->end($this->personId,$this->endingLinkId,$this->endReason);
        $this->endingLinkId=null;
        session()->flash('success','Vínculo encerrado. O registro permanece no histórico.');
    }
    public function render()
    {
        Gate::authorize('pessoas.visualizar');
        $person=config('people.resources.'.$this->resource.'.model')::findOrFail($this->personId);
        $links=$person->links()->with(['guardian','student','creator','ender'])->orderByDesc('started_at')->orderByDesc('id');
        if ($this->tab!=='links-history') $links->current();
        $guardians=collect();
        if ($this->showLinkForm) {
            Gate::authorize('vinculos.gerenciar');
            $query=Guardian::where('active',true);
            $term=trim(mb_substr($this->guardianSearch,0,150));
            if ($this->editingLinkId) $query->whereKey(GuardianStudentLink::where('student_id',$this->personId)->findOrFail($this->editingLinkId)->guardian_id);
            elseif ($term!=='') $query->where(function ($q) use ($term) {
                $q->whereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower($term).'%']);
                if (Cpf::valid($term)) $q->orWhere('cpf_hash',Cpf::fingerprint($term));
            });
            $guardians=$query->orderBy('name')->limit(30)->get();
        }
        return view('livewire.person-detail',[
            'person'=>$person,'guardians'=>$guardians,
            'links'=>$this->tab==='changes' ? collect() : $links->paginate(10),
            'revisions'=>$this->tab==='changes' ? $person->revisions()->with('actor')->latest('id')->paginate(10) : collect(),
        ])->layout('components.layouts.app',['title'=>$this->resource==='alunos' ? 'Ficha do aluno':'Ficha do responsável']);
    }
}
