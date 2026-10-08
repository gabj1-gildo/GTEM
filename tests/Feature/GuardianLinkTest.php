<?php
namespace Tests\Feature;
use App\Livewire\PersonDetail;
use App\Models\{Student,Guardian,GuardianStudentLink,AuditEvent};
use App\Services\{PeopleService,GuardianLinkService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class GuardianLinkTest extends TestCase
{
    use RefreshDatabase;
    private Student $student;
    private Guardian $guardian;
    protected function setUp(): void
    {
        parent::setUp(); $this->seed(); $this->actingAs($this->user());
        $this->student=app(PeopleService::class)->save('alunos',['name'=>'Aluno Exemplo','birth_date'=>'2015-04-10','active'=>true]);
        $this->guardian=app(PeopleService::class)->save('responsaveis',['name'=>'Responsável Exemplo','active'=>true]);
    }
    private function data(array $extra=[]): array
    {
        return [...['relationship'=>'RESPONSAVEL_LEGAL','is_primary'=>true,'can_request'=>true,'reason'=>'Vínculo conferido pela secretaria.'],...$extra];
    }
    private function link(array $extra=[]): GuardianStudentLink
    {
        return app(GuardianLinkService::class)->save($this->student->id,$this->guardian->id,$this->data($extra));
    }
    public function test_link_authorizes_only_during_its_validity_and_for_active_people(): void
    {
        $link=$this->link();
        $this->assertTrue($link->permitsRequest());
        $this->assertFalse($link->permitsRequest($link->started_at->copy()->subSecond()));
        $this->guardian->update(['active'=>false]); $this->assertFalse($link->permitsRequest());
        $this->guardian->update(['active'=>true]);
        $this->student->update(['active'=>false]); $this->assertFalse($link->permitsRequest());
        $this->assertDatabaseCount('guardian_student_links',1);
    }
    public function test_authorization_is_not_implicit_in_primary_relationship(): void
    {
        $this->assertFalse($this->link(['can_request'=>false])->permitsRequest());
    }
    public function test_duplicate_open_pair_is_blocked(): void
    {
        $this->link();
        try { $this->link(['is_primary'=>false]); $this->fail('Duplicate link accepted'); }
        catch (ValidationException $e) { $this->assertArrayHasKey('guardianId',$e->errors()); }
        $this->assertDatabaseCount('guardian_student_links',1);
    }
    public function test_second_primary_is_blocked_but_secondary_is_allowed(): void
    {
        $this->link();
        $other=app(PeopleService::class)->save('responsaveis',['name'=>'Outro responsável','active'=>true]);
        try {
            app(GuardianLinkService::class)->save($this->student->id,$other->id,$this->data());
            $this->fail('Second primary accepted');
        } catch (ValidationException $e) { $this->assertArrayHasKey('is_primary',$e->errors()); }
        $secondary=app(GuardianLinkService::class)->save($this->student->id,$other->id,$this->data(['is_primary'=>false]));
        $this->assertTrue($secondary->permitsRequest());
        $this->assertSame(1,GuardianStudentLink::current()->where('is_primary',true)->count());
    }
    public function test_database_also_prevents_duplicate_open_pairs(): void
    {
        $link=$this->link();
        $this->expectException(\Illuminate\Database\QueryException::class);
        $duplicate=$link->replicate(); $duplicate->is_primary=false; $duplicate->save();
    }
    public function test_change_creates_a_new_version_without_overwriting_previous_flags(): void
    {
        $first=$this->link();
        $new=app(GuardianLinkService::class)->save($this->student->id,$this->guardian->id,$this->data(['can_request'=>false]),$first->id);
        $old=$first->fresh();
        $this->assertTrue($old->can_request); $this->assertNotNull($old->ended_at);
        $this->assertFalse($old->permitsRequest()); $this->assertFalse($new->permitsRequest());
        $this->assertFalse($old->permitsRequest($new->started_at));
        $this->assertSame($first->id,$new->supersedes_id);
        $this->assertTrue($old->ended_at->equalTo($new->started_at));
        $this->assertSame(1,GuardianStudentLink::current()->count());
        $this->assertDatabaseCount('guardian_student_links',2);
        $this->assertDatabaseHas('audit_events',['action'=>'vinculo_versionado','entity_id'=>$new->id]);
    }
    public function test_database_also_prevents_two_current_primary_guardians(): void
    {
        $link=$this->link();
        $other=app(PeopleService::class)->save('responsaveis',['name'=>'Segundo responsável','active'=>true]);
        $duplicate=$link->replicate(); $duplicate->guardian_id=$other->id;
        $this->expectException(\Illuminate\Database\QueryException::class);
        $duplicate->save();
    }
    public function test_multiple_closed_versions_remain_allowed(): void
    {
        $first=$this->link();
        $new=app(GuardianLinkService::class)->save($this->student->id,$this->guardian->id,$this->data(['can_request'=>false]),$first->id);
        app(GuardianLinkService::class)->save($this->student->id,$this->guardian->id,$this->data(),$new->id);
        $this->assertDatabaseCount('guardian_student_links',3);
        $this->assertSame(1,GuardianStudentLink::current()->count());
        $this->assertSame(2,GuardianStudentLink::whereNotNull('ended_at')->count());
    }
    public function test_stale_link_edit_does_not_close_or_change_current_version(): void
    {
        $first=$this->link();
        $current=app(GuardianLinkService::class)->save($this->student->id,$this->guardian->id,$this->data(['can_request'=>false]),$first->id);
        try {
            app(GuardianLinkService::class)->save($this->student->id,$this->guardian->id,$this->data(),$first->id);
            $this->fail('Stale link edit accepted');
        } catch (ValidationException $e) { $this->assertArrayHasKey('link',$e->errors()); }
        $this->assertNull($current->fresh()->ended_at);
        $this->assertDatabaseCount('guardian_student_links',2);
    }
    public function test_ending_link_requires_reason_and_keeps_history_then_allows_new_link(): void
    {
        $link=$this->link();
        try { app(GuardianLinkService::class)->end($this->student->id,$link->id,''); $this->fail('Missing reason accepted'); }
        catch (ValidationException $e) { $this->assertArrayHasKey('reason',$e->errors()); }
        $this->assertNull($link->fresh()->ended_at);
        app(GuardianLinkService::class)->end($this->student->id,$link->id,'Vínculo encerrado após conferência.');
        $this->assertFalse($link->fresh()->permitsRequest());
        $new=$this->link();
        $this->assertTrue($new->permitsRequest());
        $this->assertDatabaseCount('guardian_student_links',2);
    }
    public function test_inactive_guardian_cannot_be_linked(): void
    {
        $this->guardian->update(['active'=>false]);
        $this->expectException(ValidationException::class); $this->link();
    }
    public function test_link_write_actions_are_denied_to_consultation_role(): void
    {
        $link=$this->link(); $this->actingAs($this->user('consulta'));
        Livewire::test(PersonDetail::class,['resource'=>'alunos','personId'=>$this->student->id])->call('addLink')->assertForbidden();
        Livewire::test(PersonDetail::class,['resource'=>'alunos','personId'=>$this->student->id])->call('editLink',$link->id)->assertForbidden();
        $this->assertDatabaseCount('guardian_student_links',1);
    }
    public function test_another_students_link_cannot_be_edited_from_current_profile(): void
    {
        $link=$this->link();
        $other=app(PeopleService::class)->save('alunos',['name'=>'Outro aluno','birth_date'=>'2014-03-11','active'=>true]);
        foreach (['editLink','confirmEnd'] as $action) {
            try {
                Livewire::test(PersonDetail::class,['resource'=>'alunos','personId'=>$other->id])->call($action,$link->id);
                $this->fail('An unrelated link was exposed.');
            } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
                $this->assertSame(GuardianStudentLink::class,$e->getModel());
            }
        }
        $this->assertNull($link->fresh()->ended_at);
    }
    public function test_component_flow_creates_changes_and_ends_link(): void
    {
        $component=Livewire::test(PersonDetail::class,['resource'=>'alunos','personId'=>$this->student->id])->call('addLink')
            ->set('guardianId',$this->guardian->id)->set('can_request',true)->set('is_primary',true)
            ->set('reason','Vínculo conferido administrativamente.')->call('saveLink')->assertHasNoErrors()->assertSee('Autorizado');
        $link=GuardianStudentLink::firstOrFail();
        $component->call('editLink',$link->id)->set('can_request',false)->set('reason','Responsável sem autorização de solicitação.')
            ->call('saveLink')->assertHasNoErrors()->assertSee('Não autorizado');
        $current=GuardianStudentLink::current()->firstOrFail();
        $component->call('confirmEnd',$current->id)->set('endReason','Encerramento solicitado pelo responsável.')->call('endLink')->assertHasNoErrors();
        $this->assertSame(0,GuardianStudentLink::current()->count());
        $component->set('tab','links-history')->assertSee('Encerrado')->assertSee('Responsável Exemplo');
        $this->get('/pessoas/responsaveis/'.$this->guardian->id)->assertOk();
    }
}
