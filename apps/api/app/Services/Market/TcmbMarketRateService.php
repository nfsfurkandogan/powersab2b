<?php

namespace App\Services\Market;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class TcmbMarketRateService
{
    /**
     * @return array{source:string,source_url:string,source_date:?string,updated_at:string,rates:array<int,array<string,mixed>>}
     */
    public function today(): array
    {
        return Cache::remember('tcmb_market_rates_today_xml', now()->addMinutes(30), function (): array {
            $sourceUrl = 'https://www.tcmb.gov.tr/kurlar/today.xml';
            $fallback = [
                'source' => 'TCMB',
                'source_url' => $sourceUrl,
                'source_date' => null,
                'updated_at' => now()->toJSON(),
                'rates' => [
                    $this->unavailableRate('USD', 'Dolar'),
                    $this->unavailableRate('EUR', 'Euro'),
                    $this->unavailableRate('XAU', 'Altın', 'TCMB today.xml içinde altın kuru yok.'),
                ],
            ];

            try {
                $response = Http::timeout(6)->retry(1, 250)->get($sourceUrl);

                if (! $response->successful()) {
                    return $fallback;
                }

                $xml = simplexml_load_string($response->body());

                if ($xml === false) {
                    return $fallback;
                }

                $usd = $this->rateFromXml($xml, 'USD', 'Dolar');
                $eur = $this->rateFromXml($xml, 'EUR', 'Euro');
                $gold = $this->rateFromXml($xml, 'XAU', 'Altın')
                    ?? $this->rateFromXml($xml, 'GAU', 'Gram Altın')
                    ?? $this->unavailableRate('XAU', 'Altın', 'TCMB today.xml içinde altın kuru yok.');

                return [
                    'source' => 'TCMB',
                    'source_url' => $sourceUrl,
                    'source_date' => (string) ($xml['Tarih'] ?? $xml['Date'] ?? ''),
                    'updated_at' => now()->toJSON(),
                    'rates' => [
                        $usd ?? $fallback['rates'][0],
                        $eur ?? $fallback['rates'][1],
                        $gold,
                    ],
                ];
            } catch (Throwable) {
                return $fallback;
            }
        });
    }

    /**
     * @return array<string,mixed>|null
     */
    private function rateFromXml(\SimpleXMLElement $xml, string $code, string $label): ?array
    {
        $nodes = $xml->xpath("//Currency[@Kod='{$code}']");

        if (! is_array($nodes) || ! isset($nodes[0])) {
            return null;
        }

        $node = $nodes[0];

        return [
            'code' => $code,
            'label' => $label,
            'unit' => (int) ((string) ($node->Unit ?? '1') ?: 1),
            'name' => (string) ($node->Isim ?? $label),
            'forex_buying' => $this->nullableMoney($node->ForexBuying ?? null),
            'forex_selling' => $this->nullableMoney($node->ForexSelling ?? null),
            'banknote_buying' => $this->nullableMoney($node->BanknoteBuying ?? null),
            'banknote_selling' => $this->nullableMoney($node->BanknoteSelling ?? null),
            'available' => true,
            'note' => null,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function unavailableRate(string $code, string $label, ?string $note = null): array
    {
        return [
            'code' => $code,
            'label' => $label,
            'unit' => 1,
            'name' => $label,
            'forex_buying' => null,
            'forex_selling' => null,
            'banknote_buying' => null,
            'banknote_selling' => null,
            'available' => false,
            'note' => $note ?? 'Kur bilgisi alınamadı.',
        ];
    }

    private function nullableMoney(mixed $value): ?string
    {
        $raw = trim((string) $value);

        if ($raw === '' || ! is_numeric($raw)) {
            return null;
        }

        return number_format((float) $raw, 4, '.', '');
    }
}
