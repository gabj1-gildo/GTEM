<?php
namespace App\Livewire;
use App\Models\{AcademicYear,Grade,Guardian,LineOperation,School,Shift,Student};

trait EnrollmentFormOptions
{
    private function options(): array
    {
        return [
            'students'=>Student::where('active',true)->orderBy('name')->get(),
            'years'=>AcademicYear::orderByDesc('year')->get(),
            'guardians'=>Guardian::where('active',true)->whereHas('links',fn($q)=>$q->current()->where('student_id',$this->form['student_id'] ?? 0)->where('can_request',true))->orderBy('name')->get(),
            'schools'=>School::where('active',true)->orderBy('name')->get(),
            'grades'=>Grade::where('active',true)->orderBy('name')->get(),
            'shifts'=>Shift::where('active',true)->orderBy('name')->get(),
            'operations'=>LineOperation::with('routeLine')->where('status','ATIVA')->where('academic_year_id',$this->form['academic_year_id'] ?? 0)
                ->where('shift_id',$this->form['shift_id'] ?? 0)->orderBy('starts_at')->get(),
        ];
    }
    public function updatedForm($value,?string $key=null): void
    {
        if ($key==='student_id') $this->form['guardian_id']='';
        if (in_array($key,['academic_year_id','shift_id'])) $this->form['operation_ids']=[];
        if ($key==='academic_year_id' && $year=AcademicYear::find($value)) {
            $this->form['starts_on']=max(today()->toDateString(),$year->starts_on->toDateString());
            $this->form['ends_on']=$year->ends_on->toDateString();
        }
    }
}
