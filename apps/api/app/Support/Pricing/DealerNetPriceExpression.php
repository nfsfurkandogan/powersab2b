<?php

namespace App\Support\Pricing;

final class DealerNetPriceExpression
{
    private function __construct() {}

    public static function sql(
        string $overrideColumn = 'dpo.net_price',
        string $basePriceColumn = 'bp.list_price',
        string $discountRateColumn = 'pl.discount_rate'
    ): string {
        return sprintf(
            'CASE
                WHEN %1$s IS NOT NULL THEN %1$s
                WHEN %2$s IS NULL THEN NULL
                WHEN COALESCE(%3$s, 0) > 0 THEN ROUND(%2$s * (1 - (COALESCE(%3$s, 0) / 100.0)), 2)
                ELSE %2$s
            END',
            $overrideColumn,
            $basePriceColumn,
            $discountRateColumn
        );
    }
}
