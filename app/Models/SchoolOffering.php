<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SchoolOffering extends Model
{
    protected $fillable = ['school_id', 'grade_id', 'shift_id', 'active'];
    protected function casts(): array { return ['active' => 'boolean']; }
}
