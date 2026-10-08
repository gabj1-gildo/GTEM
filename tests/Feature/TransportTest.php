<?php
namespace Tests\Feature;
use App\Livewire\{TransportCatalog,OperationDetail,Catalog};
use App\Models\{RouteLine,Vehicle,LineOperation,TransportRevision,Permission,Role};
use App\Services\TransportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\{TestCase,TransportFixtures};

class TransportTest extends TestCase
{
    use RefreshDatabase,TransportFixtures;
    protected function setUp(): void { parent::setUp(); $this->prepareTransport(); }
    public function test_catalogs_and_detail_render_and_require_login(): void
    {
        $op=$this->operation(); $this->allocation($op,$this->vehicle());
        foreach (['linhas','veiculos','operacoes'] as $resource) $this->get('/transporte/'.$resource)->assertOk();
        $this->get('/transporte/operacoes/'.$op->id)->assertOk()->assertSee('Capacidade prevista')->assertSee('40');
        auth()->logout(); $this->get('/transporte/linhas')->assertRedirect('/entrar');
    }
    public function test_unknown_resource_is_not_found(): void { $this->get('/transporte/invalid')->assertNotFound(); }
    public function test_vehicle_plate_normalization_and_unique_values(): void
    {
        $v=$this->vehicle(['plate'=>'abc-1d23']); $this->assertSame('ABC1D23',$v->plate);
        $this->invalid(fn()=>$this->vehicle(['plate'=>'ABC1D23']),'plate');
        $this->invalid(fn()=>$this->vehicle(['plate'=>'ABCXYZ1']),'plate');
        $this->invalid(fn()=>$this->vehicle(['capacity'=>0]),'capacity');
        $this->invalid(fn()=>$this->vehicle(['manufacture_year'=>2032]),'manufacture_year');
    }
    public function test_line_code_is_unique_and_untrusted_fields_are_ignored(): void
    {
        $line=$this->line(['code'=>'rural_01','version'=>500]);
        $this->assertSame('RURAL_01',$line->code); $this->assertSame(1,$line->version);
        $this->invalid(fn()=>$this->line(['code'=>'rural_01']),'code');
    }
    public function test_updates_require_reason_version_and_record_history(): void
    {
        $v=$this->vehicle();
        $this->invalid(fn()=>$this->change('veiculos',$v,['model'=>'Novo'],reason:''),'reason');
        $updated=$this->change('veiculos',$v,['model'=>'Modelo corrigido']);
        $this->assertSame(2,$updated->version);
        $this->invalid(fn()=>$this->change('veiculos',$v,['capacity'=>50]),'form');
        $revision=TransportRevision::where('entity','veiculos')->latest('id')->first();
        $this->assertSame('Ônibus escolar',$revision->before['model']);
        $this->assertSame('Modelo corrigido',$revision->after['model']);
        $this->assertDatabaseHas('audit_events',['entity'=>'vehicles','entity_id'=>$v->id]);
    }
    public function test_operation_dates_schedule_and_parents_are_validated(): void
    {
        foreach ([['starts_on'=>'2030-01-31'],['ends_on'=>'2031-01-01']] as $input)
            $this->invalid(fn()=>$this->operation($input),'starts_on');
        $this->invalid(fn()=>$this->operation(['ends_at'=>'05:00']),'ends_at');
        $this->invalid(fn()=>$this->operation(['weekdays'=>[]]),'weekdays');
        $this->invalid(fn()=>$this->operation(['weekdays'=>[8]]),'weekdays.0');
        $this->invalid(fn()=>$this->operation(['starts_on'=>'2030-02-02','ends_on'=>'2030-02-03']),'weekdays');
        $this->invalid(fn()=>$this->operation(['route_line_id'=>$this->line(['active'=>false])->id]),'route_line_id');
        $this->shift->update(['active'=>false]); $this->invalid(fn()=>$this->operation(),'shift_id');
        $this->shift->update(['active'=>true]); $this->year->update(['status'=>'ENCERRADO']);
        $this->invalid(fn()=>$this->operation(),'academic_year_id');
    }
    public function test_same_line_and_shift_cannot_have_duplicate_schedule(): void
    {
        $op=$this->operation();
        $this->invalid(fn()=>$this->operation(['route_line_id'=>$op->route_line_id,'starts_at'=>'07:00']),'starts_at');
        $adjacent=$this->operation(['route_line_id'=>$op->route_line_id,'starts_at'=>'08:00','ends_at'=>'10:00']);
        $this->assertTrue($adjacent->exists);
    }
    public function test_allocated_operation_schedule_and_vehicle_identity_are_preserved(): void
    {
        $op=$this->operation(); $v=$this->vehicle(); $this->allocation($op,$v);
        $this->invalid(fn()=>$this->change('operacoes',$op,['starts_at'=>'05:00']),'starts_at');
        $this->invalid(fn()=>$this->change('operacoes',$op,['status'=>'INATIVA']),'status');
        $this->invalid(fn()=>$this->change('veiculos',$v,['plate'=>'XYZ1A23']),'plate');
        $this->invalid(fn()=>$this->change('veiculos',$v,['capacity'=>30]),'capacity');
        $this->invalid(fn()=>$this->change('linhas',$op->routeLine,['active'=>false]),'active');
        $this->assertSame('Nome corrigido',$this->change('operacoes',$op,['name'=>'Nome corrigido'])->name);
        $this->assertSame('EM_MANUTENCAO',$this->change('veiculos',$v,['status'=>'EM_MANUTENCAO'])->status);
    }
    public function test_year_and_shift_changes_cannot_invalidate_operations(): void
    {
        $this->operation();
        Livewire::test(Catalog::class,['resource'=>'anos-letivos'])->call('edit',$this->year->id)
            ->set('form.ends_on','2030-06-01')->call('save')->assertHasErrors('form.starts_on');
        Livewire::test(Catalog::class,['resource'=>'anos-letivos'])->call('edit',$this->year->id)
            ->set('form.status','ENCERRADO')->set('reason','Encerramento do período escolar.')->call('save')->assertHasErrors('form');
        Livewire::test(Catalog::class,['resource'=>'turnos'])->call('edit',$this->shift->id)
            ->set('form.active',false)->set('reason','Reorganização dos turnos escolares.')->call('save')->assertHasErrors('form');
        $this->assertTrue($this->shift->fresh()->active);
    }
    public function test_livewire_catalog_create_edit_filter_and_history(): void
    {
        Livewire::test(TransportCatalog::class,['resource'=>'veiculos'])->call('create')->set('form',$this->vehicleData(['plate'=>'ABC1D23']))
            ->call('save')->assertHasNoErrors()->assertSee('ABC1D23');
        $v=Vehicle::first();
        Livewire::test(TransportCatalog::class,['resource'=>'veiculos'])->call('edit',$v->id)->set('form.capacity',32)
            ->set('reason','Correção da capacidade conferida.')->call('save')->assertHasNoErrors()->call('history',$v->id)->assertSee('Correção da capacidade conferida.')
            ->set('search','inexistente')->assertSee('Nenhum cadastro encontrado');
        $this->assertSame(32,$v->fresh()->capacity);
    }
    public function test_read_only_permissions_prevent_direct_write_actions(): void
    {
        $v=$this->vehicle(); $op=$this->operation(); $this->actingAs($this->user('consulta'));
        Livewire::test(TransportCatalog::class,['resource'=>'veiculos'])->assertDontSee('Novo cadastro')->call('edit',$v->id)->assertForbidden();
        Livewire::test(TransportCatalog::class,['resource'=>'linhas'])->call('save')->assertForbidden();
        Livewire::test(OperationDetail::class,['operationId'=>$op->id])->call('allocate')->assertForbidden();
    }
    public function test_revoked_permission_is_not_restored_by_seeder(): void
    {
        $role=Role::where('code','operador')->first();
        $role->permissions()->detach(Permission::where('code','alocacoes.gerenciar')->first()->id);
        $this->seed(); $this->assertFalse($role->permissions()->where('code','alocacoes.gerenciar')->exists());
        $op=$this->operation(); $this->actingAs($this->user('operador'));
        Livewire::test(OperationDetail::class,['operationId'=>$op->id])->call('allocate')->assertForbidden();
    }
}
