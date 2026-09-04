# Değişiklik Günlüğü

Bu dosyanın biçimi [Keep a Changelog](https://keepachangelog.com/tr/1.1.0/)
kalıbını izler ve proje [Semantic Versioning](https://semver.org/lang/tr/)
kurallarına uyar.

---

## [2.0.0] — 2026-09-04

### Değiştirildi — UYUMSUZ (yapılandırma)

- **Varsayılan sağlayıcı Anthropic Claude yerine Google Gemini oldu.**

  **Sorun teknik değildi, pratikti:** örneği denemek için önce kredi kartı
  girmek gerekiyordu. Bir öğrenme örneğinin önündeki en pahalı engel budur;
  kodu okumak isteyen çoğu kişi çalışır hâlini hiç görmeden ayrılıyordu.

  Gemini'nin ücretsiz katmanı vardır: `aistudio.google.com/apikey`
  adresinden Google hesabıyla girip birkaç saniyede anahtar alırsınız,
  faturalandırma açmanız gerekmez.

  **Göç:** `.env` dosyanızda `AI_PROVIDER=claude` satırı varsa hiçbir şey
  değişmez — Claude bir seçenek olarak duruyor. Satır yoksa uygulama artık
  Gemini bekler ve `GEMINI_API_KEY` ister.

### Eklendi

- **Sağlayıcı katmanı (`app/Core/Ai/`).** Uygulama artık hangi sağlayıcıyla
  konuştuğunu bilmiyor:

  ```php
  $result = Ai::fromEnv()->send($history, $systemPrompt);
  ```

  Dört sağlayıcı hazır gelir:

  | Sağlayıcı | Ücretsiz katman |
  |---|---|
  | `gemini` — Google Gemini *(varsayılan)* | **var** |
  | `groq` — Groq | **var** |
  | `openai-compatible` — xAI (Grok), OpenRouter, Ollama | sağlayıcıya göre |
  | `claude` — Anthropic | yok |

  Yapı şöyle: `Provider` arayüzü ortak yanıt sözleşmesini tanımlar,
  `HttpProvider` cURL / yeniden deneme / geri çekilmeyi **bir kez** yazar,
  her sağlayıcı yalnızca dört soruyu cevaplar (adres, başlıklar, gövde,
  yanıtın çevirisi). Yeni bir sağlayıcı eklemek bu dört metottur.

- `AI_PROVIDER`, `AI_BASE_URL` ve `AI_THINKING` ayarları.

- **Gemini'ye özgü üç tuzak koda ve yorumlara işlendi:** rol adının
  `assistant` değil `model` olması, sistem yönergesinin mesaj dizisine
  değil ayrı `systemInstruction` alanına konması, model adının gövdede
  değil adreste taşınması. Üçü de sessizce yanlış davranış üretir.

### Düzeltildi

- **Geçersiz Gemini anahtarında yanıltıcı hata mesajı.**

  **ÖLÇÜLEN DAVRANIŞ:** Gemini, hatalı anahtarda 401 değil
  `400 INVALID_ARGUMENT — API key not valid` döndürüyor. Yalnızca durum
  koduna bakan bir eşleme kullanıcıya "model adınız yanlış olabilir"
  diyordu ve saatlerce yanlış yere baktırırdı. Hata çözümleyicisi artık
  mesajın içine de bakıp doğru yönlendirmeyi yapıyor.

### Güvenlik

- **Gemini anahtarı sorgu dizesinde değil, başlıkta gönderiliyor.**
  Google'ın örnekleri `?key=…` gösterir ve çalışır — ama adres satırındaki
  anahtar sunucu erişim günlüklerine, vekil sunucu kayıtlarına ve tarayıcı
  geçmişine yazılır. `x-goog-api-key` başlığı hiçbirine düşmez.

### Kaldırıldı

- `app/Core/ClaudeClient.php` — içeriği `app/Core/Ai/ClaudeProvider.php`
  ve ortak `HttpProvider` arasında paylaştırıldı. Davranış aynı, kod
  artık üç sağlayıcı tarafından paylaşılıyor.

---

## [1.1.0] — 2026-09-04

Depo adı kısaltıldı, gereksiz kod temizlendi ve kullanıcılar sayfasına canlı
filtre eklendi. Veritabanı şeması değişmedi; var olan bir kurulumu
yükseltmek için yalnızca dosyaları değiştirmek yeterli.

### Değişti

- **Depo adı `PHP-MySQL-Yapay-Zeka-Entegrasyonu-Claude-API-Jeton-Maliyet` yerine
  `ai-integration-system` oldu.** Ad artık klasör adıyla aynı; adres satırında okunuyor ve
  vitrindeki canlı demo bağlantısı doğru klasöre gidiyor. GitHub eski
  adresi yenisine yönlendirir, ama klonunuzun adresini güncelleyin:

  ```bash
  git remote set-url origin https://github.com/CilginYazilim/ai-integration-system.git
  ```

  README, README.en ve bu dosyadaki klon · ZIP · issue · canlı demo
  adreslerinin hepsi yeni ada göre yazıldı. Canlı demo adresi artık
  `-main` eki taşımıyor.
- README'lerdeki **Ekran Görüntüleri** bölümü yeniden yazıldı. Görseller
  iki sütunlu bir markdown tablosunun hücrelerindeydi; GitHub bunu düzgün
  basıyor ama kütüphane vitrini README'yi makale olarak render ederken
  tablo satırları düz metne dönüşüyor ve sayfada `|---|---|` gibi satırlar
  görünüyordu. Her görsel artık kendi alt başlığı, ne gösterdiğini anlatan
  bir paragraf ve tam genişlikte tek bir görselle veriliyor. Sıra da
  anlamlıya çevrildi: önce projenin konusu olan ekranlar, sonra kontrol
  paneli, giriş, koyu tema ve mobil.

### Eklendi

- **Kullanıcılar sayfasında filtreler "Uygula"ya basmadan çalışıyor:**
  açılır listeler anında, arama kutusu 450 ms yazma beklemesiyle. Her tuş
  vuruşunda göndermek, "ahmet" yazan birine altı istek attırıyordu.
  Sayfa yeniden yüklendiği için kaybolan odak geri veriliyor ve imleç
  metnin sonuna konuyor.

  **Ajax bilerek kullanılmadı:** bu sayfa filtrenin ve sayfa numarasının
  adres çubuğunda taşınması üzerine kurulu — bağlantı paylaşılabiliyor,
  geri tuşu çalışıyor, yenileme aynı sonucu veriyor. Tabloyu yerinde
  değiştirmek bu üçünü de elle yeniden yazmayı gerektirirdi.

  **JavaScript kapalıysa hiçbir şey bozulmaz:** "Uygula" düğmesi yalnızca
  JS çalışıyorken gizlenir, çünkü o durumda işlevsizdir.
- **`.gitattributes`.** Satır sonları depoda her zaman LF saklanır, ikili
  dosyalar işaretlendi. Bu dosya olmadan davranış her katkıcının
  `core.autocrlf` ayarına kalıyor ve tek bir boşluk değişmediği hâlde
  "dosyanın tamamı değişti" diyen commit'ler çıkıyordu. Bootstrap ve
  jQuery `linguist-vendored` ile GitHub'ın dil çubuğundan çıkarıldı;
  bunlar bizim yazdığımız kod değil ve depoyu "JavaScript projesi"
  gösteriyorlardı.

### Kaldırıldı

- `views/errors/403.php` — ortak panel iskeletinden (`rbac-login-system`)
  taşınmış ama bu projede rol tabanlı kısıt yok; hiçbir kod yolu bu
  görünümü basmıyordu. Panel yetkisizlikte giriş ekranına yönlendirir,
  CSRF hatasında kontrol paneline döner.
- `config/config.php` içindeki `'locale' => 'tr_TR'` anahtarı. Hiçbir
  yerden okunmuyordu. Tarih biçimlendirmesi `human_date()` ile
  elle yapılıyor.

### Güvenlik

- **`.env.example` artık `APP_DEBUG=true` ile gelmiyor**, satır yorumda.
  Dosyayı olduğu gibi kopyalayıp canlıya çıkan biri, açık bir hata
  yığınıyla dosya yollarını, tablo adlarını ve sorgularını sızdırıyordu.
  Satır yokken kararı `Env::isLocalHost()` veriyor: yerelde açık, gerçek
  bir alan adında kapalı. Yerelde elle açmak gerekmiyor.

---

## [1.0.0] — 2026-09-03

İlk yayın. Yapay Zekâ Entegrasyonu (Claude API), Çılgın Yazılım Kaynak Kütüphanesi'nde yayınlandı.

### Eklendi

- `ClaudeClient` sınıfı: Messages API'sine `cURL` ile istek — SDK kullanılmıyor
- API anahtarı yalnızca sunucuda; tarayıcıya hiç gönderilmiyor
- Mesaj başına giriş/çıkış jetonu ve hesaplanmış maliyet kaydı
- Sohbet başına toplam jeton ve maliyet; mesaj toplamlarıyla birebir tutarlı
- Geçici (`429`/`529`/`5xx`) ve kalıcı (`401`/`400`) hata ayrımı
- Üstel geri çekilme + jitter ile en fazla üç yeniden deneme
- `stop_reason` ve `refusal` alanlarının ayrı ele alınması
- Modelin "düşünme" özetinin saklanması ve `<details>` ile gösterilmesi
- Model, efor seviyesi ve `max_tokens` ayarlarının `.env`'den okunması
- Üç örnek sohbet: jeton ve maliyet sayıları fiyat tablosuyla hesaplanmış

**Ortak altyapı (bütün panelli örneklerde aynı)**

- Oturum girişi, "beni hatırla" jetonu ve giriş denemesi hız sınırı
- CSRF koruması (`hash_equals` ile karşılaştırma)
- Sertleştirilmiş oturum: `HttpOnly`, `SameSite`, girişte kimlik yenileme
- Güvenlik başlıkları: CSP (`script-src 'self'`), `X-Frame-Options: DENY`,
  `X-Content-Type-Options: nosniff`, `Referrer-Policy`
- Tüm sorgular hazır ifade; `ATTR_EMULATE_PREPARES = false`
- Sunucu tarafında sayfalama ve arama; sıralama sütunu beyaz listeden
- Açık / koyu tema, kullanıcı hesabına kayıtlı
- Mobilde alt navigasyon; sayfa gövdesinde yatay kaydırma yok
- Türkçe ve İngilizce belgeler, ekran görüntüleriyle
- Sıfır bağımlılık: Composer yok, npm yok, CDN yok

### Güvenlik

- `APP_DEBUG` **ortamdan türetiliyor**: `.env` dosyası olmadan canlıya
  alınsa bile hata yığını ziyaretçiye görünmez (`Env::isLocalHost()`)
- `json_encode()` çağrılarında `JSON_INVALID_UTF8_SUBSTITUTE`; bozuk tek
  bir bayt yanıtın tamamını yutmuyor
- Komut satırı betikleri hem `.htaccess` ile hem `PHP_SAPI` kontrolüyle
  web erişimine kapalı

### Düzeltildi

- Koyu temada tablo hücrelerinin kontrastı 1,10:1 idi (okunamıyordu).
  Bootstrap'in `--bs-table-color` değişkeni markanın metin rengine
  bağlandı; ölçülen kontrast **14,48:1**
- `--cy-primary` ve `--cy-surface-2` CSS değişkenleri hiçbir yerde
  tanımlı değildi; tanımsız değişken sessizce başarısız olduğu için aktif
  menü rengi ve mobil alt çubuk renksiz kalıyordu
- "Son İşlemler" listesi `id DESC` ile sıralanıyordu; başlık zamana işaret
  ettiği hâlde sıra tarihle uyuşmuyordu. Artık `created_at DESC, id DESC`

[1.1.0]: https://github.com/CilginYazilim/ai-integration-system/releases/tag/v1.1.0
[1.0.0]: https://github.com/CilginYazilim/ai-integration-system/releases/tag/v1.0.0
