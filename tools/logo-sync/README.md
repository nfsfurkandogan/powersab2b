# Logo Sync Bridge

Bu klasor, Logo SQL Server'dan veri okuyup Powersa B2B entegrasyon endpoint'ine batch gonderen bridge scriptlerini icerir.

## Hedef Ortam
- Logo Go Wings / Logo 3.x SQL Server
- Test firma: `002` (egitim)
- Test donem: `01`
- Varsayilan cari tablo: `dbo.LG_002_CLCARD`
- Varsayilan urun tablo: `dbo.LG_002_ITEMS`
- Varsayilan cari hareket tablo: `dbo.LG_002_01_CLFLINE`

## Logo Tablo Adi Kurali

Masaustundeki `Logo-Veritabani-Dokumani.doc` dokumanina gore Logo tablolarinda
firma ve donem prefiksi kullanilir:

- Firma genel kartlari: `LG_XXX_*`
- Donem hareket tablolari: `LG_XXX_XX_*`
- Cari kart: `LG_XXX_CLCARD`
- Urun/malzeme kart: `LG_XXX_ITEMS`
- Cari hareket: `LG_XXX_XX_CLFLINE`
- Siparis baslik/satir: `LG_XXX_XX_ORFICHE`, `LG_XXX_XX_ORFLINE`
- Fatura/stok hareketleri: `LG_XXX_XX_INVOICE`, `LG_XXX_XX_STFICHE`, `LG_XXX_XX_STLINE`
- Tahsilat/odeme hareketleri: `LG_XXX_XX_PAYTRANS`, kasa hareketleri: `LG_XXX_XX_KSLINES`

`.env` icinde sadece dogru firma/donem icin `LOGO_FIRM_NO=002` ve
`LOGO_PERIOD_NO=01` benzeri degerleri vermek yeterlidir. Canli firma 003 ise
`LOGO_FIRM_NO=003` kullanilmalidir.
`LOGO_CUSTOMER_TABLE`, `LOGO_PRODUCT_TABLE` veya `LOGO_LEDGER_TABLE` bos kalirsa
scriptler tablo adlarini otomatik turetir. Karsidaki kurulumda ozel view varsa bu
alanlar yine elle ezilebilir.

Tam B2B tablo eslesmesi icin:
`docs/logo_b2b_full_integration_matrix.md`

## Ilk Kullanim
1. `.env.example` dosyasini `.env` olarak kopyalayin.
2. SQL ve Powersa bilgilerini doldurun.
3. Bagimliliklari kurun:

```bash
npm install
```

4. Cari senkronunu calistirin:

```bash
npm run sync:customers
```

5. B2B'de olusan yeni carileri Logo'ya aktarmak icin export adimini calistirin:

```bash
npm run sync:customers-export
```

6. Urun senkronunu calistirin:

```bash
npm run sync:products
```

7. Cari hareket senkronunu calistirin:

```bash
npm run sync:ledger
```

8. Bekleyen tahsilatlari Logo'ya aktarip ack gonderin:

```bash
npm run sync:collections
```

Tum adimlari sirayla calistirmak icin:

```bash
npm run sync:all
```

Windows Task Scheduler icin hazir calistirici:

```bat
run-sync-customers.cmd
```

```bat
run-sync-customers-export.cmd
run-sync-products.cmd
run-sync-ledger.cmd
run-sync-collections.cmd
run-sync-pos-sales.cmd
run-sync-pos-expenses.cmd
run-sync-documents-export.cmd
```

## Urun Sync Icin Dayanikli Calisma Ayarlari

Urun senkronu 502/timeout/network hatalarinda otomatik retry yapar, başarılı batchleri
checkpoint dosyasına yazar ve hatalı batchleri loglara ekler. Aynı script tekrar
çalıştırıldığında `SYNC_RESUME=true` ise kaldığı batch'ten devam eder.

- `SYNC_RETRY_MAX`: Yeniden deneme sayısı (varsayılan `3`)
- `SYNC_RETRY_BASE_DELAY_MS`: Başlangıç geri çekilme süresi, üstel olarak artar (varsayılan `3000`)
- `SYNC_RESUME`: Başarılı son batch sonrası kaldığı yerden başlatma (varsayılan `true`)
- `SYNC_STATE_FILE`: Checkpoint dosyası (varsayılan `.sync-state/products-sync-state.json`)
- `SYNC_FAILED_FILE`: Başarısız batch raporu (varsayılan `.sync-state/products-sync-failed.jsonl`)
- `SYNC_LOG_DIR`: Günlük dosyası dizini (varsayılan `logs`)
- `SYNC_DISABLE_LOCK`: Çift çalışmayı engelleyen lock'u devre dışı bırakır (`true`/`false`)
- `SYNC_CONTINUE_ON_ERROR`: Retry sonrası hatalı batch'leri atlayarak devam eder (`true`) veya durur (`false`)

Örnek PowerShell kullanım:

```powershell
$env:SYNC_PRODUCTS_IMAGES_ONLY="true"
$env:SYNC_RESUME="true"
$env:SYNC_BATCH_SIZE="10"
npm run sync:products
```

