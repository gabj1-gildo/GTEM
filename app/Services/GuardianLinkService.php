<?php
namespace App\Services;

use App\Models\{Student, Guardian, GuardianStudentLink};
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\{DB, Gate, Validator};
use Illuminate\Validation\{Rule, ValidationException};

final class GuardianLinkService
{
    public function save(int $studentId, int $guardianId, array $input, ?int $linkId = null): GuardianStudentLink
    {
        Gate::authorize('vinculos.gerenciar');
        $data=Validator::make($input,[
            'relationship'=>['required',Rule::in(array_keys(config('people.relationships')))],
            'is_primary'=>['required','boolean'],'can_request'=>['required','boolean'],
            'reason'=>['required','string','min:10','max:1000'],
        ],[],['relationship'=>'tipo de vínculo','reason'=>'justificativa'])->validate();
        try {
            return DB::transaction(function () use ($studentId,$guardianId,$data,$linkId) {
                $student=Student::lockForUpdate()->findOrFail($studentId);
                $guardian=Guardian::lockForUpdate()->findOrFail($guardianId);
                if (!$student->active || !$guardian->active)
                    throw ValidationException::withMessages(['guardianId'=>'O aluno e o responsável precisam estar ativos para criar ou alterar um vínculo.']);
                $previous=$linkId ? GuardianStudentLink::where('student_id',$studentId)->where('guardian_id',$guardianId)->lockForUpdate()->findOrFail($linkId) : null;
                if ($previous?->ended_at)
                    throw ValidationException::withMessages(['link'=>'Este vínculo já foi encerrado ou substituído. Atualize a página.']);
                if (!$previous && GuardianStudentLink::current()->where('student_id',$studentId)->where('guardian_id',$guardianId)->exists())
                    throw ValidationException::withMessages(['guardianId'=>'Este responsável já possui um vínculo vigente com o aluno.']);
                if ($data['is_primary'] && GuardianStudentLink::current()->where('student_id',$studentId)->where('is_primary',true)
                    ->when($linkId,fn ($q)=>$q->where('id','!=',$linkId))->exists())
                    throw ValidationException::withMessages(['is_primary'=>'Já existe um responsável principal. Altere ou encerre o vínculo principal antes de escolher outro.']);
                $at=now();
                if ($previous) {
                    $previous->update(['ended_at'=>$at,'ended_by'=>auth()->id(),'end_reason'=>$data['reason']]);
                }
                $link=GuardianStudentLink::create([
                    'student_id'=>$studentId,'guardian_id'=>$guardianId,
                    'relationship'=>$data['relationship'],'is_primary'=>$data['is_primary'],'can_request'=>$data['can_request'],
                    'started_at'=>$at,'created_by'=>auth()->id(),'reason'=>$data['reason'],'supersedes_id'=>$previous?->id,
                ]);
                Audit::record($previous ? 'vinculo_versionado':'vinculo_criado','guardian_student_links',$link->id,
                    $previous ? ['versao_anterior'=>$previous->id] : [],
                    ['student_id'=>$studentId,'guardian_id'=>$guardianId,'is_primary'=>$link->is_primary,'can_request'=>$link->can_request],$data['reason']);
                return $link;
            },3);
        } catch (QueryException $e) {
            if (in_array((string)$e->getCode(),['23000','23505']))
                throw ValidationException::withMessages(['link'=>'Outro usuário já registrou este vínculo ou um responsável principal. Atualize a página.']);
            throw $e;
        }
    }
    public function end(int $studentId, int $linkId, string $reason): void
    {
        Gate::authorize('vinculos.gerenciar');
        Validator::make(['reason'=>$reason],['reason'=>['required','string','min:10','max:1000']],[],['reason'=>'justificativa'])->validate();
        DB::transaction(function () use ($studentId,$linkId,$reason) {
            Student::lockForUpdate()->findOrFail($studentId);
            $link=GuardianStudentLink::where('student_id',$studentId)->lockForUpdate()->findOrFail($linkId);
            if ($link->ended_at) throw ValidationException::withMessages(['link'=>'Este vínculo já foi encerrado. Atualize a página.']);
            $link->update(['ended_at'=>now(),'ended_by'=>auth()->id(),'end_reason'=>$reason]);
            Audit::record('vinculo_encerrado','guardian_student_links',$link->id,
                ['student_id'=>$studentId,'guardian_id'=>$link->guardian_id],['encerrado'=>true],$reason);
        },3);
    }
}
