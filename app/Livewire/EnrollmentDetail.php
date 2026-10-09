<?php
namespace App\Livewire;
use App\Models\{AuditEvent,Enrollment};
use App\Services\EnrollmentService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\{Component,WithFileUploads};

class EnrollmentDetail extends Component
{
    use WithFileUploads,EnrollmentFormOptions;
    #[Locked] public int $enrollmentId;
    #[Locked] public int $version;
    #[Locked] public string $mode='';
    public array $form=[];
    public $term;
    public bool $reviewed=false;
    public string $reason='';
    public string $effectiveFrom='';
    public string $closureType='ENCERRAMENTO';

    public function mount(int $enrollmentId): void
    {
        Gate::authorize('matriculas.visualizar'); $this->enrollmentId=$enrollmentId;
        $this->refreshState();
    }
    private function refreshState(): void
    {
        $enrollment=Enrollment::findOrFail($this->enrollmentId); $this->version=$enrollment->version;
        $period=$enrollment->periods()->orderByDesc('id')->firstOrFail();
        $this->form=app(EnrollmentService::class)->periodData($enrollment,$period)+['reason'=>''];
        $this->mode=''; $this->term=null; $this->reviewed=false; $this->reason='';
        $this->effectiveFrom=max(today()->toDateString(),$period->starts_on->toDateString());
    }
    public function begin(string $mode): void
    {
        abort_unless(in_array($mode,['edit','transfer','close']),404);
        Gate::authorize($mode==='edit' ? 'matriculas.editar':'matriculas.aprovar');
        $this->refreshState(); $this->mode=$mode; $this->resetValidation();
        if ($mode==='transfer') $this->form['starts_on']=max(today()->toDateString(),$this->form['starts_on']);
    }
    public function cancel(): void { $this->mode=''; $this->term=null; $this->resetValidation(); }
    public function save(): void
    {
        Gate::authorize($this->mode==='edit' ? 'matriculas.editar':'matriculas.aprovar');
        $this->perform(function ($service) {
            if ($this->mode==='edit') $service->saveDraft($this->form,$this->enrollmentId,$this->version);
            elseif ($this->mode==='transfer') {
                $this->validate(['term'=>'required|file|mimes:pdf|max:10240']);
                $service->transfer($this->enrollmentId,$this->version,$this->form,$this->term,$this->reviewed);
            } else abort(422);
        },'Matrícula atualizada. O histórico anterior foi preservado.');
    }
    public function attach(): void
    {
        Gate::authorize('matriculas.editar');
        $this->perform(function ($service) {
            $this->validate(['term'=>'required|file|mimes:pdf|max:10240']);
            $service->attachTerm($this->enrollmentId,$this->version,$this->term);
        },'Termo anexado para conferência. A matrícula ainda não ocupa vaga.');
    }
    public function approve(): void
    {
        Gate::authorize('matriculas.aprovar');
        $this->perform(fn($service)=>$service->approve($this->enrollmentId,$this->version,$this->reviewed,$this->reason),'Matrícula aprovada e vagas reservadas no período informado.');
    }
    public function close(): void
    {
        Gate::authorize('matriculas.aprovar');
        $this->perform(fn($service)=>$service->close($this->enrollmentId,$this->version,$this->effectiveFrom,$this->closureType,$this->reason),'Término registrado. As vagas são liberadas a partir da data informada.');
    }
    private function perform(callable $action,string $message): void
    {
        $this->resetValidation();
        try { $action(app(EnrollmentService::class)); }
        catch (ValidationException $e) { $this->setErrorBag($e->validator->errors()); return; }
        $this->refreshState(); session()->flash('success',$message);
    }
    public function render()
    {
        Gate::authorize('matriculas.visualizar');
        $enrollment=Enrollment::with(['student','academicYear','periods.school','periods.grade','periods.shift','periods.guardian','periods.operations.routeLine','periods.reviewer'])->findOrFail($this->enrollmentId);
        return view('livewire.enrollment-detail',$this->options()+[
            'enrollment'=>$enrollment,'current'=>$enrollment->periods->whereNull('cancelled_at')->sortByDesc('id')->first(),
            'events'=>AuditEvent::with('actor')->where('entity','enrollments')->where('entity_id',$enrollment->id)->orderByDesc('id')->get(),
        ])->layout('components.layouts.app',['title'=>'Matrícula #'.$enrollment->id]);
    }
}