CMD örnekleri:

```bat
run-sync-product-stocks-fast.cmd
run-sync-product-images.cmd
run-sync-products.cmd
```

Hizli stok otomasyonu icin yeni `run-sync-product-stocks-fast.cmd` wrapper'i
kullanilir. Bu mod full stock repair gibi tum urunleri taramaz; son hareket
goren urunleri `STLINE`/`STFICHE` uzerinden lookback penceresinde bulur ve
stok summary/view tablosundan minimum `stock_only` payload gonderir. Varsayilan
lookback 10 dakikadir ve movement fallback kapali oldugu icin summary satiri
olmayan urunler skip edilir.

```powershell
cd C:\PowersaB2B\tools\logo-sync
.\run-sync-product-stocks-fast.cmd
```

Fast stock env ayarlari:

- `SYNC_PRODUCTS_STOCK_FAST`: Hizli stok modunu acar.
- `SYNC_PRODUCTS_STOCK_INCREMENTAL`: STLINE/STFICHE uzerinden degisen urunleri secer.
- `SYNC_PRODUCTS_STOCK_LOOKBACK_MINUTES`: Geriye donuk hareket penceresi.
- `SYNC_PRODUCTS_STOCK_SKIP_MOVEMENT_FALLBACK`: Summary yoksa agir movement fallback'e gitmez.
- `SYNC_PRODUCTS_STOCK_REQUIRE_SUMMARY_ROW`: Summary stok satiri yoksa kaydi gondermez.
- `SYNC_PRODUCTS_STOCK_STATE_FILE`: Fast stock run state dosyasi.
- `SYNC_PRODUCTS_STOCK_INCLUDE_PRICE`: Fast stock payload'ina fiyat ekleme kontrolu.

Fast catalog env ayarlari:

- `SYNC_PRODUCTS_CATALOG_INCREMENTAL`: Urun kartlarini tam tarama yerine hizli secimle gonderir.
- `SYNC_PRODUCTS_CATALOG_LOOKBACK_MINUTES`: Degisen/yeni urun kartlari icin geriye donuk pencere.
- `SYNC_PRODUCTS_CATALOG_RECENT_LIMIT`: En yeni LOGICALREF araligindan her kosuda kontrol edilecek urun sayisi.
- `SYNC_PRODUCTS_CATALOG_ROLLING_LIMIT`: Eski LOGICALREF araliklarini parca parca gezen ek pencere. `CS0040` gibi eski kartta yapilan degisikliklerin gece full sync beklemeden yakalanmasi icin kullanilir.
- `SYNC_PRODUCTS_CATALOG_STATE_FILE`: Rolling katalog cursor state dosyasi.

Image-only sync, Logo GO Wings tarafinda firma genel dokuman tablosundan resim
okuyabilir. Canli firma 003 icin bulunan ornek mapping:

```env
LOGO_PRODUCT_IMAGE_TABLE=dbo.LG_003_FIRMDOC
LOGO_PRODUCT_IMAGE_REF_COLUMN=INFOREF
LOGO_PRODUCT_IMAGE_DATA_COLUMN=LDATA
LOGO_PRODUCT_IMAGE_ORDER_COLUMN=LREF
```

Buyuk Logo resimleri API'ye ham 6-10 MB blob olarak gonderilmez. Image-only
wrapper varsayilan olarak resmi client-side optimize eder, batch'i 1 tutar ve
ayri state/failed dosyasi kullanir:

```env
SYNC_PRODUCT_IMAGE_OPTIMIZE=true
SYNC_PRODUCT_IMAGE_MAX_WIDTH=1200
SYNC_PRODUCT_IMAGE_JPEG_QUALITY=80
SYNC_PRODUCT_IMAGE_TARGET_MAX_BYTES=1500000
SYNC_PRODUCT_IMAGE_OUTPUT_FORMAT=jpeg
SYNC_PRODUCT_IMAGE_ALLOW_ORIGINAL_IF_SMALL=true
SYNC_PRODUCT_IMAGE_ORIGINAL_MAX_BYTES=1500000
SYNC_BATCH_SIZE=1
```

Optimize icin `sharp` kullanilir. Windows Logo sunucusunda bu paket yoksa:

```powershell
npm install
```

Image-only test komutu:

```powershell
cd C:\PowersaB2B\tools\logo-sync
.\run-sync-product-images.cmd
```

Eger optimize edilmis tekli payload'a ragmen 413 alinirsa Nginx/PHP/Laravel
request limitleri gecici olarak 32M veya 64M seviyesine alinabilir. Yine de
kalici tercih optimize edilmis gorsel veya dosya/storage/CDN mimarisidir.

Saglik kontrolu:

```bat
run-sync-doctor.cmd
```

Windows SQL makinede ilk kurulum:

```powershell
powershell -ExecutionPolicy Bypass -File .\bootstrap-logo-sync.ps1
```

`bootstrap-logo-sync.ps1` mevcut `.env` dosyasini ezmez. `.env.example`
icindeki yeni anahtarlardan eksik olanlari dosyanin sonuna ekler ve mevcut
SQL/API degerlerini korur.

