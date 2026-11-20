<?php

namespace Database\Factories\Customer;

use App\Domain\Customer\Models\CustomerAddress;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerAddress>
 */
class CustomerAddressFactory extends Factory
{
    protected $model = CustomerAddress::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'label' => 'Home',
            'recipient_name' => fake()->name(),
            'phone' => '+63 917 '.fake()->numerify('### ####'),
            'country_code' => 'PH',
            'province' => 'Metro Manila',
            'city' => 'Makati City',
            'barangay' => 'San Lorenzo',
            'postal_code' => '1223',
            'address_line_1' => fake()->streetAddress(),
            'address_line_2' => null,
            'delivery_notes' => null,
            'is_default_shipping' => false,
            'is_default_billing' => false,
        ];
    }

    public function defaultShipping(): static
    {
        return $this->state(fn (): array => [
            'is_default_shipping' => true,
        ]);
    }

    public function defaultBilling(): static
    {
        return $this->state(fn (): array => [
            'is_default_billing' => true,
        ]);
    }
}
