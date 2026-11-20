<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { MapPin, Pencil, Plus, ShieldCheck, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import Heading from '@shared/components/Heading.vue';
import InputError from '@shared/components/InputError.vue';
import { Badge } from '@shared/components/ui/badge';
import { Button } from '@shared/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@shared/components/ui/card';
import { Checkbox } from '@shared/components/ui/checkbox';
import { Input } from '@shared/components/ui/input';
import { Label } from '@shared/components/ui/label';
import { SelectField, type SelectOption } from '@shared/components/ui/select';
import {
    defaultMethod as setDefaultAddress,
    destroy as destroyAddress,
    index as addressesIndex,
    store as storeAddress,
    update as updateAddress,
} from '@/routes/addresses';
import type { CustomerAddress } from '@modules/account/types';

defineProps<{
    addresses: CustomerAddress[];
    countryOptions: SelectOption[];
    labelOptions: SelectOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Address book',
                href: addressesIndex(),
            },
        ],
    },
});

const editingId = ref<number | null>(null);
const showForm = ref(false);

const form = useForm({
    label: 'Home',
    recipient_name: '',
    phone: '',
    country_code: 'PH',
    province: '',
    city: '',
    barangay: '',
    postal_code: '',
    address_line_1: '',
    address_line_2: '',
    delivery_notes: '',
    is_default_shipping: false,
    is_default_billing: false,
});

function startCreate(): void {
    editingId.value = null;
    form.reset();
    form.clearErrors();
    form.label = 'Home';
    form.country_code = 'PH';
    showForm.value = true;
}

function startEdit(address: CustomerAddress): void {
    editingId.value = address.id;
    form.clearErrors();
    form.label = address.label;
    form.recipient_name = address.recipient_name;
    form.phone = address.phone;
    form.country_code = address.country_code;
    form.province = address.province;
    form.city = address.city;
    form.barangay = address.barangay;
    form.postal_code = address.postal_code;
    form.address_line_1 = address.address_line_1;
    form.address_line_2 = address.address_line_2;
    form.delivery_notes = address.delivery_notes;
    form.is_default_shipping = address.is_default_shipping;
    form.is_default_billing = address.is_default_billing;
    showForm.value = true;
}

function cancelForm(): void {
    editingId.value = null;
    showForm.value = false;
    form.reset();
    form.clearErrors();
}

function submitAddress(): void {
    if (editingId.value !== null) {
        form.patch(updateAddress.url(editingId.value), {
            preserveScroll: true,
            onSuccess: () => cancelForm(),
        });

        return;
    }

    form.post(storeAddress.url(), {
        preserveScroll: true,
        onSuccess: () => cancelForm(),
    });
}

function setAsDefault(
    address: CustomerAddress,
    type: 'shipping' | 'billing',
): void {
    router.patch(
        setDefaultAddress.url(address.id),
        {
            shipping: type === 'shipping',
            billing: type === 'billing',
        },
        { preserveScroll: true },
    );
}

function removeAddress(address: CustomerAddress): void {
    if (
        !window.confirm(
            `Remove saved address "${address.label} — ${address.recipient_name}"?`,
        )
    ) {
        return;
    }

    router.delete(destroyAddress.url(address.id), { preserveScroll: true });
}
</script>

