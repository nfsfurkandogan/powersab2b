# Powersa Logo Read Kit

Bu paket Logo okuma tarafini dogrulamak ve Windows bridge ortaminda eksik env
satirlarini tamamlamak icindir. Yazma islemi yapmaz.

## Windows bridge komutlari

Paket dosyalarini `C:\PowersaB2B` klasorune kopyalayin ve PowerShell'de:

```powershell
cd C:\PowersaB2B
powershell -ExecutionPolicy Bypass -File .\apply-read-env.ps1
.\run-write-queue-status.cmd
.\run-write-preflight.cmd
```

`apply-read-env.ps1` sadece `.env` icinde su iki satiri gunceller:

```env
LOGO_WAREHOUSE_NAME_MAP=0=ERZURUM POINT;1=ERZURUM DEPO;2=TRABZON DEPO;3=SAMSUN DEPO;4=BATUM DEPO
LOGO_WAREHOUSE_RAF_KEY_MAP=0=25;1=61;2=55;3=250;4=995
```

## SSMS read-only script

`sql\powersa-b2b-read-preflight-ssms.sql` dosyasi SSMS'te `LOGODB`
seciliyken calistirilir. Sadece `SELECT` yapar.

## Sonraki adim

Preflight sonucu alindiktan sonra siparis, POS satis ve sevkiyat icin
`PowersaB2B_ExportOrder`, `PowersaB2B_ExportPosSale` ve
`PowersaB2B_ExportShipment` procedure write block'lari uygulanir.
