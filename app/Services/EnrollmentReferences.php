<?php
namespace App\Services;
use App\Models\{Enrollment,EnrollmentPeriod};
use Illuminate\Database\Eloquent\Model;

final class EnrollmentReferences
{
    public function guard(string $resource,Model $record,array $data): void
    {
        if (!$record->exists) return;
        if ($resource==='anos-letivos') {
            $enrollments=Enrollment::where('academic_year_id',$record->id);
            if ((clone $enrollments)->exists() && (int)$data['year']!==(int)$record->year)
                TransportService::fail('form.year','O ano identifica matrículas existentes e não pode ser alterado.');
            $periods=EnrollmentPeriod::whereIn('enrollment_id',$enrollments->select('id'));
            if ((clone $periods)->where(fn($q)=>$q->whereDate('starts_on','<',$data['starts_on'])->orWhereDate('original_ends_on','>',$data['ends_on']))->exists())
                TransportService::fail('form.starts_on','Preserve a vigência de todas as matrículas e de seus históricos.');
            if ($data['status']!=='ABERTO' && $periods->reserved()->whereDate('ends_on','>=',today())->exists())
                TransportService::fail('form.status','Encerre as matrículas vigentes e futuras antes de fechar o ano.');
            return;
        }
        $field=['escolas'=>'school_id','series'=>'grade_id','turnos'=>'shift_id'][$resource] ?? null;
        if ($field && !$data['active'] && EnrollmentPeriod::reserved()->where($field,$record->id)->whereDate('ends_on','>=',today())->exists())
            TransportService::fail('form.active','Há matrículas vigentes ou futuras. Transfira ou encerre esses atendimentos primeiro.');
        if ($resource==='ofertas') {
            $periods=EnrollmentPeriod::reserved()->where('school_id',$record->school_id)->where('grade_id',$record->grade_id)->where('shift_id',$record->shift_id)->whereDate('ends_on','>=',today());
            if ((!$data['active'] || $record->school_id!=(int)$data['school_id'] || $record->grade_id!=(int)$data['grade_id'] || $record->shift_id!=(int)$data['shift_id']) && $periods->exists())
                TransportService::fail('form','Há matrículas vinculadas a esta oferta. Transfira ou encerre os atendimentos antes de alterá-la.');
        }
    }
}
