# Powersa — Depo Sevkiyat / İrsaliye Kargolama (Video Spec)

## Amaç
Depo personeli siparişleri barkodla toplayıp (picking/packing) sevkiyata çıkaracak.
Okutulan ürünler sevk edildi sayılacak, stok düşecek, sipariş durumu güncellenecek ve çıktı alınacak.

## Ekran Akışı (Video)
- Sipariş No: (üstte görünür)
- Barkod input: sürekli aktif (scanner ile hızlı)
- Gönderilen Tutar: okutulan ürünlerin toplam tutarı
- 2 tablo:
  1) Kalan / eksik ürünler (siparişte olup henüz sevk edilmemiş)
  2) Sevk edilen ürünler (okutulan/çıkan)

## Kurallar
- Barkod okutunca:
  - Sipariş kalemlerinde eşleşen ürün bulunur
  - shipped_qty +1 artar (veya qty param)
  - ürün “kalan”dan düşer, “sevk edilen”e geçer
- Fazla okutma engellenir (ordered_qty üstüne çıkamaz)
- Siparişte olmayan barkod okutulursa uyarı verilir
- Kısmi sevkiyat desteklenir (her ürün tam çıkmak zorunda değil)

## Entegrasyon
- Sipariş oluşturulunca reserved_total artmış kabul ediyoruz.
- Sevkiyat finalize edilince:
  - stock_summary: available_total düşer
  - stock_summary: reserved_total düşer
  - stock_movements OUT kaydı oluşur (source=shipment)
  - order_items shipped_qty güncellenir
  - order status: approved -> picking -> packed -> shipped (kısmi ise: partially_shipped)

## Çıktılar (Print)
- Packing Slip (ürün listesi + adet)
- İrsaliye/Fatura çıktısı (MVP’de packing slip yeter)
- Kargo etiketi (opsiyonel: alıcı/telefon/adres + takip no)

## Roller
- warehouse (depo)
- admin, dealer_admin
warehouse sadece depo ekranlarını görür.
