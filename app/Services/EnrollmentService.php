<?php
namespace App\Services;

use App\Models\{AcademicYear,Enrollment,EnrollmentPeriod,Grade,Guardian,GuardianStudentLink,LineOperation,School,SchoolOffering,Shift,Student};
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB,Gate,Storage,Validator};
use Illuminate\Validation\Rule;
use Throwable;

final class EnrollmentService
{
    public function saveDraft(array $input, ?int $id=null, ?int $version=null): Enrollment
    {
        Gate::authorize('matriculas.editar');
        $data=$this->validate($input);
        return DB::transaction(function () use ($data,$id,$version) {
            TransportSchedule::lock();
            $enrollment=$id ? $this->locked($id,$version) : new Enrollment;
            if ($id && $enrollment->status!=='RASCUNHO') TransportService::fail('form','Somente rascunhos podem ser editados. Use a transferência para uma matrícula aprovada.');
            if ($id && ($enrollment->student_id!=(int)$data['student_id'] || $enrollment->academic_year_id!=(int)$data['academic_year_id']))
                TransportService::fail('form','Aluno e ano letivo identificam a matrícula e não podem ser alterados.');
            if (!$id && Enrollment::where('student_id',$data['student_id'])->where('academic_year_id',$data['academic_year_id'])->exists())
                TransportService::fail('student_id','Já existe uma matrícula deste aluno neste ano. Abra o registro existente.');
            $this->checkReferences($data);
            if (!$id) $enrollment->fill(['student_id'=>$data['student_id'],'academic_year_id'=>$data['academic_year_id'],'created_by'=>auth()->id(),'status'=>'RASCUNHO','version'=>1])->save();
            else {
                $enrollment->periods()->whereNull('cancelled_at')->update(['cancelled_at'=>now()]);
                $enrollment->increment('version');
            }
            $period=$this->newPeriod($enrollment,$data);
            $this->record($enrollment,$id ? 'rascunho_alterado':'rascunho_criado',$data['reason'],['period_id'=>$period->id]);
            return $enrollment->refresh();
        },3);
    }

    public function attachTerm(int $id,int $version,UploadedFile $file): void
    {
        Gate::authorize('matriculas.editar');
        $term=$this->storeTerm($file);
        try {
            DB::transaction(function () use ($id,$version,$term) {
                TransportSchedule::lock(); $enrollment=$this->locked($id,$version);
                if ($enrollment->status!=='RASCUNHO') TransportService::fail('form','Anexe o termo ao rascunho antes da aprovação.');
                $period=$this->latest($enrollment);
                if ($period->term_path) TransportService::fail('term','Já existe um termo. Edite e salve uma nova versão do rascunho para substituí-lo.');
                $period->update($term); $enrollment->increment('version');
                $this->record($enrollment,'termo_anexado','Termo apresentado para análise administrativa.',['period_id'=>$period->id,'sha256'=>$term['term_sha256']]);
            },3);
        } catch (Throwable $e) { Storage::disk('local')->delete($term['term_path']); throw $e; }
    }

    public function approve(int $id,int $version,bool $reviewed,string $reason): Enrollment
    {
        Gate::authorize('matriculas.aprovar'); Gate::authorize('matriculas.documentos'); $this->review($reviewed,$reason);
        return DB::transaction(function () use ($id,$version,$reason) {
            TransportSchedule::lock(); $enrollment=$this->locked($id,$version);
            if ($enrollment->status!=='RASCUNHO') TransportService::fail('form','Esta matrícula não está em rascunho.');
            $period=$this->latest($enrollment);
            $data=$this->validate($this->periodData($enrollment,$period)+['reason'=>$reason]);
            $this->checkReferences($data);
            if (!$period->term_path || !Storage::disk('local')->exists($period->term_path) || !hash_equals($period->term_sha256,hash('sha256',Storage::disk('local')->get($period->term_path))))
                TransportService::fail('term','Anexe um termo PDF íntegro antes de aprovar.');
            $enrollment->update(['status'=>'APROVADA','approved_by'=>auth()->id(),'approved_at'=>now(),'version'=>$enrollment->version+1]);
            $period->update(['reviewed_by'=>auth()->id(),'reviewed_at'=>now()]);
            $this->checkCapacity($period);
            $this->record($enrollment,'matricula_aprovada',$reason,['period_id'=>$period->id,'sha256'=>$period->term_sha256]);
            return $enrollment;
        },3);
    }

