<?php
namespace App\Livewire;

use App\Services\Audit;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\{DB, Gate};
use Illuminate\Validation\{Rule, ValidationException};
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

class Catalog extends Component
{
    use WithPagination;
    #[Locked] public string $resource;
    #[Locked] public ?int $editingId = null;
    public array $form = [];
    public string $search = '';
    public string $status = '';
    public string $reason = '';
    public bool $showForm = false;

    public function mount(string $resource): void
    {
        abort_unless(array_key_exists($resource, config('catalogs')), 404);
        $this->resource = $resource;
    }
    protected function definition(): array { return config('catalogs.'.$this->resource); }
    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedStatus(): void { $this->resetPage(); }
    public function create(): void
    {
        Gate::authorize('cadastros.editar');
        $this->resetValidation(); $this->editingId = null; $this->reason = ''; $this->form = [];
        foreach ($this->definition()['fields'] as $key => $field) $this->form[$key] = $field['default'] ?? '';
        $this->showForm = true;
    }
    public function edit(int $id): void
    {
        Gate::authorize('cadastros.editar');
        $this->resetValidation();
        $record = $this->definition()['model']::findOrFail($id);
        $this->editingId = $record->id; $this->reason = '';
        foreach ($this->definition()['fields'] as $key => $field) {
            $value = $record->$key;
            $this->form[$key] = ($field['type'] ?? '') === 'date' ? $value?->format('Y-m-d') : $value;
        }
        $this->showForm = true;
    }
    public function cancel(): void { $this->showForm = false; $this->resetValidation(); }

    public function save(): void
    {
        Gate::authorize('cadastros.editar');
        $def = $this->definition(); $model = $def['model']; $table = (new $model)->getTable();
        $rules = []; $attributes = [];
        foreach ($def['fields'] as $key => $field) {
            $type = $field['type'] ?? 'text';
            if (is_string($this->form[$key] ?? null)) $this->form[$key] = trim($this->form[$key]);
            if ($key === 'code') $this->form[$key] = strtoupper($this->form[$key] ?? '');
            $rule = explode('|', $field['rules'] ?? 'required');
            if ($type === 'checkbox') $rule = ['required','boolean'];
            if ($type === 'select') $rule = ['required', Rule::in(array_keys($field['options']))];
            if ($type === 'relation') $rule = ['required','integer', Rule::exists((new $field['model'])->getTable(), 'id')];
            if ($field['unique'] ?? false) $rule[] = Rule::unique($table, $key)->ignore($this->editingId);
            $rules['form.'.$key] = $rule; $attributes['form.'.$key] = $field['label'];
        }
        $rules['reason'] = ['nullable','string','max:1000'];
        $data = $this->validate($rules, [], $attributes)['form'];
        // Only configured fields reach persistence, including if the client adds arbitrary form keys.
        $data = array_intersect_key($data, $def['fields']);
        try {
            DB::transaction(function () use ($model, $table, $data) {
                if (in_array($this->resource,['anos-letivos','turnos','escolas','series','ofertas'])) \App\Services\TransportSchedule::lock();
                $record = $this->editingId ? $model::lockForUpdate()->findOrFail($this->editingId) : new $model;
                $before = $record->only(array_keys($this->definition()['fields']));
                if ($record->exists && (($before['active'] ?? false) && !($data['active'] ?? true) ||
                    (($before['status'] ?? '') !== 'ENCERRADO' && ($data['status'] ?? '') === 'ENCERRADO'))) {
                    if (mb_strlen(trim($this->reason)) < 10) throw ValidationException::withMessages(['reason' => 'Informe uma justificativa com pelo menos 10 caracteres.']);
                }
                if ($this->resource === 'ofertas' && $data['active']) {
                    foreach (['school_id' => \App\Models\School::class, 'grade_id' => \App\Models\Grade::class, 'shift_id' => \App\Models\Shift::class] as $key => $related) {
                        if (!$related::whereKey($data[$key])->where('active', true)->lockForUpdate()->exists())
                            throw ValidationException::withMessages(['form.'.$key => 'Selecione um cadastro ativo.']);
                    }
                }
                if ($this->resource === 'periodos' && $data['active'] &&
                    \App\Models\AcademicYear::whereKey($data['academic_year_id'])->where('status', 'ENCERRADO')->exists())
                    throw ValidationException::withMessages(['form.academic_year_id' => 'O ano letivo está encerrado.']);
                app(\App\Services\TransportService::class)->guardReference($this->resource,$record,$data);
                app(\App\Services\EnrollmentReferences::class)->guard($this->resource,$record,$data);
                $record->fill($data)->save();
                Audit::record($this->editingId ? 'cadastro_alterado' : 'cadastro_criado', $table, $record->id, $before, $record->only(array_keys($data)), $this->reason ?: null);
            });
        } catch (QueryException $e) {
            if (in_array((string) $e->getCode(), ['23000','23505'])) throw ValidationException::withMessages(['form' => 'Já existe um cadastro com esta combinação. Edite o registro existente.']);
            throw $e;
        }
        $this->showForm = false;
        session()->flash('success', 'Cadastro salvo com sucesso.');
    }
    public function render()
    {
        Gate::authorize('cadastros.visualizar');
        $def = $this->definition(); $model = $def['model'];
        $query = $model::query();
        if (($def['search'] ?? '') && $this->search !== '') {
            $field = $def['search']; $value = mb_strtolower(mb_substr($this->search, 0, 100));
            $cast = $query->getConnection()->getDriverName() === 'mysql' ? 'CHAR' : 'TEXT';
            $query->whereRaw("LOWER(CAST($field AS $cast)) LIKE ?", ['%'.$value.'%']);
        }
        if ($this->status !== '' && isset($def['fields']['active'])) $query->where('active', $this->status === 'active');
        $options = [];
        foreach ($def['fields'] as $key => $field) {
            if (($field['type'] ?? '') === 'relation') $options[$key] = $field['model']::orderBy($field['display'])->pluck($field['display'], 'id')->all();
            if (($field['type'] ?? '') === 'select') $options[$key] = $field['options'];
        }
        return view('livewire.catalog', ['definition' => $def, 'records' => $query->orderByDesc('id')->paginate(12), 'options' => $options])
            ->layout('components.layouts.app', ['title' => $def['title']]);
    }
}
