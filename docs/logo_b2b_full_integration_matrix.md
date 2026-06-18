# Logo B2B Tam Entegrasyon Matrisi

Kaynak dokuman: `/Users/elifurkan/Desktop/Logo-Veritabani-Dokumani.doc`
Guncel masaustu dosyasi ayrica incelendi: `Logo-Veritabani-Dokumani.doc`,
son kayit tarihi 6 Mayis 2026.

Bu dokuman, Logo Go Wings veritabani dokumanindaki tablo ailelerini Powersa
B2B ekranlari ve mevcut entegrasyon kodlariyla esler. Dokumandaki ana tablo
listesi tamamen tarandi; B2B icin aktif gerekli olanlar, opsiyonel/yakin faz
olanlar ve kapsam disi uretilen moduller ayrildi.

## Tablo Adi Kurali

- Firma kart tablolari: `dbo.LG_XXX_*`
- Donem hareket tablolari: `dbo.LG_XXX_XX_*`
- `XXX`: firma numarasi, ornek `002`
- Ikinci `XX`: donem numarasi, ornek `01`

Bridge tarafinda bu kural `tools/logo-sync/logo-table-names.mjs` ile tek
yerden cozulur. `.env` icinde `LOGO_FIRM_NO=002` ve `LOGO_PERIOD_NO=01`
verildiginde tablo isimleri otomatik `dbo.LG_002_CLCARD`,
`dbo.LG_002_ITEMS`, `dbo.LG_002_01_CLFLINE` gibi turetilir.

## B2B Ekranlari ve Logo Baglantilari

### Kontrol Paneli / Dashboard

- Okuma: `CLCARD`, `CLFLINE`, `CLTOTFIL`, `ORFICHE`, `ORFLINE`, `INVOICE`,
  `STLINE`, `STINVTOT`, `KSLINES`, `PAYTRANS`
- B2B hedefleri: dashboard kartlari, tahsilat trendi, cari bakiye, satis ve
  acik siparis ozetleri.
- Mevcut aktif endpoint: `/api/reports/logo-dashboard`

### Urun Arama ve Kataloglar

- Okuma: `ITEMS`, `PRCLIST`, `STINVTOT`, `STINVENS`, `STLINE`, `ITMUNITA`,
  `UNITSETF`, `UNITSETL`, `ITEMSUBS`, `SPECODES`, `PERDOC`, `FIRMDOC`,
  `FOLDER`
- B2B hedefleri: `products`, `brands`, `categories`, `base_prices`,
  `stock_summary`, `product_code_aliases`, urun resmi/dokumani.
- Mevcut aktif bridge: `tools/logo-sync/logo-products-sync.mjs`
- Mevcut aktif endpoint: `/api/integrations/logo/products/sync`
- Guncel dokuman notu: standart urun resimleri `LG_XXX_XX_PERDOC`
  tablosunda `INFOREF -> LG_XXX_ITEMS.LOGICALREF` ve `LDATA image/binary`
  olarak tutulur. Bridge bu tabloyu firma/donem kodundan otomatik dener,
  binary veriyi base64 olarak B2B urun meta payload'ina ekler.

### Musteriler ve Musteri Secimi

- Okuma: `CLCARD`, `CLINTEL`, `SLSMAN`, `SLSCLREL`, `SPECODES`
- Yazma: yeni cari icin SQL bridge'in cagirdigi onayli stored procedure
  uzerinden `CLCARD`; B2B tarafindan dogrudan SQL insert yok.
- B2B hedefleri: `customers`, cari secimi, plasiyer/satis temsilcisi eslesmesi.
- Mevcut aktif bridge: `tools/logo-sync/logo-customers-sync.mjs`
- Mevcut aktif endpointler:
  - `/api/integrations/logo/customers/sync`
  - `/api/integrations/logo/customers/pending`
  - `/api/integrations/logo/customers/ack`

