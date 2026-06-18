# Logo Go Wings Veritabani Haritasi

Kaynak dokuman: `/Users/elifurkan/Desktop/Logo-Veritabani-Dokumani.doc`

Bu not, B2B entegrasyonunda kullandigimiz Logo tablolarini ve okuma/yazma
yonlerini toplar. Logo tablo adlarinda `XXX` firma numarasi, ikinci `XX` donem
numarasidir. Ornek firma `002`, donem `01` ise cari hareket tablosu
`LG_002_01_CLFLINE` olur.

Tum dokuman tablo indeksinin B2B modulleriyle eslenmis tam hali:
`docs/logo_b2b_full_integration_matrix.md`

## Okuma Yonleri

### Cari Kartlar

- Logo tablo: `LG_XXX_CLCARD`
- B2B hedefi: `customers`
- Ana alanlar:
  - `LOGICALREF` -> `source_reference`
  - `CODE` -> `customers.code`
  - `DEFINITION_` -> `customers.name`
  - `CARDTYPE` -> alici/satici tipi, B2B icin varsayilan `3`
  - `ACTIVE` -> Logo'da `0` aktif, `1` pasif
  - `ADDR1`, `ADDR2`, `CITY`, `TOWN`, `TAXOFFICE`, `TAXNR`, `TELNRS1`, `EMAILADDR`
- Bridge: `tools/logo-sync/logo-customers-sync.mjs`

### Urun / Malzeme Kartlari

- Logo tablo: `LG_XXX_ITEMS`
- B2B hedefi: `products`, `brands`, `categories`, `base_prices`, stok alanlari
- Ana alanlar:
  - `LOGICALREF` -> urun dis referansi
  - `CODE` -> SKU
  - `NAME` -> urun adi
  - `CARDTYPE` -> ticari mal icin varsayilan `1`
  - `ACTIVE` -> Logo'da `0` aktif, `1` pasif
  - `STGRPCODE`, `PRODUCERCODE`, `SPECODE`, `VAT`
- Yardimci tablolar:
  - Stok: `LG_XXX_GNTOTST`, `LG_XXX_XX_STINVTOT`, `LG_XXX_XX_STLINE`
  - Fiyat: `LG_XXX_PRCLIST`
  - Resim/dokuman: `LG_XXX_XX_PERDOC`; guncel Logo dokumanina gore
    `INFOREF` urun `LOGICALREF` bagidir, `LDATA` SQL Server `image`
    binary alanidir. `LG_XXX_FIRMDOC` ayni binary yapinin firma geneli
    dokuman tablosudur, `LG_XXX_XX_FOLDER` ise `FPATH` dosya yolu katalogudur.
- Bridge: `tools/logo-sync/logo-products-sync.mjs`

### Cari Hareket / Bakiye

- Logo tablo: `LG_XXX_XX_CLFLINE`
- B2B hedefi: `ledger_entries`
- Ana alanlar:
  - `LOGICALREF` -> hareket dis referansi
  - `CLIENTREF` -> `CLCARD.LOGICALREF`
  - `DATE_` -> hareket tarihi
  - `TRANNO` / `DOCODE` -> belge veya fis no
  - `LINEEXP` -> aciklama
  - `AMOUNT` -> tutar
  - `SIGN` -> `0 = borc`, `1 = alacak`
  - `CANCELLED` -> iptal kayitlar okunmaz
- Bridge: `tools/logo-sync/logo-ledger-sync.mjs`

### Siparis / Fatura / Sevkiyat Referanslari

- Siparis baslik: `LG_XXX_XX_ORFICHE`
- Siparis satir: `LG_XXX_XX_ORFLINE`
- Fatura baslik: `LG_XXX_XX_INVOICE`
- Irsaliye/stok fis baslik: `LG_XXX_XX_STFICHE`
- Stok/fatura/irsaliye satir: `LG_XXX_XX_STLINE`

Bu tablolar raporlama ve ileri faz siparis/fatura yazma isleri icin referans
alindi. Dogrudan SQL insert ile yazma onerilmez.

## Yazma Yonleri

Logo'ya yazilacak islemlerde mevcut B2B mimarisi once kendi veritabanina kayit
atar, sonra Logo icin disa giden event veya pending kayit uretir.

- Cari create/update:
  - Laravel event: `logo.customer.create`, `logo.customer.update`
  - Eski bridge pending: `/api/integrations/logo/customers/pending`
  - Ack: `/api/integrations/logo/customers/ack`
- Tahsilat create:
  - Laravel event: `logo.collection.create`
  - Eski bridge pending: `/api/integrations/logo/collections/pending`
  - Ack: `/api/integrations/logo/collections/ack`
- POS teslimat / satis:
  - Pending endpoint: `/api/integrations/logo/pos-sales/pending`
  - Ack: `/api/integrations/logo/pos-sales/ack`
- POS masraf:
  - Pending endpoint: `/api/integrations/logo/pos-expenses/pending`
  - Ack: `/api/integrations/logo/pos-expenses/ack`
- Siparis:
  - Pending endpoint: `/api/integrations/logo/orders/pending`
  - Ack: `/api/integrations/logo/orders/ack`
- Sevkiyat:
  - Pending endpoint: `/api/integrations/logo/shipments/pending`
  - Ack: `/api/integrations/logo/shipments/ack`
- Iade:
  - Pending endpoint: `/api/integrations/logo/returns/pending`
  - Ack: `/api/integrations/logo/returns/ack`

Go Wings tarafinda yazma icin tercih edilen yol SQL bridge'in Logo tarafinda
yetkili ekipce hazirlanmis stored procedure'leri cagirmasidir. B2B kodu Logo
tablolarina dogrudan insert/update yapmaz; bu, Logo'nun numarator, muhasebe,
stok ve fis baglantilarini bozabilir.

Bridge procedure contract ve idempotency log sablonu:
`tools/logo-sync/sql/powersa-b2b-procedure-contracts.sql`
