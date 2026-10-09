<?php
namespace App\Services;

use App\Models\{EnrollmentPeriod,LineOperation};
use Carbon\CarbonImmutable;

final class EnrollmentCapacity
{
    public function reservations(LineOperation $op, string $start, string $end)
    {
        return EnrollmentPeriod::reserved()->whereHas('operations',fn($q)=>$q->where('line_operations.id',$op->id))
            ->whereDate('starts_on','<=',$end)->whereDate('ends_on','>=',$start)->get();
    }
    public function occupied(LineOperation $op, string $date): int
    {
        return $this->reservations($op,$date,$date)->count();
    }
    public function hasReservations(LineOperation $op, string $start, string $end): bool
    {
        foreach ($this->reservations($op,$start,$end) as $period) {
            if (TransportSchedule::hasDay(max($start,$period->starts_on->toDateString()),min($end,$period->ends_on->toDateString()),$op->weekdays)) return true;
        }
        return false;
    }
    // Caller holds TransportSchedule::lock(). Split only at changes to occupancy/capacity,
    // then inspect whether each constant interval contains a scheduled service day.
    public function assertCovered(LineOperation $op, string $start, string $end): void
    {
        $periods=$this->reservations($op,$start,$end);
        $allocations=$op->allocations()->with('vehicle')->whereNull('cancelled_at')
            ->whereDate('starts_on','<=',$end)->whereDate('ends_on','>=',$start)->get();
        $points=[$start,CarbonImmutable::parse($end)->addDay()->toDateString()];
        foreach ($periods->concat($allocations) as $row) {
            $points[]=max($start,$row->starts_on->toDateString());
            $points[]=CarbonImmutable::parse(min($end,$row->ends_on->toDateString()))->addDay()->toDateString();
        }
        $points=array_values(array_unique($points)); sort($points);
        for ($i=0;$i<count($points)-1;$i++) {
            $from=$points[$i]; $to=CarbonImmutable::parse($points[$i+1])->subDay()->toDateString();
            if (!TransportSchedule::hasDay($from,$to,$op->weekdays)) continue;
            $occupied=$periods->filter(fn($p)=>$p->starts_on->toDateString()<=$from && $p->ends_on->toDateString()>=$from)->count();
            if (!$occupied) continue;
            $allocation=$allocations->first(fn($a)=>$a->starts_on->toDateString()<=$from && $a->ends_on->toDateString()>=$from);
            if (!$allocation || !$allocation->vehicle->canBeAllocated())
                TransportService::fail('operation_ids',"A operação {$op->name} precisa de veículo disponível em todo o atendimento entre {$from} e {$to}.");
            if ($occupied>$allocation->capacity_snapshot)
                TransportService::fail('operation_ids',"Não há vagas na operação {$op->name} entre {$from} e {$to}: {$occupied} alunos para {$allocation->capacity_snapshot} lugares.");
        }
    }
}
