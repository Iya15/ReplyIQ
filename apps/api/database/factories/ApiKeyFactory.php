<?php

namespace Database\Factories;

use App\Models\ApiKey;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ApiKey>
 */
class ApiKeyFactory extends Factory
{
    /**
     * @var array{plain: string}|null
     */
    private ?array $resolved = null;

    public function definition(): array
    {
        $plain = 'rk_live_'.Str::random(32);

        return [
            'organization_id' => Organization::factory(),
            'created_by' => User::factory(),
            'name' => fake()->words(2, true),
            'key_hash' => hash('sha256', $plain),
            'prefix' => substr($plain, 0, 12),
            'last_used_at' => null,
        ];
    }

    /**
     * Returns a factory state with a known plain key for assertions.
     * Access via ->withPlainKey($plain) after creating the model.
     */
    public function forPlainKey(string &$plain): static
    {
        $plain = 'rk_live_'.Str::random(32);
        $captured = $plain;

        return $this->state([
            'key_hash' => hash('sha256', $captured),
            'prefix' => substr($captured, 0, 12),
        ]);
    }
}
