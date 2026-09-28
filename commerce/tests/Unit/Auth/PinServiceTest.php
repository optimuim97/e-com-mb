<?php

namespace Tests\Unit\Auth;

use App\Models\User;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Validation\ValidationException;
use Mockery;
use Mockery\MockInterface;
use Modules\Auth\Services\PinService;
use Tests\TestCase;

class PinServiceTest extends TestCase
{
    private Hasher&MockInterface $hasher;
    private PinService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hasher  = Mockery::mock(Hasher::class);
        $this->service = new PinService($this->hasher);
    }

    // --- set() ---

    public function test_set_pin_for_new_user(): void
    {
        $user = $this->userWithPin(null);
        $user->shouldReceive('forceFill')->with(['order_pin' => 'hashed'])->andReturnSelf();
        $user->shouldReceive('save')->once();

        $this->hasher->shouldReceive('make')->with('1234')->andReturn('hashed');

        $this->service->set($user, '1234', null);
    }

    public function test_set_pin_with_correct_current_pin(): void
    {
        $user = $this->userWithPin('old_hash');
        $user->shouldReceive('forceFill')->with(['order_pin' => 'new_hash'])->andReturnSelf();
        $user->shouldReceive('save')->once();

        $this->hasher->shouldReceive('check')->with('old_plain', 'old_hash')->andReturn(true);
        $this->hasher->shouldReceive('make')->with('5678')->andReturn('new_hash');

        $this->service->set($user, '5678', 'old_plain');
    }

    public function test_set_pin_fails_with_wrong_current_pin(): void
    {
        $user = $this->userWithPin('old_hash');

        $this->hasher->shouldReceive('check')->with('wrong', 'old_hash')->andReturn(false);

        $this->expectException(ValidationException::class);

        $this->service->set($user, '1234', 'wrong');
    }

    public function test_set_pin_fails_without_current_pin_when_pin_exists(): void
    {
        $user = $this->userWithPin('old_hash');

        $this->hasher->shouldReceive('check')->with(null, 'old_hash')->andReturn(false);

        $this->expectException(ValidationException::class);

        $this->service->set($user, '1234', null);
    }

    // --- verify() ---

    public function test_verify_returns_true_for_correct_pin(): void
    {
        $user = $this->userWithPin('stored_hash');

        $this->hasher->shouldReceive('check')->with('1234', 'stored_hash')->andReturn(true);

        $this->assertTrue($this->service->verify($user, '1234'));
    }

    public function test_verify_returns_false_for_wrong_pin(): void
    {
        $user = $this->userWithPin('stored_hash');

        $this->hasher->shouldReceive('check')->with('9999', 'stored_hash')->andReturn(false);

        $this->assertFalse($this->service->verify($user, '9999'));
    }

    public function test_verify_throws_when_no_pin_defined(): void
    {
        $user = $this->userWithPin(null);

        $this->expectException(ValidationException::class);

        $this->service->verify($user, '1234');
    }

    // --- helpers ---

    private function userWithPin(?string $hash): User&MockInterface
    {
        /** @var User&MockInterface $user */
        $user = Mockery::mock(User::class)->makePartial();
        $user->setAttribute('order_pin', $hash);

        return $user;
    }
}
