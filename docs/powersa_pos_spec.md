# Powersa POS + Gün Sonu (Video Spec)

## Amaç
B2B sistemin içine "Perakende (POS)" ve "Gün Sonu" modülü eklenecek.
POS satışları stok düşecek, muhasebe/cari hareketlere işlenecek ve fiş/fatura basılacak.

## POS Akışı
1) POS ekranına gir
2) Satış tipi seç: NAKİT / KREDİ KARTI (opsiyon: HAVALE)
3) Belge tipi: İRSALİYE / FATURA
4) Cari: varsayılan "POINT NAKİT SATIŞ" veya "POINT KREDİ KARTI SATIŞ" (isterse manuel cari seçilebilir)
5) Ürün ekleme: stok kodu/barkod ile hızlı ekle + arama
6) Satır işlemleri:
   - miktar artır/azalt
   - satır sil
   - stok durumu
   - stok giriş/çıkış hareketleri
7) Toplamlar: ara toplam, iskonto, KDV, genel toplam
8) Ödeme al (cash/card) -> satış tamamla
9) Basım Yap: fiş/fatura yazdır (print template)

## Gün Sonu Akışı
1) POS oturumu aç (kasa açılış): açılış nakit
2) Gün boyu satışlar o oturuma yazılsın
3) Gün sonu: kasayı kapat
   - nakit sayımı gir
   - rapor: nakit toplamı, kart toplamı, iade/iptal, belge adedi, KDV toplamı
4) Gün sonu raporu yazdırılabilir

## Entegrasyon Kuralları
- POS satışları mevcut ürün/stock_summary yapısını kullanır.
- Satış tamamlanınca:
  - stock_summary.available_total düşer
  - stok hareket kaydı oluşur
  - ledger_entries ve/veya collections ile finans kaydı oluşur
- İptal (F10) yapılınca ters kayıt atılır (stok geri, finans ters kayıt).

## Roller
- admin, dealer_admin (şube sahibi), cashier (kasa)
- cashier yalnızca POS ve gün sonu ekranlarını kullanır.
