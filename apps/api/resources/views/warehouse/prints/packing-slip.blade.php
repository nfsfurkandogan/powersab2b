<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Powersa Sevkiyat Formu - {{ $shipment->shipment_no }}</title>
    @php
        $order = $shipment->order;
        $customer = $order?->customer;
        $dealer = $order?->dealer;
        $salesperson = $order?->user;
        $currency = $order?->currency ?? 'TRY';
        $shipmentDate = optional($shipment->shipped_at ?? $shipment->created_at)->format('d.m.Y H:i');
        $orderDate = optional($order?->ordered_at)->format('d.m.Y H:i');
        $approvedDate = optional($order?->approved_at)->format('d.m.Y H:i');
        $printedDate = optional($printedAt)->format('d.m.Y H:i');
        $customerAddress = data_get($customer?->meta, 'address')
            ?? data_get($customer?->meta, 'full_address')
            ?? $dealer?->address
            ?? '-';
        $brandCount = $shipment->items->pluck('product.brand.name')->filter()->unique()->count();
    @endphp
    <style>
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }
        body {
            font-family: Arial, Helvetica, sans-serif;
            color: #163326;
            background: #edf3ee;
        }
        h1, h2, h3, p { margin: 0; }
        .actions {
            position: sticky;
            top: 0;
            z-index: 5;
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            padding: 10px 16px;
            background: rgba(237, 243, 238, 0.96);
            border-bottom: 1px solid #d7e4d8;
            backdrop-filter: blur(8px);
        }
        .actions button {
            border: 1px solid #1f5b3f;
            background: #1f5b3f;
            color: #ffffff;
            border-radius: 999px;
            padding: 9px 14px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .03em;
            cursor: pointer;
        }
        .page {
            width: 210mm;
            min-height: 297mm;
            max-width: 100%;
            margin: 0 auto;
            padding: 12mm;
            background: #ffffff;
            box-shadow: 0 24px 64px rgba(22, 51, 38, 0.08);
        }
        .hero {
            position: relative;
            overflow: hidden;
            display: grid;
            grid-template-columns: minmax(0, 1.45fr) minmax(74mm, 0.85fr);
            gap: 7mm;
            padding: 8mm;
            border: 1px solid #dce7db;
            border-radius: 16px;
            background:
                radial-gradient(circle at top right, rgba(214, 182, 76, 0.14), transparent 30%),
                linear-gradient(135deg, #f8fcf8 0%, #eef6ef 56%, #e7f0e8 100%);
        }
        .hero::after {
            content: "POWERSA";
            position: absolute;
            right: -6mm;
            bottom: -8mm;
            font-size: 42px;
            font-weight: 800;
            letter-spacing: .18em;
            color: rgba(31, 91, 63, 0.05);
        }
        .brand {
            display: flex;
            align-items: flex-start;
            gap: 6mm;
        }
        .brand-logo {
            width: 54mm;
            max-width: 100%;
            height: auto;
            object-fit: contain;
        }
        .brand-fallback {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 54mm;
            min-height: 17mm;
            border-radius: 12px;
            background: #1f5b3f;
            color: #ffffff;
            font-size: 16px;
            font-weight: 800;
            letter-spacing: .12em;
        }
        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 5px 10px;
            border-radius: 999px;
            background: rgba(214, 182, 76, 0.18);
            color: #6b5612;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .08em;
        }
        .title {
            margin-top: 14px;
            font-size: 28px;
            line-height: 1.05;
            font-weight: 800;
            letter-spacing: -.03em;
            color: #153627;
        }
        .subtitle {
            margin-top: 8px;
            max-width: 96mm;
            color: #466353;
            font-size: 13px;
            line-height: 1.5;
        }
        .reference {
            margin-top: 14px;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .reference-chip {
            border: 1px solid #d6e4d6;
            background: rgba(255, 255, 255, 0.78);
            border-radius: 999px;
            padding: 6px 10px;
            font-size: 11px;
            font-weight: 700;
            color: #27513b;
        }
        .hero-side {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .barcode-card {
            border-radius: 14px;
            border: 1px solid #d5e3d6;
            background: rgba(255, 255, 255, 0.76);
            padding: 12px 14px;
        }
        .barcode-label {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: #6a7b6f;
        }
        .barcode-visual {
            margin-top: 10px;
            height: 22mm;
            border-radius: 8px;
            background:
                linear-gradient(90deg,
                    #101010 0 1.2mm, transparent 1.2mm 1.9mm,
                    #101010 1.9mm 2.6mm, transparent 2.6mm 3.4mm,
                    #101010 3.4mm 3.8mm, transparent 3.8mm 4.8mm,
                    #101010 4.8mm 6.1mm, transparent 6.1mm 6.7mm,
                    #101010 6.7mm 7.1mm, transparent 7.1mm 8.4mm,
                    #101010 8.4mm 9.5mm, transparent 9.5mm 10.1mm,
                    #101010 10.1mm 10.7mm, transparent 10.7mm 11.7mm,
                    #101010 11.7mm 12.9mm, transparent 12.9mm 13.6mm,
                    #101010 13.6mm 14.2mm, transparent 14.2mm 15.2mm,
                    #101010 15.2mm 16.6mm, transparent 16.6mm 17.1mm,
                    #101010 17.1mm 17.6mm, transparent 17.6mm 18.4mm,
                    #101010 18.4mm 19.6mm, transparent 19.6mm 20.3mm,
                    #101010 20.3mm 20.8mm, transparent 20.8mm 21.6mm,
                    #101010 21.6mm 22.9mm, transparent 22.9mm 23.6mm,
                    #101010 23.6mm 24.6mm, transparent 24.6mm 25.1mm,
                    #101010 25.1mm 25.6mm, transparent 25.6mm 26.6mm,
                    #101010 26.6mm 27.8mm, transparent 27.8mm 28.7mm,
                    #101010 28.7mm 29.1mm, transparent 29.1mm 30.4mm,
                    #101010 30.4mm 31.7mm, transparent 31.7mm 32.2mm,
                    #101010 32.2mm 32.8mm, transparent 32.8mm 33.8mm,
                    #101010 33.8mm 34.9mm, transparent 34.9mm 35.6mm,
                    #101010 35.6mm 36.1mm, transparent 36.1mm 37.2mm,
                    #101010 37.2mm 38.4mm, transparent 38.4mm 39.2mm,
                    #101010 39.2mm 39.8mm, transparent 39.8mm 40.8mm,
                    #101010 40.8mm 41.9mm, transparent 41.9mm 42.6mm,
                    #101010 42.6mm 43.4mm, transparent 43.4mm 44.4mm,
                    #101010 44.4mm 45.7mm, transparent 45.7mm 46.4mm,
                    #101010 46.4mm 47.1mm, transparent 47.1mm 48.1mm,
                    #101010 48.1mm 49.2mm, transparent 49.2mm 50mm);
        }
        .barcode-text {
            margin-top: 8px;
            font-size: 14px;
            font-weight: 800;
            letter-spacing: .16em;
            text-align: center;
            color: #183326;
        }
        .meta-list {
            display: grid;
            gap: 8px;
        }
        .meta-row {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            padding: 8px 10px;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.76);
            border: 1px solid #dce8dc;
            font-size: 12px;
        }
        .meta-row .label {
            color: #687a6c;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .06em;
        }
        .meta-row .value {
            color: #143726;
            font-weight: 800;
            text-align: right;
        }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 4mm;
            margin-top: 5mm;
        }
        .summary-card {
            border: 1px solid #dce7db;
            border-radius: 14px;
            background: #fbfdfb;
            padding: 12px 14px;
        }
        .summary-card .kicker {
            color: #768779;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .08em;
        }
        .summary-card .value {
            margin-top: 6px;
            color: #143726;
            font-size: 24px;
            font-weight: 800;
            letter-spacing: -.03em;
        }
        .summary-card .hint {
            margin-top: 5px;
            color: #5d7262;
            font-size: 11px;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 4mm;
            margin-top: 5mm;
        }
        .card {
            border: 1px solid #dbe7dc;
            border-radius: 14px;
            background: #ffffff;
            padding: 14px;
        }
        .card-title {
            margin-bottom: 12px;
            color: #5f7564;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .1em;
        }
        .card-name {
            color: #173a29;
            font-size: 18px;
            line-height: 1.2;
            font-weight: 800;
        }
        .stack {
            display: grid;
            gap: 8px;
        }
        .data-row {
            display: grid;
            grid-template-columns: 28mm 1fr;
            gap: 8px;
            font-size: 12px;
            line-height: 1.45;
        }
        .data-row .key {
            color: #6c7d70;
            font-weight: 700;
        }
        .data-row .val {
            color: #1b3528;
            font-weight: 600;
        }
        .note-card {
            margin-top: 5mm;
            border: 1px solid #dbe7dc;
            border-radius: 14px;
            background: linear-gradient(180deg, #fbfcf8 0%, #ffffff 100%);
            padding: 14px;
        }
        .note-body {
            margin-top: 8px;
            min-height: 20mm;
            border: 1px dashed #cddccd;
            border-radius: 12px;
            padding: 12px;
            color: #2f4737;
            font-size: 12px;
            line-height: 1.55;
            white-space: pre-line;
        }
        .table-card {
            margin-top: 5mm;
            overflow: hidden;
            border: 1px solid #d9e6da;
            border-radius: 14px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }
        thead { display: table-header-group; }
        th {
            padding: 10px 8px;
            background: #1f5b3f;
            color: #ffffff;
            text-align: left;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .06em;
            border-right: 1px solid rgba(255,255,255,0.14);
        }
        th:last-child { border-right: none; }
        td {
            padding: 9px 8px;
            border-top: 1px solid #e5eee5;
            vertical-align: top;
            color: #1d3529;
            background: #ffffff;
        }
        tbody tr:nth-child(even) td {
            background: #f8fbf8;
        }
        td.num, th.num {
            text-align: right;
            white-space: nowrap;
        }
        td.center, th.center {
            text-align: center;
        }
        .sku {
            font-weight: 800;
            letter-spacing: .03em;
        }
        .sku-sub {
            margin-top: 2px;
            color: #708173;
            font-size: 10px;
        }
        .product-name {
            font-weight: 700;
            line-height: 1.38;
        }
        .bottom-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 76mm;
            gap: 5mm;
            margin-top: 5mm;
        }
        .sign-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 4mm;
        }
        .sign-box {
            border: 1px solid #d9e5d9;
            border-radius: 14px;
            min-height: 28mm;
            padding: 12px;
            background: #ffffff;
        }
        .sign-box .line {
            margin-top: 18mm;
            border-top: 1px solid #b9c8ba;
            padding-top: 6px;
            color: #58715e;
            font-size: 11px;
            font-weight: 700;
        }
        .totals-card {
            border: 1px solid #d9e5d9;
            border-radius: 14px;
            overflow: hidden;
            background: #ffffff;
        }
        .totals-head {
            padding: 12px 14px;
            background: #173f2d;
            color: #ffffff;
        }
        .totals-head .title-sm {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: .08em;
            opacity: .78;
            font-weight: 700;
        }
        .totals-head .big {
            margin-top: 6px;
            font-size: 23px;
            font-weight: 800;
            letter-spacing: -.03em;
        }
        .totals-table {
            width: 100%;
            border-collapse: collapse;
        }
        .totals-table td {
            padding: 10px 14px;
            border-top: 1px solid #e5eee5;
            background: #ffffff;
            font-size: 12px;
        }
        .totals-table td:first-child {
            color: #617766;
            font-weight: 700;
        }
        .totals-table td:last-child {
            text-align: right;
            color: #153627;
            font-weight: 800;
        }
        .footer-note {
            margin-top: 5mm;
            display: flex;
            justify-content: space-between;
            gap: 10px;
            padding-top: 4mm;
            border-top: 1px solid #dbe7dc;
            color: #6d7f71;
            font-size: 10px;
            line-height: 1.5;
        }

        @media print {
            body {
                background: #ffffff;
            }
            .actions {
                display: none;
            }
            .page {
                width: auto;
                min-height: 0;
                margin: 0;
                padding: 0;
                box-shadow: none;
            }
            .hero {
                break-inside: avoid;
            }
            .card,
            .note-card,
            .table-card,
            .totals-card,
            .sign-box {
                break-inside: avoid;
            }
            @page {
                size: A4 portrait;
                margin: 10mm;
            }
        }
    </style>