### Sepet ve Siparis

- Okuma: `ORFICHE`, `ORFLINE`, `STLINE`, `PAYTRANS`
- Yazma: B2B siparisleri Logo tarafinda `ORFICHE` ve `ORFLINE` olusturacak
  SQL bridge/procedure akisi ile yazilmali.
- B2B hedefleri: `carts`, `cart_items`, `orders`, `order_items`.
- Durum: B2B siparis akisi aktif; `/orders/pending` ve `/orders/ack`
  endpointleri ile `logo-documents-export.mjs` bridge akisi hazir.
  Logo server tarafinda `LOGO_ORDER_EXPORT_PROCEDURE` yazma blogu
  doldurulacak.

### Cari Hesap

- Okuma: `CLFLINE`, `CLFICHE`, `CLTOTFIL`, `PAYTRANS`, `KSLINES`, `BNFLINE`,
  `CSTRANS`, `INVOICE`, `STLINE`
- B2B hedefleri: `ledger_entries`, cari hareket listesi, bakiye ve raporlar.
- Mevcut aktif bridge: `tools/logo-sync/logo-ledger-sync.mjs`
- Mevcut aktif endpoint: `/api/integrations/logo/ledger/sync`
- Dokuman kuralina gore `SIGN`: `0 = borc`, `1 = alacak`.

### Tahsilat

- Okuma: `KSCARD`, `KSLINES`, `PAYTRANS`, `BANKACC`, `BNCARD`, `BNFICHE`,
  `BNFLINE`, `CSCARD`, `CSROLL`, `CSTRANS`
- Yazma: tahsilat yontemine gore kasa/banka/cek-senet hareketi Logo
  SQL bridge procedure'u ile olusturulmali. Dogrudan tablo insert yok.
- B2B hedefleri: `collections`, `ledger_entries`, `cashboxes`.
- Mevcut aktif endpointler:
  - `/api/integrations/logo/collections/pending`
  - `/api/integrations/logo/collections/ack`
- Mevcut aktif bridge: `tools/logo-sync/logo-collections-export.mjs`

### Hizli Satis / POS

- Okuma: `ITEMS`, `PRCLIST`, `STINVTOT`, `STLINE`, `KSCARD`
- Yazma: satis icin `INVOICE`, `STFICHE`, `STLINE`, `PAYTRANS`, `KSLINES`;
  masraf icin Logo tarafinda onayli masraf/kasa proseduru.
- B2B hedefleri: `pos_sales`, `pos_sale_items`, `pos_payments`,
  `pos_expenses`, `stock_movements`.
- Mevcut aktif endpointler:
  - `/api/integrations/logo/pos-sales/pending`
  - `/api/integrations/logo/pos-sales/ack`
  - `/api/integrations/logo/pos-expenses/pending`
  - `/api/integrations/logo/pos-expenses/ack`
- Mevcut aktif bridge:
  - `tools/logo-sync/logo-pos-sales-export.mjs`
  - `tools/logo-sync/logo-pos-expenses-export.mjs`

### Depo

- Okuma: `INVDEF`, `LOCATION`, `STINVTOT`, `STINVENS`, `STLINE`, `ORFICHE`,
  `ORFLINE`, `SERILOTN`, `SLTRANS`
- Yazma: sevkiyat/fis hareketleri icin `STFICHE` ve `STLINE` SQL
  bridge/procedure uzerinden.
- B2B hedefleri: `warehouses`, `shipments`, `shipment_items`,
  `shipment_scans`, `stock_movements`.
- Mevcut aktif endpointler:
  - `/api/integrations/logo/shipments/pending`
  - `/api/integrations/logo/shipments/ack`
- Mevcut aktif bridge: `tools/logo-sync/logo-documents-export.mjs`

### Iade / Ariza

