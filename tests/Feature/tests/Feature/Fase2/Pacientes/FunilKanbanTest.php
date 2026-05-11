<?php

namespace Tests\Feature\tests\Feature\Fase2\Pacientes;

use Tests\TestCase;

class FunilKanbanTest extends TestCase
{
    /**
     * A basic feature test example.
     */
    public function test_example(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }
}
