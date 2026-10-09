<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class EnrollmentPeriod extends Model
{
    protected $guarded = ['id'];
    protected $hidden = ['term_path'];
    protected function casts(): array { return ['starts_on'=>'date','ends_on'=>'date','original_ends_on'=>'date','cancelled_at'=>'datetime','reviewed_at'=>'datetime']; }
    public function enrollment() { return $this->belongsTo(Enrollment::class); }
    public function guardian() { return $this->belongsTo(Guardian::class); }
    public function school() { return $this->belongsTo(School::class); }
    public function grade() { return $this->belongsTo(Grade::class); }
    public function shift() { return $this->belongsTo(Shift::class); }
    public function reviewer() { return $this->belongsTo(User::class, 'reviewed_by'); }
    public function operations() { return $this->belongsToMany(LineOperation::class, 'enrollment_period_operation'); }
    public function scopeReserved($query)
    {
        return $query->whereNull('cancelled_at')->whereHas('enrollment',fn($q)=>$q->where('status','APROVADA'));
    }
}