Task Scheduler kaydi:

```powershell
powershell -ExecutionPolicy Bypass -File .\register-logo-sync-task.ps1 -Mode all -IntervalMinutes 5
```

## Uretim Otomasyon Calisma Plani

Uretimde manuel ve Task Scheduler calistirmalari sadece bu klasorden
yapilmalidir:

```bat
cd /d C:\PowersaB2B\tools\logo-sync
```

Root klasorden `npm run ...` calistirmayin. Eski root log/lock/state dosyalari
silmeden yerinde birakilabilir; aktif calisma kaynagi bu klasordeki wrapper
dosyalaridir.

Ilk manuel test sirasi:

```bat
run-sync-doctor.cmd
run-sync-product-stocks.cmd
run-sync-product-images.cmd
run-sync-products.cmd
run-sync-all.cmd
```

`run-sync-products.cmd` ve `run-sync-all.cmd` sadece dusuk trafik veya gece
penceresinde denenmelidir. Gorsel sync ve full product sync ayni anda
calistirilmamalidir.

Gunduz sik calisacak isler:

```bat
run-sync-product-stocks-fast.cmd
run-sync-customers.cmd
run-sync-customers-export.cmd
run-sync-ledger.cmd
run-sync-collections.cmd
run-sync-pos-sales.cmd
run-sync-pos-expenses.cmd
run-sync-documents-export.cmd
```

Gece calisacak agir isler:

```bat
run-sync-product-stocks.cmd
run-sync-products.cmd
run-sync-product-images.cmd
```

`sync:all` gunduz rutin otomasyona alinmamalidir; bakim veya kontrollu gece
testi olarak kalmalidir.

Uretim Task Scheduler gorevlerini tek seferde kaydetmek icin:

```powershell
powershell -ExecutionPolicy Bypass -File .\install-production-tasks.ps1
```

Bu script gorevleri `MultipleInstances=IgnoreNew` ayariyla kaydeder ve calisma
klasorunu bu dizin yapar. Varsayilan plan:

- `Powersa Logo Product Stocks`: 5 dakikada bir
- `Powersa Logo Customers Sync`: 1 dakikada bir
- `Powersa Logo Customers Export`: 1 dakikada bir
- `Powersa Logo Ledger Sync`: 5 dakikada bir
- `Powersa Logo Collections Export`: 1 dakikada bir
- `Powersa Logo POS Sales Export`: 1 dakikada bir
- `Powersa Logo POS Expenses Export`: 1 dakikada bir
- `Powersa Logo Documents Export`: 1 dakikada bir
- `Powersa Logo Products Full Nightly`: her gece 01:00
- `Powersa Logo Product Images Nightly`: her gece 02:30

Gece product/gorsel gorevlerini kaydetmeden sadece hafif gunduz islerini kurmak
icin:

```powershell
powershell -ExecutionPolicy Bypass -File .\install-production-tasks.ps1 -SkipNightlyProducts -SkipNightlyImages
```

Daemon modu uzun vadede hizli isler icin kullanilabilir, ancak ilk uretim
duzeninde ayri Task Scheduler gorevleri daha okunabilir ve daha kontrolludur.
Full urun ve gorsel sync daemon icine alinmamalidir.

Log kontrol sirasi:

```text
sync-products.log
sync-product-images.log
sync-customers.log
sync-customers-export.log
sync-ledger.log
sync-collections.log
sync-pos-sales.log
sync-pos-expenses.log
sync-documents-export.log
sync-all.log
sync-daemon.log
sync-daemon-status.json
.sync-state\products-sync-state.json
.sync-state\products-sync-failed.jsonl
```

Saniyelik entegrasyon icin onerilen yeni calisma sekli daemon modudur:

```powershell
npm run sync:daemon
```

Logo yazma bloklarini uygulamadan once yalnizca okuma yapan preflight raporunu
alin:

```powershell
npm run write:preflight
npm run write:queue-status
```

Bu komut siparis, POS, sevkiyat, kasa ve cari hareket tablolarinin kolonlarini,
son kayit orneklerini, export log durumunu ve `PowersaB2B_Export*` procedure
imzalarini raporlar. Logo verisine yazmaz; SQL write block uygulanmadan hemen
once Windows sunucuda `run-write-preflight.cmd` ile calistirilmalidir.
`write:queue-status` ise B2B pending endpointlerini okur; bekleyen kayitlarda
cari ref, urun ref, birim ref, kasa ve depo gibi zorunlu alanlar eksik mi diye
raporlar ve Logo SQL'e yazmaz.

Windows acilisinda surekli calismasi icin:

```powershell
powershell -ExecutionPolicy Bypass -File .\install-sync-daemon-task.ps1
```

Bu komut task'i kaydeder ve hemen baslatir. Sadece kayit yapip baslatmamak
icin `-NoStart` eklenebilir.

Daemon varsayilan olarak 3 saniyede bir cari, cari hareket, yeni cari export,
tahsilat, POS satis/gider ve siparis/sevkiyat/iade export adimlarini calistirir.
Urun tam senkronu daha agir oldugu icin varsayilan olarak 5 dakikalik yavas
dongudedur. Bu davranis `.env` icindeki `SYNC_DAEMON_*` degerleriyle
degistirilebilir.

