# Powersa Logo Worker (Legacy / Opsiyonel)

Ana yazma karari `tools/logo-sync` SQL bridge + stored procedure akisi olarak
guncellendi. Bu klasor artik varsayilan kurulum parcasi degildir; sadece ileride
RabbitMQ tabanli ek bir worker istenirse opsiyonel/legacy referans olarak kalir.

## Amac

- B2B tarafinda olusan write event'lerini dinlemek
- Logo kurulu Windows makinede bu event'leri stored procedure modunda islemek
- Sonucu B2B API'ye `ack` endpoint'leri ile bildirmek

## Ilk Fazda Desteklenen Event Tipleri

- `logo.customer.create`
- `logo.customer.update`
- `logo.collection.create`

## Klasor Yapisi

- `Powersa.LogoWorker.csproj`
- `Program.cs`
- `appsettings.json.example`
- `Configuration/`
- `Models/`
- `Services/`

## Notlar

- Bu proje burada compile edilmedi. Bu makinede `dotnet` kurulu degil.
- Gercek kurulum Windows Logo server uzerinde yapilacak.
- RabbitMQ baglantisi ve B2B callback bilgileri `appsettings.json` veya
  environment variable ile verilmeli.
- `Logo:WriteMode=StoredProcedure` verilirse worker, Logo SQL makinesinde
  tanimli yetkili stored procedure'leri cagirir. Bu mod
  dogrudan Logo tablolarina insert/update yapmaz; procedure tarafinda Logo
  ekibinin onayladigi yazma mantigi calismalidir.

## Beklenen Kurulum

1. Klasoru Logo kurulu Windows server'a kopyalayin.
2. `.example` dosyasini `appsettings.json` olarak duzenleyin.
   - `Logo:WriteMode=StoredProcedure`
   - `Logo:SqlConnectionString`
   - `Logo:CustomerCreateProcedure`
   - `Logo:CustomerUpdateProcedure`
   - `Logo:CollectionCreateProcedure`
3. `dotnet restore`
4. `dotnet build`
5. `dotnet run`
6. Sonra Windows Service olarak publish edin.

## Logo Objects Login Teshisi (Kullanilmiyor)

Varsayilan karar Logo Objects kullanmamak oldugu icin bu bolum sadece eski
denemeler icin tutulur. COM nesnesi olusuyor ama `UserLogin` false donuyorsa
once gercek Logo kullanici/sifre ve oturum akisi netlestirilmeli:

```powershell
cd C:\PowersaB2B\logo-worker
Set-ExecutionPolicy -Scope Process Bypass -Force

.\scripts\Test-LogoObjectsLogin.ps1 `
  -LogoPath "D:\LOGO\GOWINGS" `
  -CompanyNo 2 `
  -PeriodNo 1 `
  -Users @("LOGO", "BURAK.KARACA", "FARUK.CELIK", "ERHAN.KOSAR") `
  -Passwords @("2525", "")
```

Script `UserLoginOnly`, `ConnectUserCompany` ve `CombinedLogin` akisini ayri
ayri dener. `LoggedIn=True` veya `CompanyLoggedIn=True` veren satirdaki
kullanici/sifre worker config'ine alinacak degerdir.

## Callback Akisi

Worker islem bittikten sonra mevcut B2B endpoint'lerine yazar:

- `POST /api/integrations/logo/customers/ack`
- `POST /api/integrations/logo/collections/ack`

Header:

- `X-Integration-Key: <sync key>`

## Güvenli Isleyis

- Rabbit mesaji ancak:
  - stored procedure islemi biterse
  - callback basarili olursa
  `ack` edilir.
- Hata varsa mesaj `nack/requeue` veya DLQ politikasina birakilir.
