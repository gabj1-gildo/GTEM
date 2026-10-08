<?php
namespace Database\Seeders;
use App\Models\{Permission, Role};
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
class AccessSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
        $permissions = [
            'painel.visualizar' => 'Acessar o painel',
            'cadastros.visualizar' => 'Consultar cadastros escolares',
            'cadastros.editar' => 'Criar e alterar cadastros escolares',
            'usuarios.gerenciar' => 'Gerenciar usuários administrativos',
            'perfis.gerenciar' => 'Configurar permissões dos perfis',
            'auditoria.visualizar' => 'Consultar auditoria',
        ];
        $permissions += [
            'pessoas.visualizar' => 'Consultar alunos, responsáveis e histórico',
            'pessoas.editar' => 'Cadastrar e alterar alunos e responsáveis',
            'vinculos.gerenciar' => 'Gerenciar vínculos e autorizações',
        ];
        $permissions += [
            'transporte.visualizar' => 'Consultar linhas, frota, operações e histórico',
            'transporte.editar' => 'Cadastrar e alterar linhas e operações',
            'frota.editar' => 'Cadastrar e alterar veículos',
            'alocacoes.gerenciar' => 'Alocar e liberar veículos nas operações',
        ];
        $newCodes = [];
        foreach ($permissions as $code => $name) {
            $permission = Permission::firstOrCreate(['code' => $code], ['name' => $name]);
            if ($permission->wasRecentlyCreated) $newCodes[] = $code;
        }
        $roles = [
            'administrador' => ['Administrador', array_keys($permissions)],
            'gestor' => ['Gestor', ['painel.visualizar','cadastros.visualizar','cadastros.editar','auditoria.visualizar']],
            'operador' => ['Operador', ['painel.visualizar','cadastros.visualizar','cadastros.editar']],
            'consulta' => ['Consulta / Auditoria', ['painel.visualizar','cadastros.visualizar','auditoria.visualizar']],
        ];
        foreach ($roles as $code => [$name, $codes]) {
            $codes = array_unique([...$codes, 'pessoas.visualizar', ...($code === 'consulta' ? [] : ['pessoas.editar', 'vinculos.gerenciar'])]);
            $codes = array_unique([...$codes, 'transporte.visualizar', ...($code === 'consulta' ? [] : ['transporte.editar','frota.editar','alocacoes.gerenciar'])]);
            $role = Role::firstOrCreate(['code' => $code], ['name' => $name]);
            // Re-running seeds preserves institutional changes to non-admin permissions.
            if ($role->wasRecentlyCreated || $code === 'administrador') {
                $role->permissions()->sync(Permission::whereIn('code', $codes)->pluck('id'));
            } else {
                $role->permissions()->syncWithoutDetaching(Permission::whereIn('code', array_intersect($codes, $newCodes))->pluck('id'));
            }
        }
        });
    }
}
