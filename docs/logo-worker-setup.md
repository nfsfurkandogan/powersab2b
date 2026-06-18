# Logo Worker Setup (Legacy / Opsiyonel)

Guncel karar: Logo Objects kullanilmiyor. Varsayilan yazma akisi
`tools/logo-sync` SQL bridge scriptlerinin pending endpoint'leri okuyup Logo
SQL makinesindeki onayli stored procedure'leri cagirmasidir.

Bu dosya sadece RabbitMQ tabanli worker ileride tekrar istenirse referans olarak
kalir. Yeni kurulumda asagidaki yol izlenir:

1. `tools/logo-sync` klasoru Logo SQL'e yakin Windows makineye alin.
2. `.env.example` dosyasi `.env` olarak kopyalanir.
3. SQL baglanti bilgileri, B2B endpoint key'leri ve procedure adlari girilir.
4. `npm install` calistirilir.
5. Once `npm run sync:doctor`, sonra ihtiyaca gore export komutlari calistirilir.

Aktif yazma komutlari:

```bash
npm run sync:customers-export
npm run sync:collections
npm run sync:pos-sales
npm run sync:pos-expenses
npm run sync:documents-export
```

Tum okuma/yazma adimlari:

```bash
npm run sync:all
```

Gerekli procedure ayarlari:

```env
LOGO_CUSTOMER_EXPORT_PROCEDURE=dbo.PowersaB2B_ExportCustomer
LOGO_COLLECTION_EXPORT_PROCEDURE=dbo.PowersaB2B_ExportCollection
LOGO_POS_SALE_EXPORT_PROCEDURE=dbo.PowersaB2B_ExportPosSale
LOGO_POS_EXPENSE_EXPORT_PROCEDURE=dbo.PowersaB2B_ExportPosExpense
LOGO_ORDER_EXPORT_PROCEDURE=dbo.PowersaB2B_ExportOrder
LOGO_SHIPMENT_EXPORT_PROCEDURE=dbo.PowersaB2B_ExportShipment
LOGO_RETURN_EXPORT_PROCEDURE=dbo.PowersaB2B_ExportReturn
```

Procedure sozlesmesi icin baslangic dosyasi:
`tools/logo-sync/sql/powersa-b2b-procedure-contracts.sql`. Bu dosya
imzalari ve idempotency log tablosunu kurar; Logo'ya yazan asil bloklar Logo
SQL server baglantisinda doldurulmalidir.
