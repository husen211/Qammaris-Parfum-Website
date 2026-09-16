<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\TestCase;

class LoginThrottleTest extends TestCase
{
    use RefreshDatabase;

    private const IP_ADDRESS = '203.0.113.10';

    public function test_sixth_attempt_is_throttled_even_with_the_correct_password(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => 'correct-password',
            'role' => 'admin',
        ]);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->from(route('login'))
                ->withServerVariables(['REMOTE_ADDR' => self::IP_ADDRESS])
                ->post(route('login.perform'), [
                    'email' => $user->email,
                    'password' => 'wrong-password',
                ])
                ->assertRedirect(route('login'))
                ->assertSessionHasErrors('email');
        }

        $response = $this->from(route('login'))
            ->withServerVariables(['REMOTE_ADDR' => self::IP_ADDRESS])
            ->post(route('login.perform'), [
                'email' => $user->email,
                'password' => 'correct-password',
            ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email', fn (string $message): bool => str_contains(
            $message,
            'Terlalu banyak percobaan masuk.'
        ));
        $this->assertGuest();

        RateLimiter::clear($this->throttleKey($user->email));

        $this->withServerVariables(['REMOTE_ADDR' => self::IP_ADDRESS])
            ->post(route('login.perform'), [
                'email' => $user->email,
                'password' => 'correct-password',
            ])
            ->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_failed_attempts_are_isolated_by_normalized_email(): void
    {
        $firstUser = User::factory()->create([
            'email' => 'first@example.com',
            'password' => 'correct-password',
        ]);
        $secondUser = User::factory()->create([
            'email' => 'second@example.com',
            'password' => 'correct-password',
        ]);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => self::IP_ADDRESS])
                ->post(route('login.perform'), [
                    'email' => strtoupper($firstUser->email),
                    'password' => 'wrong-password',
                ]);
        }

        $this->withServerVariables(['REMOTE_ADDR' => self::IP_ADDRESS])
            ->post(route('login.perform'), [
                'email' => $secondUser->email,
                'password' => 'correct-password',
            ])
            ->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($secondUser);
    }

    public function test_successful_login_clears_its_failed_attempt_counter(): void
    {
        $user = User::factory()->create([
            'email' => 'member@example.com',
            'password' => 'correct-password',
        ]);

        $this->withServerVariables(['REMOTE_ADDR' => self::IP_ADDRESS])
            ->post(route('login.perform'), [
                'email' => $user->email,
                'password' => 'wrong-password',
            ]);

        $this->withServerVariables(['REMOTE_ADDR' => self::IP_ADDRESS])
            ->post(route('login.perform'), [
                'email' => $user->email,
                'password' => 'correct-password',
            ])
            ->assertRedirect(route('home'));

        $this->assertSame(0, RateLimiter::attempts($this->throttleKey($user->email)));
    }

    private function throttleKey(string $email): string
    {
        return Str::transliterate(Str::lower(trim($email))).'|'.self::IP_ADDRESS;
    }
}
