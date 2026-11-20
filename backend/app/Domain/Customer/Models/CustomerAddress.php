<?php

namespace App\Domain\Customer\Models;

use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\Customer\CustomerAddressFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $label
 * @property string $recipient_name
 * @property string $phone
 * @property string $country_code
 * @property string $province
 * @property string $city
 * @property string $barangay
 * @property string $postal_code
 * @property string $address_line_1
 * @property string|null $address_line_2
 * @property string|null $delivery_notes
 * @property bool $is_default_shipping
 * @property bool $is_default_billing
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable([
    'user_id',
    'label',
    'recipient_name',
    'phone',
    'country_code',
    'province',
    'city',
    'barangay',
    'postal_code',
    'address_line_1',
    'address_line_2',
    'delivery_notes',
    'is_default_shipping',
    'is_default_billing',
])]
class CustomerAddress extends Model
{
    /** @use HasFactory<CustomerAddressFactory> */
    use HasFactory;

    protected static function newFactory(): CustomerAddressFactory
    {
        return CustomerAddressFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default_shipping' => 'boolean',
            'is_default_billing' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function formattedLine(): string
    {
        $parts = array_filter([
            $this->address_line_1,
            $this->address_line_2,
            $this->barangay,
            $this->city,
            $this->province,
            $this->postal_code,
            $this->country_code,
        ], static fn (?string $part): bool => $part !== null && trim($part) !== '');

        return implode(', ', $parts);
    }
}
