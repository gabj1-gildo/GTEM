<?php
namespace App\Services;

use App\Models\{AcademicYear,Shift,RouteLine,Vehicle,LineOperation,TransportRevision};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\{DB,Gate,Validator};
use Illuminate\Validation\{Rule,ValidationException};

final class TransportService
{
    public function save(string $resource,array $input,?int $id=null,?int $version=null,?string $reason=null): Model
    {
        $def=config('transport.resources.'.$resource); abort_unless($def,404);
        Gate::authorize($def['edit']);
        $data=array_intersect_key($input,$def['fields']); $rules=[]; $labels=[];
        foreach ($def['fields'] as $key=>$field) {
            if (is_string($data[$key] ?? null)) $data[$key]=trim($data[$key]);
            if (($data[$key] ?? null)==='' && str_contains($field['rules'] ?? '', 'nullable')) $data[$key]=null;
            $rules[$key]=explode('|',$field['rules'] ?? 'required'); $labels[$key]=$field['label'];
            if (($field['type'] ?? '')==='select') $rules[$key]=['required',Rule::in(array_keys($field['options']))];
            if (($field['type'] ?? '')==='relation') $rules[$key]=['required','integer',Rule::exists((new $field['model'])->getTable(),'id')];
        }
        if ($resource==='linhas') $data['code']=strtoupper($data['code'] ?? '');
        if ($resource==='veiculos') {
            $data['plate']=strtoupper(preg_replace('/[\s-]/','',$data['plate'] ?? ''));
            $rules['manufacture_year'][]='max:'.(now()->year+1);
        }
        if ($resource==='operacoes') {
            $rules['weekdays']=['required','array','min:1','max:7'];
            $rules['weekdays.*']=['required','integer','between:1,7','distinct'];
        }
        $data=Validator::make($data,$rules,[], $labels)->validate();
        if (isset($data['weekdays'])) { $data['weekdays']=array_map('intval',$data['weekdays']); sort($data['weekdays']); }
        $reason=trim($reason ?? '');
        Validator::make(['reason'=>$reason],['reason'=>[$id?'required':'nullable','string','min:10','max:1000']],[],['reason'=>'justificativa'])->validate();
        return DB::transaction(function () use ($resource,$def,$data,$id,$version,$reason) {
            TransportSchedule::lock();
            $record=$id ? $def['model']::lockForUpdate()->findOrFail($id) : new $def['model'];
            if ($id && $record->version!==$version) self::fail('form','Este cadastro foi alterado em outra tela. Reabra a edição.');
            $before=$id ? $this->snapshot($resource,$record) : [];
            if ($resource==='linhas') {
                if (RouteLine::where('code',$data['code'])->when($id,fn($q)=>$q->where('id','!=',$id))->exists()) self::fail('code','Este código já está cadastrado.');
                if ($id && !$data['active'] && $record->operations()->where('status','ATIVA')->whereDate('ends_on','>=',today())->exists())
                    self::fail('active','Encerre as operações vigentes ou futuras antes de inativar a linha.');
            }
            if ($resource==='veiculos') {
                if (Vehicle::where('plate',$data['plate'])->when($id,fn($q)=>$q->where('id','!=',$id))->exists()) self::fail('plate','Esta placa já está cadastrada.');
                if ($id && (int)$data['capacity']!==$record->capacity && $record->allocations()->whereNull('cancelled_at')->whereDate('ends_on','>=',today())->exists())
                    self::fail('capacity','Libere as alocações vigentes e futuras antes de alterar a capacidade.');
                if ($id && $data['plate']!==$record->plate && $record->allocations()->exists())
                    self::fail('plate','A placa identifica um veículo com histórico. Cadastre outro veículo para uma substituição.');
            }
            if ($resource==='operacoes') $this->validateOperation($record,$data,$before);
            $record->fill($data);
            if ($id && !$record->isDirty()) return $record;
            $record->version=$id ? $record->version+1 : 1; $record->save();
            $this->record($resource,$record,$before,$this->snapshot($resource,$record),$reason ?: null);
            return $record;
        },3);
    }

