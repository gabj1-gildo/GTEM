<?php
namespace App\Services;
use App\Models\LineOperation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class TransportSchedule
{
    public static function lock(): void
    {
        // All transport writes take this single lock before locking entity rows.
        // PostgreSQL serializes schedule decisions; SQLite serializes database writes.
        DB::table('transport_locks')->where('id',1)->lockForUpdate()->firstOrFail();
    }
    public static function hasDay(string $start,string $end,array $weekdays): bool
    {
        $date=CarbonImmutable::parse($start); $last=CarbonImmutable::parse($end);
        for ($i=0;$i<7 && $date->lte($last);$i++,$date=$date->addDay())
            if (in_array($date->dayOfWeekIso,array_map('intval',$weekdays),true)) return true;
        return false;
    }
    public static function conflicts(LineOperation $a,LineOperation $b,string $start,string $end): bool
    {
        if (substr($a->starts_at,0,5)>=substr($b->ends_at,0,5) || substr($b->starts_at,0,5)>=substr($a->ends_at,0,5)) return false;
        $days=array_values(array_intersect($a->weekdays,$b->weekdays));
        return $days!==[] && $start<=$end && self::hasDay($start,$end,$days);
    }
}
