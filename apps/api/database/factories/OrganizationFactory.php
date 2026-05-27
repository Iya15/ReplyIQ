<?php

namespace Database\Factories;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->numerify('###'),
            'plan' => 'free',
            'settings' => [],
        ];
    }

    public function onPlan(string $plan): static
    {
        return $this->state(['plan' => $plan]);
    }
}
