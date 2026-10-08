<?php
use App\Http\Controllers\AuthController;
use App\Livewire\{Catalog, Dashboard, UserManager, RoleManager, AuditLog};
use Illuminate\Support\Facades\Route;
Route::redirect('/', '/painel');
Route::middleware('guest')->group(function () {
    Route::view('/entrar', 'auth.login')->name('login');
    Route::post('/entrar', [AuthController::class, 'login'])->middleware('throttle:20,1');
    Route::view('/recuperar-senha', 'auth.forgot')->name('password.request');
    Route::post('/recuperar-senha', [AuthController::class, 'forgot'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/redefinir-senha/{token}', fn (string $token) => view('auth.reset', ['token' => $token]))->name('password.reset');
    Route::post('/redefinir-senha', [AuthController::class, 'reset'])->middleware('throttle:5,1')->name('password.update');
});
Route::middleware(['auth','active'])->group(function () {
    Route::post('/sair', [AuthController::class, 'logout'])->name('logout');
    Route::get('/painel', Dashboard::class)->middleware('can:painel.visualizar')->name('dashboard');
    Route::get('/pessoas/{resource}', App\Livewire\People::class)->middleware('can:pessoas.visualizar')->name('people');
    Route::get('/pessoas/{resource}/{personId}', App\Livewire\PersonDetail::class)->whereNumber('personId')->middleware('can:pessoas.visualizar')->name('people.show');
    Route::get('/transporte/operacoes/{operationId}', App\Livewire\OperationDetail::class)->whereNumber('operationId')->middleware('can:transporte.visualizar')->name('transport.operation');
    Route::get('/transporte/{resource}', App\Livewire\TransportCatalog::class)->middleware('can:transporte.visualizar')->name('transport');
    Route::get('/cadastros/{resource}', Catalog::class)->middleware('can:cadastros.visualizar')->name('catalog');
    Route::get('/administracao/usuarios', UserManager::class)->middleware('can:usuarios.gerenciar')->name('users');
    Route::get('/administracao/perfis', RoleManager::class)->middleware('can:perfis.gerenciar')->name('roles');
    Route::get('/administracao/auditoria', AuditLog::class)->middleware('can:auditoria.visualizar')->name('audit');
});
