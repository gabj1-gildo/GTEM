<?php
namespace App\Livewire;
use App\Models\Enrollment;
use App\Services\EnrollmentService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\{Component,WithPagination};

class Enrollments extends Component
{
    use WithPagination,EnrollmentFormOptions;
    public string $search='';
    public string $yearFilter='';
    public string $statusFilter='';
    public bool $showForm=false;
    public array $form=[];

    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedYearFilter(): void { $this->resetPage(); }
    public function updatedStatusFilter(): void { $this->resetPage(); }
    public function create(): void
    {
        Gate::authorize('matriculas.editar'); $this->resetValidation();
        $this->form=['student_id'=>'','academic_year_id'=>'','guardian_id'=>'','school_id'=>'','grade_id'=>'','shift_id'=>'',
            'starts_on'=>today()->toDateString(),'ends_on'=>'','operation_ids'=>[],'reason'=>''];
        $this->showForm=true;
    }
    public function cancel(): void { $this->showForm=false; $this->resetValidation(); }
    public function save(): void
    {
        Gate::authorize('matriculas.editar'); $this->resetValidation();
        try { $enrollment=app(EnrollmentService::class)->saveDraft($this->form); }
        catch (ValidationException $e) { $this->setErrorBag($e->validator->errors()); return; }
        session()->flash('success','Rascunho criado. Anexe o termo assinado e encaminhe para a conferência administrativa.');
        $this->redirectRoute('enrollments.show',['enrollmentId'=>$enrollment->id]);
    }
    public function render()
    {
        Gate::authorize('matriculas.visualizar');
        $query=Enrollment::with(['student','academicYear'])->orderByDesc('id');
        if ($this->search!=='') $query->whereHas('student',fn($q)=>$q->where('name','like','%'.mb_substr($this->search,0,100).'%'));
        if ($this->yearFilter!=='') $query->where('academic_year_id',$this->yearFilter);
        if ($this->statusFilter==='RASCUNHO') $query->where('status','RASCUNHO');
        if ($this->statusFilter==='APROVADA') $query->where('status','APROVADA')->whereNull('closed_on');
        if ($this->statusFilter==='TERMINO') $query->whereNotNull('closed_on');
        return view('livewire.enrollments',$this->options()+['records'=>$query->paginate(12)])->layout('components.layouts.app',['title'=>'Matrículas anuais']);
    }
}