    public function transfer(int $id,int $version,array $input,UploadedFile $file,bool $reviewed): Enrollment
    {
        Gate::authorize('matriculas.aprovar');
        Gate::authorize('matriculas.documentos');
        $data=$this->validate($input); $this->review($reviewed,$data['reason']);
        $term=$this->storeTerm($file);
        try {
            return DB::transaction(function () use ($id,$version,$data,$term) {
                TransportSchedule::lock(); $enrollment=$this->locked($id,$version);
                if ($enrollment->status!=='APROVADA' || $enrollment->closed_on) TransportService::fail('form','Somente matrículas aprovadas sem término agendado podem ser transferidas.');
                if ($enrollment->student_id!=(int)$data['student_id'] || $enrollment->academic_year_id!=(int)$data['academic_year_id'])
                    TransportService::fail('form','A transferência deve manter o aluno e o ano letivo.');
                $old=$this->latest($enrollment);
                if ($data['starts_on']<$old->starts_on->toDateString() || $data['starts_on']>$old->ends_on->toDateString())
                    TransportService::fail('starts_on','A transferência deve começar dentro do último período de atendimento.');
                $this->checkReferences($data);
                $this->truncate($old,$data['starts_on']);
                $period=$this->newPeriod($enrollment,$data,$term+['reviewed_by'=>auth()->id(),'reviewed_at'=>now()]);
                $this->checkCapacity($period); $enrollment->increment('version');
                $this->record($enrollment,'matricula_transferida',$data['reason'],['previous_period_id'=>$old->id,'period_id'=>$period->id,'effective_from'=>$data['starts_on']]);
                return $enrollment->refresh();
            },3);
        } catch (Throwable $e) { Storage::disk('local')->delete($term['term_path']); throw $e; }
    }

    public function close(int $id,int $version,string $effectiveFrom,string $type,string $reason): Enrollment
    {
        Gate::authorize('matriculas.aprovar');
        Validator::make(['effective_from'=>$effectiveFrom,'type'=>$type,'reason'=>trim($reason)],[
            'effective_from'=>'required|date_format:Y-m-d|after_or_equal:today',
            'type'=>['required',Rule::in(['CANCELAMENTO','ENCERRAMENTO'])],'reason'=>'required|string|min:10|max:1000',
        ])->validate();
        return DB::transaction(function () use ($id,$version,$effectiveFrom,$type,$reason) {
            TransportSchedule::lock(); $enrollment=$this->locked($id,$version);
            if ($enrollment->status!=='APROVADA' || $enrollment->closed_on) TransportService::fail('form','A matrícula deve estar aprovada e sem término já registrado.');
            $periods=$enrollment->periods()->whereNull('cancelled_at')->orderBy('starts_on')->get();
            if ($effectiveFrom<$periods->first()->starts_on->toDateString() || $effectiveFrom>$periods->last()->ends_on->addDay()->toDateString())
                TransportService::fail('effective_from','Informe o primeiro dia sem atendimento, dentro da vigência ou no dia seguinte ao término.');
            foreach ($periods as $period) if ($period->ends_on->toDateString()>=$effectiveFrom) $this->truncate($period,$effectiveFrom);
            $enrollment->update(['closed_on'=>$effectiveFrom,'closure_type'=>$type,'version'=>$enrollment->version+1]);
            $this->record($enrollment,'matricula_termino_registrado',$reason,['effective_from'=>$effectiveFrom,'type'=>$type]);
            return $enrollment;
        },3);
    }

