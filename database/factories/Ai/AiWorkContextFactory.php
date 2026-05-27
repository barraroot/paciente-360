<?php

namespace Database\Factories\Ai;

use App\Domain\Ai\WorkContext\Models\AiWorkContext;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiWorkContext>
 */
class AiWorkContextFactory extends Factory
{
    protected $model = AiWorkContext::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'services' => [['nome' => 'Consulta enxaqueca/cefaleia', 'descricao' => 'Avaliação individualizada (~1h)']],
            'pricing' => [['item' => 'Consulta', 'valor_a_vista' => 'R$300', 'valor_cartao' => 'R$330']],
            'locations' => [['cidade' => 'Aracaju', 'endereco' => 'Centro Médico Jardim Europa'], ['cidade' => 'Itabaiana']],
            'deposit_policy' => ['exige_sinal' => true, 'percentual' => 20, 'meio' => 'PIX', 'texto' => 'Sinal de 20% abatido na consulta'],
            'tone' => 'acolhedor, com emojis 💛',
            'qualification_questions' => [
                'Com que frequência as crises acontecem?',
                'Essas dores atrapalham seu trabalho ou rotina?',
                'Você já investigou isso com um médico antes?',
            ],
            'free_form' => 'A Dra. realiza uma avaliação cuidadosa e individualizada, investigando gatilhos, sono e rotina.',
            'version' => 1,
            'is_active' => true,
        ];
    }
}
