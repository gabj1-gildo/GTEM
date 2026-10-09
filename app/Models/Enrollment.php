<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Enrollment extends Model
{
    protected $guarded = ['id'];
    protected function casts(): array { return ['version'=>'integer', 'approved_at'=>'datetime', 'closed_on'=>'date']; }
    public function student() { return $this->belongsTo(Student::class); }
    public function academicYear() { return $this->belongsTo(AcademicYear::class); }
    public function periods() { return $this->hasMany(EnrollmentPeriod::class); }
    public function getStatusLabelAttribute(): string
    {
        if ($this->status==='RASCUNHO') return 'Rascunho';
        if ($this->closed_on) return $this->closed_on->gt(today()) ? 'Término agendado' : ($this->closure_type==='CANCELAMENTO' ? 'Cancelada' : 'Encerrada');
        if ($this->periods()->whereNull('cancelled_at')->whereDate('ends_on','>=',today())->exists()) return 'Aprovada';
        return 'Concluída';
    }
}
