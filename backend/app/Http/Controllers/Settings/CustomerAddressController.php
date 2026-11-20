<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Customer\Actions\DeleteCustomerAddressAction;
use App\Domain\Customer\Actions\SaveCustomerAddressAction;
use App\Domain\Customer\Models\CustomerAddress;
use App\Domain\Identity\Enums\SecurityAuditEvent;
use App\Domain\Identity\Services\SecurityAuditLogger;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\SaveCustomerAddressRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CustomerAddressController extends Controller
{
    public function __construct(
        private readonly SecurityAuditLogger $auditLogger,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(403);
        }

        $addresses = $user->addresses()
            ->orderByDesc('is_default_shipping')
            ->orderByDesc('is_default_billing')
            ->orderByDesc('updated_at')
            ->get()
            ->map(static fn (CustomerAddress $address): array => [
                'id' => $address->id,
                'label' => $address->label,
                'recipient_name' => $address->recipient_name,
                'phone' => $address->phone,
                'country_code' => $address->country_code,
                'province' => $address->province,
                'city' => $address->city,
                'barangay' => $address->barangay,
                'postal_code' => $address->postal_code,
                'address_line_1' => $address->address_line_1,
                'address_line_2' => $address->address_line_2 ?? '',
                'delivery_notes' => $address->delivery_notes ?? '',
                'is_default_shipping' => $address->is_default_shipping,
                'is_default_billing' => $address->is_default_billing,
                'formatted_line' => $address->formattedLine(),
            ])
            ->values()
            ->all();

        return Inertia::render('settings/Addresses', [
            'addresses' => $addresses,
            'countryOptions' => [
                ['value' => 'PH', 'label' => 'Philippines (PH)'],
                ['value' => 'SG', 'label' => 'Singapore (SG)'],
                ['value' => 'US', 'label' => 'United States (US)'],
            ],
            'labelOptions' => [
                ['value' => 'Home', 'label' => 'Home'],
                ['value' => 'Office', 'label' => 'Office'],
                ['value' => 'Warehouse', 'label' => 'Warehouse'],
                ['value' => 'Billing', 'label' => 'Billing'],
            ],
        ]);
    }

    public function store(SaveCustomerAddressRequest $request, SaveCustomerAddressAction $action): RedirectResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(403);
        }

        $address = $action->execute($user, null, $request->validated());

        $this->auditLogger->record(
            event: SecurityAuditEvent::AddressCreated,
            user: $user,
            actor: $user,
            metadata: ['address_id' => $address->id, 'label' => $address->label],
            request: $request,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Address saved.')]);

        return to_route('addresses.index');
    }

    public function update(
        SaveCustomerAddressRequest $request,
        CustomerAddress $address,
        SaveCustomerAddressAction $action,
    ): RedirectResponse {
        Gate::authorize('update', $address);

        $user = $request->user();
        if (! $user instanceof User) {
            abort(403);
        }

        $action->execute($user, $address, $request->validated());

        $this->auditLogger->record(
            event: SecurityAuditEvent::AddressUpdated,
            user: $user,
            actor: $user,
            metadata: ['address_id' => $address->id, 'label' => $address->label],
            request: $request,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Address updated.')]);

        return to_route('addresses.index');
    }

    public function destroy(
        Request $request,
        CustomerAddress $address,
        DeleteCustomerAddressAction $action,
    ): RedirectResponse {
        Gate::authorize('delete', $address);

        $user = $request->user();
        if (! $user instanceof User) {
            abort(403);
        }

        $deletedId = $address->id;
        $action->execute($user, $address);

        $this->auditLogger->record(
            event: SecurityAuditEvent::AddressDeleted,
            user: $user,
            actor: $user,
            metadata: ['address_id' => $deletedId],
            request: $request,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Address removed.')]);

        return to_route('addresses.index');
    }

    public function setDefault(
        Request $request,
        CustomerAddress $address,
        SaveCustomerAddressAction $action,
    ): RedirectResponse {
        Gate::authorize('update', $address);

        $validated = $request->validate([
            'shipping' => ['sometimes', 'boolean'],
            'billing' => ['sometimes', 'boolean'],
        ]);

        $user = $request->user();
        if (! $user instanceof User) {
            abort(403);
        }

        $action->setDefaults(
            $user,
            $address,
            (bool) ($validated['shipping'] ?? false),
            (bool) ($validated['billing'] ?? false),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Default address updated.')]);

        return to_route('addresses.index');
    }
}
