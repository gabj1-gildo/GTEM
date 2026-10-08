<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class VehicleAllocation extends Model
{
    protected $guarded=['id'];
    protected function casts(): array
    {
        return ['starts_on'=>'date','ends_on'=>'date','original_ends_on'=>'date','cancelled_at'=>'datetime','released_at'=>'datetime','capacity_snapshot'=>'integer','version'=>'integer'];
    }
    public function operation(){ return $this->belongsTo(LineOperation::class,'line_operation_id'); }
    public function vehicle(){ return $this->belongsTo(Vehicle::class); }
    public function creator(){ return $this->belongsTo(User::class,'created_by'); }
    public function releaser(){ return $this->belongsTo(User::class,'released_by'); }
}