- Okuma: `INVOICE`, `STFICHE`, `STLINE`, `CLFLINE`, `PAYTRANS`
- Yazma: iade faturasi veya stok iade fisi SQL bridge/procedure ile.
- B2B hedefi: `return_requests`.
- Mevcut aktif endpointler:
  - `/api/integrations/logo/returns/pending`
  - `/api/integrations/logo/returns/ack`
- Mevcut aktif bridge: `tools/logo-sync/logo-documents-export.mjs`

### Raporlar

- Okuma: yukaridaki aktif kaynaklarin toplamlari; finansal rapor genislerse
  `EMFICHE`, `EMFLINE`, `EMUHTOT`, `EMUHACC`, `ACCCODES`.
- B2B hedefleri: `report_runs`, rapor controllerlari ve dashboard endpointleri.

### Moderator, Kullanici Yetkileri, Login

- Bu alanlar B2B yerel veritabaninda kalir.
- Logo dokumanindaki `EMPLOYEE`, `EMPGROUP`, `L_CAPIUSER` benzeri sistem
  tablolarina baglanmiyoruz; kullanici yetkileri B2B `users.menu_permissions`
  ile yonetiliyor.

## Kodda Aktif Baglanan Ana Tablolar

| B2B alan | Logo tablo | Yon | Kod |
| --- | --- | --- | --- |
| Musteriler | `LG_XXX_CLCARD` | Logo -> B2B | `tools/logo-sync/logo-customers-sync.mjs` |
| Musteri export | `CLCARD` | B2B -> Logo | `tools/logo-sync/logo-customers-export.mjs` |
| Urunler | `LG_XXX_ITEMS` | Logo -> B2B | `tools/logo-sync/logo-products-sync.mjs` |
| Fiyat | `LG_XXX_PRCLIST` | Logo -> B2B | `tools/logo-sync/logo-products-sync.mjs` |
| Stok | `LG_XXX_XX_STINVTOT`, `STLINE` fallback | Logo -> B2B | `tools/logo-sync/logo-products-sync.mjs` |
| Cari hareket | `LG_XXX_XX_CLFLINE` | Logo -> B2B | `tools/logo-sync/logo-ledger-sync.mjs` |
| Tahsilat export | `KSLINES/PAYTRANS/CLFLINE` ailesi | B2B -> Logo | `tools/logo-sync/logo-collections-export.mjs` |
| POS satis export | `INVOICE/STFICHE/STLINE` ailesi | B2B -> Logo | `tools/logo-sync/logo-pos-sales-export.mjs` |
| POS masraf export | `KSLINES` veya onayli masraf proseduru | B2B -> Logo | `tools/logo-sync/logo-pos-expenses-export.mjs` |
| Siparis export | `ORFICHE/ORFLINE` | B2B -> Logo | `tools/logo-sync/logo-documents-export.mjs` |
| Sevkiyat export | `STFICHE/STLINE/INVOICE` | B2B -> Logo | `tools/logo-sync/logo-documents-export.mjs` |
| Iade export | `STFICHE/STLINE/INVOICE` | B2B -> Logo | `tools/logo-sync/logo-documents-export.mjs` |

## Doctor Kapsamina Alinan B2B Core Tablolari

`tools/logo-sync/logo-sync-doctor.mjs` artik asagidaki tablo ailelerini de
firma/donem numarasindan turetip SQL tarafinda arar:

- Cari: `CLCARD`, `CLTOTFIL`, `CLFICHE`, `CLFLINE`
- Urun: `ITEMS`, `ITMUNITA`, `UNITSETF`, `UNITSETL`, `ITEMSUBS`, `PRCLIST`
- Stok/siparis/fatura: `STINVTOT`, `STFICHE`, `STLINE`, `ORFICHE`,
  `ORFLINE`, `INVOICE`, `PAYTRANS`
- Tahsilat/banka/kasa: `KSCARD`, `KSLINES`, `BNCARD`, `BANKACC`, `BNFICHE`,
  `BNFLINE`, `CSCARD`