Logo'da bir kaydin saniyeler icinde B2B'ye dusmesi icin daemon'un SQL makinede
calisiyor olmasi gerekir. B2B'de olusan kayitlarin Logo'ya yazilmasi icin de
ilgili `LOGO_*_EXPORT_PROCEDURE` degerleri dolu olmali ve bu procedure'lerin
Logo yazma bloklari uygulanmis olmalidir.

## Beklenen Akis
- Logo Objects kullanilmaz. Yazma lisansi olan kurulumda B2B -> Logo yazma
  adimlari bu bridge'in pending endpoint'lerden aldigi kayitlari Logo SQL
  makinesindeki onayli stored procedure'lere vermesiyle ilerler.
- Kaynak tablo: `dbo.LG_002_CLCARD`
- Mevcut test ekranina gore firma tablo prefiksi: `LG_002_*`
- Script `CARDTYPE = 3` kayitlarini okuyup `POWERSA_SYNC_URL` adresine yollar.
- Powersa API gelen kayitlari `insert/update` eder.
- Ilk calistirmada tam senkron yapar.
- Sonraki calismalarda `CAPIBLOCK_MODIFIEDDATE` + `LOGICALREF` uzerinden sadece degisen carileri gonderir.
- `SYNC_CUSTOMERS_LOOKBACK_SECONDS` varsayilan `300` saniyedir; son cursor etrafinda kucuk bir pencereyi tekrar okuyarak Logo zaman hassasiyeti veya ayni saniye icindeki gec gelen cari guncellemelerinin kacirilmasini engeller.
- Urun senkronu durum bilgisi `SYNC_STATE_FILE` içinde tutulur (`.sync-state/products-sync-state.json`).
- Script tablo kolonlarini calisma aninda okur; bu sayede farkli Logo kurulumlarinda bulunan ek cari alanlari da `meta.integrations.logo.payload` altinda saklanir.
- Cari kartinin ham satiri da `meta.integrations.logo.payload.raw` altina yazilir.
- B2B'de acilan veya duzenlenen yerel cariler `GET /api/integrations/logo/customers/pending` kuyruguna duser. `logo-customers-export.mjs` bu kuyruktaki kayitlari `LOGO_CUSTOMER_EXPORT_PROCEDURE` ile Logo `LG_003_CLCARD` / Cari Hesap Karti ekranina yazar ve `POST /api/integrations/logo/customers/ack` ile sonucu isler. Kurulum SQL dosyasi: `sql/powersa-b2b-customer-write-procedure.sql`.
- Urun senkronu varsayilan olarak tam tarama yapar; stok ve fiyat degisimi urun kartinda her zaman `CAPIBLOCK_MODIFIEDDATE` alanini guncellemedigi icin urun tarafinda full sync daha guvenlidir.
- Urun kartlari icin varsayilan kaynak tablo `dbo.LG_002_ITEMS` kabul edilir.
- Stok ve fiyat tablolarinin kolonlari Logo kurulumuna gore degisebildigi icin `LOGO_STOCK_TABLE` ve `LOGO_PRICE_TABLE` opsiyoneldir. Script once bu alanlari kullanir; bos ise `LG_XXX_ITEMS` tablosundan yaygin Logo tablo adlarini (`GNTOTST`, `STINVTOT`, `VRNTINVTOT`, `PRCLIST`) otomatik denemeye calisir. Destekli kolonlari bulursa `available_total`, `reserved_total` ve `list_price` alanlarini da gonderir.
- Logo dokumanina gore standart urun resmi/dokumani `LG_XXX_XX_PERDOC` icinde tutulur. Bu tabloda `INFOREF` urun karti `LG_XXX_ITEMS.LOGICALREF` degerine, `LDATA` ise SQL Server `image` binary alana karsilik gelir. `LOGO_PRODUCT_IMAGE_TABLE` bos ise script once `LOGO_PRODUCT_DOCUMENT_TABLE`, sonra firma/donem kodundan `PERDOC`, `FIRMDOC` ve `FOLDER` adaylarini otomatik dener.
- Urun resmi ayri bir custom tabloda veya dosya yolunda tutuluyorsa `LOGO_PRODUCT_IMAGE_TABLE` ile baglanabilir. `LOGO_PRODUCT_IMAGE_REF_COLUMN` urun bagini, `LOGO_PRODUCT_IMAGE_DATA_COLUMN` blob/base64 kolonu, `LOGO_PRODUCT_IMAGE_PATH_COLUMN` ise dosya yolu veya URL kolonunu tanimlar. Logo standart tablo icin elle vermek gerekirse `LOGO_PRODUCT_IMAGE_REF_COLUMN=INFOREF` ve `LOGO_PRODUCT_IMAGE_DATA_COLUMN=LDATA` kullanin. `LOGO_PRODUCT_IMAGE_ROOT` verilirse goreli dosya yollarini oradan okuyup resmi base64 olarak payload'a ekler.
- Ayri tablo bulunamiyorsa `LOGO_PRODUCT_IMAGE_MAP_FILE` ile bir CSV/TSV esleme dosyasi verilebilir. Ornek: `sku,image_path` ve `CS 0040,C:\LOGO\Resimler\IMG_9382.jpg`. Dosya yolu tam path, map dosyasina gore goreli path, URL veya `data:image/...` olabilir.
- Ayri resim tablosu olmayan kurulumlarda `LOGO_PRODUCT_IMAGE_FALLBACK_DIR` tanimlanabilir. Script bu klasoru recursive tarar, dosya adini urun kodu/SKU ile eslestirir (`CS 0040.jpg`, `CS0040.png`, `CS-0040.jpeg` gibi) ve buldugu resmi payload'a base64 olarak ekler.
- `upsert-product-image-map.ps1` ile map dosyasina tek komutla kayit eklenebilir veya guncellenebilir. Ornek: `powershell -ExecutionPolicy Bypass -File .\upsert-product-image-map.ps1 -Sku "CS 0040" -ImagePath "C:\LOGO\Resimler\IMG_9382.jpg" -MapFile "C:\LOGO\product-image-map.csv"`.
- OEM ve rakip kodlar ayri child tablolarda duruyorsa `LOGO_OEM_TABLE` ve `LOGO_COMPETITOR_TABLE` degerleri de tanimlanabilir. Script bu tablolardan `CARDREF/ITEMREF/PARLOGREF` baglantisini ve uygun kod kolonlarini bulursa `code_aliases` olarak B2B'ye yollar.
- Custom Logo tablolarinda kodlar genis kolon yapisinda tutuluyorsa da desteklenir. Ornek: `dbo.LG_XT1001_002` tablosunda `PARLOGREF` urune baglanip `RKP1..RKP7` rakip kodlari ve `OEM1..OEM12` OEM kodlari olarak okunabilir.
- Muadil urunler standart `LG_XXX_ITEMSUBS` tablosundan otomatik bulunur; gerekirse `LOGO_PRODUCT_SUBSTITUTE_TABLE` ile elle verilebilir. `MAINITEMREF -> SUBITEMREF` kayitlari B2B'ye `equivalent` kod alias'i olarak gider.
- Stok okumasinda ambar kolonu (`INVENNO`) varsa payload `meta.logo_stock.warehouses` altinda ambar bazli `onhand/reserved/available` degerlerini de tasir. Fiyat okumasinda `PRCLIST` icindeki `UOMREF`, `INCVAT`, tarih, oncelik ve musteri filtresi gibi kolonlar `meta.logo_price` altinda saklanir.
- Ambar adlari Logo bilgi tablosundan bos gelirse `LOGO_WAREHOUSE_NAME_MAP`
  ile kod-ad eslemesi verilebilir. Format: `0=ERZURUM POINT;1=ERZURUM DEPO;2=TRABZON DEPO`.
