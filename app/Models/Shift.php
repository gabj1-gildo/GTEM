<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Shift extends Model
{
    protected $fillable = ['code', 'name', 'active'];
    protected function casts(): array { return ['active' => 'boolean']; }
}