</head>
<body>
<div class="actions">
    <button type="button" onclick="window.print()">Yazdır / PDF Kaydet</button>
</div>
<div class="page">
    <section class="hero">
        <div>
            <div class="brand">
                @if($powersaLogoDataUri)
                    <img src="{{ $powersaLogoDataUri }}" alt="Powersa Logo" class="brand-logo">
                @else
                    <div class="brand-fallback">POWERSA</div>
                @endif
                <div>
                    <span class="eyebrow">Depo Operasyon Belgesi</span>
                    <h1 class="title">Sipariş Formu</h1>
                    <p class="subtitle">
                        Sevkiyat Toplama Fişi. Depo toplama, kontrol ve sevk sürecinde kullanılmak üzere
                        müşteri, sipariş ve ürün kalemlerini tek sayfada toplar.
                    </p>
                    <div class="reference">
                        <span class="reference-chip">Sipariş {{ $order?->order_no ?? '-' }}</span>
                        <span class="reference-chip">Sevkiyat {{ $shipment->shipment_no }}</span>
                        <span class="reference-chip">{{ $statusLabel }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="hero-side">
            <div class="barcode-card">
                <div class="barcode-label">Sevkiyat Referansı</div>
                <div class="barcode-visual" aria-hidden="true"></div>
                <div class="barcode-text">{{ $shipment->shipment_no }}</div>
            </div>

            <div class="meta-list">
                <div class="meta-row">
                    <span class="label">Sevk Tarihi</span>
                    <span class="value">{{ $shipmentDate ?: '-' }}</span>
                </div>
                <div class="meta-row">
                    <span class="label">Sipariş Onay</span>
                    <span class="value">{{ $approvedDate ?: ($orderDate ?: '-') }}</span>
                </div>
                <div class="meta-row">
                    <span class="label">Çıktı Zamanı</span>
                    <span class="value">{{ $printedDate ?: '-' }}</span>
                </div>
            </div>
        </div>
    </section>

    <section class="summary-grid">
        <div class="summary-card">
            <div class="kicker">Toplam Kalem</div>
            <div class="value">{{ $shipment->items->count() }}</div>
            <div class="hint">{{ $brandCount }} farklı marka</div>
        </div>
        <div class="summary-card">
            <div class="kicker">Sevk Adedi</div>
            <div class="value">{{ $totals['shipped_qty_total'] }}</div>
            <div class="hint">Sipariş toplamı {{ $totals['ordered_qty_total'] }}</div>
        </div>
        <div class="summary-card">
            <div class="kicker">Kalan Adet</div>
            <div class="value">{{ $totals['remaining_qty_total'] }}</div>
            <div class="hint">Kısmi sevk kontrolü</div>
        </div>
        <div class="summary-card">
            <div class="kicker">Gönderim Tutarı</div>
            <div class="value">{{ number_format((float) $totals['sent_amount'], 0, ',', '.') }}</div>
            <div class="hint">{{ $currency }} toplam sevk tutarı</div>
        </div>
    </section>

    <section class="info-grid">
        <div class="card">
            <div class="card-title">Alıcı Cari</div>
            <div class="card-name">{{ $customer?->name ?? '-' }}</div>
            <div class="stack" style="margin-top: 12px;">
                <div class="data-row">
                    <div class="key">Cari Kodu</div>
                    <div class="val">{{ $customer?->code ?? '-' }}</div>
                </div>
                <div class="data-row">
                    <div class="key">İl / İlçe</div>
                    <div class="val">{{ $customer?->city ?? '-' }} / {{ $customer?->district ?? '-' }}</div>
                </div>
                <div class="data-row">
                    <div class="key">Telefon</div>
                    <div class="val">{{ $customer?->phone ?? '-' }}</div>
                </div>
                <div class="data-row">
                    <div class="key">Adres</div>
                    <div class="val">{{ $customerAddress }}</div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-title">Sipariş ve Operasyon</div>
            <div class="stack">
                <div class="data-row">
                    <div class="key">Sipariş No</div>
                    <div class="val">{{ $order?->order_no ?? '-' }}</div>
                </div>
                <div class="data-row">
                    <div class="key">Sipariş Tarihi</div>
                    <div class="val">{{ $orderDate ?: '-' }}</div>
                </div>
                <div class="data-row">
                    <div class="key">Plasiyer</div>
                    <div class="val">{{ $salesperson?->name ?? '-' }}</div>
                </div>
                <div class="data-row">
                    <div class="key">Gönderen</div>
                    <div class="val">{{ $shipment->createdBy?->name ?? '-' }}</div>
                </div>
                <div class="data-row">
                    <div class="key">Gönderi Şekli</div>
                    <div class="val">Depo Sevk</div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-title">Sevkiyat Bilgisi</div>
            <div class="stack">
                <div class="data-row">
                    <div class="key">Depo</div>
                    <div class="val">{{ $shipment->warehouse?->name ?? '-' }}</div>
                </div>
                <div class="data-row">
                    <div class="key">Bayi</div>
                    <div class="val">{{ $dealer?->name ?? '-' }}</div>
                </div>
                <div class="data-row">
                    <div class="key">Taşıyıcı</div>
                    <div class="val">{{ $shipment->carrier_name ?? '-' }}</div>
                </div>
                <div class="data-row">
                    <div class="key">Takip No</div>
                    <div class="val">{{ $shipment->tracking_no ?? '-' }}</div>
                </div>
                <div class="data-row">
                    <div class="key">Durum</div>
                    <div class="val">{{ $statusLabel }}</div>
                </div>
            </div>
        </div>
    </section>

    <section class="note-card">
        <div class="card-title">Sipariş Notu</div>
        <div class="note-body">{{ $shipment->note ?: ($order?->note ?: 'Bu sevkiyat için ek not girilmedi.') }}</div>
    </section>

    <section class="table-card">
        <table>
            <thead>
            <tr>
                <th class="center" style="width: 9mm;">Sr</th>
                <th style="width: 32mm;">Ürün Kodu</th>
                <th>Ürün Adı</th>
                <th style="width: 22mm;">Üretici</th>
                <th class="num" style="width: 14mm;">Sipariş</th>
                <th class="num" style="width: 14mm;">Sevk</th>
                <th class="num" style="width: 14mm;">Kalan</th>
                <th class="num" style="width: 22mm;">Birim</th>
                <th class="num" style="width: 24mm;">Tutar</th>
            </tr>
            </thead>
            <tbody>
            @forelse($shipment->items as $index => $item)
                @php
                    $ordered = (int) $item->ordered_qty;
                    $shipped = (int) $item->shipped_qty;
                    $remaining = max(0, $ordered - $shipped);
                    $sku = $item->product?->sku ?? '-';
                    $oem = $item->product?->oem_code ?? null;
                    $brand = $item->product?->brand?->name ?? '-';
                @endphp
                <tr>
                    <td class="center">{{ $index + 1 }}</td>
                    <td>
                        <div class="sku">{{ $sku }}</div>
                        <div class="sku-sub">{{ $oem ?: '-' }}</div>
                    </td>
                    <td>
                        <div class="product-name">{{ $item->product?->name ?? '-' }}</div>
                    </td>
                    <td>{{ $brand }}</td>
                    <td class="num">{{ $ordered }}</td>
                    <td class="num">{{ $shipped }}</td>
                    <td class="num">{{ $remaining }}</td>
                    <td class="num">{{ number_format((float) $item->unit_price, 2, ',', '.') }}</td>
                    <td class="num">{{ number_format((float) $item->line_total_shipped, 2, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" style="text-align: center; padding: 18px; color: #6d7f71;">
                        Sevkiyata ait kalem bulunamadı.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </section>

    <section class="bottom-grid">
        <div class="sign-grid">
            <div class="sign-box">
                <div class="card-title">Depo Çıkış Kontrolü</div>
                <p style="font-size: 12px; color: #506a57; line-height: 1.5;">
                    Sipariş kalemleri depo tarafından kontrol edilip sevke hazır hale getirilmiştir.
                </p>
                <div class="line">Hazırlayan / İmza</div>
            </div>
            <div class="sign-box">
                <div class="card-title">Teslim Alan</div>
                <p style="font-size: 12px; color: #506a57; line-height: 1.5;">
                    Kargo veya dağıtım ekibi teslim aşamasında bu alanı kullanabilir.
                </p>
                <div class="line">Teslim Alan / İmza</div>
            </div>
        </div>

        <div class="totals-card">
            <div class="totals-head">
                <div class="title-sm">Sevkiyat Özeti</div>
                <div class="big">{{ number_format((float) $totals['sent_amount'], 2, ',', '.') }} {{ $currency }}</div>
            </div>
            <table class="totals-table">
                <tr>
                    <td>Toplam Sipariş Adedi</td>
                    <td>{{ $totals['ordered_qty_total'] }}</td>
                </tr>
                <tr>
                    <td>Gönderilen Adet</td>
                    <td>{{ $totals['shipped_qty_total'] }}</td>
                </tr>
                <tr>
                    <td>Kalan Adet</td>
                    <td>{{ $totals['remaining_qty_total'] }}</td>
                </tr>
                <tr>
                    <td>Gönderilen Tutar</td>
                    <td>{{ number_format((float) $totals['sent_amount'], 2, ',', '.') }} {{ $currency }}</td>
                </tr>
            </table>
        </div>
    </section>

    <section class="footer-note">
        <div>
            Bu belge Powersa depo operasyonu için üretildi. Basılı kopya sevkiyat, teslim ve kontrol aşamalarında
            referans olarak kullanılabilir.
        </div>
        <div>
            Çıktı: {{ $printedDate ?: '-' }}<br>
            Belge No: {{ $shipment->shipment_no }}
        </div>
    </section>
</div>
</body>
</html>
