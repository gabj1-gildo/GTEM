<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Student extends Model
{
    protected $fillable = ['name','cpf','birth_date','active'];
    protected $hidden = ['cpf','cpf_hash'];
    protected function casts(): array { return ['active'=>'boolean','birth_date'=>'date','cpf'=>'encrypted']; }
    public function links(): HasMany { return $this->hasMany(GuardianStudentLink::class); }
    public function revisions(): HasMany { return $this->hasMany(PersonRevision::class, 'person_id')->where('person_type','student'); }
    public function getMaskedCpfAttribute(): string { return \App\Support\Cpf::masked($this->cpf); }
}
