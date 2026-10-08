<?php
namespace App\Services;

use App\Models\{LineOperation,Vehicle,VehicleAllocation};
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\{DB,Gate,Validator};

final class AllocationService
{
    public function allocate(int $operationId,array $input): VehicleAllocation
    {
        Gate::authorize('alocacoes.gerenciar');
        $data=Validator::make($input,[
            'vehicle_id'=>'required|integer|exists:vehicles,id',
            'starts_on'=>'required|date_format:Y-m-d|after_or_equal:today',
            'ends_on'=>'required|date_format:Y-m-d|after_or_equal:starts_on',
            'reason'=>'required|string|min:10|max:1000',
        ])->validate();
        return DB::transaction(function () use ($operationId,$data) {
            TransportSchedule::lock();
            $op=LineOperation::lockForUpdate()->findOrFail($operationId);
            $vehicle=Vehicle::lockForUpdate()->findOrFail($data['vehicle_id']);
            if ($op->status!=='ATIVA' || !$op->routeLine->active || !$op->shift->active || $op->academicYear->status==='ENCERRADO')
                TransportService::fail('form','A operação, a linha, o turno e o ano letivo devem estar habilitados.');
            if (!$vehicle->canBeAllocated()) TransportService::fail('vehicle_id','Este veículo está indisponível para alocação.');
            if ($data['starts_on']<$op->starts_on->format('Y-m-d') || $data['ends_on']>$op->ends_on->format('Y-m-d'))
                TransportService::fail('starts_on','A alocação deve estar dentro da vigência da operação.');
            if (!TransportSchedule::hasDay($data['starts_on'],$data['ends_on'],$op->weekdays))
                TransportService::fail('starts_on','O período não contém nenhum dia de atendimento desta operação.');
            $allocations=VehicleAllocation::with('operation')->whereNull('cancelled_at')
                ->whereDate('starts_on','<=',$data['ends_on'])->whereDate('ends_on','>=',$data['starts_on'])
                ->where(fn($q)=>$q->where('vehicle_id',$vehicle->id)->orWhere('line_operation_id',$op->id))->get();
            foreach ($allocations as $existing) {
                if ($existing->line_operation_id===$op->id) TransportService::fail('starts_on','Esta operação já possui um veículo neste período. Libere a alocação antes de substituí-lo.');
                if (TransportSchedule::conflicts($op,$existing->operation,max($data['starts_on'],$existing->starts_on->format('Y-m-d')),min($data['ends_on'],$existing->ends_on->format('Y-m-d'))))
                    TransportService::fail('vehicle_id','Este veículo já atende outra operação em um dos dias e horários informados.');
            }
            $record=VehicleAllocation::create($data+['line_operation_id'=>$op->id,'original_ends_on'=>$data['ends_on'],
                'capacity_snapshot'=>$vehicle->capacity,'created_by'=>auth()->id()]);
            app(TransportService::class)->record('alocacoes',$record,[],$this->snapshot($record),$data['reason']);
            return $record;
        },3);
    }
    public function release(int $operationId,int $id,int $version,string $effectiveFrom,string $reason): VehicleAllocation
    {
        Gate::authorize('alocacoes.gerenciar');
        Validator::make(['effective_from'=>$effectiveFrom,'reason'=>trim($reason)],
            ['effective_from'=>'required|date_format:Y-m-d|after_or_equal:today','reason'=>'required|string|min:10|max:1000'])->validate();
        return DB::transaction(function () use ($operationId,$id,$version,$effectiveFrom,$reason) {
            TransportSchedule::lock();
            $allocation=VehicleAllocation::where('line_operation_id',$operationId)->lockForUpdate()->findOrFail($id);
            if ($allocation->version!==$version) TransportService::fail('form','A alocação foi alterada. Reabra a liberação.');
            if ($allocation->cancelled_at || $effectiveFrom<$allocation->starts_on->format('Y-m-d') || $effectiveFrom>$allocation->ends_on->format('Y-m-d'))
                TransportService::fail('effective_from','Escolha uma data dentro da vigência atual da alocação.');
            $before=$this->snapshot($allocation);
            if ($effectiveFrom===$allocation->starts_on->format('Y-m-d')) $allocation->cancelled_at=now();
            else $allocation->ends_on=CarbonImmutable::parse($effectiveFrom)->subDay()->format('Y-m-d');
            $allocation->released_at=now(); $allocation->released_by=auth()->id(); $allocation->release_reason=trim($reason);
            $allocation->version++; $allocation->save();
            app(TransportService::class)->record('alocacoes',$allocation,$before,$this->snapshot($allocation),trim($reason));
            return $allocation;
        },3);
    }
    public function snapshot(VehicleAllocation $a): array
    {
        return ['line_operation_id'=>$a->line_operation_id,'vehicle_id'=>$a->vehicle_id,
            'starts_on'=>$a->starts_on->format('Y-m-d'),'ends_on'=>$a->ends_on->format('Y-m-d'),
            'original_ends_on'=>$a->original_ends_on->format('Y-m-d'),'capacity_snapshot'=>$a->capacity_snapshot,
            'cancelled_at'=>$a->cancelled_at?->toIso8601String(),'released_at'=>$a->released_at?->toIso8601String(),
            'reason'=>$a->reason,'release_reason'=>$a->release_reason];
    }
}
