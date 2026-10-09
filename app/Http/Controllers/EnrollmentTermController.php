<?php
namespace App\Http\Controllers;
use App\Models\EnrollmentPeriod;
use App\Services\Audit;
use Illuminate\Support\Facades\{Gate,Storage};

class EnrollmentTermController
{
    public function __invoke(int $enrollmentId,int $periodId)
    {
        Gate::authorize('matriculas.documentos');
        $period=EnrollmentPeriod::where('enrollment_id',$enrollmentId)->findOrFail($periodId);
        abort_unless($period->term_path && Storage::disk('local')->exists($period->term_path),404);
        Audit::record('termo_consultado','enrollments',$enrollmentId,[],['period_id'=>$period->id]);
        return Storage::disk('local')->download($period->term_path,"termo-matricula-{$enrollmentId}-{$periodId}.pdf",[
            'Content-Type'=>'application/pdf','Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff',
        ]);
    }
}
