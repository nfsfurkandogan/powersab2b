# Logo Cari Entegrasyonu

## Kisa Cevap
- Evet, bir entegrasyon servisi gerekir.
- Frontend veya browser Logo SQL Server'a baglanmamalidir.
- `powersab2b.com` icindeki Laravel API, kendi `customers` tablosunu kullanmaya devam etmelidir.
- Logo tarafindan cari listesi batch olarak bu API'ye gonderilmelidir.
- B2B tarafinda acilan yeni cariler de ayri bir export kuyrugu ile Logo'ya geri yazilmalidir.
- Hedef kaynak Logo surumu `3.07`, test firma `002` (egitim) olarak alinmistir.

## Neden Bu Yol
- Mevcut ekranlar `/api/customers` uzerinden bizim veritabanimizdaki `customers` tablosunu kullaniyor.
- `72.62.159.198:65002` SQL Server degil, SSH servisidir; hosting erisimi ile Logo SQL baglantisi ayni sey degildir.
- Hostinger tarafinda `sqlsrv/pdo_sqlsrv` olmayabilir. Bu nedenle push tabanli entegrasyon daha guvenli ve deploy acisindan daha saglamdir.

## Onerilen Akis
1. Logo tarafinda, SQL Server'a yakin bir makinede kucuk bir bridge servis veya zamanlanmis gorev calisir.
2. Bu servis Logo cari tablosundan veya hazir bir SQL view'dan veriyi okur.
3. Veriyi normalize edip `POST /api/integrations/logo/customers/sync` endpoint'ine yollar.
4. Laravel API veriyi `customers` tablosuna upsert eder.
5. Web arayuzu hic degismeden guncel cari listesini gostermeye devam eder.
6. Ilk calismadan sonra bridge sadece degisen carileri gonderir; her 5 dakikada tam tablo tasimaz.

## Bu Repoda Eklenen Endpoint
- URL: `/api/integrations/logo/customers/sync`
- Header: `X-Integration-Key: <LOGO_CUSTOMER_SYNC_KEY>`
- Method: `POST`

Ek olarak `B2B -> Logo` yonu icin:
- `GET /api/integrations/logo/customers/pending`
- `POST /api/integrations/logo/customers/ack`

Ornek payload:

```json
{
  "dealer_id": 1,
  "records": [
    {
      "external_ref": "12345",
      "code": "120.01.0001",
      "name": "ABC OTOMOTIV",
      "contact_name": "Ahmet Kaya",
      "email": "abc@example.com",
      "phone": "05321234567",
      "city": "Istanbul",
      "district": "Umraniye",
      "tax_office": "Umraniye",
      "tax_number": "1234567890",
      "credit_limit": 250000,
      "is_active": true,
      "address": "Dudullu OSB",
      "iban": "TR000000000000000000000000",
      "salesperson_email": "plasiyer@bayi.com",
      "meta": {
        "logo_firm_no": 1
      }
    }
  ]
}
```

## Logo Tarafinda Ne Okunacak
- En temiz yol, Logo SQL tarafinda cari icin bir view olusturmaktir.
- Bridge servis o view'dan okur ve yukaridaki payload'a map eder.
- Ekran goruntusundeki test ortamina gore kaynak tablo `dbo.LG_002_CLCARD` olarak dogrulandi.
- Firma prefiksi `LG_002_*` oldugu icin test firmasi `002` uzerinden ilerleniyor.
- Yeni bridge surumu, tablo kolonlarini calisma aninda okuyup mevcut olan tum cari alanlarini `meta.integrations.logo.payload` altinda saklar.
- Ham Logo satiri da `meta.integrations.logo.payload.raw` altina yazilir; boylece sonradan ek alan ihtiyacinda yeniden SQL esleme yapmak zorunlu olmaz.

Ornek kolon eslestirme:
- `LOGICALREF` -> `external_ref`
- `CODE` -> `code`
- `DEFINITION_` -> `name`
- `INCHARGE` -> `contact_name`
- `TELNRS1` -> `phone`
- `TOWN` -> `district`
- `CITY` -> `city`
- `TAXOFFICE` -> `tax_office`
- `TAXNR` -> `tax_number`
- `ADDR1 + ADDR2` -> `address`
- `ACTIVE = 0` -> `is_active = true` (mevcut test kaydina gore)

Bridge script klasoru:
- `tools/logo-sync`
- Node script: `tools/logo-sync/logo-customers-sync.mjs`

## Ag Notu
- `GUCSASRV\\LOGO` gibi Windows instance adlari sadece ilgili agda cozulur.
- Dis agdan TCP baglantisi acik degilse bridge scripti Hostinger uzerinde degil, Logo'nun bulundugu sunucuda veya ayni yerel agda calistirilmalidir.
- Gerekirse `LOGO_SQL_SERVER` alanina makine adi yerine lokal IP yazilabilir.

## "Web Service" Ne Demek
- Logo firmasinin dedigi yapi pratikte su: Logo tarafinda 5 dakikada bir calisan kucuk bir istemci, bizim web servis endpoint'imizi cagirir.
- Yani B2B sistemi Logo SQL'e dogrudan baglanmaz.
- "Web service" bizim tarafta `/api/integrations/logo/customers/sync` endpoint'idir.
- Zamanlayici tarafinda ise Windows Task Scheduler yeterlidir; ayrica agir bir Windows servisi yazmak zorunlu degildir.

## B2B'de Acilan Cariyi Logo'ya Gonderme
- B2B panelden olusturulan yeni cariler `customers.source_system = b2b` ve `sync_status = pending` olarak isaretlenir.
- Logo SQL makinesindeki bridge `GET /api/integrations/logo/customers/pending` ile bu kayitlari alir.
- Bridge, `LOGO_CUSTOMER_EXPORT_PROCEDURE` ile cariyi Logo'ya yazar.
- Yazma sonucu `POST /api/integrations/logo/customers/ack` ile B2B'ye doner.
- Ack sonrasi cariye `source_reference = <Logo external ref>` yazilir; bir sonraki `Logo -> B2B` sync geldiginde ayni kayit duplicate olusturmadan `source_reference` uzerinden eslesir.

Bekleyen export kaydi ornegi:

```json
{
  "customer_id": 15,
  "export_key": "B2B-CUST-15",
  "customer_code": "DLR001-0001",
  "name": "ABC OTOMOTIV",
  "contact_name": "Ahmet Kaya",
  "phone": "05321234567",
  "tax_number": "1234567890",
  "address": "Dudullu OSB",
  "sync_status": "pending"
}
```

Ack payload ornegi:

```json
{
  "records": [
    {
      "customer_id": 15,
      "status": "synced",
      "external_ref": "12345",
      "meta": {
        "logo_code": "120.01.0001"
      }
    }
  ]
}
```

## Ortam Degiskeni
`apps/api/.env` veya production ortaminda:

```env
LOGO_CUSTOMER_SYNC_KEY=change-this-to-a-long-random-secret
```

## Not
- Bu repodaki yapi "alici taraf"tir.
- Logo SQL'e baglanan bridge servisi ayri yazilmalidir. Isterseniz sonraki adimda Windows/Node/PHP icin o bridge scriptini de cikarabiliriz.
