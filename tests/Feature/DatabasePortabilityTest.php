<?php
namespace Tests\Feature;
use App\Livewire\Catalog;
use App\Models\{AcademicYear,School};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DatabasePortabilityTest extends TestCase
{
    use RefreshDatabase;
    public function test_search_supports_numeric_years_and_text_names(): void
    {
        $this->seed(); $this->actingAs($this->user());
        AcademicYear::create(['year'=>2027,'starts_on'=>'2027-02-01','ends_on'=>'2027-12-20','status'=>'PLANEJAMENTO']);
        AcademicYear::create(['year'=>2028,'starts_on'=>'2028-02-01','ends_on'=>'2028-12-20','status'=>'PLANEJAMENTO']);
        Livewire::test(Catalog::class,['resource'=>'anos-letivos'])->set('search','2027')->assertSee('2027')->assertDontSee('2028');
        School::create(['name'=>'Escola Horizonte','network'=>'MUNICIPAL','active'=>true]);
        Livewire::test(Catalog::class,['resource'=>'escolas'])->set('search','horizonte')->assertSee('Escola Horizonte')
            ->set('search','inexistente')->assertSee('Nenhum cadastro encontrado');
    }
}
