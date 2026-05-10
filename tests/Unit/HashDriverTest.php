<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class HashDriverTest extends TestCase
{
    public function test_default_driver_is_argon2id(): void
    {
        $this->assertSame('argon2id', config('hashing.driver'));
    }

    public function test_hash_make_produces_argon2id_prefix(): void
    {
        $hash = Hash::make('paciente-360-test');

        $this->assertStringStartsWith('$argon2id$', $hash);
    }

    public function test_argon_options_meet_security_baseline(): void
    {
        $options = config('hashing.argon');

        $this->assertGreaterThanOrEqual(65536, $options['memory']);
        $this->assertGreaterThanOrEqual(1, $options['threads']);
        $this->assertGreaterThanOrEqual(4, $options['time']);
    }

    public function test_bcrypt_fallback_default_is_at_least_12_rounds(): void
    {
        // phpunit.xml força BCRYPT_ROUNDS=4 para acelerar testes. O critério LGPD/segurança
        // exige que o DEFAULT do arquivo de config (sem env override) seja ≥ 12.
        $contents = file_get_contents(config_path('hashing.php'));

        $this->assertMatchesRegularExpression(
            "/env\(\s*['\"]BCRYPT_ROUNDS['\"]\s*,\s*(1[2-9]|[2-9]\d)\s*\)/",
            $contents,
            'O default de BCRYPT_ROUNDS no config/hashing.php deve ser ≥ 12.'
        );
    }

    public function test_hash_check_validates_argon2id_hash(): void
    {
        $plain = 'segredo-forte-123';
        $hash = Hash::make($plain);

        $this->assertTrue(Hash::check($plain, $hash));
        $this->assertFalse(Hash::check('errado', $hash));
    }
}
