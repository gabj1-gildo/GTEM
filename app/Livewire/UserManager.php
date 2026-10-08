<?php
namespace App\Livewire;
use App\Models\{User, Role};
use App\Services\Audit;
use Illuminate\Support\Facades\{DB, Gate};
use Illuminate\Validation\{Rule, ValidationException};
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Locked;
use Livewire\{Component, WithPagination};
class UserManager extends Component
{
    use WithPagination;
    #[Locked] public ?int $editingId = null;
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public $roleId = '';
    public bool $active = true;
    public string $reason = '';
    public string $search = '';
    public bool $showForm = false;
    public function updatedSearch(): void { $this->resetPage(); }
    public function create(): void
    {
        Gate::authorize('usuarios.gerenciar');
        $this->reset(['editingId','name','email','password','roleId','active','reason']);
        $this->resetValidation(); $this->showForm = true;
    }
    public function edit(int $id): void
    {
        Gate::authorize('usuarios.gerenciar');
        $user = User::findOrFail($id);
        $this->editingId = $id; $this->name = $user->name; $this->email = $user->email;
        $this->roleId = $user->role_id; $this->active = $user->active;
        $this->password = ''; $this->reason = ''; $this->showForm = true; $this->resetValidation();
    }
    public function cancel(): void { $this->showForm = false; $this->password = ''; }
    public function save(): void
    {
        Gate::authorize('usuarios.gerenciar');
        $this->email = strtolower(trim($this->email)); $this->name = trim($this->name);
        $this->validate([
            'name' => 'required|string|max:120', 'email' => ['required','email','max:255', Rule::unique('users')->ignore($this->editingId)],
            'roleId' => ['required','integer', Rule::exists('roles','id')], 'active' => 'boolean',
            'password' => [$this->editingId ? 'nullable' : 'required', Password::min(12)->mixedCase()->numbers()],
            'reason' => [$this->editingId ? 'required' : 'nullable','string','min:10','max:1000'],
        ]);
        DB::transaction(function () {
            // Serialize administrative changes through the administrator role.
            $admin = Role::where('code','administrador')->lockForUpdate()->firstOrFail();
            $user = $this->editingId ? User::lockForUpdate()->findOrFail($this->editingId) : new User;
            $before = $user->only(['role_id','active']);
            if ($user->id === auth()->id() && (!$this->active || (int)$this->roleId !== $user->role_id))
                throw ValidationException::withMessages(['roleId' => 'Você não pode desativar sua própria conta nem alterar seu próprio perfil.']);
            if ($user->exists && $user->active && $user->role_id === $admin->id &&
                (!$this->active || (int)$this->roleId !== $admin->id) &&
                User::where('role_id',$admin->id)->where('active',true)->count() <= 1)
                throw ValidationException::withMessages(['roleId' => 'Mantenha ao menos um administrador ativo.']);
            $user->fill(['name' => $this->name, 'email' => $this->email, 'role_id' => $this->roleId, 'active' => $this->active]);
            if ($this->password !== '') $user->password = $this->password;
            $invalidate = $user->exists && ($user->isDirty(['active','role_id','password','email']));
            if ($invalidate) $user->remember_token = null;
            $user->save();
            if ($invalidate) DB::table('sessions')->where('user_id', $user->id)->delete();
            Audit::record($this->editingId ? 'usuario_alterado' : 'usuario_criado', 'users', $user->id, $before,
                ['role_id' => $user->role_id,'active' => $user->active,'senha_alterada' => $this->password !== ''], $this->reason ?: null);
        });
        $this->password = ''; $this->showForm = false; session()->flash('success', 'Usuário salvo com sucesso.');
    }
    public function render()
    {
        Gate::authorize('usuarios.gerenciar');
        $search = '%'.mb_strtolower(mb_substr($this->search,0,100)).'%';
        return view('livewire.users', ['users' => User::with('role')->where(fn ($q) =>
            $q->whereRaw('LOWER(name) LIKE ?',[$search])->orWhereRaw('LOWER(email) LIKE ?',[$search]))->orderBy('name')->paginate(12),
            'roles' => Role::orderBy('name')->get()])->layout('components.layouts.app',['title' => 'Usuários']);
    }
}
