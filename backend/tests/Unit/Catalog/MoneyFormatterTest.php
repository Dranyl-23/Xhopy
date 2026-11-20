<?php

use App\Domain\Catalog\Services\MoneyFormatter;

test('money formatter parses decimal amounts to exact integer centavos without float drift', function () {
    expect(MoneyFormatter::toCentavos('6490.00'))->toBe(649000)
        ->and(MoneyFormatter::toCentavos('0.10') + MoneyFormatter::toCentavos('0.20'))->toBe(30)
        ->and(MoneyFormatter::format('6490.00', 'PHP'))->toBe('₱6,490.00')
        ->and(MoneyFormatter::format('125.5', 'USD'))->toBe('$125.50');
});

test('money formatter calculates savings label only when compare-at exceeds current price', function () {
    expect(MoneyFormatter::discountLabel('6490.00', '7490.00', 'PHP'))->toBe('Save ₱1,000')
        ->and(MoneyFormatter::discountLabel('6490.50', '6990.75', 'PHP'))->toBe('Save ₱500.25')
        ->and(MoneyFormatter::discountLabel('6490.00', '6490.00', 'PHP'))->toBeNull()
        ->and(MoneyFormatter::discountLabel('6490.00', '5000.00', 'PHP'))->toBeNull()
        ->and(MoneyFormatter::discountLabel('6490.00', null, 'PHP'))->toBeNull();
});
