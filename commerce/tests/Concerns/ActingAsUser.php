<?php

namespace Tests\Concerns;

use App\Models\User;
use Spatie\Permission\Models\Role;

trait ActingAsUser
{
    protected User $user;

    protected function setUpUser(): void
    {
        Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
        $this->user = User::factory()->create();
    }

    protected function asUser(?User $user = null)
    {
        return $this->actingAs($user ?? $this->user, 'sanctum');
    }
}
