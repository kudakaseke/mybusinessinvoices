<?php

namespace App;

use Illuminate\Validation\ValidationException;

class DocumentTotals
{
    public static function scaled(string|int|float $value): int
    {
        $negative = str_starts_with((string) $value, '-');
        $parts = explode('.', ltrim((string) $value, '+-'));
        $amount = ((int) $parts[0] * 100) + (int) str_pad(substr($parts[1] ?? '', 0, 2), 2, '0');

        return $negative ? -$amount : $amount;
    }

    public static function decimal(int $value): string
    {
        $amount = abs($value);

        return ($value < 0 ? '-' : '').sprintf('%d.%02d', intdiv($amount, 100), $amount % 100);
    }

    /** @param list<array{description: string, quantity: mixed, unit_price: mixed, product_id?: mixed}> $items
     * @return array{items: array, subtotal: string, vat_amount: string, discount_amount: string, total: string}
     */
    public function calculate(array $items, string|int|float $vatRate, string|int|float $discountRate): array
    {
        $subtotal = 0;
        foreach ($items as &$item) {
            $quantity = self::scaled($item['quantity']);
            $price = self::scaled($item['unit_price']);
            $amount = intdiv($price, 100) * $quantity + intdiv(($price % 100) * $quantity + 50, 100);
            if ($amount > 99999999999999 || $subtotal > 99999999999999 - $amount) {
                throw ValidationException::withMessages(['items' => 'The document total exceeds the supported amount.']);
            }
            $subtotal += $amount;
            $item['amount'] = self::decimal($amount);
        }
        unset($item);
        $discount = intdiv($subtotal * self::scaled($discountRate) + 5000, 10000);
        $vat = intdiv(($subtotal - $discount) * self::scaled($vatRate) + 5000, 10000);
        $total = $subtotal - $discount + $vat;
        if ($total > 99999999999999) {
            throw ValidationException::withMessages(['items' => 'The document total exceeds the supported amount.']);
        }

        return ['items' => $items, 'subtotal' => self::decimal($subtotal), 'vat_amount' => self::decimal($vat), 'discount_amount' => self::decimal($discount), 'total' => self::decimal($total)];
    }
}
