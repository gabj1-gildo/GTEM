<?php
use App\Models\{AcademicYear, RegistrationPeriod, School, Grade, Shift, Locality, SchoolOffering};
return [
    'anos-letivos' => ['title' => 'Anos letivos', 'description' => 'Organize cada ciclo sem perder o histórico dos anos anteriores.', 'model' => AcademicYear::class, 'search' => 'year', 'fields' => [
        'year' => ['label' => 'Ano', 'type' => 'number', 'rules' => 'required|integer|min:2000|max:2200', 'unique' => true],
        'starts_on' => ['label' => 'Início do ano letivo', 'type' => 'date', 'rules' => 'required|date'],
        'ends_on' => ['label' => 'Fim do ano letivo', 'type' => 'date', 'rules' => 'required|date|after_or_equal:form.starts_on'],
        'status' => ['label' => 'Situação', 'type' => 'select', 'options' => ['PLANEJAMENTO' => 'Planejamento','ABERTO' => 'Aberto','ENCERRADO' => 'Encerrado'], 'default' => 'PLANEJAMENTO'],
    ]],
    'periodos' => ['title' => 'Períodos de inscrição', 'description' => 'Configure as janelas de primeira matrícula e rematrícula.', 'model' => RegistrationPeriod::class, 'fields' => [
        'academic_year_id' => ['label' => 'Ano letivo', 'type' => 'relation', 'model' => AcademicYear::class, 'display' => 'year'],
        'type' => ['label' => 'Modalidade', 'type' => 'select', 'options' => ['PRIMEIRA_MATRICULA' => 'Primeira matrícula','REMATRICULA' => 'Rematrícula']],
        'starts_on' => ['label' => 'Abertura', 'type' => 'date', 'rules' => 'required|date'],
        'ends_on' => ['label' => 'Encerramento', 'type' => 'date', 'rules' => 'required|date|after_or_equal:form.starts_on'],
        'active' => ['label' => 'Ativo', 'type' => 'checkbox', 'default' => true],
    ]],
    'escolas' => ['title' => 'Escolas', 'description' => 'Mantenha as unidades de ensino atendidas pelo município.', 'model' => School::class, 'search' => 'name', 'fields' => [
        'name' => ['label' => 'Nome da escola', 'rules' => 'required|string|max:150'],
        'network' => ['label' => 'Rede de ensino', 'type' => 'select', 'options' => ['MUNICIPAL' => 'Municipal','ESTADUAL' => 'Estadual','FEDERAL' => 'Federal','PRIVADA' => 'Privada']],
        'phone' => ['label' => 'Telefone', 'rules' => 'nullable|string|max:30'],
        'address' => ['label' => 'Endereço', 'rules' => 'nullable|string|max:255'],
        'active' => ['label' => 'Ativa', 'type' => 'checkbox', 'default' => true],
    ]],
    'series' => ['title' => 'Séries / anos escolares', 'description' => 'Cadastre as etapas de ensino disponíveis para as escolas.', 'model' => Grade::class, 'search' => 'name', 'fields' => [
        'code' => ['label' => 'Código', 'rules' => 'required|alpha_dash:ascii|max:30', 'unique' => true],
        'name' => ['label' => 'Nome', 'rules' => 'required|string|max:100'],
        'active' => ['label' => 'Ativa', 'type' => 'checkbox', 'default' => true],
    ]],
    'turnos' => ['title' => 'Turnos', 'description' => 'Defina os turnos para a oferta escolar e as futuras operações.', 'model' => Shift::class, 'search' => 'name', 'fields' => [
        'code' => ['label' => 'Código', 'rules' => 'required|alpha_dash:ascii|max:30', 'unique' => true],
        'name' => ['label' => 'Nome', 'rules' => 'required|string|max:100'],
        'active' => ['label' => 'Ativo', 'type' => 'checkbox', 'default' => true],
    ]],
    'localidades' => ['title' => 'Localidades', 'description' => 'Organize bairros, comunidades e demais áreas de atendimento.', 'model' => Locality::class, 'search' => 'name', 'fields' => [
        'name' => ['label' => 'Nome', 'rules' => 'required|string|max:150'],
        'type' => ['label' => 'Tipo', 'type' => 'select', 'options' => ['BAIRRO' => 'Bairro','COMUNIDADE' => 'Comunidade','FAZENDA' => 'Fazenda','DISTRITO' => 'Distrito','OUTRO' => 'Outro']],
        'active' => ['label' => 'Ativa', 'type' => 'checkbox', 'default' => true],
    ]],
    'ofertas' => ['title' => 'Oferta escolar', 'description' => 'Relacione escola, série e turno para evitar combinações inválidas na matrícula.', 'model' => SchoolOffering::class, 'fields' => [
        'school_id' => ['label' => 'Escola', 'type' => 'relation', 'model' => School::class, 'display' => 'name'],
        'grade_id' => ['label' => 'Série / ano', 'type' => 'relation', 'model' => Grade::class, 'display' => 'name'],
        'shift_id' => ['label' => 'Turno', 'type' => 'relation', 'model' => Shift::class, 'display' => 'name'],
        'active' => ['label' => 'Ativa', 'type' => 'checkbox', 'default' => true],
    ]],
];
