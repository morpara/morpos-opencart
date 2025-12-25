# MorPOS for OpenCart

[![OpenCart Sürümü](https://img.shields.io/badge/OpenCart-3.0%2B-blue.svg)](https://www.opencart.com/)
[![PHP Sürümü](https://img.shields.io/badge/PHP-7.1%2B-777bb4.svg)](https://php.net/)
[![Lisans](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

**MorPOS for OpenCart**, OpenCart 3.x mağazalarına **Morpara MorPOS** ödeme sistemini entegre eden güvenli ve kullanımı kolay bir ödeme ağ geçidi eklentisidir. Müşteriler, siparişlerini tamamlarken güvenli **Barındırılan Ödeme Sayfası (HPP)** akışı üzerinden yönlendirilir veya **Gömülü Ödeme Formu** kullanabilir.

> **📌 Not**: Bu versiyon **OpenCart 3.x** sürümünü desteklemektedir. **OpenCart 4.x** desteği arıyorsanız, lütfen [4.x versiyonuna](https://github.com/morpara/morpos-opencart/tree/4.x) gidin.

## ✨ Özellikler

- 🛒 **OpenCart Entegrasyonu**: OpenCart 3.x için MorPOS'u ödeme yöntemi olarak sorunsuzca ekler
- 🔒 **Güvenli Ödemeler**: Barındırılan Ödeme Sayfası (HPP) ve Gömülü Ödeme Formu seçenekleri
- 🌍 **Çoklu Para Birimi**: TRY, USD, EUR para birimlerini destekler
- 💳 **Çoklu Ödeme Seçenekleri**: Kredi kartları, banka kartları ve taksitli ödemeler
- 🧪 **Test Modu**: Geliştirme için eksiksiz test ortamı
- 🔧 **Kolay Yapılandırma**: Bağlantı testi ile basit yönetici paneli kurulumu
- 🛡️ **Güvenlik Özellikleri**: TLS 1.2+ gereksinimi, imzalı API iletişimi, sepet/sipariş doğrulaması
- 🌐 **Çoklu Dil**: Türkçe ve İngilizce desteği (genişletilebilir)

## 📋 Gereksinimler

### Sunucu Gereksinimleri

| Bileşen | Minimum | Önerilen |
|---------|---------|----------|
| **OpenCart** | 3.0 | 3.0.4.1+ |
| **PHP** | 7.1 | 7.4+ |
| **TLS** | 1.2 | 1.3 |

### PHP Eklentileri

- `cURL` - API iletişimi için gereklidir
- `json` - Veri işleme için gereklidir
- `hash` - Güvenlik imzaları için gereklidir
- `openssl` - Güvenli bağlantılar için gereklidir (TLS 1.2 desteği için OpenSSL 1.0.1+)

### OpenCart Özellikleri

- **Yönetici Paneli Erişimi**: Eklenti yapılandırması için gereklidir
- **Veritabanı Erişimi**: Konuşma takip tablosu için
- **HTTPS**: Üretim ortamları için önerilir
- **Oturum SameSite Politikası**: Kurulum sırasında otomatik olarak 'Lax' olarak yapılandırılır

## 🚀 Kurulum

### Yöntem 1: Eklenti Yükleyici (Önerilen)

1. **Eklentiyi İndirin**
   - En son sürümü [GitHub Releases](https://github.com/morpara/morpos-opencart/releases) sayfasından ZIP dosyası olarak indirin

2. **OpenCart Yönetici Panelinden Yükleyin**
   - **Eklentiler** → **Yükleyici** bölümüne gidin
   - **Yükle** butonuna tıklayın ve ZIP dosyasını seçin
   - Yüklemenin tamamlanmasını bekleyin
   - **Eklentiler** → **Eklentiler** bölümüne gidin
   - **Eklenti Türü**: **Ödemeler** seçin
   - **MorPOS Payment Gateway** bulun
   - **Yükle** butonuna tıklayın (yeşil + butonu)

3. **Eklentiyi Yapılandırın**
   - **Düzenle** butonuna tıklayın (mavi kalem ikonu)
   - MorPOS kimlik bilgilerinizi girin
   - Doğrulamak için **Bağlantıyı Test Et** butonuna tıklayın
   - **Kaydet** butonuna tıklayın

### Yöntem 2: Manuel Kurulum (Geliştiriciler)

1. **Eklentiyi İndirin**
   ```bash
   git clone https://github.com/morpara/morpos-opencart.git
   cd morpos-opencart
   ```

2. **Dosyaları OpenCart'a Kopyalayın**

   ```bash
   # Yönetici dosyalarını kopyalayın
   cp -r upload/admin/controller/extension/payment/morpos_gateway.php /path/to/opencart/admin/controller/extension/payment/
   cp -r upload/admin/language/en-gb/extension/payment/morpos_gateway.php /path/to/opencart/admin/language/en-gb/extension/payment/
   cp -r upload/admin/language/tr-tr/extension/payment/morpos_gateway.php /path/to/opencart/admin/language/tr-tr/extension/payment/
   cp -r upload/admin/model/extension/payment/morpos_gateway.php /path/to/opencart/admin/model/extension/payment/
   cp -r upload/admin/view/template/extension/payment/morpos_gateway.twig /path/to/opencart/admin/view/template/extension/payment/
   cp -r upload/admin/view/javascript/ /path/to/opencart/admin/view/javascript/
   cp -r upload/admin/view/stylesheet/ /path/to/opencart/admin/view/stylesheet/
   
   # Katalog dosyalarını kopyalayın
   cp -r upload/catalog/controller/extension/payment/morpos_gateway.php /path/to/opencart/catalog/controller/extension/payment/
   cp -r upload/catalog/language/en-gb/extension/payment/morpos_gateway.php /path/to/opencart/catalog/language/en-gb/extension/payment/
   cp -r upload/catalog/language/tr-tr/extension/payment/morpos_gateway.php /path/to/opencart/catalog/language/tr-tr/extension/payment/
   cp -r upload/catalog/model/extension/payment/morpos_gateway.php /path/to/opencart/catalog/model/extension/payment/
   cp -r upload/catalog/model/extension/payment/morpos_conversation.php /path/to/opencart/catalog/model/extension/payment/
   cp -r upload/catalog/view/theme/default/ /path/to/opencart/catalog/view/theme/default/
   
   # Sistem kütüphane dosyalarını kopyalayın
   cp -r upload/system/library/morpos/* /path/to/opencart/system/library/morpos/
   ```

3. **Doğru İzinleri Ayarlayın**
   ```bash
   # Dosya izinlerini ayarlayın (yolu gerektiği gibi düzenleyin)
   chmod 644 /path/to/opencart/admin/controller/extension/payment/morpos_gateway.php
   chmod 644 /path/to/opencart/catalog/controller/extension/payment/morpos_gateway.php
   chmod 644 /path/to/opencart/system/library/morpos/*.php
   ```

4. **OpenCart Yönetici Panelinden Yükleyin**
   - **Eklentiler** → **Eklentiler** bölümüne gidin
   - **Eklenti Türü**: **Ödemeler** seçin
   - **MorPOS Payment Gateway** bulun
   - **Yükle** butonuna tıklayın (yeşil + butonu)
   - Yapılandırmak için **Düzenle** butonuna tıklayın (mavi kalem ikonu)

### Yöntem 3: FTP Yükleme

1. Eklenti dosyalarını [GitHub](https://github.com/morpara/morpos-opencart) üzerinden indirin
2. ZIP dosyasını çıkartın
3. FTP istemcinizi kullanarak `upload/` içeriğini OpenCart kök dizinine yükleyin
4. Yükleme sırasında dizin yapısını koruyun
5. Kurulumu tamamlamak için Yöntem 2'deki 4. adımı takip edin

## ⚙️ Yapılandırma

### 1. Ayarlara Erişim

**Eklentiler** → **Eklentiler** → **Eklenti Türü Seçin: Ödemeler** → **MorPOS Payment Gateway** → **Düzenle** yolunu izleyin

### 2. Gerekli Ayarlar

Aşağıdaki zorunlu alanları doldurun:

| Alan | Açıklama | Örnek |
|------|----------|-------|
| **Merchant ID** | MorPOS'tan aldığınız benzersiz üye işyeri kimliğiniz | `12345` |
| **Client ID** | MorPOS'tan aldığınız OAuth istemci kimliği | `your_client_id` |
| **Client Secret** | MorPOS'tan aldığınız OAuth istemci gizli anahtarı | `your_client_secret` |
| **API Key** | API istekleri için kimlik doğrulama anahtarı | `your_api_key` |

> **Kimlik bilgilerini nereden alabilirsiniz?** Üye işyeri kimlik bilgilerinizi almak için [Morpara Destek](https://morpara.com/support) ile iletişime geçin.

### 3. Ortam Ayarları

**Test Modu (Sandbox)**
- ✅ Geliştirme/test için etkinleştirin
- Sandbox uç noktalarını kullanır
- Gerçek işlem yapılmaz
- Test kart numaraları kabul edilir

**Form Tipi**
Ödeme arayüzünüzü seçin:
- **Hosted (Barındırılan)**: MorPOS ödeme sayfasına yönlendirme (önerilen)
  - Daha güvenli
  - PCI uyumluluğu MorPOS tarafından sağlanır
  - Profesyonel ödeme arayüzü
- **Embedded (Gömülü)**: Siteniz içinde ödeme formu
  - Kesintisiz ödeme deneyimi
  - SSL sertifikası gerektirir

**Sipariş Durumu Ayarları**
- **Başarılı Sipariş Durumu**: Ödeme başarılı olduğunda ayarlanacak durum (örn. "İşleniyor")
- **Başarısız Sipariş Durumu**: Ödeme başarısız olduğunda ayarlanacak durum (örn. "Başarısız")

**Sıralama**
- Ödeme yöntemlerinin ödeme sayfasındaki görüntülenme sırasını belirler

### 4. Bağlantı Testi

Kimlik bilgilerini girdikten sonra:

1. Yönetici panelinde **Bağlantıyı Test Et** butonuna tıklayın
2. Bağlantı testinin tamamlanmasını bekleyin
3. Başarılı bağlantı için yeşil onay işaretinin göründğünü doğrulayın
4. Aşağıdaki sistem gereksinimleri tablosunu kontrol edin:
   - ✅ PHP sürüm uyumluluğu
   - ✅ TLS sürüm desteği
   - ✅ OpenCart sürümü
   - ⚠️ Herhangi bir uyarı, önerilerle birlikte görüntülenecektir

### 5. Ödeme Yöntemini Etkinleştirin

- **Durum**'u **Etkin** olarak ayarlayın
- MorPOS'u mağazanızda aktifleştirmek için **Kaydet** butonuna tıklayın

## 🔧 Ödeme Akışı

### Müşteri Ödeme Süreci

1. **Sepet İncelemesi**: Müşteri sepetteki ürünleri inceler
2. **Ödeme**: Müşteri ödeme sayfasına geçer
3. **Ödeme Yöntemi Seçimi**: Müşteri ödeme yöntemi olarak MorPOS'u seçer
4. **Ödeme Başlatma**: 
   - Sistem sepeti doğrular ve sipariş oluşturur
   - Güvenlik kontrolleri sepet/sipariş tutarlılığını sağlar
   - İzleme için benzersiz konuşma kimliği oluşturulur
5. **Ödeme İşleme**:
   - **Barındırılan Mod**: Müşteri MorPOS ödeme sayfasına yönlendirilir
   - **Gömülü Mod**: Ödeme formu ödeme sayfası içinde yüklenir
6. **Ödeme Tamamlama**: 
   - Müşteri ödemeyi tamamlar
   - Sistem MorPOS'tan geri çağrı alır
   - Sipariş durumu otomatik olarak güncellenir
7. **Onay**: Müşteri başarı/başarısızlık sayfasını görür

### Güvenlik Özellikleri

Eklenti birden fazla güvenlik katmanı uygular:

- **Sepet/Sipariş Doğrulaması**: Ödeme sırasında tutar manipülasyonunu önler
- **Konuşma Takibi**: Her ödeme benzersiz bir takip kimliği alır
- **İmza Doğrulaması**: Tüm API istekleri Client Secret ile imzalanır
- **Oturum Senkronizasyonu**: Ödeme yönlendirmeleri için otomatik oturum yönetimi
- **Tutar Çift Kontrolü**: Ödeme öncesi sepet toplamının sipariş toplamıyla eşleştiğini doğrular
- **Otomatik Sipariş Yeniden Oluşturma**: Olası kurcalama girişimlerini tespit eder ve düzeltir

## 🛠️ Hata Ayıklama

### Loglama

OpenCart'ta hata loglamayı etkinleştirin:

1. **OpenCart 3.x:**
   ```php
   // config.php ve admin/config.php içinde
   define('ERROR_LOG', '/path/to/your/error.log');
   ```

2. **Logları Kontrol Edin:**
   ```bash
   tail -f /path/to/your/error.log
   ```

3. **MorPOS Spesifik Loglar:**
   Eklenti güvenlikle ilgili olayları yazar:
   ```
   MorPOS Security: Cart/Order total mismatch detected
   MorPOS Gateway: Updated session SameSite policy to Lax
   ```

### Ödeme Akışlarını Test Etme

**Test Kartları (Yalnızca Sandbox Modu):**

Test kart numaraları için MorPOS sandbox belgelerinize bakın.

**Test Kontrol Listesi:**
- ✅ Başarılı ödeme akışı
- ✅ Başarısız ödeme işleme
- ✅ Ağ hatası senaryoları
- ✅ Ödeme sırasında sepet değişikliği
- ✅ Ödeme sırasında oturum zaman aşımı
- ✅ Para birimi dönüşümü (varsa)
- ✅ Taksit seçenekleri
- ✅ Sipariş durumu güncellemeleri

## 🔍 Sorun Giderme

### Yaygın Sorunlar

#### "Ödeme başlatılırken bir hata oluştu"

**Nedenler ve Çözümler:**

1. **Yanlış Kimlik Bilgileri**
   - Merchant ID, Client ID, Client Secret ve API Key'i doğrulayın
   - Doğrulamak için **Bağlantıyı Test Et** özelliğini kullanın

2. **Ortam Uyuşmazlığı**
   - Test Modu ayarının kimlik bilgilerinizle eşleştiğinden emin olun (sandbox vs üretim)

3. **TLS Gereksinimleri**
   - Sunucu TLS 1.2 veya üstünü desteklemelidir
   - Yönetici panelinde sistem gereksinimlerini kontrol edin

4. **cURL Sorunları**
   - cURL eklentisinin yüklü olduğunu doğrulayın: `php -m | grep curl`
   - OpenSSL sürümünü kontrol edin: `php -r "echo OPENSSL_VERSION_TEXT;"`

#### Bağlantı Testi Başarısız Oluyor

1. **Güvenlik Duvarı/Ağ Sorunları**
   ```bash
   # Sunucudan bağlantıyı test edin
   curl -v https://finagopay-pf-sale-api-gateway.prp.morpara.com
   ```

2. **DNS Çözümlemesi**
   ```bash
   # DNS'in doğru çözümlendiğini doğrulayın
   nslookup finagopay-pf-sale-api-gateway.prp.morpara.com
   ```

3. **Sunucu Saati**
   - Sunucu saatinin doğru olduğundan emin olun
   ```bash
   date
   ```

#### Ödeme Geri Çağrısı Başarısız Oluyor

1. **Oturum Sorunları**
   - Eklenti otomatik olarak SameSite politikasını 'Lax' olarak ayarlar
   - Veritabanında doğrulayın: `SELECT * FROM oc_setting WHERE key = 'config_session_samesite';`

2. **URL Yeniden Yazma**
   - OpenCart SEO URL'lerinin düzgün yapılandırıldığından emin olun
   - Geri çağrı URL'sini manuel olarak test edin

3. **Konuşma Tablosu**
   - `oc_morpos_conversation` tablosunun var olup olmadığını kontrol edin
   - Veritabanı izinlerini doğrulayın

#### Para Birimi Desteklenmiyor

**Desteklenen Para Birimleri:**
- TRY (Türk Lirası) - Kod: 949
- USD (ABD Doları) - Kod: 840
- EUR (Euro) - Kod: 978

**Çözüm:** OpenCart'ı desteklenen para birimlerinden birini kullanacak şekilde yapılandırın.

### Sistem Gereksinimleri Kontrolü

Eklenti, yönetici panelinde yerleşik bir sistem gereksinimleri kontrol aracı içerir:

| Bileşen | Kontrol |
|---------|---------|
| **PHP Sürümü** | 7.1+ gerekli, 7.4+ önerilen |
| **OpenCart Sürümü** | 3.0+ gerekli, 3.0.4.1+ önerilen |
| **TLS Desteği** | 1.2+ gerekli, 1.3+ önerilen |

**Durum Göstergeleri:**
- 🟢 **Yeşil**: Önerilen gereksinimleri karşılıyor
- 🟡 **Sarı**: Minimum gereksinimleri karşılıyor, yükseltme önerilir
- 🔴 **Kırmızı**: Gereksinimleri karşılamıyor, çalışmayacak

### Hata Ayıklama Modu

Ayrıntılı hata ayıklama için:

1. **OpenCart Hata Görüntülemeyi Etkinleştirin:**
   ```php
   // config.php içinde
   error_reporting(E_ALL);
   ini_set('display_errors', 1);
   ini_set('display_startup_errors', 1);
   ```

2. **PHP Hata Logunu Kontrol Edin:**
   ```bash
   tail -f /var/log/php_errors.log
   ```

3. **Web Sunucusu Loglarını Kontrol Edin:**
   ```bash
   # Apache
   tail -f /var/log/apache2/error.log
   
   # Nginx
   tail -f /var/log/nginx/error.log
   ```

## 🌐 Uluslararasılaştırma

Eklenti birden fazla dili destekler:

- **Türkçe (tr-tr)**: Yerel destek
- **İngilizce (en-gb)**: Varsayılan dil

### Yeni Çeviri Ekleme

1. **Dil Dosyasını Kopyalayın:**
   ```bash
   # Yönetici paneli için
   cp upload/admin/language/en-gb/extension/payment/morpos_gateway.php \
      upload/admin/language/[dil-kodu]/extension/payment/morpos_gateway.php
   
   # Katalog için (müşteriye yönelik)
   cp upload/catalog/language/en-gb/extension/payment/morpos_gateway.php \
      upload/catalog/language/[dil-kodu]/extension/payment/morpos_gateway.php
   ```

2. **Metinleri Çevirin:**
   ```php
   <?php
   // Örnek: Almanca (de-de)
   $_['heading_title'] = 'MorPOS Zahlungsgateway';
   $_['text_success'] = 'Einstellungen gespeichert!';
   // ... tüm metinleri çevirin
   ```

3. **Çeviriyi Test Edin:**
   - OpenCart yönetici dilini değiştirin
   - Tüm metinlerin doğru görüntülendiğini doğrulayın

### Mevcut Çeviri Metinleri

Dil dosyaları şunları içerir:
- Yönetici paneli etiketleri ve mesajları
- Müşteriye yönelik ödeme yöntemi metinleri
- Hata mesajları
- Sistem gereksinimi açıklamaları
- Bildirim mesajları
- Buton etiketleri

## 🤝 Katkıda Bulunma

Katkılarınızı bekliyoruz! İşte nasıl başlayacağınız:

### Geliştirme Kurulumu

1. **Depoyu Fork Edin**
   ```bash
   git clone https://github.com/KULLANICI_ADINIZ/morpos-opencart.git
   cd morpos-opencart
   ```

2. **Yerel Ortamı Kurun**
   - OpenCart 3.x'i yerel olarak yükleyin
   - Eklenti dosyalarını OpenCart dizinine kopyalayın
   - Veritabanı ve web sunucusunu yapılandırın

3. **Değişiklik Yapın**
   - OpenCart kodlama standartlarını takip edin
   - Uygun dokümantasyon ekleyin
   - OpenCart 3.x ile test edin

4. **Pull Request Gönderin**
   - Özellik dalı oluşturun: `git checkout -b feature/yeni-ozellik`
   - Değişiklikleri commit edin: `git commit -m "Yeni özellik ekle"`
   - Dalı push edin: `git push origin feature/yeni-ozellik`
   - GitHub'da pull request açın

### Kodlama Standartları

- [OpenCart Eklenti Geliştirme Kılavuzları](https://docs.opencart.com/)'nu takip edin
- OpenCart 3.x ile uyumluluğu koruyun
- Fonksiyonlar ve sınıflar için PHPDoc yorumları ekleyin
- Anlamlı commit mesajları yazın
- OpenCart 3.x dizin yapısını takip edin

### Test Kılavuzları

Pull request göndermeden önce test edin:

1. **Kurulum/Kaldırma**
   - Temiz kurulum çalışıyor
   - Kaldırma tüm verileri kaldırıyor
   - Önceki sürümden yükseltme

2. **Ödeme Akışları**
   - Başarılı ödeme (barındırılan ve gömülü)
   - Başarısız ödeme
   - İptal edilen ödeme
   - Ağ hataları

3. **Çoklu Para Birimi**
   - TRY, USD, EUR dönüşümleri
   - Para birimi görüntüleme (yönetici ve ön yüz)

4. **Güvenlik**
   - Sepet tutar doğrulaması
   - Oturum yönetimi
   - API imza doğrulaması

5. **Uyumluluk**
   - OpenCart 3.x
   - PHP 7.1, 7.2, 7.3, 7.4

## 📄 Lisans

Bu proje **MIT** Lisansı altında lisanslanmıştır - detaylar için [LICENSE](LICENSE) dosyasına bakın.

## 🆘 Destek

### Dokümantasyon
- Bu README'yi detaylıca inceleyin
- Kod içi yorumları gözden geçirin
- [OpenCart Dokümantasyonu](https://docs.opencart.com/)'nu kontrol edin

### Sorun Bildirme
- **GitHub Issues**: [Hata Bildir](https://github.com/morpara/morpos-opencart/issues)
- Şunları ekleyin:
  - OpenCart sürümü
  - PHP sürümü
  - Eklenti sürümü
  - Hata mesajları/loglar
  - Sorunu yeniden üretme adımları

### Ticari Destek
- **Morpara Destek**: [Destek İletişim](https://morpara.com/support)
- **E-posta**: support@morpara.com

### Topluluk
- **OpenCart Forumu**: Diğer kullanıcılarla deneyimlerinizi paylaşın
- **GitHub Discussions**: Sorular sorun ve ipuçları paylaşın

## 🙏 Teşekkürler

- **OpenCart Ekibi** - Mükemmel e-ticaret platformu için
- **OpenCart Topluluğu** - Güçlü ekosistem ve destek için
- **Morpara** - Güvenli ödeme altyapısı için

## 🔐 Güvenlik

Bir güvenlik açığı keşfederseniz, lütfen sorun izleyici kullanmak yerine security@morpara.com adresine e-posta gönderin. Tüm güvenlik açıkları hızlıca ele alınacaktır.

### Güvenlik Özellikleri

- ✅ TLS 1.2+ şifreli iletişim
- ✅ HMAC-SHA256 ile imzalanmış API istekleri
- ✅ Sepet/sipariş tutar doğrulaması
- ✅ Konuşma kimliği takibi
- ✅ Oturum güvenliği (SameSite politikası)
- ✅ SQL enjeksiyon koruması (hazırlanmış ifadeler)
- ✅ XSS koruması (çıktı kaçışı)
- ✅ CSRF koruması (OpenCart token'ları)

---

**[Morpara](https://morpara.com/) tarafından ❤️ ile yapılmıştır**

MorPOS ödeme çözümleri hakkında daha fazla bilgi için [morpara.com](https://morpara.com/) adresini ziyaret edin.