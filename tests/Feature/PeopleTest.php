<?php
namespace Tests\Feature;
use App\Livewire\{People,PersonDetail};
use App\Models\{Student,Guardian,PersonRevision,AuditEvent,Permission};
use App\Services\PeopleService;
use App\Support\Cpf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;
class PeopleTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void { parent::setUp(); $this->seed(); $this->actingAs($this->user()); }
    private function input(array $extra=[]): array { return [...['name'=>'Aluno Exemplo','birth_date'=>'2015-03-20','cpf'=>null,'active'=>true],...$extra]; }
    private function save(array $extra=[]): Student { return app(PeopleService::class)->save('alunos',$this->input($extra)); }
    public function test_cpf_validation_rejects_bad_digits_and_repeated_numbers(): void
    {
        $this->assertTrue(Cpf::valid('529.982.247-25'));
        foreach (['52998224724','11111111111','00000000000','123','52998224725abc'] as $value) $this->assertFalse(Cpf::valid($value));
    }
    public function test_student_creation_encrypts_cpf_and_revision_and_redacts_audit(): void
    {
        $student=$this->save(['cpf'=>'529.982.247-25']);
        $this->assertSame('52998224725',$student->fresh()->cpf);
        $this->assertStringNotContainsString('52998224725',DB::table('students')->value('cpf'));
        $this->assertStringNotContainsString('Aluno Exemplo',DB::table('person_revisions')->value('changes'));
        $this->assertStringNotContainsString('52998224725',AuditEvent::first()->toJson());
        $this->assertStringNotContainsString('Aluno Exemplo',AuditEvent::first()->toJson());
        $this->assertSame('Aluno Exemplo',PersonRevision::first()->changes['name']['after']);
    }
    public function test_cpf_duplicates_are_detected_after_normalization(): void
    {
        $this->save(['cpf'=>'529.982.247-25']);
        Livewire::test(People::class,['resource'=>'alunos'])->call('create')
            ->set('form',$this->input(['cpf'=>'52998224725']))->call('save')->assertHasErrors('form.cpf');
        $this->assertDatabaseCount('students',1);
    }
    public function test_missing_cpfs_do_not_create_false_duplicates(): void
    {
        $this->save(); $this->save(['name'=>'Outro aluno']); $this->assertDatabaseCount('students',2);
    }
    public function test_student_birth_date_is_required_and_cannot_be_future(): void
    {
        $component=Livewire::test(People::class,['resource'=>'alunos'])->call('create')->set('form',$this->input(['birth_date'=>'']));
        $component->call('save')->assertHasErrors('form.birth_date');
        $component->set('form.birth_date',today()->addDay()->toDateString())->call('save')->assertHasErrors('form.birth_date');
        $this->assertDatabaseCount('students',0);
    }
    public function test_guardian_contact_is_validated_and_email_normalized(): void
    {
        $component=Livewire::test(People::class,['resource'=>'responsaveis'])->call('create')
            ->set('form',['name'=>'Responsável Exemplo','cpf'=>'','birth_date'=>'','active'=>true,'phone'=>'(38) 99999-1234','email'=>'errado']);
        $component->call('save')->assertHasErrors('form.email');
        $component->set('form.email','CONTATO@EXAMPLE.TEST')->call('save')->assertHasNoErrors();
        $this->assertDatabaseHas('guardians',['email'=>'contato@example.test']);
    }
    public function test_inactivation_requires_reason_and_preserves_encrypted_history(): void
    {
        $student=$this->save();
        $component=Livewire::test(People::class,['resource'=>'alunos'])->call('edit',$student->id)->set('form.active',false);
        $component->call('save')->assertHasErrors('reason');
        $this->assertTrue($student->fresh()->active);
        $component->set('reason','Aluno deixou o atendimento municipal.')->call('save')->assertHasNoErrors();
        $this->assertFalse($student->fresh()->active);
        $this->assertDatabaseCount('students',1); $this->assertDatabaseCount('person_revisions',2);
        $this->assertSame(['before'=>true,'after'=>false],PersonRevision::latest('id')->first()->changes['active']);
    }
    public function test_stale_form_cannot_overwrite_another_edit(): void
    {
        $student=$this->save();
        $first=Livewire::test(People::class,['resource'=>'alunos'])->call('edit',$student->id);
        $second=Livewire::test(People::class,['resource'=>'alunos'])->call('edit',$student->id);
        $first->set('form.name','Nome atualizado')->set('reason','Correção de grafia confirmada.')->call('save')->assertHasNoErrors();
        $second->set('form.name','Nome desatualizado')->set('reason','Outra alteração concorrente.')->call('save')->assertHasErrors('form');
        $this->assertSame('Nome atualizado',$student->fresh()->name);
    }
    public function test_unknown_form_fields_do_not_reach_persistence(): void
    {
        Livewire::test(People::class,['resource'=>'alunos'])->call('create')
            ->set('form',$this->input(['cpf_hash'=>'fraude','id'=>999,'school_id'=>123]))->call('save')->assertHasNoErrors();
        $student=Student::firstOrFail(); $this->assertNull($student->cpf_hash); $this->assertNotEquals(999,$student->id);
    }
    public function test_consultation_role_can_read_but_cannot_write_even_by_direct_action(): void
    {
        $student=$this->save(['cpf'=>'52998224725']); $this->actingAs($this->user('consulta'));
        $this->get('/pessoas/alunos')->assertOk()->assertDontSee('52998224725');
        Livewire::test(People::class,['resource'=>'alunos'])->set('form',$this->input())->call('save')->assertForbidden();
        Livewire::test(People::class,['resource'=>'alunos'])->call('edit',$student->id)->assertForbidden();
    }
    public function test_cpf_stays_masked_on_profile_history_and_can_be_used_for_exact_search(): void
    {
        $student=$this->save(['cpf'=>'52998224725']); $this->save(['name'=>'Pessoa diferente']);
        Livewire::test(People::class,['resource'=>'alunos'])->set('search','529.982.247-25')
            ->assertSee('Aluno Exemplo')->assertDontSee('Pessoa diferente')->assertSee('***.***.247-**');
        Livewire::test(PersonDetail::class,['resource'=>'alunos','personId'=>$student->id])
            ->set('tab','changes')->assertSee('Aluno Exemplo')->assertDontSee('52998224725')->assertSee('***.***.247-**');
    }
    public function test_permission_revocation_applies_to_existing_component(): void
    {
        $user=$this->user('operador'); $this->actingAs($user);
        $component=Livewire::test(People::class,['resource'=>'alunos'])->call('create')->set('form',$this->input());
        $user->role->permissions()->detach(Permission::where('code','pessoas.editar')->value('id'));
        $component->call('save')->assertForbidden(); $this->assertDatabaseCount('students',0);
    }
    public function test_seeder_preserves_permission_revocations(): void
    {
        $user=$this->user('operador'); $user->role->permissions()->detach(Permission::where('code','pessoas.editar')->value('id'));
        $this->seed(); $this->assertFalse($user->hasPermission('pessoas.editar')); $this->assertTrue($user->hasPermission('pessoas.visualizar'));
    }
}
