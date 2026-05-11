<?php

namespace Database\Factories;

use App\Models\Tag;
use App\Models\Tenant;
use App\Support\Tags\TagNormalizer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * T247 — Factory para o Model `Tag`.
 *
 * Define tags livres por padrão. State `sistemica()` aplica prefixo
 * `sys:` e tipo `sistemica`.
 *
 * @extends Factory<Tag>
 */
class TagFactory extends Factory
{
    protected $model = Tag::class;

    /** @var list<string> Nomes de tags médicas realistas */
    private const NOMES = [
        'Diabético',
        'Hipertenso',
        'Cardiopata',
        'Alérgico',
        'Idoso',
        'Gestante',
        'Oncológico',
        'Reabilitação',
        'Pediátrico',
        'Crônico',
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nome = fake()->randomElement(self::NOMES).' '.fake()->numerify('##');

        return [
            'nome' => $nome,
            'nome_normalizado' => TagNormalizer::normalize($nome),
            'tipo' => 'livre',
            'cor' => null,
            'descricao' => null,
        ];
    }

    /**
     * Estado: tag sistêmica (prefixo `sys:`).
     */
    public function sistemica(string $slug = ''): static
    {
        $slug = $slug !== '' ? $slug : fake()->slug(2);
        $nomeCompleto = 'sys:'.$slug;

        return $this->state([
            'nome' => $nomeCompleto,
            'nome_normalizado' => TagNormalizer::normalize($nomeCompleto),
            'tipo' => 'sistemica',
        ]);
    }

    /**
     * Vincula ao tenant especificado.
     */
    public function forTenant(Tenant $tenant): static
    {
        return $this->state(['tenant_id' => $tenant->id]);
    }
}
