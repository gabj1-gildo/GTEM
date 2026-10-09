<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class LineOperation extends Model
{
    protected $fillable=['route_line_id','academic_year_id','shift_id','name','starts_on','ends_on','starts_at','ends_at','weekdays','status'];
    protected function casts(): array { return ['starts_on'=>'date','ends_on'=>'date','weekdays'=>'array','version'=>'integer']; }
    public function routeLine(){ return $this->belongsTo(RouteLine::class); }
    public function academicYear(){ return $this->belongsTo(AcademicYear::class); }
    public function shift(){ return $this->belongsTo(Shift::class); }
    public function allocations(){ return $this->hasMany(VehicleAllocation::class); }
    public function enrollmentPeriods(){ return $this->belongsToMany(EnrollmentPeriod::class,'enrollment_period_operation'); }
}
