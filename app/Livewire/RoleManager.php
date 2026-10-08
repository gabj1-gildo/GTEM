<?php
namespace App\Livewire;
use App\Models\{Permission, Role};
use App\Services\Audit;
use Illuminate\Support\Facades\{Gate, DB};
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;
class RoleManager extends Component
{
    #[Locked] public ?int $roleId = null;
    public array $selected = [];
    public string $reason = '';
    public function edit(int $id): void
    {
        Gate::authorize('perfis.gerenciar');
        $role = Role::findOrFail($id); abort_if($role->code === 'administrador', 403);
        $this->roleId = $id; $this->selected = $role->permissions()->pluck('permissions.id')->map(fn ($id) => (string)$id)->all();
        $this->reason = ''; $this->resetValidation();
    }
    public function cancel(): void { $this->roleId = null; $this->resetValidation(); }
    public function save(): void
    {
        Gate::authorize('perfis.gerenciar');
        $this->validate(['selected' => 'array', 'selected.*' => ['integer', 'distinct', Rule::exists('permissions','id')],
            'reason' => 'required|string|min:10|max:1000']);
        DB::transaction(function () {
            $role = Role::lockForUpdate()->findOrFail($this->roleId); abort_if($role->code === 'administrador',403);
            // Administrative account management stays reserved for the protected administrator role.
            abort_if(Permission::whereIn('id',$this->selected)->whereIn('code',['usuarios.gerenciar','perfis.gerenciar'])->exists(),403);
            if (Permission::whereIn('id',$this->selected)->where('code','cadastros.editar')->exists()) {
                $this->selected[] = (string) Permission::where('code','cadastros.visualizar')->value('id');
            }
            $this->selected[] = (string) Permission::where('code','painel.visualizar')->value('id');
            if (Permission::whereIn('id',$this->selected)->whereIn('code',['pessoas.editar','vinculos.gerenciar'])->exists()) {
                $this->selected[] = (string) Permission::where('code','pessoas.visualizar')->value('id');
            }
            if (Permission::whereIn('id',$this->selected)->whereIn('code',['transporte.editar','frota.editar','alocacoes.gerenciar'])->exists()) {
                $this->selected[] = (string) Permission::where('code','transporte.visualizar')->value('id');
            }
            $before = $role->permissions()->pluck('code')->all();
            $role->permissions()->sync(array_unique($this->selected));
            Audit::record('permissoes_alteradas','roles',$role->id,['permissions' => $before],
                ['permissions' => $role->permissions()->pluck('code')->all()],$this->reason);
        });
        $this->roleId = null; session()->flash('success','Permissões atualizadas.');
    }
    public function render()
    {
        Gate::authorize('perfis.gerenciar');
        return view('livewire.roles',['roles' => Role::with('permissions')->orderBy('id')->get(),
            'permissions' => Permission::whereNotIn('code',['usuarios.gerenciar','perfis.gerenciar'])->get()])
            ->layout('components.layouts.app',['title' => 'Perfis e permissões']);
    }
}
