<?php
namespace App\Livewire;
use App\Models\{AcademicYear, School, Grade, Shift, Locality, SchoolOffering, AuditEvent};
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
class Dashboard extends Component
{
    public function render()
    {
        Gate::authorize('painel.visualizar');
        $canView = auth()->user()->can('cadastros.visualizar');
        return view('livewire.dashboard', [
            'stats' => $canView ? [
                ['Escolas ativas',School::where('active',true)->count(),'escolas','Unidades de ensino'],
                ['Ofertas escolares',SchoolOffering::where('active',true)->whereIn('school_id',School::where('active',true)->select('id'))->whereIn('grade_id',Grade::where('active',true)->select('id'))->whereIn('shift_id',Shift::where('active',true)->select('id'))->count(),'ofertas','Escola, série e turno ativos'],
                ['Localidades',Locality::where('active',true)->count(),'localidades','Áreas cadastradas'],
                ['Anos letivos',AcademicYear::count(),'anos-letivos','Histórico preservado'],
            ] : [],
            'transportStats' => auth()->user()->can('transporte.visualizar') ? [
                ['Linhas ativas',\App\Models\RouteLine::where('active',true)->count(),'linhas','Trajetos cadastrados'],
                ['Veículos cadastrados',\App\Models\Vehicle::count(),'veiculos','Base da frota'],
                ['Veículos indisponíveis',\App\Models\Vehicle::whereIn('status',['EM_MANUTENCAO','INDISPONIVEL','INATIVO'])->count(),'veiculos','Situação atual da frota'],
                ['Operações vigentes',\App\Models\LineOperation::where('status','ATIVA')->whereDate('starts_on','<=',today())->whereDate('ends_on','>=',today())->count(),'operacoes','Dentro do período de atendimento'],
            ] : [],
            'years' => $canView ? AcademicYear::orderByDesc('year')->limit(4)->get() : collect(),
            'events' => auth()->user()->can('auditoria.visualizar') ? AuditEvent::with('actor')->latest('id')->limit(5)->get() : collect(),
        ])->layout('components.layouts.app',['title' => 'Visão geral']);
    }
}
