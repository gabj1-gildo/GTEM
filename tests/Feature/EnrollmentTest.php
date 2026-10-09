<?php
namespace Tests\Feature;

use App\Livewire\{Catalog,EnrollmentDetail,Enrollments,OperationDetail};
use App\Models\{Enrollment,EnrollmentPeriod,Grade,Guardian,GuardianStudentLink,Permission,Role,School,SchoolOffering,Student,VehicleAllocation};
use App\Services\{AllocationService,EnrollmentService,GuardianLinkService,OperationCapacity};
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB,Storage};
use Livewire\Livewire;
use Tests\{TestCase,TransportFixtures};

class EnrollmentTest extends TestCase
{
    use RefreshDatabase,TransportFixtures;
    private School $school;
    private Grade $grade;
    private EnrollmentService $service;
    protected function setUp(): void
    {
        parent::setUp(); $this->prepareTransport(); Storage::fake('local');
        // The business clock is 2030; filesystem timestamps still use the host clock.
        config(['livewire.temporary_file_upload.cleanup'=>false]);
        $this->school=School::create(['name'=>'Escola Municipal','network'=>'MUNICIPAL','active'=>true]);
        $this->grade=Grade::create(['code'=>'G4','name'=>'4º ano','active'=>true]);
        SchoolOffering::create(['school_id'=>$this->school->id,'grade_id'=>$this->grade->id,'shift_id'=>$this->shift->id,'active'=>true]);
        $this->service=app(EnrollmentService::class);
    }
    private function data(array $overrides=[]): array
    {
        $student=Student::create(['name'=>'Aluno '.(Student::count()+1),'active'=>true]);
        $guardian=Guardian::create(['name'=>'Responsável de teste','active'=>true]);
        app(GuardianLinkService::class)->save($student->id,$guardian->id,['relationship'=>'MAE','is_primary'=>true,'can_request'=>true,'reason'=>'Responsável conferido no cadastro.']);
        return $overrides+['student_id'=>$student->id,'guardian_id'=>$guardian->id,'academic_year_id'=>$this->year->id,
            'school_id'=>$this->school->id,'grade_id'=>$this->grade->id,'shift_id'=>$this->shift->id,
            'starts_on'=>'2030-02-04','ends_on'=>'2030-02-28','operation_ids'=>[$this->operation()->id],'reason'=>'Cadastro conferido pela secretaria.'];
    }
    private function pdf(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('termo.pdf',"%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF");
    }
    private function draftWithTerm(array $data): Enrollment
    {
        $enrollment=$this->service->saveDraft($data);
        $this->service->attachTerm($enrollment->id,$enrollment->version,$this->pdf());
        return $enrollment->refresh();
    }
    private function approveData(array $data): Enrollment
    {
        $enrollment=$this->draftWithTerm($data);
        return $this->service->approve($enrollment->id,$enrollment->version,true,'Termo e assinatura conferidos pelo servidor.');
    }
    public function test_draft_does_not_reserve_and_approval_requires_term_and_human_review(): void
    {
        $op=$this->operation(); $this->allocation($op,$this->vehicle(['capacity'=>1]));
        $e=$this->service->saveDraft($this->data(['operation_ids'=>[$op->id],'status'=>'APROVADA','approved_by'=>999]));
        $this->assertSame('RASCUNHO',$e->status);
        $this->assertSame(0,app(OperationCapacity::class)->on($op,'2030-02-04')['occupied']);
        $this->invalid(fn()=>$this->service->approve($e->id,1,true,'Documentos conferidos pelo servidor.'),'term');
        $this->service->attachTerm($e->id,1,$this->pdf()); $e->refresh();
        $this->invalid(fn()=>$this->service->approve($e->id,$e->version,false,'Documentos conferidos pelo servidor.'),'reviewed');
        $this->service->approve($e->id,$e->version,true,'Documentos conferidos pelo servidor.');
        $capacity=app(OperationCapacity::class)->on($op,'2030-02-04');
        $this->assertSame(1,$capacity['occupied']); $this->assertSame(0,$capacity['available']);
        $this->assertDatabaseHas('audit_events',['entity'=>'enrollments','entity_id'=>$e->id,'action'=>'matricula_aprovada']);
    }
    public function test_duplicate_student_year_is_rejected_by_service_and_database(): void
    {
        $data=$this->data(); $this->service->saveDraft($data);
        $this->invalid(fn()=>$this->service->saveDraft($data),'student_id');
        $this->expectException(QueryException::class);
        DB::transaction(fn()=>Enrollment::create(['student_id'=>$data['student_id'],'academic_year_id'=>$data['academic_year_id'],'created_by'=>auth()->id()]));
    }
    public function test_future_peak_occupancy_blocks_approval_without_partial_reservation(): void
    {
        $op=$this->operation(); $this->allocation($op,$this->vehicle(['capacity'=>1]));
        $this->approveData($this->data(['operation_ids'=>[$op->id],'starts_on'=>'2030-02-11','ends_on'=>'2030-02-15']));
        $e=$this->draftWithTerm($this->data(['operation_ids'=>[$op->id]]));
        $this->invalid(fn()=>$this->service->approve($e->id,$e->version,true,'Termo conferido pelo servidor responsável.'),'operation_ids');
        $this->assertSame('RASCUNHO',$e->fresh()->status);
        $this->assertNull($e->periods()->first()->reviewed_at);
        $this->assertSame(0,app(OperationCapacity::class)->on($op,'2030-02-04')['occupied']);
    }
    public function test_non_overlapping_students_share_the_same_seat(): void
    {
        $op=$this->operation(); $this->allocation($op,$this->vehicle(['capacity'=>1]));
        $this->approveData($this->data(['operation_ids'=>[$op->id],'ends_on'=>'2030-02-08']));
        $this->approveData($this->data(['operation_ids'=>[$op->id],'starts_on'=>'2030-02-11']));
        $this->assertSame(1,app(OperationCapacity::class)->on($op,'2030-02-08')['occupied']);
        $this->assertSame(1,app(OperationCapacity::class)->on($op,'2030-02-11')['occupied']);
        $this->assertNull(app(OperationCapacity::class)->on($op,'2030-02-09')['occupied']);
    }
    public function test_unallocated_service_day_blocks_but_weekend_gap_is_allowed(): void
    {
        $op=$this->operation(); $v=$this->vehicle();
        $this->allocation($op,$v,['ends_on'=>'2030-02-08']);
        $this->allocation($op,$v,['starts_on'=>'2030-02-12']);
        $e=$this->draftWithTerm($this->data(['operation_ids'=>[$op->id]]));
        $this->invalid(fn()=>$this->service->approve($e->id,$e->version,true,'Termo conferido pelo servidor responsável.'),'operation_ids');
        $this->allocation($op,$v,['starts_on'=>'2030-02-11','ends_on'=>'2030-02-11']);
        $this->service->approve($e->id,$e->version,true,'Termo conferido pelo servidor responsável.');
        $this->assertSame('APROVADA',$e->fresh()->status);
    }
    public function test_school_offering_guardian_dates_and_operation_conflicts_are_validated(): void
    {
        $data=$this->data(); $other=$this->operation();
        $this->invalid(fn()=>$this->service->saveDraft(array_replace($data,['operation_ids'=>[$data['operation_ids'][0],$other->id]])),'operation_ids');
        $this->invalid(fn()=>$this->service->saveDraft(array_replace($data,['starts_on'=>'2030-02-03'])),'starts_on');
        $this->invalid(fn()=>$this->service->saveDraft(array_replace($data,['ends_on'=>'2031-01-01'])),'starts_on');
        SchoolOffering::query()->update(['active'=>false]);
        $this->invalid(fn()=>$this->service->saveDraft($data),'school_id');
        SchoolOffering::query()->update(['active'=>true]);
        GuardianStudentLink::query()->update(['can_request'=>false]);
        $this->invalid(fn()=>$this->service->saveDraft($data),'guardian_id');
    }
    public function test_approval_revalidates_revoked_guardian_permission(): void
    {
        $op=$this->operation(); $this->allocation($op,$this->vehicle());
        $e=$this->draftWithTerm($this->data(['operation_ids'=>[$op->id]]));
        GuardianStudentLink::query()->update(['ended_at'=>now()]);
        $this->invalid(fn()=>$this->service->approve($e->id,$e->version,true,'Conferência administrativa do termo.'),'guardian_id');
        $this->assertSame('RASCUNHO',$e->fresh()->status);
    }
    public function test_transfer_preserves_history_and_moves_capacity_on_effective_date(): void
    {
        $op=$this->operation(); $next=$this->operation();
        $this->allocation($op,$this->vehicle(['capacity'=>1])); $this->allocation($next,$this->vehicle(['capacity'=>1]));
        $data=$this->data(['operation_ids'=>[$op->id]]); $e=$this->approveData($data); $old=$e->periods()->first();
        $e=$this->service->transfer($e->id,$e->version,array_replace($data,['operation_ids'=>[$next->id],'starts_on'=>'2030-02-11']),$this->pdf(),true);
        $this->assertSame('2030-02-10',$old->fresh()->ends_on->toDateString());
        $this->assertSame('2030-02-28',$old->fresh()->original_ends_on->toDateString());
        Storage::disk('local')->assertExists($old->term_path);
        $this->assertSame(1,app(OperationCapacity::class)->on($op,'2030-02-08')['occupied']);
        $this->assertSame(0,app(OperationCapacity::class)->on($op,'2030-02-11')['occupied']);
        $this->assertSame(1,app(OperationCapacity::class)->on($next,'2030-02-11')['occupied']);
        $this->assertSame(2,$e->periods()->count());
    }
    public function test_failed_transfer_rolls_back_dates_audit_and_uploaded_file(): void
    {
        $op=$this->operation(); $next=$this->operation(); $this->allocation($op,$this->vehicle());
        $data=$this->data(['operation_ids'=>[$op->id]]); $e=$this->approveData($data);
        $files=Storage::disk('local')->allFiles('enrollment-terms');
        $this->invalid(fn()=>$this->service->transfer($e->id,$e->version,array_replace($data,['operation_ids'=>[$next->id],'starts_on'=>'2030-02-11']),$this->pdf(),true),'operation_ids');
        $this->assertSame('2030-02-28',$e->periods()->first()->ends_on->toDateString());
        $this->assertSame(1,$e->periods()->count());
        $this->assertSame($files,Storage::disk('local')->allFiles('enrollment-terms'));
        $this->assertDatabaseMissing('audit_events',['action'=>'matricula_transferida']);
    }
    public function test_cancellation_releases_only_from_date_and_keeps_previous_occupancy(): void
    {
        $op=$this->operation(); $this->allocation($op,$this->vehicle());
        $e=$this->approveData($this->data(['operation_ids'=>[$op->id]]));
        $this->service->close($e->id,$e->version,'2030-02-11','CANCELAMENTO','Mudança de endereço comunicada pelo responsável.');
        $this->assertSame('Término agendado',$e->fresh()->status_label);
        $this->assertSame(1,app(OperationCapacity::class)->on($op,'2030-02-08')['occupied']);
        $this->assertSame(0,app(OperationCapacity::class)->on($op,'2030-02-11')['occupied']);
        $this->travelTo(now()->setDate(2030,2,11)); $this->assertSame('Cancelada',$e->fresh()->status_label);
    }
    public function test_same_day_cancellation_preserves_period_without_reserving(): void
    {
        $op=$this->operation(); $this->allocation($op,$this->vehicle());
        $e=$this->approveData($this->data(['operation_ids'=>[$op->id]]));
        $this->service->close($e->id,$e->version,'2030-02-04','ENCERRAMENTO','Encerramento antes do primeiro atendimento.');
        $this->assertNotNull($e->periods()->first()->cancelled_at);
        $this->assertSame(0,app(OperationCapacity::class)->on($op,'2030-02-04')['occupied']);
        $this->get(route('enrollments.show',$e->id))->assertOk()->assertSee('Encerrada');
    }
    public function test_vehicle_release_requires_replacement_and_insufficient_replacement_is_atomic(): void
    {
        $op=$this->operation(); $allocation=$this->allocation($op,$this->vehicle(['capacity'=>2]));
        $this->approveData($this->data(['operation_ids'=>[$op->id]])); $this->approveData($this->data(['operation_ids'=>[$op->id]]));
        $service=app(AllocationService::class); $reason='Substituição por manutenção preventiva.';
        $this->invalid(fn()=>$service->release($op->id,$allocation->id,1,'2030-02-11',$reason),'replacement_vehicle_id');
        $small=$this->vehicle(['capacity'=>1]);
        $this->invalid(fn()=>$service->release($op->id,$allocation->id,1,'2030-02-11',$reason,$small->id),'operation_ids');
        $this->assertSame('2030-02-28',$allocation->fresh()->ends_on->toDateString());
        $this->assertSame(1,VehicleAllocation::count());
        $next=$this->vehicle(['capacity'=>2]);
        Livewire::test(OperationDetail::class,['operationId'=>$op->id])->call('beginRelease',$allocation->id)
            ->set('effectiveFrom','2030-02-11')->set('releaseReason',$reason)->set('replacementVehicleId',(string)$next->id)->call('release')->assertHasNoErrors();
        $this->assertSame($next->id,app(OperationCapacity::class)->on($op,'2030-02-11')['allocation']->vehicle_id);
        $this->assertSame(2,VehicleAllocation::count());
    }
    public function test_edit_creates_new_draft_version_invalidates_term_and_rejects_stale_approval(): void
    {
        $data=$this->data(); $e=$this->draftWithTerm($data); $version=$e->version; $old=$e->periods()->first();
        $this->service->saveDraft($data,$e->id,$e->version);
        $this->assertNotNull($old->fresh()->cancelled_at); Storage::disk('local')->assertExists($old->term_path);
        $this->assertNull($this->service->latest($e)->term_path);
        $this->invalid(fn()=>$this->service->approve($e->id,$version,true,'Conferência administrativa do termo.'),'form');
    }
    public function test_pdf_validation_and_integrity_prevent_invalid_approval(): void
    {
        $e=$this->service->saveDraft($this->data());
        $this->invalid(fn()=>$this->service->attachTerm($e->id,$e->version,UploadedFile::fake()->createWithContent('termo.pdf','This is not a PDF')),'term');
        $this->service->attachTerm($e->id,$e->version,$this->pdf()); $e->refresh();
        Storage::disk('local')->put($e->periods()->first()->term_path,'tampered content');
        $this->invalid(fn()=>$this->service->approve($e->id,$e->version,true,'Conferência administrativa do termo.'),'term');
    }
    public function test_term_download_requires_permission_and_scopes_to_enrollment(): void
    {
        $e=$this->draftWithTerm($this->data()); $period=$e->periods()->first();
        $this->get(route('enrollments.term',[$e->id,$period->id]))->assertOk()->assertDownload("termo-matricula-{$e->id}-{$period->id}.pdf");
        $other=$this->service->saveDraft($this->data());
        $this->get(route('enrollments.term',[$other->id,$period->id]))->assertNotFound();
        $this->actingAs($this->user('consulta'));
        $this->get(route('enrollments.term',[$e->id,$period->id]))->assertForbidden();
        $this->get(route('enrollments.show',$e->id))->assertOk()->assertDontSee('Aprovar e reservar vagas');
    }
    public function test_operator_cannot_approve_even_by_direct_service_or_livewire_action(): void
    {
        $e=$this->draftWithTerm($this->data()); $this->actingAs($this->user('operador'));
        Livewire::test(EnrollmentDetail::class,['enrollmentId'=>$e->id])->set('reviewed',true)->set('reason','Conferência administrativa do termo.')->call('approve')->assertForbidden();
        $this->expectException(AuthorizationException::class);
        $this->service->approve($e->id,$e->version,true,'Conferência administrativa do termo.');
    }
    public function test_consultation_cannot_create_and_revoked_permissions_remain_revoked_after_seed(): void
    {
        $this->actingAs($this->user('consulta'));
        Livewire::test(Enrollments::class)->call('create')->assertForbidden();
        $role=Role::where('code','gestor')->first();
        $role->permissions()->detach(Permission::where('code','matriculas.aprovar')->value('id'));
        $this->seed();
        $this->assertFalse($role->permissions()->where('code','matriculas.aprovar')->exists());
    }
    public function test_livewire_creates_uploads_approves_filters_and_shows_history(): void
    {
        $op=$this->operation(); $this->allocation($op,$this->vehicle()); $data=$this->data(['operation_ids'=>[$op->id]]);
        Livewire::test(Enrollments::class)->call('create')->set('form',$data)->call('save')->assertHasNoErrors()->assertRedirect(route('enrollments.show',Enrollment::first()->id));
        $e=Enrollment::first();
        Livewire::test(EnrollmentDetail::class,['enrollmentId'=>$e->id])->set('term',$this->pdf())->call('attach')->assertHasNoErrors()->assertSee('Baixar para conferência')
            ->set('reviewed',true)->set('reason','Termo e assinatura conferidos pelo servidor.')->call('approve')->assertHasNoErrors()->assertSee('Aprovada')->assertSee('Matricula aprovada');
        Livewire::test(Enrollments::class)->set('search',$e->student->name)->set('yearFilter',(string)$this->year->id)->set('statusFilter','APROVADA')->assertSee($e->student->name);
        $this->get(route('enrollments.show',$e->id))->assertOk()->assertSee('Conferido por');
    }
    public function test_reference_changes_cannot_invalidate_approved_enrollments(): void
    {
        $op=$this->operation(); $this->allocation($op,$this->vehicle()); $this->approveData($this->data(['operation_ids'=>[$op->id]]));
        Livewire::test(Catalog::class,['resource'=>'escolas'])->call('edit',$this->school->id)->set('form.active',false)->set('reason','Escola desativada pela administração.')->call('save')->assertHasErrors('form.active');
        $this->assertTrue($this->school->fresh()->active);
        $this->invalid(fn()=>$this->change('operacoes',$op,['status'=>'INATIVA']),'status');
    }
    public function test_livewire_transfer_can_retry_the_same_upload_after_capacity_is_fixed(): void
    {
        $op=$this->operation(); $next=$this->operation(); $this->allocation($op,$this->vehicle());
        $e=$this->approveData($this->data(['operation_ids'=>[$op->id]]));
        $page=Livewire::test(EnrollmentDetail::class,['enrollmentId'=>$e->id])->call('begin','transfer')
            ->set('form.starts_on','2030-02-11')->set('form.operation_ids',[$next->id])
            ->set('form.reason','Transferência conferida pela secretaria.')->set('term',$this->pdf())->set('reviewed',true)
            ->call('save')->assertHasErrors('operation_ids');
        $this->assertSame(1,$e->periods()->count());
        $this->allocation($next,$this->vehicle());
        $page->call('save')->assertHasNoErrors()->assertSee('Matricula transferida');
        $this->assertSame(2,$e->periods()->count());
    }
    public function test_student_reserves_seats_for_both_outbound_and_return_operations(): void
    {
        $outbound=$this->operation(); $return=$this->operation(['starts_at'=>'12:00','ends_at'=>'13:00']);
        $v=$this->vehicle(['capacity'=>1]); $this->allocation($outbound,$v); $this->allocation($return,$v);
        $this->approveData($this->data(['operation_ids'=>[$outbound->id,$return->id]]));
        foreach ([$outbound,$return] as $op) {
            $this->assertSame(1,app(OperationCapacity::class)->on($op,'2030-02-04')['occupied']);
            $this->assertSame(0,app(OperationCapacity::class)->on($op,'2030-02-04')['available']);
        }
    }
    public function test_school_year_and_operation_identity_preserve_draft_history(): void
    {
        $op=$this->operation(); $this->service->saveDraft($this->data(['operation_ids'=>[$op->id]]));
        $this->invalid(fn()=>$this->change('operacoes',$op,['starts_at'=>'05:00']),'starts_at');
        Livewire::test(Catalog::class,['resource'=>'anos-letivos'])->call('edit',$this->year->id)->set('form.year',2031)->call('save')->assertHasErrors('form.year');
        $this->assertSame(2030,(int)$this->year->fresh()->year);
    }
}
