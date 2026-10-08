<?php
namespace App\Livewire;
use App\Models\AuditEvent;
use Illuminate\Support\Facades\Gate;
use Livewire\{Component, WithPagination};
class AuditLog extends Component
{
    use WithPagination;
    public string $search = '';
    public string $from = '';
    public string $to = '';
    public function updated($property): void { if (in_array($property,['search','from','to'])) $this->resetPage(); }
    public function render()
    {
        Gate::authorize('auditoria.visualizar');
        $query = AuditEvent::with('actor')->latest('id');
        if ($this->search !== '') $query->where('action','like','%'.mb_substr($this->search,0,100).'%');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/',$this->from)) $query->whereDate('created_at','>=',$this->from);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/',$this->to)) $query->whereDate('created_at','<=',$this->to);
        return view('livewire.audit',['events' => $query->paginate(15)])->layout('components.layouts.app',['title' => 'Auditoria']);
    }
}
