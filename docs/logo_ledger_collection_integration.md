# Logo Cari Hareket ve Tahsilat Entegrasyonu

## Durum
- `Logo -> B2B` cari hareket senkronu icin alici endpoint eklendi.
- `B2B -> Logo` tahsilat senkronu icin koleksiyon kayitlarina kaynak/ref/sync durumu alanlari eklendi.
- Bu dokumandaki ikinci yon, yani Logo'ya tahsilat gonderen bridge/export adimi bir sonraki parcadir.

## Cari Hareket Sync Endpoint
- URL: `/api/integrations/logo/ledger/sync`
- Header: `X-Integration-Key: <LOGO_LEDGER_SYNC_KEY>`
- Method: `POST`

Ornek payload:

```json
{
  "dealer_id": 1,
  "records": [
    {
      "customer_external_ref": "12345",
      "external_ref": "CLFLINE-98765",
      "date": "2026-04-10",
      "type": "invoice",
      "debit": 1250.50,
      "credit": 0,
      "currency": "TRY",
      "reference_no": "FAT-001",
      "description": "Logo fatura hareketi",
      "meta": {
        "logo_module": "sales",
        "raw": {
          "LOGICALREF": 98765
        }
      }
    }
  ]
}
```

## Esleme Kurali
- `customer_external_ref` varsa once `customers.source_reference` uzerinden eslesir.
- Yoksa `customer_code` ile ayni bayide `customers.code` aranir.
- `external_ref` alanı her hareket icin idempotency anahtari olarak kullanilir.

## Bakiye Davranisi
- Sync servisinde hareketler upsert edilir.
- Ardindan etkilenmis musterilerin `ledger_entries.balance_after` degerleri bastan hesaplanir.
- Bu sayede tarihsel Logo hareketleri sonradan gelse bile cari bakiye tutarliligi korunur.

## Tahsilat Omurgasi
- Yeni B2B tahsilatlari `collections.source_system = b2b` ile acilir.
- Logo kaynakli musteriler icin yeni tahsilatlar `sync_status = pending` ile isaretlenir.
- Tahsilat export kuyrugu endpoint'i: `GET /api/integrations/logo/collections/pending`
- Tahsilat ack endpoint'i: `POST /api/integrations/logo/collections/ack`
- Bridge, `export_key = B2B-COL-{collection_id}` degerini Logo tarafinda idempotency anahtari olarak kullanmalidir.

Ornek pending kaydi:

```json
{
  "collection_id": 15,
  "export_key": "B2B-COL-15",
  "customer_code": "120.01.0001",
  "customer_external_ref": "12345",
  "date": "2026-04-10",
  "method": "transfer",
  "amount": "1500.00",
  "currency": "TRY",
  "reference_no": "TRF-001"
}
```

Ornek ack payload:

```json
{
  "records": [
    {
      "collection_id": 15,
      "status": "synced",
      "external_ref": "CLFICHE-5001",
      "meta": {
        "logo_fiche_no": "THS-001"
      }
    }
  ]
}
```

## Ortam Degiskenleri

```env
LOGO_CUSTOMER_SYNC_KEY=change-this-to-a-long-random-secret
LOGO_PRODUCT_SYNC_KEY=change-this-to-a-long-random-secret
LOGO_LEDGER_SYNC_KEY=change-this-to-a-long-random-secret
LOGO_COLLECTION_SYNC_KEY=change-this-to-a-long-random-secret
```
