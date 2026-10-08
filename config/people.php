<?php
return [
    'resources' => [
        'alunos' => ['type'=>'student','model'=>App\Models\Student::class,'title'=>'Alunos','singular'=>'aluno'],
        'responsaveis' => ['type'=>'guardian','model'=>App\Models\Guardian::class,'title'=>'Responsáveis','singular'=>'responsável'],
    ],
    'relationships' => ['MAE'=>'Mãe','PAI'=>'Pai','RESPONSAVEL_LEGAL'=>'Responsável legal','OUTRO'=>'Outro vínculo'],
    'labels' => ['name'=>'Nome','cpf'=>'CPF','birth_date'=>'Data de nascimento','phone'=>'Telefone','email'=>'E-mail','active'=>'Situação'],
];
