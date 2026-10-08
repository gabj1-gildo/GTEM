<?php
namespace Tests\Feature;
use App\Livewire\OperationDetail;
use App\Models\{VehicleAllocation,TransportRevision};
use App\Services\{AllocationService,OperationCapacity};
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\{TestCase,TransportFixtures};

class AllocationTest extends TestCase
{
    use RefreshDatabase,TransportFixtures;
    protected function setUp(): void { parent::setUp(); $this->prepareTransport(); }
    public function test_allocation_records_real_capacity_and_ignores_client_snapshot(): void
    {
        $op=$this->operation(); $v=$this->vehicle(['capacity'=>28]);
        $a=$this->allocation($op,$v,['capacity_snapshot'=>999,'created_by'=>999]);
        $this->assertSame(28,$a->capacity_snapshot); $this->assertSame(auth()->id(),$a->created_by);
        $result=app(OperationCapacity::class)->on($op,'2030-02-05');
        $this->assertSame(28,$result['seats']); $this->assertSame($a->id,$result['allocation']->id);
    }
    public function test_single_day_allocation_is_inclusive_and_blocks_conflicting_vehicle(): void
    {
        $op=$this->operation(); $v=$this->vehicle();
        $this->allocation($op,$v,['starts_on'=>'2030-02-04','ends_on'=>'2030-02-04']);
        $this->assertSame(40,app(OperationCapacity::class)->on($op,'2030-02-04')['seats']);
        $this->invalid(fn()=>$this->allocation($this->operation(),$v,['starts_on'=>'2030-02-04','ends_on'=>'2030-02-04']),'vehicle_id');
        $this->allocation($op,$v,['starts_on'=>'2030-02-05','ends_on'=>'2030-02-05']);
        $this->assertSame(2,VehicleAllocation::count());
    }
    public function test_same_vehicle_cannot_work_in_overlapping_operations(): void
    {
        $v=$this->vehicle(); $this->allocation($this->operation(),$v);
        $op=$this->operation(['starts_at'=>'07:00','ends_at'=>'09:00']);
        $this->invalid(fn()=>$this->allocation($op,$v),'vehicle_id');
        $this->assertSame(1,VehicleAllocation::count());
    }
    public function test_adjacent_times_and_disjoint_weekdays_are_allowed(): void
    {
        $v=$this->vehicle(); $this->allocation($this->operation(['weekdays'=>[1,3,5]]),$v);
        $this->allocation($this->operation(['starts_at'=>'08:00','ends_at'=>'10:00']),$v);
        $this->allocation($this->operation(['weekdays'=>[2,4]]),$v);
        $this->assertSame(3,VehicleAllocation::count());
    }
    public function test_shared_weekday_must_actually_occur_within_intersection(): void
    {
        $v=$this->vehicle();
        $this->allocation($this->operation(['weekdays'=>[1,2]]),$v,['starts_on'=>'2030-02-05','ends_on'=>'2030-02-06']);
        $this->allocation($this->operation(['weekdays'=>[1,3]]),$v,['starts_on'=>'2030-02-05','ends_on'=>'2030-02-06']);
        $this->assertSame(2,VehicleAllocation::count());
    }
    public function test_operation_cannot_have_two_vehicles_for_overlapping_dates(): void
    {
        $op=$this->operation(); $this->allocation($op,$this->vehicle());
        $this->invalid(fn()=>$this->allocation($op,$this->vehicle()),'starts_on');
    }
    public function test_allocation_requires_valid_period_and_available_vehicle(): void
    {
        $op=$this->operation(); $v=$this->vehicle();
        $this->invalid(fn()=>$this->allocation($op,$v,['starts_on'=>'2030-02-03']),'starts_on');
        $this->invalid(fn()=>$this->allocation($op,$v,['ends_on'=>'2030-12-21']),'starts_on');
        $this->invalid(fn()=>$this->allocation($op,$v,['starts_on'=>'2030-02-09','ends_on'=>'2030-02-10']),'starts_on');
        $this->invalid(fn()=>$this->allocation($op,$v,['reason'=>'']),'reason');
        $this->change('veiculos',$v,['status'=>'EM_MANUTENCAO']);
        $this->invalid(fn()=>$this->allocation($op,$v),'vehicle_id');
    }
    public function test_inactive_operation_cannot_receive_allocation(): void
    {
        $op=$this->operation(['status'=>'INATIVA']); $v=$this->vehicle();
        $this->invalid(fn()=>$this->allocation($op,$v),'form');
    }
    public function test_release_and_replacement_preserve_old_dates_and_capacity(): void
    {
        $op=$this->operation(); $old=$this->vehicle(['capacity'=>40]); $next=$this->vehicle(['capacity'=>25]); $a=$this->allocation($op,$old);
        app(AllocationService::class)->release($op->id,$a->id,1,'2030-02-11','Substituição para manutenção preventiva.');
        $this->allocation($op,$next,['starts_on'=>'2030-02-11']);
        $a->refresh(); $this->assertSame('2030-02-10',$a->ends_on->format('Y-m-d'));
        $this->assertSame('2030-02-28',$a->original_ends_on->format('Y-m-d')); $this->assertSame(40,$a->capacity_snapshot);
        $this->assertSame(40,app(OperationCapacity::class)->on($op,'2030-02-08')['seats']);
        $this->assertSame(25,app(OperationCapacity::class)->on($op,'2030-02-11')['seats']);
        $this->assertSame(3,TransportRevision::where('entity','alocacoes')->count());
    }
    public function test_cancel_before_start_frees_period_but_keeps_audit_record(): void
    {
        $op=$this->operation(); $v=$this->vehicle(); $a=$this->allocation($op,$v,['starts_on'=>'2030-02-11']);
        app(AllocationService::class)->release($op->id,$a->id,1,'2030-02-11','Cancelamento do planejamento inicial.');
        $this->assertNotNull($a->fresh()->cancelled_at);
        $this->assertNull(app(OperationCapacity::class)->on($op,'2030-02-11')['seats']);
        $this->allocation($op,$v,['starts_on'=>'2030-02-11']); $this->assertSame(2,VehicleAllocation::count());
    }
    public function test_release_rejects_past_dates_outside_range_and_stale_versions(): void
    {
        $op=$this->operation(); $a=$this->allocation($op,$this->vehicle()); $s=app(AllocationService::class);
        $this->invalid(fn()=>$s->release($op->id,$a->id,1,'2030-02-03','Liberação solicitada por motivo técnico.'),'effective_from');
        $this->invalid(fn()=>$s->release($op->id,$a->id,1,'2030-03-01','Liberação solicitada por motivo técnico.'),'effective_from');
        $s->release($op->id,$a->id,1,'2030-02-11','Liberação solicitada por motivo técnico.');
        $this->invalid(fn()=>$s->release($op->id,$a->id,1,'2030-02-05','Liberação solicitada por motivo técnico.'),'form');
    }
    public function test_cannot_release_another_operations_allocation(): void
    {
        $op=$this->operation(); $a=$this->allocation($op,$this->vehicle()); $other=$this->operation();
        try { app(AllocationService::class)->release($other->id,$a->id,1,'2030-02-04','Tentativa de alteração de outro registro.'); $this->fail('Expected scoped not found'); }
        catch (ModelNotFoundException) { $this->assertNull($a->fresh()->cancelled_at); }
    }
    public function test_no_allocation_or_non_service_day_is_not_reported_as_zero_seats(): void
    {
        $op=$this->operation(); $s=app(OperationCapacity::class);
        $this->assertSame('Sem veículo alocado',$s->on($op,'2030-02-05')['label']);
        $this->assertNull($s->on($op,'2030-02-05')['seats']);
        $this->assertSame('Dia sem atendimento',$s->on($op,'2030-02-09')['label']);
        $this->assertSame('Fora da vigência',$s->on($op,'2031-01-01')['label']);
        $this->invalid(fn()=>$s->on($op,'2030-02-31'),'date');
    }
    public function test_current_unavailable_vehicle_is_flagged_without_erasing_planning(): void
    {
        $op=$this->operation(); $v=$this->vehicle(); $this->allocation($op,$v); $this->change('veiculos',$v,['status'=>'INDISPONIVEL']);
        $result=app(OperationCapacity::class)->on($op,'2030-02-05');
        $this->assertSame(40,$result['seats']); $this->assertStringContainsString('indisponível atualmente',$result['warning']);
    }
    public function test_livewire_allocate_release_and_invalid_date(): void
    {
        $op=$this->operation(); $v=$this->vehicle();
        $page=Livewire::test(OperationDetail::class,['operationId'=>$op->id])->set('allocation.vehicle_id',$v->id)
            ->set('allocation.reason','Atendimento regular autorizado.')->call('allocate')->assertHasNoErrors()->assertSee($v->plate);
        $a=VehicleAllocation::first();
        $page->call('beginRelease',$a->id)->set('effectiveFrom','2030-02-11')->set('releaseReason','Substituição programada para manutenção.')
            ->call('release')->assertHasNoErrors()->assertSee('Substituição programada para manutenção.')
            ->set('date','')->assertSee('Selecione uma data válida');
        $this->assertSame('2030-02-10',$a->fresh()->ends_on->format('Y-m-d'));
    }
}
