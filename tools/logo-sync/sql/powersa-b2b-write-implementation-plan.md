# Powersa B2B Logo Write Implementation Plan

Bu not, B2B -> Logo yazma tarafinda uygulanacak sirayi sabitler. Amaç,
queued kayitlari sahte `external_ref` ile kapatmadan gercek Logo fis/fatura
kayitlarini olusturmaktir.

## Canli Durum

- `customers`: B2B cari export ve ack akisi hazir.
- `collections`: Tahsilat export ve ack akisi hazir; `collections-write`
  durumu `collections` ack sonucu ile eslenir.
- `orders`: B2B payload hazir, Logo prosedur write block bekliyor.
- `pos-sales`: B2B payload hazir, Logo prosedur write block bekliyor.
- `warehouse-shipments`: B2B payload hazir, Logo prosedur write block bekliyor.

## Uygulama Sirasi

1. Windows sunucuda `run-write-preflight.cmd` calistirilir.
2. Rapor sonucunda asagidaki tablo/kolonlar ve son kayit formatlari dogrulanir:
   - Siparis: `LG_003_01_ORFICHE`, `LG_003_01_ORFLINE`
   - Sevkiyat: `LG_003_01_STFICHE`, `LG_003_01_STLINE`
   - POS satis: `LG_003_01_INVOICE`, `LG_003_01_STFICHE`, `LG_003_01_STLINE`
   - Tahsilat/kasa: `LG_003_01_KSLINES`, `LG_003_01_CLFLINE`, `LG_003_01_PAYTRANS`
3. Son Logo kayitlarindan `TRCODE`, `SOURCEINDEX`, `BRANCH`, `DEPARTMENT`,
   `GENEXP*`, `SPECODE`, numarator ve cari/stok referans kurallari netlestirilir.
4. `powersa-b2b-procedure-contracts.sql` icindeki ilgili
   `IMPLEMENT LOGO WRITE BLOCK` alanlari gercek Logo insert/update kodu ile
   degistirilir.
5. Her prosedur basarili yazmada sadece Logo tarafinda olusan kalici ref'i
   `@ExternalRef` olarak dondurur ve `PowersaB2B_FinishExport` cagrilir.
6. Once tek kayit ile `npm run sync:documents-export` veya
   `npm run sync:pos-sales` denenir; sonra daemon yeniden baslatilir.

## Payload Eslesmesi

### `dbo.PowersaB2B_ExportOrder`

- Kaynak endpoint: `/api/integrations/logo/orders/pending`
- B2B key: `B2B-ORDER-{id}`
- Logo hedefi: `ORFICHE/ORFLINE`
- Zorunlu alanlar: `customer_external_ref`, `order_date`, `order_no`,
  `items[].product_external_ref`, `items[].quantity`, fiyat/KDV alanlari.

### `dbo.PowersaB2B_ExportShipment`

- Kaynak endpoint: `/api/integrations/logo/shipments/pending`
- B2B key: `B2B-SHIP-{id}`
- Logo hedefi: `STFICHE/STLINE`, gerekiyorsa `INVOICE`
- Zorunlu alanlar: `customer_external_ref`, `shipment_date`, `shipment_no`,
  `warehouse_code`, `items[].product_external_ref`, `items[].shipped_qty`.

### `dbo.PowersaB2B_ExportPosSale`

- Kaynak endpoint: `/api/integrations/logo/pos-sales/pending`
- B2B key: `B2B-POSSALE-{id}`
- Logo hedefi: `INVOICE/STFICHE/STLINE`, ödeme tipine göre `PAYTRANS/KSLINES`
- Zorunlu alanlar: `customer_external_ref`, `date`, `receipt_no`,
  `cashbox_code`, `items[].product_external_ref`, `payments[]`.

## Güvenlik Kurallari

- Queued kayitlar gercek Logo fis/fatura/kasa kaydi olusmadan `synced`
  yapilmaz.
- Prosedur idempotent calisir: ayni `ExportKey` ikinci kez geldiginde mevcut
  `POWERSA_B2B_EXPORT_LOG.EXTERNAL_REF` dondurulur.
- Yazma bloklari once tek kayit ve yedekli Logo database uzerinde denenmelidir.
