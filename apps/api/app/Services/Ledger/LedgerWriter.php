<?php

namespace App\Services\Ledger;

use App\Models\LedgerEntry;

class LedgerWriter
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function write(array $attributes): LedgerEntry
    {
        $dealerId = (int) $attributes['dealer_id'];
        $customerId = (int) $attributes['customer_id'];
        $date = (string) $attributes['date'];
        $debit = (float) ($attributes['debit'] ?? 0);
        $credit = (float) ($attributes['credit'] ?? 0);

        $lastBalance = (float) (LedgerEntry::query()
            ->where('customer_id', $customerId)
            ->lockForUpdate()
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->value('balance_after') ?? 0);

        $newBalance = $lastBalance + $debit - $credit;
        $legacyEntryType = $debit > 0 ? 'debit' : 'credit';
        $legacyAmount = $debit > 0 ? $debit : $credit;

        return LedgerEntry::create([
            'dealer_id' => $dealerId,
            'customer_id' => $customerId,
            'source_system' => $attributes['source_system'] ?? null,
            'source_reference' => $attributes['source_reference'] ?? null,
            'last_synced_at' => $attributes['last_synced_at'] ?? null,
            'order_id' => $attributes['order_id'] ?? null,
            'collection_id' => $attributes['collection_id'] ?? null,
            'date' => $date,
            'type' => $attributes['type'] ?? ($debit > 0 ? 'debit' : 'credit'),
            'debit' => number_format($debit, 2, '.', ''),
            'credit' => number_format($credit, 2, '.', ''),
            'balance_after' => number_format($newBalance, 2, '.', ''),
            'entry_date' => $date,
            'entry_type' => $legacyEntryType,
            'amount' => number_format($legacyAmount, 2, '.', ''),
            'currency' => $attributes['currency'] ?? 'TRY',
            'reference_no' => $attributes['reference_no'] ?? null,
            'description' => $attributes['description'] ?? null,
            'created_by_user_id' => $attributes['created_by_user_id'] ?? null,
            'meta' => $attributes['meta'] ?? null,
        ]);
    }
}
