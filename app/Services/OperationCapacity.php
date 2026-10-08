<?php
namespace App\Services;
use App\Models\LineOperation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;

final class OperationCapacity
{
    public function on(LineOperation $op,string $date): array
    {
        Validator::make(['date'=>$date],['date'=>'required|date_format:Y-m-d'])->validate();
        $result=['seats'=>null,'allocation'=>null,'warning'=>null];
        if ($date<$op->starts_on->format('Y-m-d') || $date>$op->ends_on->format('Y-m-d')) return $result+['label'=>'Fora da vigência'];
        if (!in_array(CarbonImmutable::parse($date)->dayOfWeekIso,$op->weekdays)) return $result+['label'=>'Dia sem atendimento'];
        $allocation=$op->allocations()->with('vehicle')->whereNull('cancelled_at')->whereDate('starts_on','<=',$date)->whereDate('ends_on','>=',$date)->first();
        $result['allocation']=$allocation;
        if ($allocation) $result['seats']=$allocation->capacity_snapshot;
        if ($op->status!=='ATIVA' || !$op->routeLine->active || !$op->shift->active || $op->academicYear->status==='ENCERRADO')
            $result['warning']='A operação ou um de seus cadastros está desativado atualmente.';
        if ($allocation && !$allocation->vehicle->canBeAllocated())
            $result['warning']=trim(($result['warning'] ?? '').' O veículo está indisponível atualmente. Providencie a substituição.');
        return $result+['label'=>$allocation ? 'Capacidade prevista' : 'Sem veículo alocado'];
    }
}
