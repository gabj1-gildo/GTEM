<?php
namespace Tests;
use App\Models\{Role,User};
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app=parent::createApplication();
        if (!$app->environment('testing') || !in_array($app['config']->get('database.connections.'.$app['config']->get('database.default').'.database'),[':memory:','gtem_test'],true))
            throw new \RuntimeException('Tests require :memory: or an isolated gtem_test database.');
        return $app;
    }
    protected function user(string $role='administrador'): User
    {
        return User::create(['name'=>'Usuário de teste','email'=>bin2hex(random_bytes(5)).'@example.test',
            'password'=>'SenhaTeste12345','active'=>true,'role_id'=>Role::where('code',$role)->firstOrFail()->id]);
    }
}
