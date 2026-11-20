<?php

namespace App\Domain\Customer\Actions;

use App\Domain\Customer\Models\CustomerAddress;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class SaveCustomerAddressAction
{
    /**
     * Create or update a customer's saved shipping/billing address cleanly inside a transaction.
     *
     * @param  array<string, mixed>  $data
     */
    public function execute(User $user, ?CustomerAddress $address, array $data): CustomerAddress
    {
        if ($address !== null && $address->user_id !== $user->id) {
            throw new InvalidArgumentException('Address does not belong to the given user.');
        }

        return DB::transaction(function () use ($user, $address, $data): CustomerAddress {
            $existingAddresses = CustomerAddress::query()
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->get(['id', 'is_default_shipping', 'is_default_billing']);

            $isFirstAddress = $address === null && $existingAddresses->isEmpty();

            $setShippingDefault = (bool) ($data['is_default_shipping'] ?? false) || $isFirstAddress;
            $setBillingDefault = (bool) ($data['is_default_billing'] ?? false) || $isFirstAddress;

            if ($setShippingDefault) {
                CustomerAddress::query()
                    ->where('user_id', $user->id)
                    ->when($address !== null, fn ($q) => $q->where('id', '!=', $address->id))
                    ->update(['is_default_shipping' => false]);
            }

            if ($setBillingDefault) {
                CustomerAddress::query()
                    ->where('user_id', $user->id)
                    ->when($address !== null, fn ($q) => $q->where('id', '!=', $address->id))
                    ->update(['is_default_billing' => false]);
            }

            $target = $address ?? new CustomerAddress;
            $target->user_id = $user->id;
            $target->fill([
                'label' => self::cleanString($data['label'] ?? 'Home', 'Home'),
                'recipient_name' => self::cleanString($data['recipient_name'] ?? ''),
                'phone' => self::cleanString($data['phone'] ?? ''),
                'country_code' => strtoupper(self::cleanString($data['country_code'] ?? 'PH', 'PH')),
                'province' => self::cleanString($data['province'] ?? ''),
                'city' => self::cleanString($data['city'] ?? ''),
                'barangay' => self::cleanString($data['barangay'] ?? ''),
                'postal_code' => self::cleanString($data['postal_code'] ?? ''),
                'address_line_1' => self::cleanString($data['address_line_1'] ?? ''),
                'address_line_2' => self::nullableString($data['address_line_2'] ?? null),
                'delivery_notes' => self::nullableString($data['delivery_notes'] ?? null),
                'is_default_shipping' => $setShippingDefault,
                'is_default_billing' => $setBillingDefault,
            ]);
            $target->save();

            return $target->refresh();
        });
    }

    /**
     * Toggle shipping and/or billing defaults explicitly for an existing address.
     */
    public function setDefaults(User $user, CustomerAddress $address, bool $defaultShipping, bool $defaultBilling): CustomerAddress
    {
        if ($address->user_id !== $user->id) {
            throw new InvalidArgumentException('Address does not belong to the given user.');
        }

        return DB::transaction(function () use ($user, $address, $defaultShipping, $defaultBilling): CustomerAddress {
            CustomerAddress::query()
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->get(['id']);

            if ($defaultShipping) {
                CustomerAddress::query()
                    ->where('user_id', $user->id)
                    ->where('id', '!=', $address->id)
                    ->update(['is_default_shipping' => false]);
                $address->is_default_shipping = true;
            }

            if ($defaultBilling) {
                CustomerAddress::query()
                    ->where('user_id', $user->id)
                    ->where('id', '!=', $address->id)
                    ->update(['is_default_billing' => false]);
                $address->is_default_billing = true;
            }

            $address->save();

            return $address->refresh();
        });
    }

    private static function cleanString(mixed $value, string $fallback = ''): string
    {
        $trimmed = trim(is_scalar($value) ? (string) $value : '');

        return $trimmed !== '' ? $trimmed : $fallback;
    }

    private static function nullableString(mixed $value): ?string
    {
        $trimmed = trim(is_scalar($value) ? (string) $value : '');

        return $trimmed !== '' ? $trimmed : null;
    }
}
