<?php

namespace App\Domain\Customer\Actions;

use App\Domain\Customer\Models\CustomerAddress;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class DeleteCustomerAddressAction
{
    public function execute(User $user, CustomerAddress $address): void
    {
        if ($address->user_id !== $user->id) {
            throw new InvalidArgumentException('Address does not belong to the given user.');
        }

        DB::transaction(function () use ($user, $address): void {
            $wasShippingDefault = $address->is_default_shipping;
            $wasBillingDefault = $address->is_default_billing;

            $address->delete();

            if (! $wasShippingDefault && ! $wasBillingDefault) {
                return;
            }

            /** @var CustomerAddress|null $fallback */
            $fallback = CustomerAddress::query()
                ->where('user_id', $user->id)
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if ($fallback === null) {
                return;
            }

            if ($wasShippingDefault) {
                $fallback->is_default_shipping = true;
            }

            if ($wasBillingDefault) {
                $fallback->is_default_billing = true;
            }

            $fallback->save();
        });
    }
}
