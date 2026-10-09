<?php
namespace App\Providers;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}
    public function boot(): void
    {
        foreach (['painel.visualizar', 'cadastros.visualizar', 'cadastros.editar', 'usuarios.gerenciar', 'perfis.gerenciar', 'auditoria.visualizar', 'pessoas.visualizar', 'pessoas.editar', 'vinculos.gerenciar'] as $permission) {
            Gate::define($permission, fn (User $user) => $user->hasPermission($permission));
        }
        foreach (['transporte.visualizar','transporte.editar','frota.editar','alocacoes.gerenciar','matriculas.visualizar','matriculas.editar','matriculas.aprovar','matriculas.documentos'] as $permission) {
            Gate::define($permission, fn (User $user) => $user->hasPermission($permission));
        }
        Livewire::addPersistentMiddleware([\App\Http\Middleware\EnsureActiveUser::class]);
    }
}
