<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kargo Etiketi - {{ $shipment->shipment_no }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, Helvetica, sans-serif; color: #111827; }
        .label { width: 100mm; min-height: 140mm; margin: 8mm auto; border: 1px solid #111827; padding: 6mm; }
        h1, h2, p { margin: 0; }
        .muted { color: #4b5563; font-size: 11px; }
        .row { margin-bottom: 5mm; }
        .box { border: 1px solid #374151; padding: 3mm; margin-top: 2mm; }
        .title { font-size: 18px; font-weight: 700; margin-bottom: 1mm; }
        .big { font-size: 16px; font-weight: 700; letter-spacing: 0.5px; }
        .actions { text-align: right; padding: 8px; border-bottom: 1px solid #e5e7eb; }
        .actions button { border: 1px solid #d1d5db; background: #fff; border-radius: 6px; padding: 6px 10px; cursor: pointer; }

        @media print {
            .actions { display: none; }
            .label { margin: 0; border: 1px solid #111827; }
            @page { size: 100mm 150mm; margin: 4mm; }
        }
    </style>
</head>
<body>
<div class="actions">
    <button type="button" onclick="window.print()">Yazdır</button>
</div>
<div class="label">
    <div class="row">
        <p class="title">KARGO ETİKETİ</p>
        <p class="muted">{{ $shipment->shipment_no }} · Sipariş {{ $shipment->order?->order_no ?? '-' }}</p>
    </div>

    <div class="row">
        <p class="muted">ALICI</p>
        <div class="box">
            <p class="big">{{ $shipment->order?->customer?->name ?? '-' }}</p>
            <p>{{ $shipment->order?->customer?->phone ?? '-' }}</p>
            <p>{{ $shipment->order?->customer?->city ?? '-' }} / {{ $shipment->order?->customer?->district ?? '-' }}</p>
        </div>
    </div>

    <div class="row">
        <p class="muted">TAŞIYICI</p>
        <div class="box">
            <p>{{ $shipment->carrier_name ?? 'Belirtilmedi' }}</p>
            <p class="big">{{ $shipment->tracking_no ?? '-' }}</p>
        </div>
    </div>

    <div class="row">
        <p class="muted">DEPO</p>
        <div class="box">
            <p>{{ $shipment->warehouse?->name ?? '-' }}</p>
            <p>{{ optional($shipment->shipped_at ?? $shipment->created_at)->format('d.m.Y H:i') }}</p>
        </div>
    </div>
</div>
</body>
</html>
