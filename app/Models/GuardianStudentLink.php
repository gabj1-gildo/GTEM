<?php
namespace App\Models;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
class GuardianStudentLink extends Model
{
    protected $guarded = ['id'];
    protected $dateFormat = 'Y-m-d H:i:s.u';
    protected function casts(): array
    {
        return ['is_primary'=>'boolean','can_request'=>'boolean','started_at'=>'datetime','ended_at'=>'datetime'];
    }
    public function student() { return $this->belongsTo(Student::class); }
    public function guardian() { return $this->belongsTo(Guardian::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function ender() { return $this->belongsTo(User::class, 'ended_by'); }
    public function scopeCurrent($query) { return $query->whereNull('ended_at'); }
    public function permitsRequest(?CarbonInterface $at = null): bool
    {
        $at ??= now();
        return $this->can_request && $this->started_at->lte($at) &&
            ($this->ended_at === null || $at->lt($this->ended_at)) &&
            $this->student()->where('active',true)->exists() &&
            $this->guardian()->where('active',true)->exists();
    }
}
