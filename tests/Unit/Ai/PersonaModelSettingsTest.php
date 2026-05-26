<?php

namespace Tests\Unit\Ai;

use App\Domain\Ai\Model\Models\AiModel;
use App\Http\Requests\Ai\PersonaSettingsValidator;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PersonaModelSettingsTest extends TestCase
{
    private function modelWithSchema(): AiModel
    {
        return new AiModel([
            'config_schema' => [
                'temperature' => ['min' => 0, 'max' => 1],
                'max_tokens' => ['min' => 256, 'max' => 4096],
            ],
        ]);
    }

    #[Test]
    public function it_accepts_settings_within_bounds(): void
    {
        $validator = Validator::make([], []);
        PersonaSettingsValidator::validate($validator, $this->modelWithSchema(), [
            'temperature' => 0.5,
            'max_tokens' => 1024,
        ]);

        $this->assertFalse($validator->errors()->has('model_settings.temperature'));
        $this->assertFalse($validator->errors()->has('model_settings.max_tokens'));
    }

    #[Test]
    public function it_rejects_value_above_max(): void
    {
        $validator = Validator::make([], []);
        PersonaSettingsValidator::validate($validator, $this->modelWithSchema(), [
            'temperature' => 5,
        ]);

        $this->assertTrue($validator->errors()->has('model_settings.temperature'));
    }

    #[Test]
    public function it_rejects_unknown_setting_key(): void
    {
        $validator = Validator::make([], []);
        PersonaSettingsValidator::validate($validator, $this->modelWithSchema(), [
            'top_p' => 0.9,
        ]);

        $this->assertTrue($validator->errors()->has('model_settings.top_p'));
    }
}