<template>
    <Head title="Address book" />

    <h1 class="sr-only">Address book</h1>

    <div class="space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                variant="small"
                title="Saved addresses"
                description="Manage delivery and billing addresses for faster, verified checkout"
            />

            <Button
                v-if="!showForm"
                type="button"
                class="h-10 gap-2"
                data-test="add-address-button"
                @click="startCreate"
            >
                <Plus class="size-4" aria-hidden="true" />
                <span>Add address</span>
            </Button>
        </div>

        <Card v-if="showForm || addresses.length === 0">
            <CardHeader>
                <CardTitle>{{
                    editingId !== null ? 'Edit Address' : 'Add New Address'
                }}</CardTitle>
                <CardDescription>
                    Enter complete recipient and street details for accurate
                    courier dispatch.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <form class="space-y-4" @submit.prevent="submitAddress">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="space-y-1.5">
                            <Label for="addr-label">Address Label</Label>
                            <SelectField
                                id="addr-label"
                                v-model="form.label"
                                :options="labelOptions"
                            />
                            <InputError :message="form.errors.label" />
                        </div>

                        <div class="space-y-1.5">
                            <Label for="addr-country">Country</Label>
                            <SelectField
                                id="addr-country"
                                v-model="form.country_code"
                                :options="countryOptions"
                            />
                            <InputError :message="form.errors.country_code" />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="space-y-1.5">
                            <Label for="addr-recipient">Recipient Name</Label>
                            <Input
                                id="addr-recipient"
                                v-model="form.recipient_name"
                                required
                                placeholder="Juan Dela Cruz"
                                class="h-11"
                            />
                            <InputError :message="form.errors.recipient_name" />
                        </div>

                        <div class="space-y-1.5">
                            <Label for="addr-phone">Contact Number</Label>
                            <Input
                                id="addr-phone"
                                v-model="form.phone"
                                required
                                placeholder="+63 917 555 0100"
                                class="h-11"
                            />
                            <InputError :message="form.errors.phone" />
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="addr-line1"
                            >Street Address / Building</Label
                        >
                        <Input
                            id="addr-line1"
                            v-model="form.address_line_1"
                            required
                            placeholder="Unit 4B, 128 Ayala Avenue"
                            class="h-11"
                        />
                        <InputError :message="form.errors.address_line_1" />
                    </div>

                    <div class="space-y-1.5">
                        <Label for="addr-line2"
                            >Apartment / Suite / Landmark (Optional)</Label
                        >
                        <Input
                            id="addr-line2"
                            v-model="form.address_line_2"
                            placeholder="Near lobby reception"
                            class="h-11"
                        />
                        <InputError :message="form.errors.address_line_2" />
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="space-y-1.5">
                            <Label for="addr-barangay"
                                >Barangay / District</Label
                            >
                            <Input
                                id="addr-barangay"
                                v-model="form.barangay"
                                required
                                placeholder="San Lorenzo"
                                class="h-11"
                            />
                            <InputError :message="form.errors.barangay" />
                        </div>

                        <div class="space-y-1.5">
                            <Label for="addr-city">City / Municipality</Label>
                            <Input
                                id="addr-city"
                                v-model="form.city"
                                required
                                placeholder="Makati City"
                                class="h-11"
                            />
                            <InputError :message="form.errors.city" />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="space-y-1.5">
                            <Label for="addr-province">Province / Region</Label>
                            <Input
                                id="addr-province"
                                v-model="form.province"
                                required
                                placeholder="Metro Manila"
                                class="h-11"
                            />
                            <InputError :message="form.errors.province" />
                        </div>

                        <div class="space-y-1.5">
                            <Label for="addr-postal">Postal Code</Label>
                            <Input
                                id="addr-postal"
                                v-model="form.postal_code"
                                required
                                placeholder="1223"
                                class="h-11"
                            />
                            <InputError :message="form.errors.postal_code" />
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="addr-notes"
                            >Delivery Instructions (Optional)</Label
                        >
                        <Input
                            id="addr-notes"
                            v-model="form.delivery_notes"
                            placeholder="Leave parcel with concierge on weekdays"
                            class="h-11"
                        />
                        <InputError :message="form.errors.delivery_notes" />
                    </div>

                    <div class="flex flex-wrap gap-6 pt-1">
                        <div class="flex items-center gap-2.5">
                            <Checkbox
                                id="addr-default-shipping"
                                :model-value="form.is_default_shipping"
                                @update:model-value="
                                    form.is_default_shipping = $event === true
                                "
                            />
                            <Label
                                for="addr-default-shipping"
                                class="cursor-pointer text-sm"
                            >
                                Default shipping address
                            </Label>
                        </div>

                        <div class="flex items-center gap-2.5">
                            <Checkbox
                                id="addr-default-billing"
                                :model-value="form.is_default_billing"
                                @update:model-value="
                                    form.is_default_billing = $event === true
                                "
                            />
                            <Label
                                for="addr-default-billing"
                                class="cursor-pointer text-sm"
                            >
                                Default billing address
                            </Label>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-3 pt-2">
                        <Button
                            type="submit"
                            class="h-11"
                            :disabled="form.processing"
                            data-test="save-address-button"
                        >
                            {{
                                editingId !== null
                                    ? 'Update address'
                                    : 'Save address'
                            }}
                        </Button>
                        <Button
                            v-if="addresses.length > 0"
                            type="button"
                            variant="outline"
                            class="h-11"
                            @click="cancelForm"
                        >
                            Cancel
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>

        <div v-if="addresses.length > 0" class="space-y-4">
            <Card v-for="address in addresses" :key="address.id">
                <CardContent class="space-y-3 p-5">
                    <div
                        class="flex flex-wrap items-start justify-between gap-3"
                    >
                        <div class="space-y-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span
                                    class="inline-flex items-center gap-1.5 font-semibold text-foreground"
                                >
                                    <MapPin
                                        class="size-4 text-primary"
                                        aria-hidden="true"
                                    />
                                    {{ address.label }}
                                </span>
                                <Badge
                                    v-if="address.is_default_shipping"
                                    variant="default"
                                >
                                    Default Shipping
                                </Badge>
                                <Badge
                                    v-if="address.is_default_billing"
                                    variant="secondary"
                                >
                                    Default Billing
                                </Badge>
                            </div>
                            <p class="text-sm font-medium text-foreground">
                                {{ address.recipient_name }} ·
                                {{ address.phone }}
                            </p>
                            <p class="text-sm text-muted-foreground">
                                {{ address.formatted_line }}
                            </p>
                            <p
                                v-if="address.delivery_notes"
                                class="text-xs text-muted-foreground"
                            >
                                Note: {{ address.delivery_notes }}
                            </p>
                        </div>

                        <div class="flex items-center gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                class="h-9 gap-1.5"
                                @click="startEdit(address)"
                            >
                                <Pencil class="size-3.5" aria-hidden="true" />
                                <span>Edit</span>
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                class="h-9 gap-1.5 text-destructive hover:text-destructive"
                                @click="removeAddress(address)"
                            >
                                <Trash2 class="size-3.5" aria-hidden="true" />
                                <span>Remove</span>
                            </Button>
                        </div>
                    </div>

                    <div
                        v-if="
                            !address.is_default_shipping ||
                            !address.is_default_billing
                        "
                        class="flex flex-wrap gap-2 border-t border-border pt-3"
                    >
                        <Button
                            v-if="!address.is_default_shipping"
                            type="button"
                            variant="outline"
                            size="sm"
                            class="h-8 gap-1.5 text-xs"
                            @click="setAsDefault(address, 'shipping')"
                        >
                            <ShieldCheck
                                class="size-3.5 text-primary"
                                aria-hidden="true"
                            />
                            <span>Set default shipping</span>
                        </Button>
                        <Button
                            v-if="!address.is_default_billing"
                            type="button"
                            variant="outline"
                            size="sm"
                            class="h-8 gap-1.5 text-xs"
                            @click="setAsDefault(address, 'billing')"
                        >
                            <span>Set default billing</span>
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
