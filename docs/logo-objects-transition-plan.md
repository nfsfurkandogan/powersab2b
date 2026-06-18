# Logo SQL Bridge Yazma Plani

Bu belge eski Logo Objects gecis planinin yerine gecer. Powersa B2B yazma
akisi icin karar degisti: Logo Objects/RabbitMQ zorunlu hat olarak
kullanilmayacak. Yazma lisansi olan kurulumda yazma isi Logo SQL makinesinde
calisan bridge tarafindan, onayli stored procedure'ler uzerinden yapilacak.

## Karar

- Okuma islemleri Logo SQL Server'dan devam eder.
- Yazma islemlerinde B2B uygulamasi Logo veritabanina dogrudan baglanmaz.
- B2B kendi veritabaninda kaydi olusturur ve `sync_status = pending` birakir.
- `tools/logo-sync` bridge scriptleri pending endpoint'leri poll eder.
- Bridge, Logo SQL makinesindeki yetkili stored procedure'leri cagirir.
- Sonuc mevcut `ack` endpoint'leriyle B2B'ye geri yazilir.
- RabbitMQ / `.NET logo-worker` hattı sadece ileride ayrica istenirse
  opsiyonel publish yolu olarak kalir; varsayilan akis degildir.

## Aktif Yazma Akislari

1. Cari olusturma / guncelleme
   - Pending: `GET /api/integrations/logo/customers/pending`
   - Ack: `POST /api/integrations/logo/customers/ack`
   - Bridge: `tools/logo-sync/logo-customers-export.mjs`
   - Procedure: `LOGO_CUSTOMER_EXPORT_PROCEDURE`

2. Tahsilat olusturma
   - Pending: `GET /api/integrations/logo/collections/pending`
   - Ack: `POST /api/integrations/logo/collections/ack`
   - Bridge: `tools/logo-sync/logo-collections-export.mjs`
   - Procedure: `LOGO_COLLECTION_EXPORT_PROCEDURE`

3. POS satis / masraf
   - Pending: `GET /api/integrations/logo/pos-sales/pending`
   - Ack: `POST /api/integrations/logo/pos-sales/ack`
   - Pending: `GET /api/integrations/logo/pos-expenses/pending`
   - Ack: `POST /api/integrations/logo/pos-expenses/ack`
   - Bridge:
     - `tools/logo-sync/logo-pos-sales-export.mjs`
     - `tools/logo-sync/logo-pos-expenses-export.mjs`
   - Procedure:
     - `LOGO_POS_SALE_EXPORT_PROCEDURE`
     - `LOGO_POS_EXPENSE_EXPORT_PROCEDURE`

4. Siparis / sevkiyat / iade
   - Pending: `GET /api/integrations/logo/orders/pending`
   - Ack: `POST /api/integrations/logo/orders/ack`
   - Pending: `GET /api/integrations/logo/shipments/pending`
   - Ack: `POST /api/integrations/logo/shipments/ack`
   - Pending: `GET /api/integrations/logo/returns/pending`
   - Ack: `POST /api/integrations/logo/returns/ack`
   - Bridge: `tools/logo-sync/logo-documents-export.mjs`
   - Procedure:
     - `LOGO_ORDER_EXPORT_PROCEDURE`
     - `LOGO_SHIPMENT_EXPORT_PROCEDURE`
     - `LOGO_RETURN_EXPORT_PROCEDURE`

## Laravel Tarafi

- `LOGO_WRITE_TRANSPORT=bridge` varsayilandir.
- `LOGO_WRITE_ENABLED=false` birakilabilir; bridge polling icin bu ayar
  gerekli degildir.
- Customer ve tahsilat kayitlari yine `integration_sync_events` icine izleme
  kaydi uretir, ancak bridge transport'ta event publish edilmis sayilmaz.
- Pending endpoint'leri asil kuyruk kaynagidir.

## Bridge Tarafi

SQL makinedeki `.env` icinde:

```env
LOGO_CUSTOMER_EXPORT_PROCEDURE=dbo.PowersaB2B_ExportCustomer
LOGO_COLLECTION_EXPORT_PROCEDURE=dbo.PowersaB2B_ExportCollection
LOGO_POS_SALE_EXPORT_PROCEDURE=dbo.PowersaB2B_ExportPosSale
LOGO_POS_EXPENSE_EXPORT_PROCEDURE=dbo.PowersaB2B_ExportPosExpense
LOGO_ORDER_EXPORT_PROCEDURE=dbo.PowersaB2B_ExportOrder
LOGO_SHIPMENT_EXPORT_PROCEDURE=dbo.PowersaB2B_ExportShipment
LOGO_RETURN_EXPORT_PROCEDURE=dbo.PowersaB2B_ExportReturn
```

Bridge komutlari:

```bash
npm run sync:customers-export
npm run sync:collections
npm run sync:pos-sales
npm run sync:pos-expenses
```

Tum okuma ve yazma adimlari icin:

```bash
npm run sync:all
```

## Guvenlik ve Idempotency

- Procedure'ler `ExportKey` degerini idempotency anahtari olarak kullanmalidir.
- Cari icin format: `B2B-CUST-{customer_id}`
- Tahsilat icin format: `B2B-COL-{collection_id}`
- POS satis icin format: `B2B-POS-SALE-{pos_sale_id}`
- POS masraf icin format: `B2B-POS-EXPENSE-{pos_expense_id}`
- Siparis icin format: `B2B-ORDER-{order_id}`
- Sevkiyat icin format: `B2B-SHIP-{shipment_id}`
- Iade icin format: `B2B-RETURN-{return_request_id}`
- Basarili yazmada procedure `@ExternalRef OUTPUT` dondurmelidir.
- Hata durumunda bridge `ack` endpoint'ine `status=failed` ve hata mesajini
  gonderir; B2B kaydi tekrar pending/failed listesinden cekilebilir.

## Neden Dogrudan Tablo Yazmiyoruz

Yazma lisansi SQL tarafinda yazma yetkisini mumkun kilsa da Logo'nun numarator,
muhasebe, stok, fis ve cari baglantilari dogru kurulmalidir. Bu nedenle yazma
mantigi tek yerde, Logo SQL makinesindeki onayli procedure'lerde tutulur; B2B
sadece pending/ack sozlesmesini yonetir.
