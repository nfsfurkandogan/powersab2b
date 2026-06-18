@php
    $currencyLabel = 'GEL';
@endphp
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>POS Gün Sonu Raporu</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, Helvetica, sans-serif; color: #1f2937; background: #f8fafc; }
        .actions { position: sticky; top: 0; z-index: 20; background: #f8fafc; padding: 8px; border-bottom: 1px solid #e5e7eb; text-align: right; }
        .actions button { border: 1px solid #d1d5db; background: #fff; border-radius: 6px; padding: 6px 10px; cursor: pointer; }
        .page { width: 210mm; max-width: 100%; margin: 0 auto; background: #fff; padding: 12mm; }
        h1, h2, h3, p { margin: 0; }
        .header { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; margin-bottom: 10mm; }
        .title { font-size: 22px; font-weight: 700; }
        .sub { color: #4b5563; font-size: 12px; margin-top: 4px; }
        .badge { display: inline-block; border: 1px solid #d1d5db; border-radius: 999px; padding: 4px 8px; font-size: 12px; }
        .grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; margin-bottom: 8mm; }
        .card { border: 1px solid #e5e7eb; border-radius: 8px; padding: 10px; background: #fafafa; }
        .card .label { color: #6b7280; font-size: 11px; text-transform: uppercase; letter-spacing: .03em; }
        .card .value { margin-top: 6px; font-size: 20px; font-weight: 700; }
        .section { margin-top: 8mm; }
        .section h3 { font-size: 12px; text-transform: uppercase; color: #6b7280; margin-bottom: 8px; letter-spacing: .03em; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th, td { border: 1px solid #e5e7eb; padding: 8px; text-align: left; }
        th { background: #f3f4f6; font-weight: 700; }
        .num { text-align: right; white-space: nowrap; }

        @media print {
            body { background: #fff; }
            .actions { display: none; }
            .page { width: auto; margin: 0; padding: 0; }
            @page { size: A4 portrait; margin: 10mm; }
        }
    </style>
</head>
<body>
<div class="actions">
    <button type="button" onclick="window.print()">Yazdır</button>
</div>

<main class="page">
    <header class="header">
        <div>
            <h1 class="title">POS Gün Sonu Raporu</h1>
            <p class="sub">Powersa B2B Perakende Satış Özeti</p>
        </div>
        <div style="text-align:right;">
            <p><strong>Üretilme:</strong> {{ \Illuminate\Support\Carbon::parse($report['generated_at'] ?? now())->format('d.m.Y H:i') }}</p>
            @if(!empty($report['session']['id']))
                <p class="sub">Oturum: #{{ $report['session']['id'] }}</p>
                <span class="badge">{{ strtoupper((string) ($report['session']['status'] ?? '')) }}</span>
            @endif
        </div>
    </header>

    <section class="grid">
        <article class="card">
            <p class="label">Toplam Satış</p>
            <p class="value">{{ (int) ($report['summary']['sale_count'] ?? 0) }}</p>
        </article>
        <article class="card">
            <p class="label">Tahsil Edilen</p>
            <p class="value">{{ $currencyLabel }} {{ number_format((float) ($report['summary']['grand_total'] ?? 0), 2, ',', '.') }}</p>
        </article>
        <article class="card">
            <p class="label">İptal Tutarı</p>
            <p class="value">{{ $currencyLabel }} {{ number_format((float) ($report['summary']['grand_total_cancelled'] ?? 0), 2, ',', '.') }}</p>
        </article>
        <article class="card">
            <p class="label">Net Ciro</p>
            <p class="value">{{ $currencyLabel }} {{ number_format((float) ($report['summary']['net_total'] ?? 0), 2, ',', '.') }}</p>
        </article>
        <article class="card">
            <p class="label">Masraf Toplamı</p>
            <p class="value">{{ $currencyLabel }} {{ number_format((float) ($report['summary']['expense_total'] ?? 0), 2, ',', '.') }}</p>
        </article>
        <article class="card">
            <p class="label">Beklenen Kasa</p>
            <p class="value">{{ $currencyLabel }} {{ number_format((float) ($report['summary']['expected_cash'] ?? 0), 2, ',', '.') }}</p>
        </article>
    </section>

    <section class="section">
        <h3>Ödeme Tipi Dağılımı</h3>
        <table>
            <thead>
            <tr>
                <th>Yöntem</th>
                <th class="num">İşlem</th>
                <th class="num">Tutar</th>
            </tr>
            </thead>
            <tbody>
            @forelse(($report['totals_by_method'] ?? []) as $row)
                <tr>
                    <td>{{ strtoupper((string) ($row['method'] ?? '-')) }}</td>
                    <td class="num">{{ (int) ($row['payment_count'] ?? 0) }}</td>
                    <td class="num">{{ $currencyLabel }} {{ number_format((float) ($row['total_amount'] ?? 0), 2, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" style="text-align:center; color:#6b7280;">Kayıt bulunamadı.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </section>

    <section class="section">
        <h3>Masraf Özeti</h3>
        <table>
            <thead>
            <tr>
                <th>Kategori</th>
                <th class="num">İşlem</th>
                <th class="num">Tutar</th>
            </tr>
            </thead>
            <tbody>
            @forelse(($report['expenses']['by_category'] ?? []) as $row)
                <tr>
                    <td>{{ (string) ($row['category'] ?? '-') }}</td>
                    <td class="num">{{ (int) ($row['expense_count'] ?? 0) }}</td>
                    <td class="num">{{ $currencyLabel }} {{ number_format((float) ($row['total_amount'] ?? 0), 2, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" style="text-align:center; color:#6b7280;">Masraf kaydı bulunamadı.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </section>

    <section class="section">
        <h3>Filtreler</h3>
        <table>
            <tbody>
            <tr>
                <th style="width:30%;">Kasa ID</th>
                <td>{{ $report['filters']['cashbox_id'] ?? '-' }}</td>
            </tr>
            <tr>
                <th>Oturum ID</th>
                <td>{{ $report['filters']['pos_session_id'] ?? '-' }}</td>
            </tr>
            <tr>
                <th>Tarih</th>
                <td>{{ $report['filters']['date'] ?? '-' }}</td>
            </tr>
            <tr>
                <th>Tarih Aralığı</th>
                <td>{{ $report['filters']['date_from'] ?? '-' }} - {{ $report['filters']['date_to'] ?? '-' }}</td>
            </tr>
            </tbody>
        </table>
    </section>
</main>
</body>
</html>