- Logo ambar numarasi ile raf Excel kolonlari farkliysa `LOGO_WAREHOUSE_RAF_KEY_MAP`
  kullanilir. Ornek: `0=25;1=61;2=55;3=250;4=995`.
- Urun ozel kodlari payload'a tasinir: `SPECODE -> kod1`, `SPECODE2 -> kod2`,
  `SPECODE3 -> kod3`, `SPECODE4 -> specode4`, `SPECODE5 -> specode5`. Marka
  icin oncelik `SPECODE5` degerindedir; bu alan doluysa B2B'de marka olarak
  upsert edilir. Raf adresi icin `RAF`, `RAF_ADRESI`, `RAFADRESI`,
  `SHELF_ADDRESS`, `LOCATION` ve ambar koduna bagli `RAF01`, `RAF_01`,
  `RAFADRESI01` gibi kolon adlari desteklenir.
- Logo'daki `RAF BILGILERI` tab'i ayri custom tabloda tutuluyorsa
  `LOGO_PRODUCT_RAF_TABLE` doldurulmalidir. Bu tabloda `PARLOGREF`, `ITEMREF`,
  `CARDREF`, `STOCKREF`, `PRODUCTREF` veya `INFOREF` urun kartina baglanir;
  `RAF25`, `RAF61`, `RAF55`, `RAF250`, `RAF995` gibi ambar bazli raf kolonlari
  ham urun payload'ina eklenir ve B2B arama tablosunda ilgili ambar stokunun
  altinda gosterilir.
- Raf kaynagi Excel'den CSV/TSV olarak alinacaksa `LOGO_PRODUCT_RAF_MAP_FILE`
  kullanilabilir. Dosyada `Urun_Kodu`/`sku`/`code` kolonu ve ambar kolonlari
  (`25`, `61`, `55`, `250`, `995` gibi) bulunabilir. `BOS-0450905930` gibi
  prefix'li kodlarda prefix sonrasi kod da eslesme anahtari kabul edilir.
