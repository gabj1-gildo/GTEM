<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class School extends Model
{
    protected $fillable = ['name', 'network', 'address', 'phone', 'active'];
    protected function casts(): array { return ['active' => 'boolean']; }
}
