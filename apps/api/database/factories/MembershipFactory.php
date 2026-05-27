<?php

namespace Database\Factories;

use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Membership>
 */
class MembershipFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'user_id' => User::factory(),
            'role' => 'member',
        ];
    }

    public function owner(): static
    {
        return $this->state(['role' => 'owner']);
    }

    public function admin(): static
    {
        return $this->state(['role' => 'admin']);
    }
}