- Urun sync payload'i `brand/category/product/stock/price` bilgisini tek endpoint'e yollar; API bu verileri kendi tarafinda sirayla upsert eder.
- Cari hareket sync'i varsayilan olarak `LG_XXX_XX_CLFLINE` tablosunu okur.
  Dokumandaki `SIGN` kuralina gore `0=borc`, `1=alacak` seklinde map edilir.
  Kurulumda daha temiz bir SQL view hazirlanirsa `LOGO_LEDGER_TABLE` bu view'a
  isaret edebilir; view en az su alanlari donmelidir: `external_ref`,
  `customer_external_ref` veya `customer_code`, `date`, `debit`/`credit`.
- Tahsilat export scripti B2B'den `pending` kayitlari ceker, `LOGO_COLLECTION_EXPORT_PROCEDURE` ile Logo tarafina yazar ve sonra `ack` endpoint'ine sonuc dondurur.
- Cari export procedure'u su inputlari kabul etmelidir: `@CustomerCode`, `@Name`, `@ContactName`, `@Email`, `@Phone`, `@City`, `@District`, `@TaxOffice`, `@TaxNumber`, `@CreditLimit`, `@IsActive`, `@Address`, `@Iban`, `@ExportKey`, `@PayloadJson`, `@ExternalRef OUTPUT`.
- Tahsilat export procedure'u su inputlari kabul etmelidir: `@CustomerExternalRef`, `@CustomerCode`, `@CollectionDate`, `@Method`, `@Amount`, `@Currency`, `@ReferenceNo`, `@Note`, `@ExportKey`, `@PayloadJson`, `@ExternalRef OUTPUT`.
- Tahsilat kaydinda kasa bilgisi B2B'den `cashbox_id`, `cashbox_code`, `cashbox_name` olarak gelir. Script bu bilgiyi her zaman `@PayloadJson` icine koyar; procedure tarafinda `@CashboxId`, `@CashboxCode` veya `@CashboxName` parametreleri varsa bunlari otomatik algilayip direkt parametre olarak da gonderir. Kayitta kasa yoksa opsiyonel `LOGO_COLLECTION_DEFAULT_CASHBOX_ID`, `LOGO_COLLECTION_DEFAULT_CASHBOX_CODE`, `LOGO_COLLECTION_DEFAULT_CASHBOX_NAME` degerleri fallback olarak kullanilir.
- `ExportKey` B2B tarafinda `B2B-COL-{id}` formatinda gelir ve Logo tarafinda idempotency anahtari olarak kullanilmalidir.
- Musteri export tarafinda `ExportKey` degeri `B2B-CUST-{id}` formatindadir ve ayni sekilde idempotency icin kullanilmalidir.
- Siparis, sevkiyat, mal kabul, iade ve hasar/ariza fire cikislari `logo-documents-export.mjs` tarafindan calistirilir. Sirayla `GET /orders/pending`, `GET /shipments/pending`, `GET /purchase-receipts/pending`, `GET /returns/pending`, `GET /return-scraps/pending` endpointlerinden kayit cekip `LOGO_ORDER_EXPORT_PROCEDURE`, `LOGO_SHIPMENT_EXPORT_PROCEDURE`, `LOGO_PURCHASE_RECEIPT_EXPORT_PROCEDURE`, `LOGO_RETURN_EXPORT_PROCEDURE`, `LOGO_RETURN_SCRAP_EXPORT_PROCEDURE` sakli yordamlarina verir ve ilgili `ack` endpoint'lerine sonucu dondurur. `return`, `damaged` ve `faulty` taleplerinin ucu de `returns` endpointinden `(03) Toptan Satis Iade Faturasi` olarak aktarilir; `damaged` ve `faulty` talepleri buna ek olarak `return-scraps` endpointinden fire fisi olarak aktarilir. Siparis, sevkiyat ve POS satis uygulama dosyasi `sql/powersa-b2b-order-shipment-pos-write-procedure.sql` dosyasidir.
- POS masraf export scripti B2B'den `GET /pos-expenses/pending` kayitlarini ceker, `LOGO_POS_EXPENSE_EXPORT_PROCEDURE` ile Logo tarafina kasa cikisi olarak yazar ve `POST /pos-expenses/ack` endpoint'ine sonucu dondurur. Uygulama dosyasi `sql/powersa-b2b-pos-expense-write-procedure.sql`; Logo tarafinda `KSLINES` uzerinden `TRCODE = 12` yazar.
- POS masraf import scripti `logo-pos-expenses-sync.mjs`, Logo `KSLINES` uzerinden `TRCODE = 12` Batum kasa cikislarini okur ve `POST /pos-expenses/sync` endpoint'ine yollar. Tek basina calistirmak icin `run-sync-pos-expenses-from-logo.cmd` kullanilir. Varsayilan filtre kasa adinda `BATUM` arar; daha net filtre icin `.env` icinde `POWERSA_POS_EXPENSES_CASHBOX_CODE=100.01.002` veya `POWERSA_POS_EXPENSES_CASHBOX_NAME=...` verilebilir. Bu kayitlar B2B'deki acik POS oturumuna baglanir; acik oturum yoksa import bilerek durur.
- Siparis procedure'u icin standart parametreler: `@CustomerExternalRef`, `@CustomerCode`, `@OrderDate`, `@OrderNo`, `@Currency`, `@Subtotal`, `@DiscountTotal`, `@VatTotal`, `@GrandTotal`, `@ExportKey`, `@PayloadJson`, `@ExternalRef OUTPUT`.
- Sevkiyat procedure'u icin standart parametreler: `@CustomerExternalRef`, `@CustomerCode`, `@ShipmentDate`, `@ShipmentNo`, `@OrderNo`, `@WarehouseCode`, `@Subtotal`, `@VatTotal`, `@GrandTotal`, `@ExportKey`, `@PayloadJson`, `@ExternalRef OUTPUT`.
- Mal kabul procedure'u icin standart parametreler: `@ReceiptDate`, `@ReceiptNo`, `@DocumentNo`, `@SupplierName`, `@WarehouseCode`, `@WarehouseName`, `@AcceptedQuantityTotal`, `@ExpectedQuantityTotal`, `@ExportKey`, `@PayloadJson`, `@ExternalRef OUTPUT`.
- POS masraf procedure'u icin standart parametreler: `@ExpenseDate`, `@Category`, `@Amount`, `@Currency`, `@Note`, `@CashboxCode`, `@ExportKey`, `@PayloadJson`, `@ExternalRef OUTPUT`.
- Iade procedure'u icin standart parametreler: `@CustomerExternalRef`, `@CustomerCode`, `@ReturnDate`, `@RequestNo`, `@ReturnType`, `@ReasonCode`, `@Amount`, `@Currency`, `@ExportKey`, `@PayloadJson`, `@ExternalRef OUTPUT`. Uygulama dosyasi `sql/powersa-b2b-return-write-procedure.sql`; Logo tarafinda `INVOICE/STFICHE/STLINE` uzerinden `TRCODE = 3` ve `GRPCODE = 2` ile `(03) Toptan Satis Iade Faturasi` yazar.
- Fire fisi procedure'u icin standart parametreler: `@CustomerExternalRef`, `@CustomerCode`, `@ScrapDate`, `@DocumentNo`, `@RequestNo`, `@ReturnType`, `@ReasonCode`, `@Amount`, `@Currency`, `@ExportKey`, `@PayloadJson`, `@ExternalRef OUTPUT`. B2B `@DocumentNo` alanina cari kodunu gonderir; Logo tarafinda `STFICHE.DOCODE` bu degerle dolar.
- Dokuman export scripti procedure parametrelerini okuyabiliyorsa sadece procedure'de bulunan parametreleri gonderir; detayli Logo kolonlari her durumda `@PayloadJson` icinde vardir. Bu JSON, Logo tarafinda `ORFICHE/ORFLINE`, `STFICHE/STLINE/INVOICE`, `PAYTRANS` gibi tablolari doldurmak icin kaynak kabul edilmelidir.
- Procedure imzalari ve guvenli idempotency log sablonu `sql/powersa-b2b-procedure-contracts.sql` icindedir. Bu dosya as-is Logo yazmaz; yazma blogu uygulanmadan calistirilirse bilerek hata doner. Logo server'a erisildiginde bu contract imzalari korunarak Logo yazma bloglari doldurulmalidir.

