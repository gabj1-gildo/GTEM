<?php
namespace Tests\Feature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class FoundationSmokeTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void { parent::setUp(); $this->seed(); }
    public function test_login_existing_dashboard_and_all_catalogs_still_work(): void
    {
        $user=$this->user();
        $this->get('/pessoas/alunos')->assertRedirect('/entrar');
        $this->post('/entrar',['email'=>$user->email,'password'=>'SenhaTeste12345'])->assertRedirect('/pessoas/alunos');
        $this->assertAuthenticatedAs($user);
        foreach (['/painel','/administracao/usuarios','/administracao/perfis','/administracao/auditoria','/pessoas/alunos','/pessoas/responsaveis'] as $path)
            $this->get($path)->assertOk();
        foreach (array_keys(config('catalogs')) as $resource) $this->get('/cadastros/'.$resource)->assertOk();
        $this->post('/sair')->assertRedirect('/entrar'); $this->assertGuest();
    }
    public function test_unknown_people_resource_returns_not_found(): void
    {
        $this->actingAs($this->user());
        $this->get('/pessoas/invalid')->assertNotFound();
    }
    public function test_inactive_user_cannot_log_in(): void
    {
        $user=$this->user(); $user->update(['active'=>false]);
        $this->post('/entrar',['email'=>$user->email,'password'=>'SenhaTeste12345'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}
