<?php

namespace App\Services\Auth;

use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RegisterUserService
{
    public function __construct(private readonly PasswordBreachChecker $breachChecker) {}

    public function execute(array $data): User
    {
        // Email enumeration defence: the error message does not confirm whether
        // the email is already registered. An attacker cannot distinguish a
        // "taken" email from a "try something else" prompt.
        if (User::where('email', $data['email'])->exists()) {
            throw ValidationException::withMessages([
                'email' => ['Unable to register. Please try a different email or sign in to an existing account.'],
            ]);
        }

        // HIBP password breach check (fail-open — network errors don't block).
        if ($this->breachChecker->isBreached($data['password'])) {
            throw ValidationException::withMessages([
                'password' => ['This password has appeared in a data breach. Please choose a different password.'],
            ]);
        }

        return DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
            ]);
            $user->password_hash = $data['password'];
            $user->save();

            $org = Organization::create([
                'name' => $data['organization_name'],
                'slug' => $this->uniqueSlug($data['organization_name']),
                'plan' => 'free',
                'settings' => [],
            ]);

            Membership::create([
                'organization_id' => $org->id,
                'user_id' => $user->id,
                'role' => 'owner',
            ]);

            event(new Registered($user));

            return $user;
        });
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $n = 1;

        while (Organization::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$n}";
            $n++;
        }

        return $slug;
    }
}