## Kurulum Notu
- Bu script en saglikli sekilde Logo SQL'e yakin bir makinede calismalidir.
- `LOGO_SQL_SERVER` olarak Windows sunucu adi kullaniliyorsa, bu ad ayni agdan cozulmelidir. Cozulmuyorsa sunucunun lokal IP'sini girin.
- Dis agdan SQL instance erisimi kapaliysa scripti dogrudan Logo sunucusunda veya ayni LAN icindeki bir makinede calistirin.
- Eski kurulumda Windows Task Scheduler veya cron ile 5 dakikada bir cagirabilirsiniz.
- Saniyelik canli akis icin `install-sync-daemon-task.ps1` ile daemon task'i kullanin.
- Her 5 dakikada tam tablo cekmek yerine delta senkron kullanildigi icin hem SQL yuku hem de bizim veritabani yazma yuku dusuk kalir.
- Gerekirse tek seferlik tam yeniden yukleme icin `SYNC_FORCE_FULL=true` kullanin; calisma bitince tekrar `false` yapin.
- `POWERSA_PRODUCTS_SYNC_URL` ve `POWERSA_PRODUCTS_SYNC_KEY` bos birakilirsa sirasiyla `POWERSA_SYNC_URL` ve `POWERSA_SYNC_KEY` fallback olarak kullanilir.
- `POWERSA_CUSTOMERS_PENDING_URL`, `POWERSA_CUSTOMERS_ACK_URL`, `POWERSA_LEDGER_SYNC_URL`, `POWERSA_COLLECTIONS_PENDING_URL`, `POWERSA_COLLECTIONS_ACK_URL` ve ilgili key alanlari da bos birakilirsa customers sync URL/key uzerinden turetilebilir veya ayni secret kullanilabilir.
- Fiyatlari `base_prices` tablosuna yazarken varsayilan hedef liste `POWERSA_PRICE_LIST_CODE=A` olur.
- `logo-sync-doctor.mjs` DNS, HTTPS endpoint ve SQL baglantisini kontrol eder; B2B icin gerekli cari, urun, stok, fiyat, siparis, fatura, kasa, banka ve tahsilat tablo ailelerini firma/donem numarasindan turetip raporlar. Kritik `CLCARD`, `ITEMS` ve `CLFLINE` tablolarini hata olarak, diger yakin faz tablolarini uyari olarak gosterir. Ayrica yazma tarafinda tanimli cari, tahsilat, POS, siparis, sevkiyat ve iade export procedure'lerinin SQL tarafinda gorunup gorunmedigini ve beklenen parametre imzalarina uyup uymadigini kontrol eder.

