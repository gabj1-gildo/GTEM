<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PersonRevision extends Model
{
    public $timestamps = false;
    protected $guarded = ['id'];
    protected $hidden = ['changes'];
    protected function casts(): array { return ['changes'=>'encrypted:array','created_at'=>'datetime']; }
    public function actor() { return $this->belongsTo(User::class, 'actor_id'); }
}