    private function validate(array $input): array
    {
        $input['reason']=trim($input['reason'] ?? '');
        return Validator::make($input,[
            'student_id'=>'required|integer|exists:students,id','academic_year_id'=>'required|integer|exists:academic_years,id',
            'guardian_id'=>'required|integer|exists:guardians,id','school_id'=>'required|integer|exists:schools,id',
            'grade_id'=>'required|integer|exists:grades,id','shift_id'=>'required|integer|exists:shifts,id',
            'starts_on'=>'required|date_format:Y-m-d|after_or_equal:today','ends_on'=>'required|date_format:Y-m-d|after_or_equal:starts_on',
            'operation_ids'=>'required|array|min:1|max:14','operation_ids.*'=>'required|integer|distinct|exists:line_operations,id',
            'reason'=>'required|string|min:10|max:1000',
        ])->validate();
    }

    private function checkReferences(array $data): void
    {
        // Same person lock order as GuardianLinkService prevents a link being revoked
        // between its validation and the enrollment decision.
        $student=Student::lockForUpdate()->findOrFail($data['student_id']);
        $guardian=Guardian::lockForUpdate()->findOrFail($data['guardian_id']);
        if (!$student->active || !$guardian->active) TransportService::fail('guardian_id','O aluno e o responsável devem estar ativos.');
        $link=GuardianStudentLink::current()->where('student_id',$student->id)->where('guardian_id',$guardian->id)->first();
        if (!$link || !$link->permitsRequest()) TransportService::fail('guardian_id','Selecione um responsável com vínculo vigente e autorização para solicitar transporte.');
        $year=AcademicYear::findOrFail($data['academic_year_id']);
        if ($year->status!=='ABERTO') TransportService::fail('academic_year_id','O ano letivo deve estar aberto.');
        if ($data['starts_on']<$year->starts_on->toDateString() || $data['ends_on']>$year->ends_on->toDateString())
            TransportService::fail('starts_on','O atendimento deve estar dentro do ano letivo.');
        foreach (['school_id'=>School::class,'grade_id'=>Grade::class,'shift_id'=>Shift::class] as $key=>$model)
            if (!$model::findOrFail($data[$key])->active) TransportService::fail($key,'Selecione um cadastro ativo.');
        if (!SchoolOffering::where('school_id',$data['school_id'])->where('grade_id',$data['grade_id'])->where('shift_id',$data['shift_id'])->where('active',true)->exists())
            TransportService::fail('school_id','Não existe oferta ativa para esta escola, série e turno.');
        $ops=LineOperation::with(['routeLine','shift'])->whereIn('id',$data['operation_ids'])->get();
        foreach ($ops as $i=>$op) {
            if ($op->academic_year_id!=(int)$data['academic_year_id'] || $op->shift_id!=(int)$data['shift_id'] || $op->status!=='ATIVA' || !$op->routeLine->active || !$op->shift->active)
                TransportService::fail('operation_ids','Todas as operações devem estar ativas e pertencer ao ano e turno da matrícula.');
            if ($data['starts_on']<$op->starts_on->toDateString() || $data['ends_on']>$op->ends_on->toDateString() || !TransportSchedule::hasDay($data['starts_on'],$data['ends_on'],$op->weekdays))
                TransportService::fail('operation_ids','O período deve estar dentro da vigência de todas as operações e conter dias de atendimento.');
            foreach ($ops->take($i) as $other) if (TransportSchedule::conflicts($op,$other,$data['starts_on'],$data['ends_on']))
                TransportService::fail('operation_ids','O aluno não pode ocupar operações com dias e horários conflitantes.');
        }
    }

