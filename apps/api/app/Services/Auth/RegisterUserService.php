<?php

namespace App\Services\Auth;

use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RegisterUserService
{
    public function execute(array $data): User
    {
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
