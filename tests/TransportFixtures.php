<?php
namespace Tests;
use App\Models\{AcademicYear,Shift,LineOperation,Vehicle,RouteLine,VehicleAllocation};
use App\Services\{TransportService,AllocationService};
use Illuminate\Validation\ValidationException;

trait TransportFixtures
{
    protected AcademicYear $year;
    protected Shift $shift;
    protected function prepareTransport(): void
    {
        $this->travelTo(now()->setDate(2030,2,4)->setTime(8,0));
        $this->seed(); $this->actingAs($this->user());
        $this->year=AcademicYear::create(['year'=>2030,'starts_on'=>'2030-02-01','ends_on'=>'2030-12-20','status'=>'ABERTO']);
        $this->shift=Shift::create(['code'=>'MANHA','name'=>'Manhã','active'=>true]);
    }
    protected function line(array $data=[]): RouteLine
    {
        return app(TransportService::class)->save('linhas',$data+['code'=>'L'.(RouteLine::count()+1),'name'=>'Linha Rural','description'=>null,'active'=>true]);
    }
    protected function vehicleData(array $data=[]): array
    {
        return $data+['plate'=>'ABC'.str_pad((string)(Vehicle::count()+1),4,'0',STR_PAD_LEFT),'prefix'=>null,'manufacturer'=>null,
            'manufacture_year'=>2025,'model'=>'Ônibus escolar','kind'=>'ONIBUS','capacity'=>40,'ownership'=>'PROPRIO','status'=>'DISPONIVEL'];
    }
    protected function vehicle(array $data=[]): Vehicle { return app(TransportService::class)->save('veiculos',$this->vehicleData($data)); }
    protected function operationData(array $data=[]): array
    {
        return $data+['route_line_id'=>$data['route_line_id'] ?? $this->line()->id,'academic_year_id'=>$this->year->id,'shift_id'=>$this->shift->id,
            'name'=>'Ida à escola','starts_on'=>'2030-02-01','ends_on'=>'2030-12-20','starts_at'=>'06:00','ends_at'=>'08:00','weekdays'=>[1,2,3,4,5],'status'=>'ATIVA'];
    }
    protected function operation(array $data=[]): LineOperation { return app(TransportService::class)->save('operacoes',$this->operationData($data)); }
    protected function allocation(LineOperation $op,Vehicle $v,array $data=[]): VehicleAllocation
    {
        return app(AllocationService::class)->allocate($op->id,$data+['vehicle_id'=>$v->id,'starts_on'=>'2030-02-04','ends_on'=>'2030-02-28','reason'=>'Planejamento inicial do atendimento.']);
    }
    protected function invalid(callable $action,string $field): void
    {
        try { $action(); $this->fail('Expected validation rejection: '.$field); }
        catch (ValidationException $e) { $this->assertArrayHasKey($field,$e->errors()); }
    }
    protected function change(string $resource,$record,array $data=[],?int $version=null,string $reason='Ajuste administrativo conferido.')
    {
        $service=app(TransportService::class);
        return $service->save($resource,array_replace($service->snapshot($resource,$record),$data),$record->id,$version ?? $record->version,$reason);
    }
}