    private function locked(int $id,?int $version): Enrollment
    {
        $enrollment=Enrollment::lockForUpdate()->findOrFail($id);
        if ($enrollment->version!==$version) TransportService::fail('form','A matrícula foi alterada em outra tela. Atualize a página antes de continuar.');
        return $enrollment;
    }
    public function latest(Enrollment $enrollment): EnrollmentPeriod
    {
        return $enrollment->periods()->whereNull('cancelled_at')->orderByDesc('starts_on')->orderByDesc('id')->firstOrFail();
    }
    public function periodData(Enrollment $enrollment,EnrollmentPeriod $period): array
    {
        return ['student_id'=>$enrollment->student_id,'academic_year_id'=>$enrollment->academic_year_id,
            'guardian_id'=>$period->guardian_id,'school_id'=>$period->school_id,'grade_id'=>$period->grade_id,'shift_id'=>$period->shift_id,
            'starts_on'=>$period->starts_on->toDateString(),'ends_on'=>$period->ends_on->toDateString(),'operation_ids'=>$period->operations()->pluck('line_operations.id')->all()];
    }
    private function newPeriod(Enrollment $enrollment,array $data,array $extra=[]): EnrollmentPeriod
    {
        $period=$enrollment->periods()->create(array_intersect_key($data,array_flip(['guardian_id','school_id','grade_id','shift_id','starts_on','ends_on']))+
            ['original_ends_on'=>$data['ends_on']]+$extra);
        $period->operations()->attach($data['operation_ids']);
        return $period;
    }
    private function truncate(EnrollmentPeriod $period,string $from): void
    {
        if ($from<=$period->starts_on->toDateString()) $period->update(['cancelled_at'=>now()]);
        else $period->update(['ends_on'=>CarbonImmutable::parse($from)->subDay()->toDateString()]);
    }
    private function checkCapacity(EnrollmentPeriod $period): void
    {
        $others=EnrollmentPeriod::reserved()->with('operations')
            ->whereHas('enrollment',fn($q)=>$q->where('student_id',$period->enrollment->student_id))
            ->where('id','!=',$period->id)->whereDate('starts_on','<=',$period->ends_on)->whereDate('ends_on','>=',$period->starts_on)->get();
        foreach ($others as $other) foreach ($other->operations as $otherOp) foreach ($period->operations as $op) {
            if (TransportSchedule::conflicts($op,$otherOp,max($period->starts_on->toDateString(),$other->starts_on->toDateString()),min($period->ends_on->toDateString(),$other->ends_on->toDateString())))
                TransportService::fail('operation_ids','O aluno já possui atendimento aprovado em horário conflitante.');
        }
        foreach ($period->operations as $op) app(EnrollmentCapacity::class)->assertCovered($op,$period->starts_on->toDateString(),$period->ends_on->toDateString());
    }
    private function review(bool $reviewed,string $reason): void
    {
        Validator::make(['reviewed'=>$reviewed,'reason'=>trim($reason)],['reviewed'=>'accepted','reason'=>'required|string|min:10|max:1000'],
            ['reviewed.accepted'=>'Confirme a conferência do termo, da assinatura GOV.BR e dos dados da matrícula.'])->validate();
    }
    private function storeTerm(UploadedFile $file): array
    {
        Validator::make(['term'=>$file],['term'=>'required|file|mimes:pdf|mimetypes:application/pdf|max:10240'])->validate();
        if (file_get_contents($file->getRealPath(),false,null,0,5)!=='%PDF-')
            TransportService::fail('term','O arquivo deve conter um documento PDF.');
        // Livewire may move the temporary file when both disks are the same.
        $hash=hash_file('sha256',$file->getRealPath());
        // Explicit visibility also keeps the temporary upload available for retry
        // if validation inside the database transaction rejects the operation.
        $path=$file->store('enrollment-terms',['disk'=>'local','visibility'=>'private']);
        return ['term_path'=>$path,'term_sha256'=>$hash];
    }
    private function record(Enrollment $enrollment,string $action,string $reason,array $details): void
    {
        Audit::record($action,'enrollments',$enrollment->id,[],['version'=>$enrollment->version]+$details,trim($reason));
    }
}
