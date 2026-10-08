<?php
namespace App\Services;

use App\Models\PersonRevision;
use App\Rules\ValidCpf;
use App\Support\Cpf;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\{DB, Gate, Validator};
use Illuminate\Validation\ValidationException;

final class PeopleService
{
    public function save(string $resource, array $input, ?int $id = null, ?int $expectedVersion = null, ?string $reason = null): Model
    {
        Gate::authorize('pessoas.editar');
        $definition = config('people.resources.'.$resource);
        abort_unless($definition,404);
        $model = $definition['model'];
        $data = array_intersect_key($input, array_flip($resource === 'alunos'
            ? ['name','cpf','birth_date','active'] : ['name','cpf','birth_date','active','phone','email']));
        $data['name'] = trim($data['name'] ?? '');
        $data['cpf'] = Cpf::normalize($data['cpf'] ?? null);
        foreach (['birth_date','phone','email'] as $key) if (array_key_exists($key,$data))
            $data[$key] = is_string($data[$key]) ? (trim($data[$key]) ?: null) : $data[$key];
        if ($data['email'] ?? null) $data['email'] = mb_strtolower($data['email']);
        $rules = [
            'name'=>['required','string','min:3','max:150'],
            'cpf'=>['nullable','string',new ValidCpf],
            'birth_date'=>[$resource === 'alunos' ? 'required' : 'nullable','date_format:Y-m-d','after_or_equal:1900-01-01','before_or_equal:today'],
            'active'=>['required','boolean'],
        ];
        if ($resource === 'responsaveis') {
            $rules['email']=['nullable','email','max:255'];
            $rules['phone']=['nullable','string','regex:/^[+() 0-9.-]{8,20}$/'];
        }
        $data = Validator::make($data,$rules,[
            'birth_date.before_or_equal'=>'A data de nascimento não pode ser futura.',
            'birth_date.after_or_equal'=>'Informe uma data a partir de 01/01/1900.',
            'birth_date.date_format'=>'Informe a data de nascimento no formato válido.',
            'phone.regex'=>'Informe um telefone válido.',
        ],config('people.labels'))->validate();
        Validator::make(['reason'=>$reason],['reason'=>[$id ? 'required':'nullable','string','min:10','max:1000']],
            [],['reason'=>'justificativa'])->validate();
        try {
            return DB::transaction(function () use ($model,$definition,$data,$id,$expectedVersion,$reason) {
                $person = $id ? $model::lockForUpdate()->findOrFail($id) : new $model;
                if ($id && (int)$person->revisions()->max('id') !== $expectedVersion)
                    throw ValidationException::withMessages(['form'=>'Este cadastro foi alterado em outra tela. Reabra a edição para carregar os dados atuais.']);
                $hash = Cpf::fingerprint($data['cpf']);
                if ($hash && $model::where('cpf_hash',$hash)->when($id,fn ($q)=>$q->where('id','!=',$id))->exists())
                    throw ValidationException::withMessages(['cpf'=>'Este CPF já está cadastrado. Localize o registro existente.']);
                $before = $id ? $this->snapshot($person) : [];
                $person->fill($data);
                $person->cpf_hash = $hash;
                $after = $this->snapshot($person);
                $changes = [];
                foreach ($after as $field=>$value) {
                    if (!$id || ($before[$field] ?? null) !== $value)
                        $changes[$field]=['before'=>$before[$field] ?? null,'after'=>$value];
                }
                if ($id && $changes === []) return $person;
                $person->save();
                PersonRevision::create([
                    'person_type'=>$definition['type'],'person_id'=>$person->id,'actor_id'=>auth()->id(),
                    'changes'=>$changes,'reason'=>$reason,'created_at'=>now(),
                ]);
                Audit::record($id ? 'pessoa_alterada':'pessoa_cadastrada',$person->getTable(),$person->id,
                    [],['campos_alterados'=>array_keys($changes),'active'=>$person->active],$reason);
                return $person;
            },3);
        } catch (QueryException $e) {
            if (in_array((string)$e->getCode(),['23000','23505']))
                throw ValidationException::withMessages(['cpf'=>'Este CPF já foi cadastrado por outro usuário. Atualize a consulta.']);
            throw $e;
        }
    }
    public function snapshot(Model $person): array
    {
        $snapshot = ['name'=>$person->name,'cpf'=>$person->cpf,'birth_date'=>$person->birth_date?->format('Y-m-d'),'active'=>(bool)$person->active];
        if ($person instanceof \App\Models\Guardian) $snapshot += ['phone'=>$person->phone,'email'=>$person->email];
        return $snapshot;
    }
}
