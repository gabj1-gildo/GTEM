<?php
namespace Tests;
use App\Models\{Role,User};
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app=parent::createApplication();
        // Resolve DB_URL too, before RefreshDatabase is allowed to run migrations.
        if (!$app->environment('testing') || !in_array($app['db']->connection()->getDatabaseName(),[':memory:','gtem_test'],true))
            throw new \RuntimeException('Tests require :memory: or an isolated gtem_test database.');
        return $app;
    }
    protected function user(string $role='administrador'): User
    {
        return User::create(['name'=>'Usuário de teste','email'=>bin2hex(random_bytes(5)).'@example.test',
            'password'=>'SenhaTeste12345','active'=>true,'role_id'=>Role::where('code',$role)->firstOrFail()->id]);
    }
}
