<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kargo Etiketi - {{ $shipment->shipment_no }}</title>
    @php
        $order = $shipment->order;
        $customer = $order?->customer;
        $dealer = $order?->dealer;
        $customerMeta = is_array($customer?->meta) ? $customer->meta : [];
        $firstFilled = static function (array $values): ?string {
            foreach ($values as $value) {
                $candidate = trim((string) $value);
                if ($candidate !== '') {
                    return $candidate;
                }
            }

            return null;
        };
        $upperTr = static fn (string $value): string => mb_strtoupper(str_replace(['i', 'ı'], ['İ', 'I'], $value), 'UTF-8');

        $companyName = $firstFilled([
            $customer?->title,
            $customer?->name,
            data_get($customerMeta, 'integrations.logo.payload.title'),
            data_get($customerMeta, 'integrations.logo.payload.raw.DEFINITION_'),
            data_get($customerMeta, 'integrations.logo.payload.raw.DEFINITION'),
            $dealer?->name,
            $order?->order_no,
        ]) ?? '-';

        $customerAddress = $firstFilled([
            $customer?->address,
            data_get($customerMeta, 'address'),
            data_get($customerMeta, 'full_address'),
            data_get($customerMeta, 'integrations.logo.payload.address'),
            data_get($customerMeta, 'integrations.logo.payload.full_address'),
            data_get($customerMeta, 'integrations.logo.payload.raw.ADDR1'),
            data_get($customerMeta, 'integrations.logo.payload.raw.ADDR2'),
            data_get($customerMeta, 'integrations.logo.payload.raw.ADDRESS'),
            data_get($customerMeta, 'integrations.logo.payload.raw.ADRES'),
        ]) ?? '-';
        $customerCity = $firstFilled([
            $customer?->city,
            data_get($customerMeta, 'integrations.logo.payload.city'),
            data_get($customerMeta, 'integrations.logo.payload.province'),
            data_get($customerMeta, 'integrations.logo.payload.raw.CITY'),
            data_get($customerMeta, 'integrations.logo.payload.raw.IL'),
        ]) ?? '';
        $customerDistrict = $firstFilled([
            $customer?->district,
            data_get($customerMeta, 'integrations.logo.payload.district'),
            data_get($customerMeta, 'integrations.logo.payload.town'),
            data_get($customerMeta, 'integrations.logo.payload.raw.TOWN'),
            data_get($customerMeta, 'integrations.logo.payload.raw.DISTRICT'),
            data_get($customerMeta, 'integrations.logo.payload.raw.ILCE'),
            data_get($customerMeta, 'integrations.logo.payload.raw.İLÇE'),
        ]) ?? '';
        $locationParts = array_values(array_filter([$customerDistrict, $customerCity, $customerDistrict], fn ($value) => trim((string) $value) !== ''));
        $location = $locationParts !== [] ? implode('/', $locationParts) : '-';
        $customerPhone = $firstFilled([
            $customer?->phone,
            data_get($customerMeta, 'integrations.logo.payload.phone'),
            data_get($customerMeta, 'integrations.logo.payload.phone_1'),
            data_get($customerMeta, 'integrations.logo.payload.mobile_phone'),
            data_get($customerMeta, 'integrations.logo.payload.raw.TELNRS1'),
            data_get($customerMeta, 'integrations.logo.payload.raw.TELNR1'),
            data_get($customerMeta, 'integrations.logo.payload.raw.PHONE'),
            data_get($customerMeta, 'integrations.logo.payload.raw.TELEFON'),
            data_get($customerMeta, 'integrations.logo.payload.raw.GSM'),
        ]) ?? '-';
        $printedAt = trim((string) ($labelShipTime ?? '')) !== ''
            ? trim((string) $labelShipTime)
            : (optional($shipment->shipped_at ?? $shipment->created_at)->format('d.m.Y H:i:s') ?? '-');
        $address = trim((string) $customerAddress);
        $customerCode = $firstFilled([
            $customer?->code,
            data_get($customerMeta, 'code'),
            data_get($customerMeta, 'integrations.logo.payload.code'),
            data_get($customerMeta, 'integrations.logo.payload.raw.CODE'),
            data_get($customerMeta, 'integrations.logo.payload.raw.CODE_'),
        ]) ?? '-';
        $recipientLength = mb_strlen($companyName, 'UTF-8');
        $addressLength = mb_strlen($address, 'UTF-8');
        $recipientClass = $recipientLength > 46 ? ' recipient-long' : ($recipientLength > 30 ? ' recipient-medium' : '');
        $addressClass = $addressLength > 58 ? ' address-long' : ($addressLength > 34 ? ' address-medium' : '');
    @endphp
    <style>
        * { box-sizing: border-box; }
        html,
        body {
            width: 100mm;
            height: 70mm;
            margin: 0;
            padding: 0;
            background: #fff;
            color: #111;
            font-family: Arial, Helvetica, sans-serif;
        }
        body {
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .actions {
            position: fixed;
            top: 8px;
            right: 8px;
            z-index: 5;
        }
        .actions button {
            border: 1px solid #222;
            background: #fff;
            border-radius: 5px;
            padding: 6px 10px;
            font-weight: 800;
            cursor: pointer;
        }
        .label {
            width: 100mm;
            height: 70mm;
            padding: 2.2mm;
            background: #f5f7f2;
            overflow: hidden;
        }
        .label-inner {
            width: 100%;
            height: 100%;
            border: 1.6px solid #111;
            border-radius: 1.5mm;
            padding: 2.4mm 3mm 2.6mm;
            background: #fff;
            display: flex;
            flex-direction: column;
            gap: 2mm;
            box-shadow: inset 0 0 0 0.55mm #d9eadf;
        }
        .topbar {
            min-height: 7mm;
            border: 1.2px solid #184c36;
            border-left-width: 4mm;
            border-radius: 1mm;
            background: #f4faf5;
            display: grid;
            grid-template-columns: 1fr auto;
            align-items: center;
            gap: 2mm;
            padding: 1mm 1.8mm;
            color: #184c36;
            font-weight: 950;
            line-height: 1;
            text-transform: uppercase;
        }
        .topbar-title {
            font-size: 10.5px;
            letter-spacing: 1.25px;
        }
        .shipment-no {
            border: 1px solid #111;
            border-radius: 0.8mm;
            background: #f0c85a;
            color: #111;
            padding: 1mm 1.8mm;
            font-size: 8px;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }
        .recipient {
            min-height: 18.5mm;
            border: 1.4px solid #0a2419;
            border-radius: 0.8mm;
            background: #11261d;
            color: #fff;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 2.1mm 3mm;
            font-size: 16.2px;
            line-height: 1.08;
            font-weight: 950;
            letter-spacing: 0.65px;
            text-transform: uppercase;
            word-break: break-word;
        }
        .recipient::before {
            content: "ALICI";
            margin-bottom: 1mm;
            border-radius: 999px;
            background: rgba(240, 200, 90, 0.95);
            color: #111;
            padding: 0.7mm 2.4mm;
            font-size: 7px;
            line-height: 1;
            letter-spacing: 1.1px;
        }
        .recipient-medium {
            font-size: 14px;
            line-height: 1.06;
        }
        .recipient-long {
            font-size: 12.5px;
            line-height: 1.05;
            letter-spacing: 0.35px;
        }
        .address {
            min-height: 18.5mm;
            border: 1.5px solid #184c36;
            border-radius: 0.8mm;
            background: #fff8dc;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 1.5mm 2.4mm;
            font-size: 18.5px;
            line-height: 1.12;
            font-weight: 950;
            letter-spacing: 0.45px;
            text-transform: uppercase;
            word-break: break-word;
        }
        .address::before {
            content: "TESLİMAT ADRESİ";
            margin-bottom: 0.9mm;
            color: #184c36;
            font-size: 6.8px;
            line-height: 1;
            letter-spacing: 1px;
        }
        .address-medium {
            font-size: 16.2px;
            line-height: 1.1;
        }
        .address-long {
            font-size: 13.8px;
            line-height: 1.08;
            letter-spacing: 0.35px;
        }
        .bottom {
            display: flex;
            flex-direction: column;
            gap: 1.5mm;
            margin-top: auto;
        }
        .details {
            border: 1px solid #184c36;
            border-left-width: 3mm;
            border-radius: 0.9mm;
            background: #f5faf4;
            padding: 1.5mm 2mm;
            display: grid;
            grid-template-columns: 1.2fr 1fr 1.25fr;
            gap: 1.5mm 2.5mm;
            font-size: 9.2px;
            line-height: 1;
            font-weight: 950;
            letter-spacing: 0.45px;
            text-transform: uppercase;
        }
        .details-row {
            display: flex;
            align-items: baseline;
            gap: 1mm;
            min-width: 0;
        }
        .details-label {
            flex: 0 0 auto;
            color: #2f7d4f;
            font-size: 6.4px;
            letter-spacing: 0.9px;
        }
        .details-value {
            min-width: 0;
            overflow-wrap: anywhere;
        }
        .phone,
        .date {
            font-size: 10.8px;
            letter-spacing: 0.7px;
        }
        .refs {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5mm;
        }
        .ref {
            border: 1px solid #d8b64f;
            border-radius: 0.8mm;
            background: #fff9df;
            padding: 1mm 1.5mm;
            font-size: 7.5px;
            line-height: 1.15;
            font-weight: 950;
            text-transform: uppercase;
            overflow-wrap: anywhere;
        }
        .ref span {
            color: #2f7d4f;
            letter-spacing: 0.75px;
        }
        @page {
            size: 100mm 70mm;
            margin: 0;
        }
        @media print {
            .actions { display: none; }
            .label {
                padding: 2.2mm;
                background: #fff;
            }
            .label-inner {
                border-width: 1.6px;
                box-shadow: inset 0 0 0 0.65mm #d9eadf;
            }
        }
    </style>
</head>
<body>
<div class="actions">
    <button type="button" onclick="window.print()">Yazdır</button>
</div>
<main class="label">
    <section class="label-inner">
        <div class="topbar">
            <div class="topbar-title">Kargo Etiketi</div>
            <div class="shipment-no">{{ $shipment->shipment_no }}</div>
        </div>
        <div class="recipient{{ $recipientClass }}">{{ $upperTr((string) $companyName) }}</div>
        <div class="address{{ $addressClass }}">{{ $upperTr($address) }}</div>
        <div class="bottom">
            <div class="details">
                <div class="details-row">
                    <span class="details-label">BÖLGE</span>
                    <span class="details-value">{{ $upperTr($location) }}</span>
                </div>
                <div class="details-row phone">
                    <span class="details-label">TEL</span>
                    <span class="details-value">{{ $customerPhone }}</span>
                </div>
                <div class="details-row date">
                    <span class="details-label">TARİH</span>
                    <span class="details-value">{{ $printedAt }}</span>
                </div>
                <div class="details-row">
                    <span class="details-label">KOD</span>
                    <span class="details-value">{{ $customerCode }}</span>
                </div>
                <div class="details-row">
                    <span class="details-label">DEPO</span>
                    <span class="details-value">{{ $upperTr((string) ($shipment->warehouse?->name ?? '-')) }}</span>
                </div>
            </div>
            <div class="refs">
                <div class="ref"><span>Sevkiyat</span><br>{{ $shipment->shipment_no }}</div>
                <div class="ref"><span>Sipariş</span><br>{{ $order?->order_no ?? '-' }}</div>
            </div>
        </div>
    </section>
</main>
</body>
</html>