- Yardimci kartlar: `SLSMAN`, `SLSCLREL`, `SPECODES`, `INVDEF`, `LOCATION`,
  `PERDOC`

Kritik tablo eksikse hata verir: `CLCARD`, `ITEMS`, `CLFLINE`. Digerleri
opsiyonel/yakin faz olarak raporlanir.

## Dokuman Tablo Indeksi ve B2B Karari

### Donem Tablolari

| Tablo | B2B karari |
| --- | --- |
| `LG_XXX_XX_BNFICHE` | Tahsilat/banka opsiyonel |
| `LG_XXX_XX_BNFLINE` | Tahsilat/banka opsiyonel |
| `LG_XXX_XX_BNTOTFIL` | Banka raporu ileri faz |
| `LG_XXX_XX_CLFICHE` | Cari hesap opsiyonel |
| `LG_XXX_XX_CLFLINE` | Aktif cari hareket |
| `LG_XXX_XX_CLRNUMS` | Risk raporu ileri faz |
| `LG_XXX_XX_CLTOTFIL` | Dashboard/rapor opsiyonel |
| `LG_XXX_XX_CSCARD` | Cek/senet tahsilat opsiyonel |
| `LG_XXX_XX_CSHTOTS` | Kasa toplam raporu ileri faz |
| `LG_XXX_XX_CSROLL` | Cek/senet bordro ileri faz |
| `LG_XXX_XX_CSTRANS` | Cek/senet hareket ileri faz |
| `LG_XXX_XX_EMFICHE` | Muhasebe raporu ileri faz |
| `LG_XXX_XX_EMFLINE` | Muhasebe raporu ileri faz |
| `LG_XXX_XX_EMUHTOT` | Muhasebe raporu ileri faz |
| `LG_XXX_XX_FOLDER` | Dokuman katalog yolu; `FPATH` dosya yeri opsiyonel |
| `LG_XXX_XX_INVOICE` | POS/siparis/iade yazma ve rapor |
| `LG_XXX_XX_KSLINES` | Tahsilat/POS yazma |
| `LG_XXX_XX_ORFICHE` | Siparis yazma/okuma |
| `LG_XXX_XX_ORFLINE` | Siparis satir yazma/okuma |
| `LG_XXX_XX_PAYTRANS` | Tahsilat/odeme okuma-yazma |
| `LG_XXX_XX_PERDOC` | Aktif urun dokumani/resmi; `INFOREF` urun referansi, `LDATA` binary resim |
| `LG_XXX_XX_PRDCOST` | Maliyet kapanis, kapsam disi |
| `LG_XXX_XX_PRODUCER` | Mustahsil, kapsam disi |
| `LG_XXX_XX_SERILOTN` | Seri/lot depo ileri faz |
| `LG_XXX_XX_SLQCASGN` | Kalite kontrol, kapsam disi |
| `LG_XXX_XX_SLTRANS` | Seri/lot depo ileri faz |
| `LG_XXX_XX_SRVNUMS` | Hizmet toplam, ileri faz |
| `LG_XXX_XX_SRVTOT` | Hizmet toplam, ileri faz |
| `LG_XXX_XX_STFICHE` | Depo/POS/iade yazma |
| `LG_XXX_XX_STINVENS` | Stok aylik toplam opsiyonel |
| `LG_XXX_XX_STINVTOT` | Aktif stok okuma |
| `LG_XXX_XX_STLINE` | Stok/fatura/siparis detay okuma-yazma |
| `LG_XXX_XX_TRANSAC` | Firma donem bilgisi, sistemsel |

### Firma Tablolari

