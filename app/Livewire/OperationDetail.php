<?php
namespace App\Livewire;

use App\Models\{LineOperation,Vehicle};
use App\Services\{AllocationService,OperationCapacity};
use Illuminate\Support\Facades\{Gate,Validator};
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\{Component,WithPagination};

class OperationDetail extends Component
{
    use WithPagination;
    #[Locked] public int $operationId;
    #[Locked] public ?int $releaseId=null;
    #[Locked] public ?int $releaseVersion=null;
    public string $date='';
    public array $allocation=[];
    public string $effectiveFrom='';
    public string $releaseReason='';
    public string $replacementVehicleId='';

    public function mount(int $operationId): void
    {
        Gate::authorize('transporte.visualizar');
        $op=LineOperation::findOrFail($operationId); $this->operationId=$op->id;
        $this->date=min(max(today()->format('Y-m-d'),$op->starts_on->format('Y-m-d')),$op->ends_on->format('Y-m-d'));
        $this->resetAllocation($op);
    }
    private function resetAllocation(LineOperation $op): void
    {
        $this->allocation=['vehicle_id'=>'','starts_on'=>max(today()->format('Y-m-d'),$op->starts_on->format('Y-m-d')),'ends_on'=>$op->ends_on->format('Y-m-d'),'reason'=>''];
    }
    public function allocate(): void
    {
        Gate::authorize('alocacoes.gerenciar'); $this->resetValidation();
        try { app(AllocationService::class)->allocate($this->operationId,$this->allocation); }
        catch (ValidationException $e) { $this->errorsFor($e,'allocation'); return; }
        $this->resetAllocation(LineOperation::findOrFail($this->operationId)); $this->resetPage();
        session()->flash('success','Veículo alocado. A capacidade prevista foi registrada.');
    }
    public function beginRelease(int $id): void
    {
        Gate::authorize('alocacoes.gerenciar');
        $a=LineOperation::findOrFail($this->operationId)->allocations()->findOrFail($id);
        abort_if($a->cancelled_at || $a->ends_on->lt(today()),422);
        $this->releaseId=$id; $this->releaseVersion=$a->version;
        $this->effectiveFrom=max(today()->format('Y-m-d'),$a->starts_on->format('Y-m-d'));
        $this->releaseReason=''; $this->replacementVehicleId=''; $this->resetValidation();
    }
    public function cancelRelease(): void { $this->releaseId=null; $this->resetValidation(); }
    public function release(): void
    {
        Gate::authorize('alocacoes.gerenciar'); abort_unless($this->releaseId,422); $this->resetValidation();
        try {
            Validator::make(['replacement_vehicle_id'=>$this->replacementVehicleId],['replacement_vehicle_id'=>'nullable|integer|exists:vehicles,id'])->validate();
            app(AllocationService::class)->release($this->operationId,$this->releaseId,$this->releaseVersion,$this->effectiveFrom,$this->releaseReason,$this->replacementVehicleId!=='' ? (int)$this->replacementVehicleId : null);
        }
        catch (ValidationException $e) { $this->errorsFor($e,'release'); return; }
        $this->releaseId=null;
        session()->flash('success',$this->replacementVehicleId!=='' ? 'Substituição confirmada. A capacidade atende às matrículas do período.' : 'Liberação registrada. Você já pode alocar outro veículo a partir da data informada.');
    }
    private function errorsFor(ValidationException $e,string $prefix): void
    {
        foreach($e->errors() as $key=>$messages) foreach($messages as $message) $this->addError($prefix.'.'.$key,$message);
    }
    public function render()
    {
        Gate::authorize('transporte.visualizar');
        $op=LineOperation::with(['routeLine','academicYear','shift'])->findOrFail($this->operationId);
        $validDate=Validator::make(['date'=>$this->date],['date'=>'required|date_format:Y-m-d'])->passes();
        $capacity=$validDate ? app(OperationCapacity::class)->on($op,$this->date) : ['label'=>'Selecione uma data válida','seats'=>null,'allocation'=>null,'warning'=>null];
        return view('livewire.operation-detail',[
            'operation'=>$op,'capacity'=>$capacity,
            'vehicles'=>Vehicle::whereIn('status',['DISPONIVEL','EM_OPERACAO'])->orderBy('plate')->get(),
            'allocations'=>$op->allocations()->with(['vehicle','creator','releaser'])->orderByDesc('starts_on')->orderByDesc('id')->paginate(10),
        ])->layout('components.layouts.app',['title'=>'Operação da linha']);
    }
}