    private function validateOperation(LineOperation $record,array $data,array $before): void
    {
        if ($record->exists && ($record->allocations()->exists() || $record->enrollmentPeriods()->exists())) {
            foreach (config('transport.resources.operacoes.fields') as $key=>$field) {
                if (($field['fixed'] ?? false) && $data[$key]!=$before[$key])
                    self::fail($key,'Esta operação já possui histórico de alocação. Crie outra operação para mudar a programação.');
            }
        }
        $year=AcademicYear::findOrFail($data['academic_year_id']);
        if ($record->exists && $data['status']!=='ATIVA' && app(EnrollmentCapacity::class)->hasReservations($record,today()->toDateString(),$record->ends_on->toDateString()))
            self::fail('status','Transfira ou encerre as matrículas vigentes e futuras antes de inativar a operação.');
        if ($data['starts_on']<$year->starts_on->format('Y-m-d') || $data['ends_on']>$year->ends_on->format('Y-m-d'))
            self::fail('starts_on','A vigência deve estar dentro das datas do ano letivo.');
        if (!TransportSchedule::hasDay($data['starts_on'],$data['ends_on'],$data['weekdays']))
            self::fail('weekdays','A vigência não contém nenhum dos dias da semana selecionados.');
        if ($data['status']==='ATIVA') {
            if ($year->status==='ENCERRADO') self::fail('academic_year_id','O ano letivo está encerrado.');
            if (!RouteLine::findOrFail($data['route_line_id'])->active) self::fail('route_line_id','Selecione uma linha ativa.');
            if (!Shift::findOrFail($data['shift_id'])->active) self::fail('shift_id','Selecione um turno ativo.');
            $candidate=new LineOperation($data);
            $others=LineOperation::where('route_line_id',$data['route_line_id'])->where('academic_year_id',$data['academic_year_id'])
                ->where('shift_id',$data['shift_id'])->where('status','ATIVA')->whereDate('starts_on','<=',$data['ends_on'])->whereDate('ends_on','>=',$data['starts_on'])
                ->when($record->exists,fn($q)=>$q->where('id','!=',$record->id))->get();
            foreach ($others as $other) if (TransportSchedule::conflicts($candidate,$other,max($data['starts_on'],$other->starts_on->format('Y-m-d')),min($data['ends_on'],$other->ends_on->format('Y-m-d'))))
                self::fail('starts_at','Já existe uma operação desta linha e turno no período e horário informados.');
        } elseif ($record->exists && $record->allocations()->whereNull('cancelled_at')->whereDate('ends_on','>=',today())->exists()) {
            self::fail('status','Libere as alocações vigentes e futuras antes de inativar a operação.');
        }
    }

    public function guardReference(string $resource,Model $record,array $data): void
    {
        if (!$record->exists || !in_array($resource,['anos-letivos','turnos'])) return;
        $ops=LineOperation::where($resource==='anos-letivos' ? 'academic_year_id':'shift_id',$record->id);
        if ($resource==='anos-letivos' && (clone $ops)->where(fn($q)=>$q->whereDate('starts_on','<',$data['starts_on'])->orWhereDate('ends_on','>',$data['ends_on']))->exists())
            self::fail('form.starts_on','As datas devem preservar a vigência de todas as operações deste ano.');
        if ($resource==='anos-letivos' && (int)$record->year!==(int)$data['year'] && (clone $ops)->exists())
            self::fail('form.year','Este ano já possui operações. Crie outro ano letivo.');
        if (($resource==='anos-letivos' ? $data['status']==='ENCERRADO' : !$data['active']) &&
            $ops->where('status','ATIVA')->whereDate('ends_on','>=',today())->exists())
            self::fail('form','Encerre as operações vigentes e futuras antes de desativar este cadastro.');
    }

    public function snapshot(string $resource,Model $record): array
    {
        $data=[];
        foreach (config('transport.resources.'.$resource.'.fields') as $key=>$field) {
            $value=$record->$key; $type=$field['type'] ?? '';
            $data[$key]=$type==='date' ? $value?->format('Y-m-d') : ($type==='time' ? substr($value ?? '',0,5) : $value);
        }
        return $data;
    }
    public function record(string $entity,Model $record,array $before,array $after,?string $reason): void
    {
        TransportRevision::create(['entity'=>$entity,'entity_id'=>$record->id,'actor_id'=>auth()->id(),'before'=>$before,'after'=>$after,'reason'=>$reason,'created_at'=>now()]);
        Audit::record($before ? 'transporte_alterado':'transporte_cadastrado',$record->getTable(),$record->id,$before,$after,$reason);
    }
    public static function fail(string $field,string $message): never { throw ValidationException::withMessages([$field=>$message]); }
}
