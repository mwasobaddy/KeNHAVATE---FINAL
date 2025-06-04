<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $firstName = fake()->firstName();
        $lastName = fake()->lastName();
        
        return [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => fake()->unique()->safeEmail(),
            'personal_email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'gender' => fake()->randomElement(['male', 'female', 'other']),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'account_status' => 'active',
            'terms_accepted' => true,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Create a KeNHA staff member with @kenha.co.ke email.
     */
    public function kenhaStaff(): static
    {
        return $this->state(function (array $attributes) {
            $firstName = $attributes['first_name'];
            $lastName = $attributes['last_name'];
            $username = strtolower($firstName . '.' . $lastName);
            
            return [
                'email' => $username . '@kenha.co.ke',
            ];
        });
    }

    /**
     * Create a user with pending account status.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'account_status' => 'pending',
            'email_verified_at' => null,
        ]);
    }

    /**
     * Create a banned user.
     */
    public function banned(): static
    {
        return $this->state(fn (array $attributes) => [
            'account_status' => 'banned',
        ]);
    }

    /**
     * Create a user who hasn't accepted terms.
     */
    public function termsNotAccepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'terms_accepted' => false,
        ]);
    }
}