## SQL Makinede Onerilen Kurulum
1. `logo-sync` klasorunu SQL makineye kopyalayin.
2. `bootstrap-logo-sync.ps1` calistirin.
3. `.env` icinde SQL server adini cozulmeyen Windows makine adi yerine lokal IP ile degistirin.
4. `run-sync-doctor.cmd` ile kontrol alin.
5. Doktor ciktisina gore `LOGO_STOCK_TABLE`, `LOGO_PRICE_TABLE`, `LOGO_PRODUCT_IMAGE_TABLE`, `LOGO_PRODUCT_RAF_TABLE`, `LOGO_PRODUCT_SUBSTITUTE_TABLE`, `LOGO_OEM_TABLE` ve `LOGO_COMPETITOR_TABLE` degerlerini doldurun. Standart Logo resimleri icin genelde `LOGO_PRODUCT_IMAGE_TABLE` bos kalabilir; script `LG_XXX_XX_PERDOC` tablosunu otomatik bulur.
6. Cari hareket icin normalize view, yerel cariler/tahsilat/POS/siparis/sevkiyat/iade icin ilgili `LOGO_*_EXPORT_PROCEDURE` degerlerini tanimlayin.
7. Elle bir kez `run-sync-customers.cmd`, `run-sync-customers-export.cmd`, `run-sync-products.cmd`, `run-sync-ledger.cmd`, `run-sync-collections.cmd` ve `run-sync-documents-export.cmd` calistirin.
8. Saniyelik akis isteniyorsa once konsolda `npm run sync:daemon` ile loglari izleyin, sonra `install-sync-daemon-task.ps1` ile acilista calisan daemon task'ini kaydedin. Geriye uyum icin `register-logo-sync-task.ps1` ile 5 dakikalik task da kullanilabilir, fakat anlik entegrasyon icin daemon tercih edilmelidir.

## Windows Klasor Temizligi

Canli SQL makinede aktif klasor tek bir `C:\PowersaB2B` olmalidir. Temiz
kurulumda aktif klasorde sunlar kalabilir:

- `.env`, `.sync-state/products-sync-state.json`, `.sync-state/products-sync-failed.jsonl`, `.ledger-sync-state.json`
- `package.json`, `package-lock.json`, `node_modules`
- `logo-*.mjs`, `logo-table-names.mjs`, `run-sync-*.cmd`
- `bootstrap-logo-sync.ps1`, `register-logo-sync-task.ps1`,
  `install-sync-daemon-task.ps1`,
  `upsert-product-image-map.ps1`
- `sql` klasoru, `README.md`, varsa gercek urun resim map dosyasi

Aktif klasorden tasinabilir veya arsivlenebilir dosyalar:

- `_MACOSX`, eski `logo-sync-upgrade`, `_logo-sync-current`,
  `powersa-logo-sync-clean-current-*`, ic ice `PowersaB2B` gibi gecici
  kopyalar
- Eski `.zip` paketleri, `node-lts-x64.msi`, eski `.env.backup-*` dosyalari
- Tek seferlik inceleme dosyalari ve eski loglar. Log tutmak gerekiyorsa aktif
  klasorde sadece son loglar kalsin veya ayri `logs` klasorune tasinsin.

## Onerilen Procedure Adlari

`tools/logo-sync/.env.example` varsayilan olarak procedure adlarini bos birakir. Logo server tarafinda contract dosyasindaki adlar kullanilirsa `.env` icinde su degerler yeterlidir:

```env
LOGO_CUSTOMER_EXPORT_PROCEDURE=dbo.PowersaB2B_ExportCustomer
LOGO_COLLECTION_EXPORT_PROCEDURE=dbo.PowersaB2B_ExportCollection
LOGO_POS_SALE_EXPORT_PROCEDURE=dbo.PowersaB2B_ExportPosSale
LOGO_POS_EXPENSE_EXPORT_PROCEDURE=dbo.PowersaB2B_ExportPosExpense
LOGO_ORDER_EXPORT_PROCEDURE=dbo.PowersaB2B_ExportOrder
LOGO_SHIPMENT_EXPORT_PROCEDURE=dbo.PowersaB2B_ExportShipment
LOGO_RETURN_EXPORT_PROCEDURE=dbo.PowersaB2B_ExportReturn
LOGO_RETURN_SCRAP_EXPORT_PROCEDURE=dbo.PowersaB2B_ExportReturnScrap
```
