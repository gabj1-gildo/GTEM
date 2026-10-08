<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Vehicle extends Model
{
    protected $fillable=['plate','prefix','manufacturer','model','manufacture_year','kind','capacity','ownership','status'];
    protected function casts(): array { return ['capacity'=>'integer','manufacture_year'=>'integer','version'=>'integer']; }
    public function allocations(){ return $this->hasMany(VehicleAllocation::class); }
    public function canBeAllocated(): bool { return in_array($this->status,['DISPONIVEL','EM_OPERACAO'],true); }
}
