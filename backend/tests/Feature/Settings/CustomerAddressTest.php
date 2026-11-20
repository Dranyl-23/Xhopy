<?php

use App\Domain\Customer\Models\CustomerAddress;
use App\Domain\Identity\Enums\SecurityAuditEvent;
use App\Domain\Identity\Models\SecurityAuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('authenticated verified customer can manage addresses and atomic defaults', function () {
    $customer = User::factory()->create();

    $this->actingAs($customer)
        ->get(route('addresses.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Addresses')
            ->where('addresses', [])
        );

    $this->actingAs($customer)
        ->post(route('addresses.store'), [
            'label' => 'Home',
            'recipient_name' => 'Juan Dela Cruz',
            'phone' => '+63 917 555 0101',
            'country_code' => 'PH',
            'province' => 'Metro Manila',
            'city' => 'Makati City',
            'barangay' => 'San Lorenzo',
            'postal_code' => '1223',
            'address_line_1' => '128 Ayala Avenue',
            'address_line_2' => 'Unit 4B',
            'delivery_notes' => 'Call upon arrival',
        ])
        ->assertRedirect(route('addresses.index'));

    /** @var CustomerAddress $firstAddress */
    $firstAddress = CustomerAddress::query()->where('user_id', $customer->id)->firstOrFail();

    expect($firstAddress->is_default_shipping)->toBeTrue()
        ->and($firstAddress->is_default_billing)->toBeTrue();

    // Adding a second address marked default shipping swaps default cleanly.
    $this->actingAs($customer)
        ->post(route('addresses.store'), [
            'label' => 'Office',
            'recipient_name' => 'Juan Dela Cruz',
            'phone' => '+63 917 555 0202',
            'country_code' => 'PH',
            'province' => 'Metro Manila',
            'city' => 'Taguig City',
            'barangay' => 'Fort Bonifacio',
            'postal_code' => '1634',
            'address_line_1' => '32nd Street BGC',
            'is_default_shipping' => true,
            'is_default_billing' => false,
        ])
        ->assertRedirect(route('addresses.index'));

    /** @var CustomerAddress $secondAddress */
    $secondAddress = CustomerAddress::query()
        ->where('user_id', $customer->id)
        ->where('id', '!=', $firstAddress->id)
        ->firstOrFail();

    expect($firstAddress->fresh()?->is_default_shipping)->toBeFalse()
        ->and($firstAddress->fresh()?->is_default_billing)->toBeTrue()
        ->and($secondAddress->is_default_shipping)->toBeTrue();

    // Update address details and swap billing default.
    $this->actingAs($customer)
        ->patch(route('addresses.update', $secondAddress), [
            'label' => 'Office HQ',
            'recipient_name' => 'Juan Santos',
            'phone' => '+63 917 555 0303',
            'country_code' => 'PH',
            'province' => 'Metro Manila',
            'city' => 'Taguig City',
            'barangay' => 'Fort Bonifacio',
            'postal_code' => '1634',
            'address_line_1' => '9th Ave BGC',
            'is_default_shipping' => true,
            'is_default_billing' => true,
        ])
        ->assertRedirect(route('addresses.index'));

    expect($secondAddress->fresh()?->label)->toBe('Office HQ')
        ->and($secondAddress->fresh()?->is_default_billing)->toBeTrue()
        ->and($firstAddress->fresh()?->is_default_billing)->toBeFalse();

    // Deleting the default address automatically promotes the remaining address.
    $this->actingAs($customer)
        ->delete(route('addresses.destroy', $secondAddress))
        ->assertRedirect(route('addresses.index'));

    expect(CustomerAddress::query()->where('id', $secondAddress->id)->exists())->toBeFalse()
        ->and($firstAddress->fresh()?->is_default_shipping)->toBeTrue()
        ->and($firstAddress->fresh()?->is_default_billing)->toBeTrue();

    expect(SecurityAuditLog::query()->where('user_id', $customer->id)->where('event', SecurityAuditEvent::AddressCreated->value)->count())->toBe(2)
        ->and(SecurityAuditLog::query()->where('user_id', $customer->id)->where('event', SecurityAuditEvent::AddressDeleted->value)->count())->toBe(1);
});

test('customers cannot modify, default, or delete another users saved address (IDOR protection)', function () {
    $owner = User::factory()->create();
    $attacker = User::factory()->create();

    $victimAddress = CustomerAddress::factory()->defaultShipping()->create([
        'user_id' => $owner->id,
        'label' => 'Victim Home',
    ]);

    $this->actingAs($attacker)
        ->patch(route('addresses.update', $victimAddress), [
            'label' => 'Hijacked',
            'recipient_name' => 'Attacker',
            'phone' => '+63 917 111 2222',
            'country_code' => 'PH',
            'province' => 'Metro Manila',
            'city' => 'Pasig City',
            'barangay' => 'Kapitolyo',
            'postal_code' => '1603',
            'address_line_1' => '123 Street',
        ])
        ->assertForbidden();

    $this->actingAs($attacker)
        ->patch(route('addresses.default', $victimAddress), [
            'shipping' => true,
        ])
        ->assertForbidden();

    $this->actingAs($attacker)
        ->delete(route('addresses.destroy', $victimAddress))
        ->assertForbidden();

    expect($victimAddress->fresh()?->label)->toBe('Victim Home');
});

test('address validation rejects invalid phone, postal code, or unsupported country code', function () {
    $customer = User::factory()->create();

    $this->actingAs($customer)
        ->from(route('addresses.index'))
        ->post(route('addresses.store'), [
            'label' => 'Home',
            'recipient_name' => 'Juan Dela Cruz',
            'phone' => 'invalid-phone!!',
            'country_code' => 'XX',
            'province' => 'Metro Manila',
            'city' => 'Makati City',
            'barangay' => 'San Lorenzo',
            'postal_code' => '!@#',
            'address_line_1' => '128 Ayala Avenue',
        ])
        ->assertRedirect(route('addresses.index'))
        ->assertSessionHasErrors(['phone', 'country_code', 'postal_code']);
});
