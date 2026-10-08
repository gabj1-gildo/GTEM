<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class RegistrationPeriod extends Model
{
    protected $fillable = ['academic_year_id', 'type', 'starts_on', 'ends_on', 'active'];
    protected function casts(): array { return ['active' => 'boolean', 'starts_on' => 'date', 'ends_on' => 'date']; }
}
