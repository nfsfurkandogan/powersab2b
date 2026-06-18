# Powersa B2B — Video Spec (Source of Truth)

## Kullanıcı Rolleri
- admin
- dealer_admin
- salesperson (plasiyer)

## Temel Akış
1) Login
2) Müşteri seçimi (cari listesi)
   - filtre: sepette ürün olanlar (has_cart)
   - filtre: sipariş bakiyesi olanlar (has_order_balance)
3) Ana sayfa / Ürün arama
   - araç filtreleri (marka/model/tip/yıl)
   - ürün arama (OEM/SKU/isim)
4) Ürün listesi
   - stok + fiyat görünür
   - sepete ekle / adet arttır-azalt
5) Sepet (Cari sepet)
   - depo/transfer
   - gönderme şekli
   - sipariş notu
   - toplam/iskonto/KDV/genel toplam
   - sipariş gönder
6) Siparişler
   - liste, tarih/durum filtre
   - sipariş detayı + durum geçmişi
7) Cari hesap / Cari hareketler
   - ekstre benzeri liste
8) Tahsilat
   - sekmeler: Nakit / Çek / Senet / Kredi Kartı / Havale
   - tahsilat kaydı oluşturunca cari harekete işlenir
9) Kataloglar
   - yeni ürünler
   - sıcak ürünler
10) Raporlar
   - cari bakiye/aging
   - sipariş bakiye
   - tahsilat raporu
   - satış raporu

## Performans Kuralları
- Her liste pagination (limit max 50)
- Ürün listesi: cursor/keyset pagination
- N+1 sorgu yok
- stok = stock_summary tablosu
- fiyat = base_price + price_list + (opsiyonel) dealer override
- ağır raporlar queue ile async

## Önerilen Veri Modeli (MySQL)
- dealers, users, roles
- customers (dealer_id)
- products, brands, categories
- vehicles, vehicle_products (fitment)
- stock_summary (product_id, available_total, reserved_total)
- price_lists (A/B/C), dealers.price_list_id
- base_prices (product_id, list_price)
- dealer_price_overrides (dealer_id, product_id, net_price) [sadece özel fiyat]
- carts, cart_items
- orders, order_items, order_status_history
- ledger_entries
- collections (method: cash/transfer/check/note/cc)
