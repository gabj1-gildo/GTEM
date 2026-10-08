<?php
namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class User extends Authenticatable
{
    use Notifiable;
    protected $fillable = ['name', 'email', 'password', 'role_id', 'active'];
    protected $hidden = ['password', 'remember_token'];
    protected function casts(): array { return ['active' => 'boolean', 'password' => 'hashed']; }
    public function role(): BelongsTo { return $this->belongsTo(Role::class); }
    public function hasPermission(string $permission): bool
    {
        return $this->active && $this->role()->whereHas('permissions', fn ($q) => $q->where('code', $permission))->exists();
    }
}
