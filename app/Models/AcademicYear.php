<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AcademicYear extends Model
{
    protected $fillable = ['year', 'starts_on', 'ends_on', 'status'];
    protected function casts(): array { return ['year' => 'integer', 'starts_on' => 'date', 'ends_on' => 'date']; }
}
