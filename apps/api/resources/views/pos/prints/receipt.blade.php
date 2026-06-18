@php
    $isDelivery = $sale->document_type === 'delivery';
    $documentTitle = $isDelivery ? 'Sevk İrsaliyesi' : 'Satış Fişi';
    $currencyLabel = 'GEL';
    $customerTitle = trim((string) (($sale->customer?->code ?? '-') . ' ' . ($sale->customer?->name ?? '')));
    $customerLocation = trim((string) collect([$sale->customer?->district, $sale->customer?->city])->filter()->implode(' / '));
@endphp
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $documentTitle }} - {{ $sale->receipt_no }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, Helvetica, sans-serif; color: #111827; background: #f3f4f6; }
        .actions { position: sticky; top: 0; z-index: 20; background: #f3f4f6; padding: 8px; border-bottom: 1px solid #d1d5db; text-align: right; }
        .actions button { border: 1px solid #9ca3af; background: #fff; border-radius: 6px; padding: 6px 10px; cursor: pointer; }
        .page { width: {{ $isDelivery ? '190mm' : '80mm' }}; max-width: 100%; margin: 0 auto; background: #fff; padding: {{ $isDelivery ? '10mm 12mm' : '8mm 6mm' }}; }
        h1, h2, h3, p { margin: 0; }
        .muted { color: #6b7280; }
        .small { font-size: 11px; }
        .line { border-top: 1px dashed #9ca3af; margin: 8px 0; }
        .header { display: flex; justify-content: space-between; gap: 12px; align-items: flex-start; }
        .header h1 { font-size: {{ $isDelivery ? '22px' : '16px' }}; }
        .document-code { font-size: {{ $isDelivery ? '18px' : '13px' }}; font-weight: 700; }
        .document-chip { display: inline-block; padding: 4px 10px; border-radius: 999px; background: #e8f3ea; color: #155e3b; font-size: 11px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
        .meta-grid { display: grid; grid-template-columns: repeat({{ $isDelivery ? '3' : '1' }}, minmax(0, 1fr)); gap: 8px; margin-top: 10px; }
        .meta-card { border: 1px solid #d1d5db; border-radius: 10px; padding: 8px 10px; background: #fbfcfb; }
        .meta-label { color: #6b7280; font-size: 11px; text-transform: uppercase; letter-spacing: .08em; }
        .meta-value { margin-top: 4px; font-size: 13px; font-weight: 700; }
        .row { display: flex; justify-content: space-between; gap: 8px; font-size: 12px; }
        .label { color: #374151; }
        .value { text-align: right; white-space: nowrap; font-weight: 600; }
        .customer-box { border: 1px solid #d1d5db; border-radius: 12px; background: #f9fafb; padding: 10px; margin-top: 10px; }
        .customer-box h2 { font-size: 13px; margin-bottom: 6px; }
        .table { width: 100%; border-collapse: collapse; font-size: 12px; margin-top: 10px; }
        .table th, .table td { border-bottom: 1px solid #e5e7eb; padding: 6px 0; text-align: left; vertical-align: top; }
        .table th:nth-last-child(-n+3), .table td:nth-last-child(-n+3) { text-align: right; white-space: nowrap; }
        .totals { margin-top: 10px; font-size: 12px; }
        .totals .row { margin: 2px 0; }
        .grand { font-size: 14px; font-weight: 700; border-top: 1px solid #111827; padding-top: 6px; margin-top: 6px; }
        .footer { margin-top: 14px; font-size: 11px; color: #4b5563; }
        .signature-grid { display: {{ $isDelivery ? 'grid' : 'none' }}; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px; margin-top: 26px; }
        .signature-box { border-top: 1px solid #9ca3af; padding-top: 8px; font-size: 12px; text-align: center; color: #4b5563; }

        @media print {
            body { background: #fff; }
            .actions { display: none; }
            .page { width: auto; padding: 0; }
            @page { size: {{ $isDelivery ? 'A4 portrait' : '80mm auto' }}; margin: {{ $isDelivery ? '10mm' : '5mm' }}; }
        }
    </style>
</head>
<body>
<div class="actions">
    <button type="button" onclick="window.print()">Yazdır</button>
</div>

<main class="page">
    <section class="header">
        <div>
            <span class="document-chip">Powersa B2B</span>
            <h1 style="margin-top: 8px;">{{ $documentTitle }}</h1>
            <p class="small muted" style="margin-top: 4px;">
                {{ $isDelivery ? 'Bayi hızlı satış kanalından oluşturuldu.' : 'POS satış kaydı' }}
            </p>
        </div>
        <div style="text-align: right;">
            <p class="small muted">Belge No</p>
            <p class="document-code">{{ $sale->receipt_no }}</p>
        </div>
    </section>

    <section class="meta-grid">
        <div class="meta-card">
            <p class="meta-label">Tarih</p>
            <p class="meta-value">{{ optional($sale->created_at)->format('d.m.Y H:i') }}</p>
        </div>
        <div class="meta-card">
            <p class="meta-label">Belge Tipi</p>
            <p class="meta-value">{{ strtoupper((string) $sale->document_type) }}</p>
        </div>
        <div class="meta-card">
            <p class="meta-label">Satış Tipi</p>
            <p class="meta-value">{{ strtoupper((string) $sale->sale_type) }}</p>
        </div>
    </section>

    <section class="meta-grid">
        <div class="meta-card">
            <p class="meta-label">Durum</p>
            <p class="meta-value">{{ strtoupper((string) $sale->status) }}</p>
        </div>
        <div class="meta-card">
            <p class="meta-label">Kasa</p>
            <p class="meta-value">{{ $sale->posSession?->cashbox?->code ?? '-' }}</p>
        </div>
        <div class="meta-card">
            <p class="meta-label">İşlem Yapan</p>
            <p class="meta-value">{{ $sale->createdBy?->name ?? '-' }}</p>
        </div>
    </section>

    <section class="customer-box">
        <h2>Müşteri</h2>
        <p style="font-size: 14px; font-weight: 700;">{{ $customerTitle !== '' ? $customerTitle : '-' }}</p>
        <p class="small muted" style="margin-top: 6px;">
            {{ $customerLocation !== '' ? $customerLocation : 'Konum bilgisi yok' }}
            @if($sale->customer?->phone)
                · {{ $sale->customer->phone }}
            @endif
        </p>
    </section>

    <table class="table">
        <thead>
        <tr>
            <th>Stok Kodu</th>
            <th>Ürün</th>
            <th>Adet</th>
            <th>Birim</th>
            <th>Tutar</th>
        </tr>
        </thead>
        <tbody>
        @forelse($sale->items as $item)
            <tr>
                <td>{{ $item->product?->sku ?? '-' }}</td>
                <td>
                    <div>{{ $item->product?->name ?? '-' }}</div>
                    <div class="small muted">
                        {{ $item->product?->oem_code ?? '-' }}
                        @if($item->product?->brand?->name)
                            · {{ $item->product->brand->name }}
                        @endif
                    </div>
                </td>
                <td>{{ number_format((float) $item->qty, 0, ',', '.') }}</td>
                <td>{{ $currencyLabel }} {{ number_format((float) $item->unit_price, 2, ',', '.') }}</td>
                <td>{{ $currencyLabel }} {{ number_format((float) $item->line_total, 2, ',', '.') }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="5" class="muted">Kalem bulunamadı.</td>
            </tr>
        @endforelse
        </tbody>
    </table>

    <div class="line"></div>

    <section class="totals">
        <div class="row"><span class="label">Ara Toplam</span><span class="value">{{ $currencyLabel }} {{ number_format((float) $sale->subtotal, 2, ',', '.') }}</span></div>
        <div class="row"><span class="label">İskonto</span><span class="value">{{ $currencyLabel }} {{ number_format((float) $sale->discount_total, 2, ',', '.') }}</span></div>
        <div class="row"><span class="label">KDV</span><span class="value">{{ $currencyLabel }} {{ number_format((float) $sale->vat_total, 2, ',', '.') }}</span></div>
        <div class="row grand"><span>Genel Toplam</span><span>{{ $currencyLabel }} {{ number_format((float) $sale->grand_total, 2, ',', '.') }}</span></div>
    </section>

    <div class="line"></div>

    <section class="small">
        <p style="margin-bottom: 4px;"><strong>{{ $isDelivery ? 'Tahsilat / Kapatma' : 'Ödeme' }}</strong></p>
        @forelse($sale->payments as $payment)
            <div class="row">
                <span class="label">{{ strtoupper((string) $payment->method) }}</span>
                <span class="value">{{ $currencyLabel }} {{ number_format((float) $payment->amount, 2, ',', '.') }}</span>
            </div>
        @empty
            <p class="muted">Ödeme kaydı bulunamadı.</p>
        @endforelse
    </section>

    <section class="signature-grid">
        <div class="signature-box">Teslim Eden</div>
        <div class="signature-box">Teslim Alan</div>
    </section>

    <p class="footer">{{ $isDelivery ? 'Belge yazdırıldıktan sonra imza alanlarını kullanın.' : 'İyi günler dileriz.' }}</p>
</main>
</body>
</html>
