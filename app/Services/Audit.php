<?php
namespace App\Services;
use App\Models\AuditEvent;
use Illuminate\Support\Arr;
class Audit
{
    public static function record(string $action, string $entity, int $id, array $before = [], array $after = [], ?string $reason = null): void
    {
        // Explicit snapshots supplied by callers: never store credentials, session data or request bodies.
        $hidden = ['password', 'remember_token', 'email', 'cpf', 'token'];
        AuditEvent::create([
            'actor_id' => auth()->id(), 'action' => $action, 'entity' => $entity, 'entity_id' => $id,
            'before' => Arr::except($before, $hidden), 'after' => Arr::except($after, $hidden),
            'reason' => $reason, 'created_at' => now(),
        ]);
    }
}
