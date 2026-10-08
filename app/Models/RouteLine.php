<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class RouteLine extends Model
{
    protected $fillable=['code','name','description','active'];
    protected function casts(): array { return ['active'=>'boolean','version'=>'integer']; }
    public function operations(){ return $this->hasMany(LineOperation::class); }
}
