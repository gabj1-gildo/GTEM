<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Locality extends Model
{
    protected $fillable = ['name', 'type', 'active'];
    protected function casts(): array { return ['active' => 'boolean']; }
}
