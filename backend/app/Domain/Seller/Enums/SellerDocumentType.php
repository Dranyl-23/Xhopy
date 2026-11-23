<?php

namespace App\Domain\Seller\Enums;

enum SellerDocumentType: string
{
    case GovernmentId = 'government_id';
    case BusinessRegistration = 'business_registration';
    case TaxCertificate = 'tax_certificate';
    case PayoutProof = 'payout_proof';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::GovernmentId => 'Valid Government ID',
            self::BusinessRegistration => 'Business Registration / Permit',
            self::TaxCertificate => 'Tax Registration Document',
            self::PayoutProof => 'Payout Account Proof',
            self::Other => 'Supporting Document',
        };
    }
}
