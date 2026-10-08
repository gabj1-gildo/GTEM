<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Grade extends Model
{
    protected $fillable = ['code', 'name', 'active'];
    protected function casts(): array { return ['active' => 'boolean']; }
}