| Tablo | B2B karari |
| --- | --- |
| `LG_XXX_ACCCODES` | Muhasebe entegrasyon kodu, ileri faz |
| `LG_XXX_ASCOND` | Satis kosullari, fiyat ileri faz |
| `LG_XXX_BANKACC` | Tahsilat/banka opsiyonel |
| `LG_XXX_BNCARD` | Tahsilat/banka opsiyonel |
| `LG_XXX_BOMASTER` | Uretim recetesi, kapsam disi |
| `LG_XXX_BOMLINE` | Uretim recetesi, kapsam disi |
| `LG_XXX_BOMREVSN` | Uretim recetesi, kapsam disi |
| `LG_XXX_CHARASGN` | Urun ozellikleri ileri faz |
| `LG_XXX_CHARCODE` | Urun ozellikleri ileri faz |
| `LG_XXX_CHARVAL` | Urun ozellikleri ileri faz |
| `LG_XXX_CLCARD` | Aktif cari kart |
| `LG_XXX_CLINTEL` | Cari detay/istihbarat ileri faz |
| `LG_XXX_COPRDBOM` | Uretim recetesi, kapsam disi |
| `LG_XXX_CRDACREF` | Muhasebe kod eslesmesi, ileri faz |
| `LG_XXX_DECARDS` | Indirim/masraf kartlari, fiyat ileri faz |
| `LG_XXX_DISPLINE` | Is emri, kapsam disi |
| `LG_XXX_DISTLINE` | Dagitim sablonu, kapsam disi |
| `LG_XXX_DISTTEMP` | Dagitim sablonu, kapsam disi |
| `LG_XXX_EMCENTER` | Masraf merkezi, muhasebe ileri faz |
| `LG_XXX_EMGRPASS` | Personel grup, kapsam disi |
| `LG_XXX_EMPGROUP` | Personel grup, kapsam disi |
| `LG_XXX_EMPLOYEE` | Personel, B2B kullaniciya baglanmaz |
| `LG_XXX_EMUHACC` | Muhasebe hesaplari, ileri faz |
| `LG_XXX_ENGCLINE` | Muhendislik degisikligi, kapsam disi |
| `LG_XXX_FAREGIST` | Sabit kiymet, kapsam disi |
| `LG_XXX_FAYEAR` | Sabit kiymet, kapsam disi |
| `LG_XXX_FIRMDOC` | Firma dokumani/resmi; `INFOREF` referans, `LDATA` binary |
| `LG_XXX_INVDEF` | Depo/ambar opsiyonel |
| `LG_XXX_ITEMS` | Aktif urun/malzeme karti |
| `LG_XXX_ITEMSUBS` | Muadil/alternatif urun opsiyonel |
| `LG_XXX_ITMBOMAS` | Uretim recetesi, kapsam disi |
| `LG_XXX_ITMCLSAS` | Urun sinifi ileri faz |
| `LG_XXX_ITMFACTP` | Fabrika bilgisi, kapsam disi |
| `LG_XXX_ITMUNITA` | Urun birim opsiyonel |
| `LG_XXX_ITMWSDEF` | Is istasyonu, kapsam disi |
| `LG_XXX_ITMWSTOT` | Is istasyonu toplam, kapsam disi |
| `LG_XXX_KSCARD` | Kasa/tahsilat opsiyonel |
| `LG_XXX_LABORREQ` | Uretim calisan ihtiyaci, kapsam disi |
| `LG_XXX_LNGEXCSETS` | Coklu dil aciklamalari, opsiyonel |
| `LG_XXX_LNOPASGN` | Operasyon-malzeme, kapsam disi |
| `LG_XXX_LOCATION` | Stok yeri/depo opsiyonel |
| `LG_XXX_LOGREP` | Log/izleme, sistemsel |
| `LG_XXX_OCCUPATN` | Kaynak kullanim, kapsam disi |
| `LG_XXX_OPATTASG` | Uretim operasyon ozelligi, kapsam disi |
| `LG_XXX_OPERTION` | Uretim operasyonu, kapsam disi |
| `LG_XXX_OPRTREQ` | Uretim operasyon ihtiyaci, kapsam disi |
| `LG_XXX_PAYLINES` | Odeme plani satiri, cari ileri faz |
| `LG_XXX_PAYPLANS` | Odeme plani, cari ileri faz |
| `LG_XXX_PEGGING` | Uretim/siparis baglantisi, ileri faz |
| `LG_XXX_PRCARDS` | Promosyon kartlari, fiyat ileri faz |
| `LG_XXX_PRCLIST` | Aktif fiyat okuma |
| `LG_XXX_PRODORD` | Uretim emri, kapsam disi |
| `LG_XXX_PRVOPASG` | Uretim operasyon iliskisi, kapsam disi |
| `LG_XXX_QASGN` | Kalite kontrol, kapsam disi |
| `LG_XXX_QCLVAL` | Kalite kontrol, kapsam disi |
| `LG_XXX_QCSET` | Kalite kontrol, kapsam disi |
| `LG_XXX_QCSLINE` | Kalite kontrol, kapsam disi |
| `LG_XXX_ROUTE` | Satis rota/rapor ileri faz |
| `LG_XXX_ROUTETRS` | Satis rota satiri ileri faz |
| `LG_XXX_ROUTING` | Uretim rota, kapsam disi |
| `LG_XXX_RTNGLINE` | Uretim rota satiri, kapsam disi |
| `LG_XXX_SELCHVAL` | Urun ozellik degeri ileri faz |
| `LG_XXX_SLSCLREL` | Satis elemani-cari opsiyonel |
| `LG_XXX_SLSMAN` | Satis elemani opsiyonel |
| `LG_XXX_SPECODES` | Ozel kod filtreleri opsiyonel |
| `LG_XXX_SRVCARD` | Hizmet karti ileri faz |
| `LG_XXX_SRVUNITA` | Hizmet birim atama ileri faz |
| `LG_XXX_STCOMPLN` | Karma koli ileri faz |
| `LG_XXX_SUPPASGN` | Tedarikci/urun opsiyonel |
| `LG_XXX_TARGETS` | Satis hedefleri ileri faz |
| `LG_XXX_TOOLREQ` | Uretim arac ihtiyaci, kapsam disi |
| `LG_XXX_TRGPAR` | Trigger parametreleri, sistemsel |
| `LG_XXX_UNITSETC` | Birim cevrim ileri faz |
| `LG_XXX_UNITSETF` | Urun birim seti opsiyonel |
| `LG_XXX_UNITSETL` | Urun birimleri opsiyonel |
| `LG_XXX_WORKSTAT` | Is istasyonu, kapsam disi |
| `LG_XXX_WSATTASG` | Is istasyonu ozelligi, kapsam disi |
| `LG_XXX_WSATTVAS` | Is istasyonu ozellik degeri, kapsam disi |
| `LG_XXX_WSCHCODE` | Is istasyonu ozellik kodu, kapsam disi |
| `LG_XXX_WSCHVAL` | Is istasyonu ozellik degeri, kapsam disi |
| `LG_XXX_WSGRPASS` | Is istasyonu grup atama, kapsam disi |
| `LG_XXX_WSGRPF` | Is istasyonu grup, kapsam disi |

## Yazma Prensibi

Logo tarafina yazilacak hicbir islemde B2B uygulamasi Logo tablolarina dogrudan
`INSERT` veya `UPDATE` yapmamalidir. Dogru yontem:

1. B2B kendi veritabanina kaydi olusturur.
2. `integration_sync_events` veya pending endpoint uzerinden Logo'ya gidecek
   olay uretilir.
3. Logo sunucusundaki `tools/logo-sync` bridge, Logo ekibinin onayladigi
   stored procedure'u cagirir.
4. Sonuc `ack` endpoint'iyle B2B'ye geri yazilir.

Bu kural siparis, fatura, stok, tahsilat, cari ve POS kayitlari icin aynidir.
