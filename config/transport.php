<?php
use App\Models\{RouteLine,Vehicle,LineOperation,AcademicYear,Shift};
return [
    'weekdays'=>[1=>'Segunda',2=>'Terça',3=>'Quarta',4=>'Quinta',5=>'Sexta',6=>'Sábado',7=>'Domingo'],
    'resources'=>[
        'linhas'=>['model'=>RouteLine::class,'title'=>'Linhas','description'=>'Organize os trajetos permanentes do transporte escolar.','edit'=>'transporte.editar','fields'=>[
            'code'=>['label'=>'Código','rules'=>'required|alpha_dash:ascii|max:30'],
            'name'=>['label'=>'Nome da linha','rules'=>'required|string|max:150'],
            'description'=>['label'=>'Descrição do trajeto','type'=>'textarea','rules'=>'nullable|string|max:2000','list'=>false],
            'active'=>['label'=>'Ativa','type'=>'checkbox','default'=>true,'rules'=>'required|boolean'],
        ]],
        'veiculos'=>['model'=>Vehicle::class,'title'=>'Veículos','description'=>'Cadastre a frota, a capacidade para alunos e a situação operacional.','edit'=>'frota.editar','fields'=>[
            'plate'=>['label'=>'Placa','rules'=>'required|string|regex:/^[A-Z]{3}[0-9][A-Z0-9][0-9]{2}$/'],
            'prefix'=>['label'=>'Prefixo','rules'=>'nullable|string|max:30'],
            'manufacturer'=>['label'=>'Fabricante','rules'=>'nullable|string|max:80','list'=>false],
            'model'=>['label'=>'Modelo','rules'=>'required|string|max:100'],
            'manufacture_year'=>['label'=>'Ano de fabricação','type'=>'number','rules'=>'nullable|integer|min:1900','list'=>false],
            'kind'=>['label'=>'Tipo','type'=>'select','options'=>['ONIBUS'=>'Ônibus','MICRO_ONIBUS'=>'Micro-ônibus','VAN'=>'Van','OUTRO'=>'Outro']],
            'capacity'=>['label'=>'Capacidade para alunos','type'=>'number','rules'=>'required|integer|min:1|max:200'],
            'ownership'=>['label'=>'Propriedade','type'=>'select','options'=>['PROPRIO'=>'Próprio','TERCEIRIZADO'=>'Terceirizado']],
            'status'=>['label'=>'Situação','type'=>'select','default'=>'DISPONIVEL','options'=>['DISPONIVEL'=>'Disponível','EM_OPERACAO'=>'Em operação','EM_MANUTENCAO'=>'Em manutenção','INDISPONIVEL'=>'Indisponível','INATIVO'=>'Inativo']],
        ]],
        'operacoes'=>['model'=>LineOperation::class,'title'=>'Operações das linhas','description'=>'Defina ano letivo, turno, dias, horários e vigência de cada atendimento.','edit'=>'transporte.editar','fields'=>[
            'route_line_id'=>['label'=>'Linha','type'=>'relation','model'=>RouteLine::class,'display'=>'name','fixed'=>true],
            'academic_year_id'=>['label'=>'Ano letivo','type'=>'relation','model'=>AcademicYear::class,'display'=>'year','fixed'=>true],
            'shift_id'=>['label'=>'Turno','type'=>'relation','model'=>Shift::class,'display'=>'name','fixed'=>true],
            'name'=>['label'=>'Identificação da operação','rules'=>'required|string|max:100'],
            'starts_on'=>['label'=>'Início da vigência','type'=>'date','rules'=>'required|date_format:Y-m-d','fixed'=>true],
            'ends_on'=>['label'=>'Fim da vigência','type'=>'date','rules'=>'required|date_format:Y-m-d|after_or_equal:starts_on','fixed'=>true],
            'starts_at'=>['label'=>'Horário de início','type'=>'time','rules'=>'required|date_format:H:i','fixed'=>true],
            'ends_at'=>['label'=>'Horário de término','type'=>'time','rules'=>'required|date_format:H:i|after:starts_at','fixed'=>true],
            'weekdays'=>['label'=>'Dias da semana','type'=>'weekdays','default'=>[1,2,3,4,5],'fixed'=>true],
            'status'=>['label'=>'Situação','type'=>'select','default'=>'ATIVA','options'=>['ATIVA'=>'Ativa','INATIVA'=>'Inativa']],
        ]],
    ],
];
