<?php
use App\Models\{User, Role, AcademicYear, School, Grade, Shift, Locality, SchoolOffering, RegistrationPeriod};
use App\Services\Audit;
use Illuminate\Support\Facades\{Artisan, DB, Validator};
use Illuminate\Validation\Rules\Password;
Artisan::command('gtem:admin', function () {
    $data = ['name' => $this->ask('Nome'), 'email' => strtolower(trim($this->ask('E-mail'))),
        'password' => $this->secret('Senha (12+ caracteres, maiúsculas, minúsculas e números)')];
    $validator = Validator::make($data, ['name' => 'required|max:120', 'email' => 'required|email|unique:users,email',
        'password' => ['required', Password::min(12)->mixedCase()->numbers()]]);
    if ($validator->fails()) { foreach ($validator->errors()->all() as $error) $this->error($error); return 1; }
    DB::transaction(function () use ($data) {
        $role = Role::where('code', 'administrador')->firstOrFail();
        $user = User::create([...$data, 'role_id' => $role->id, 'active' => true]);
        Audit::record('usuario_criado_cli', 'users', $user->id, [], ['role_id' => $role->id, 'active' => true]);
    });
    $this->info('Administrador criado. Acesse /entrar.');
})->purpose('Criar administrador sem senha padrão');
Artisan::command('gtem:demo', function () {
    if (!app()->environment(['local','testing'])) { $this->error('Comando disponível apenas no ambiente local.'); return 1; }
    DB::transaction(function () {
        $year = AcademicYear::firstOrCreate(['year' => now()->year + 1], ['starts_on' => now()->addYear()->startOfYear()->addMonth()->toDateString(), 'ends_on' => now()->addYear()->endOfYear()->toDateString(), 'status' => 'PLANEJAMENTO']);
        foreach (['MANHA' => 'Manhã','TARDE' => 'Tarde','INTEGRAL' => 'Integral'] as $code => $name) Shift::firstOrCreate(['code' => $code], ['name' => $name]);
        foreach (range(1,9) as $number) Grade::firstOrCreate(['code' => 'EF'.$number], ['name' => $number.'º ano — Ensino fundamental']);
        $school = School::firstOrCreate(['name' => 'Escola Demonstração Horizonte'], ['network' => 'MUNICIPAL','address' => 'Endereço fictício']);
        Locality::firstOrCreate(['name' => 'Comunidade Demonstração'], ['type' => 'COMUNIDADE']);
        SchoolOffering::firstOrCreate(['school_id' => $school->id,'grade_id' => Grade::first()->id,'shift_id' => Shift::first()->id]);
        Audit::record('carga_demonstracao', 'academic_years', $year->id);
    });
    $this->info('Cadastros fictícios criados. Nenhum usuário ou senha foi adicionado.');
})->purpose('Adicionar somente cadastros fictícios para demonstração local');
