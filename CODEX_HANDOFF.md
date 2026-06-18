# Powersa B2B Codex Handoff

Bu repo iki Mac arasinda GitHub uzerinden ortak calisilacak Powersa B2B projesidir.

## Repo

- GitHub: `git@github.com:nfsfurkandogan/powersab2b.git`
- Ana branch: `main`
- Calisma modeli: her Mac'te ise baslamadan `git pull`, is bitince `commit` ve `git push`

## Codex Kurallari

- Her isten once `git status --short --branch` kontrol et.
- `AGENTS.md` talimatlarina uy.
- Deploy yapma, production komutu calistirma, migration calistirma, veri resetleme veya live Logo sync baslatma; bunlar icin kullanicidan acik onay al.
- Gercek `.env`, token, sifre, API key, SQL credential, Hostinger veya Logo entegrasyon bilgilerini yazdirma ve commit etme.
- Kod refactor'u yapma; istenen degisikligi dar kapsamda yap.
- Degisiklikten sonra dokunulan dosyalari, davranis degisikligini ve nasil dogrulanacagini raporla.

## Local Dosyalar

Bu dosyalar GitHub'a gitmez; her Mac'te local olarak olusturulur ve gercek degerler kullanicidan alinir:

```bash
cp apps/api/.env.example apps/api/.env
cp apps/web/.env.local.example apps/web/.env.local
cp tools/logo-sync/.env.example tools/logo-sync/.env
```

Secret uydurma. Eksik gercek deger varsa kullaniciya sor.

## Gunluk Git Akisi

Ise baslamadan:

```bash
git pull
```

Is bitince:

```bash
git status --short
git add .
git commit -m "Kisa aciklama"
git push
```

## Diger Mac Icin Kisa Baslangic

```bash
git clone git@github.com:nfsfurkandogan/powersab2b.git
cd powersab2b
cp apps/api/.env.example apps/api/.env
cp apps/web/.env.local.example apps/web/.env.local
cp tools/logo-sync/.env.example tools/logo-sync/.env
```

Sonra Codex'e su baglam verilebilir:

```text
Bu Powersa B2B projesinde AGENTS.md ve CODEX_HANDOFF.md talimatlarina uy.
Iki Mac arasinda git pull / commit / push ile calisiyoruz.
Deploy yapma, production komutu calistirma, .env icerigini yazdirma.
Once git status kontrol et, sonra sadece istedigim degisikligi yap.
```
