<div align="center">

<img src="assets/images/logo.png" alt="Çılgın Yazılım" width="90">

# Yapay Zekâ Entegrasyonu (Claude API)

### PHP 8 · PDO · MySQL · Claude Messages API · Jeton ve Maliyet Takibi · Düşünme Özeti · Çılgın Yazılım Tasarım Kalıbı

**Anahtar sunucuda kalır, her yanıtın maliyeti hesaplanır, sohbet geçmişi veritabanında durur.**

[![PHP](https://img.shields.io/badge/PHP-8.0%2B-777BB4?style=flat-square&logo=php&logoColor=white)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-5.7%2B-4479A1?style=flat-square&logo=mysql&logoColor=white)](https://mysql.com)
[![Claude](https://img.shields.io/badge/Claude-Messages_API-d97757?style=flat-square&logo=anthropic&logoColor=white)](https://docs.anthropic.com)
[![Composer](https://img.shields.io/badge/Composer-gerekmiyor-16a34a?style=flat-square)](#kurulum)
[![License](https://img.shields.io/badge/Lisans-MIT-16a34a?style=flat-square)](LICENSE)

**🇹🇷 Türkçe** · [🇬🇧 English](README.en.md)

[**▶ Canlı Demo**](https://cilginyazilim.com/kutuphane/uygulama/PHP-MySQL-Yapay-Zeka-Entegrasyonu-Claude-API-Jeton-Maliyet-main/) · [Kaynak Kütüphanesi](https://cilginyazilim.com/kutuphane/php-ai-integration) · [cilginyazilim.com](https://cilginyazilim.com)

</div>

---

<div align="center">

## Canlı Demo

**Kurulum yok, kayıt yok, indirme yok — tarayıcınızdan 3 saniyede deneyin.**

<a href="https://cilginyazilim.com/kutuphane/uygulama/PHP-MySQL-Yapay-Zeka-Entegrasyonu-Claude-API-Jeton-Maliyet-main/"><img src="https://img.shields.io/badge/CANLI_DEMOYU_A%C3%87-0b5cb5?style=for-the-badge&logo=googlechrome&logoColor=white&labelColor=061321" alt="Canlı Demoyu Aç" height="42"></a>
<a href="https://cilginyazilim.com/kutuphane/php-ai-integration"><img src="https://img.shields.io/badge/KAYNAK_KODU_%C4%B0NCELE-0ea5e9?style=for-the-badge&logo=readthedocs&logoColor=white&labelColor=061321" alt="Kaynak Kodu İncele" height="42"></a>
<a href="https://github.com/CilginYazilim/PHP-MySQL-Yapay-Zeka-Entegrasyonu-Claude-API-Jeton-Maliyet/archive/refs/heads/main.zip"><img src="https://img.shields.io/badge/ZIP_%C4%B0ND%C4%B0R-16a34a?style=for-the-badge&logo=github&logoColor=white&labelColor=061321" alt="ZIP İndir" height="42"></a>

<br><br>

<a href="https://cilginyazilim.com/kutuphane/uygulama/PHP-MySQL-Yapay-Zeka-Entegrasyonu-Claude-API-Jeton-Maliyet-main/" title="Canlı demoyu açmak için tıklayın">
  <img src="docs/screenshots/04-sohbet-detay.png" alt="Yapay zekâ entegrasyonu canlı demo önizlemesi" width="860">
</a>

<sub>▲ Görsele tıklayarak demoyu açabilirsiniz</sub>

</div>

<br>

### Demo hesapları

| Rol | E-posta | Parola |
|---|---|---|
| Yönetici | `admin@cilginyazilim.com` | `Admin1234` |
| Kullanıcı | `demo@cilginyazilim.com` | `Demo1234` |

### Demoda 60 saniyede neleri deneyebilirsiniz?

| # | Şunu deneyin | Perde arkasında ne oluyor? |
|---|---|---|
| **1** | **Sohbetler** sayfasını açın ve dört sayaca bakın | Sohbet · mesaj · toplam jeton · **tahmini maliyet**. Maliyet, model fiyat tablosundan hesaplanır; sabit bir sayı değildir |
| **2** | "PDO ile hazır ifadeler" sohbetini **Aç** | Üç soru-cevap. Kullanıcı mesajları sağda mavi, yanıtlar solda; kod örnekleri girintisi korunarak basılır |
| **3** | Her yanıtın altındaki **jeton satırına** bakın | `412 giriş · 386 çıkış jetonu · $0,011710`. Bu rakam uydurma değil: `giriş/1M × 5$ + çıkış/1M × 25$` |
| **4** | Aynı sohbette **giriş jetonlarının arttığını** izleyin: 412 → 890 → 1.503 | Model durum tutmaz; her istekte sohbetin **tamamı** yeniden gönderilir. Uzun sohbetlerin pahalılaşmasının sebebi tam olarak budur |
| **5** | Sohbet başlığındaki rozete bakın: `3.977 jeton · $0,04333` | Sohbet toplamı, mesaj toplamlarına **birebir eşittir**. Sayfa kendi içinde çelişmez |
| **6** | "N+1 sorgu problemi" sohbetini açıp **"düşünme özetini göster"**e basın | Modelin yanıtı kurmadan önceki muhakemesi. `<details>` ile açılır — JavaScript gerekmez |
| **7** | Kontrol panelindeki **API Kurulumu** kartına bakın | Anahtarın tanımlı olup olmadığı, model, efor seviyesi ve uç nokta tek bakışta |
| **8** | Aynı kartta "İstek **sunucudan** atılır" notunu okuyun | Anahtar tarayıcıya **asla** gönderilmez. Anahtarı JavaScript'e koyup doğrudan API'ye istek atmak, onu sayfayı açan herkese vermektir |
| **9** | Bir sohbeti **Sil**e basın | Mesajlar `ON DELETE CASCADE` ile birlikte gider; yetim satır kalmaz. Yalnızca **kendi** sohbetinizi silebilirsiniz |
| **10** | Telefonunuzdan açın | Balonlar genişler, sayaçlar alt alta dizilir; sayfa gövdesinde **yatay kaydırma yoktur** |

> **Demoda API anahtarı yoktur** — bu yüzden yeni bir yanıt üretilemez. Ekrandaki üç sohbet, arayüzü dolu hâliyle göstermek için **elle yazılmış** örneklerdir; jeton ve maliyet sayıları gerçek fiyat tablosuyla hesaplanmıştır. Kendi anahtarınızı `.env` dosyasına ekleyince gerçek yanıtlar gelmeye başlar.

### Demo alanı hakkında bilinmesi gerekenler

| Konu | Durum |
|---|---|
| **Veriler** | `database.sql` içindeki **51 kullanıcı + 3 örnek sohbet + 14 mesaj**. Gerçek kişi verisi yoktur. |
| **API anahtarı** | Demoda **yok**. Sohbet gönderme kapalıdır; var olan sohbetler okunabilir. |
| **Örnek sohbetler** | Gerçek API çağrısı değil, elle yazılmış örneklerdir. Jeton ve maliyet sayıları fiyat tablosuyla **tutarlıdır**. |
| **Sıfırlama** | Demo veritabanı **düzenli aralıklarla** başlangıç hâline döner. |
| **`APP_DEBUG`** | Canlıda **kendiliğinden `false`** — sunucu adından türetilir. |
| **Bağımlılık** | **Sıfır.** SDK yok, Composer yok, npm yok, CDN yok. İstek `cURL` ile atılır. |

---

## Bu Proje Nedir?

Bir yapay zekâ API'sini projeye bağlamak, ilk bakışta tek bir `curl` çağrısıdır. Gerçekte dört soru hemen ardından gelir ve dördü de hafife alınırsa pahalıya patlar:

- **Anahtar nerede duracak?** JavaScript'e koyarsanız, sayfayı açan herkes onu görür ve sizin hesabınıza istek atabilir.
- **Bu ay ne kadar harcadım?** Fatura ayın sonunda gelir; o zamana kadar hiçbir fikriniz yoktur.
- **Neden ikinci soru birinciden pahalı?** Çünkü model durum tutmaz: her istekte sohbetin tamamı yeniden gönderilir ve **yeniden ücretlendirilir**.
- **API hata verdiğinde ne olacak?** `429` ve `529` geçicidir, tekrar denenmelidir; `401` kalıcıdır, denemenin anlamı yoktur. İkisini ayırt etmeyen kod ya boşuna uğraşır ya da erken pes eder.

Bu proje dördünü de ele alan bir entegrasyon katmanı kuruyor. Anahtar `.env` içinde durur ve **yalnızca sunucuda** kullanılır. Her yanıtın giriş/çıkış jetonu ve **hesaplanmış maliyeti** kaydedilir. Sohbet geçmişi veritabanındadır. Hata yönetimi geçici ile kalıcıyı ayırır ve geçici olanları **üstel geri çekilmeyle** yeniden dener.

Modelin "düşünme" özeti de saklanır ve arayüzde istenirse açılır — yanıtın nasıl kurulduğunu görmek, çıktıyı değerlendirmenin en pratik yoludur.

`ClaudeClient` sınıfı hiçbir SDK kullanmaz; istek `cURL` ile atılır, yanıt elle ayrıştırılır.

**Kimler için uygun?**

- Projesine yapay zekâ özelliği ekleyecek, ama maliyeti kontrol altında tutmak isteyenler
- API anahtarını nereye koyacağını soran herkes
- Jeton muhasebesinin nasıl yapıldığını öğrenmek isteyenler
- SDK kurmadan, saf PHP ile Messages API'sine bağlanmak isteyenler
- Bootstrap 5 üzerine kurulu, tekrar kullanılabilir bir panel kalıbı arayanlar

Bu proje, **[Çılgın Yazılım Kütüphanesi](https://cilginyazilim.com/kutuphane)** altında yayınlanan açıklamalı, üretime hazır örneklerden biridir.

---

## İçindekiler

- [Canlı Demo](#canlı-demo)
- [Bu Proje Nedir?](#bu-proje-nedir)
- [Ekran Görüntüleri](#ekran-görüntüleri)
- [Bir isteğin yolculuğu](#bir-isteğin-yolculuğu)
- [Kritik Kararlar](#kritik-kararlar)
- [Neler Var?](#neler-var)
- [Maliyet nasıl hesaplanıyor?](#maliyet-nasıl-hesaplanıyor)
- [Hata yönetimi](#hata-yönetimi)
- [Güvenlik: Neyi, Nasıl Kapattık?](#güvenlik-neyi-nasıl-kapattık)
- [Kurulum](#kurulum)
- [Yapılandırma](#yapılandırma)
- [Dosya Yapısı](#dosya-yapısı)
- [Veritabanı Şeması](#veritabanı-şeması)
- [SSS](#sss)
- [Canlı Ortama Alırken](#canlı-ortama-alırken)
- [Sorun Giderme](#sorun-giderme)
- [Yol Haritası](#yol-haritası)
- [Katkı](#katkı)
- [Lisans](#lisans)

---

## Ekran Görüntüleri

| Giriş | Kontrol Paneli |
|---|---|
| <img src="docs/screenshots/01-giris.png" width="420" alt="Giriş ekranı"> | <img src="docs/screenshots/02-kontrol-paneli.png" width="420" alt="Kontrol paneli"> |

| Sohbetler | Sohbet Detayı |
|---|---|
| <img src="docs/screenshots/03-sohbetler.png" width="420" alt="Sohbet listesi"> | <img src="docs/screenshots/04-sohbet-detay.png" width="420" alt="Sohbet detayı"> |

<div align="center">
<img src="docs/screenshots/05-koyu-tema.png" width="720" alt="Koyu tema">
<br><sub>Koyu tema</sub>
<br><br>
<img src="docs/screenshots/06-mobil.png" width="300" alt="Mobil görünüm">
<br><sub>390px genişlikte mobil görünüm</sub>
</div>

---

## Bir isteğin yolculuğu

```
 TARAYICI                          SUNUCU (PHP)                    ANTHROPIC API
 ────────                          ────────────                    ─────────────
 Kullanıcı mesajı yazar
    │  POST /api/chat/send
    │  (CSRF jetonu ile)
    ▼
                          ai_messages'a "user" satırı
                                   │
                                   │  ClaudeClient::send()
                                   │
                                   │  1) SOHBETİN TAMAMINI topla
                                   │     (model durum tutmaz)
                                   │
                                   │  2) x-api-key: <ANAHTAR>   ◄── ANAHTAR
                                   │     anthropic-version: ...      BURADA KALIR
                                   │                                 tarayıcıya
                                   │  3) POST /v1/messages ──────────► GİTMEZ
                                   │                                     │
                                   │                                     ▼
                                   │  ◄────────────────────── 200 / 429 / 529 / 4xx
                                   │
                                   │  4) 429 · 529 · 5xx  → GEÇİCİ
                                   │     üstel geri çekilme + jitter
                                   │     en fazla 3 kez yeniden dene
                                   │
                                   │     401 · 400        → KALICI
                                   │     denemeden vazgeç, açıkla
                                   │
                                   │  5) Yanıtı ayrıştır:
                                   │     text · thinking · stop_reason
                                   │     refusal · usage
                                   │
                                   │  6) MALİYET HESAPLA
                                   │     giriş/1M×5$ + çıkış/1M×25$
                                   ▼
                          ai_messages'a "assistant" satırı
                          (metin · düşünme · jetonlar · maliyet)
                                   │
                          ai_conversations toplamları güncellenir
                                   │
    ◄──────────────────────── JSON yanıt
    ▼
 Balon çizilir, jeton satırı basılır
```

---

## Kritik Kararlar

### 1. API anahtarı **asla** tarayıcıya gitmez

İnternetteki örneklerin şaşırtıcı bir kısmı anahtarı JavaScript'e koyar ve `fetch` ile doğrudan API'ye gider. Bu, anahtarı **sayfayı açan herkese vermektir**: `F12 → Sources` yeterlidir. Anahtarınızla başkaları istek atar, faturayı siz ödersiniz.

Bu projede istek **her zaman sunucudan** atılır:

```
Tarayıcı → (kendi sunucunuz) → Anthropic API
```

Anahtar `.env` dosyasındadır, `.gitignore` içindedir ve PHP dışına hiç çıkmaz. Tarayıcı yalnızca kendi sunucunuzla konuşur — CSRF korumalı, oturum gerektiren bir uç nokta üzerinden.

### 2. Her mesajın jetonu ve maliyeti **kaydedilir**

Fatura ayın sonunda gelir. O zamana kadar hangi özelliğin ne kadar harcadığını bilmiyorsanız, maliyeti yönetemezsiniz.

```sql
ai_messages: input_tokens · output_tokens · cost_usd
ai_conversations: total_tokens · total_cost
```

Sayılar API'nin `usage` alanından gelir — tahmin değil, ölçümdür. Maliyet ise fiyat tablosundan hesaplanır.

Sohbet satırındaki toplam, mesajların toplamına **eşittir**; iki yerde tutulan bir sayının tutarsız kalması, güvenilmez bir gösterge demektir.

### 3. Giriş jetonu neden her mesajda artıyor?

Çünkü **model durum tutmaz**. "Önceki mesajımı hatırla" diye bir şey yoktur; her istekte sohbetin tamamını yeniden gönderirsiniz ve tamamı yeniden ücretlendirilir.

Örnek sohbette bunu somut görürsünüz: `412 → 890 → 1.503`. Üçüncü soru, birincinin dört katı giriş jetonu tüketir — soru aynı uzunlukta olsa bile.

Bu, uzun sohbetlerin neden pahalılaştığını açıklar. Pratik sonuç: geçmişi sınırsız göndermeyin. Uzun sohbetlerde eski mesajları özetleyip özeti göndermek yaygın ve etkili bir kalıptır.

### 4. Geçici hata ile kalıcı hata ayrılır

```php
$retryable = $status === 429 || $status === 529 || $status >= 500;
```

| Kod | Anlamı | Ne yapılır |
|---|---|---|
| `429` | Hız sınırı | **Geçici** — geri çekilmeyle yeniden dene |
| `529` | Servis yoğun | **Geçici** — yeniden dene |
| `5xx` | Sunucu hatası | **Geçici** — yeniden dene |
| `401` | Anahtar geçersiz | **Kalıcı** — denemenin anlamı yok |
| `400` | İstek hatalı | **Kalıcı** — istek düzeltilmeli |

Ayrım yapmayan kod iki yönden de kaybeder: `401` için üç kez deneyip zaman kaybeder, `429` için hiç denemeyip kullanıcıya gereksiz hata gösterir.

Yeniden denemeler **üstel geri çekilme + jitter** ile yapılır. Jitter (rastgele sapma) önemlidir: aynı anda sınıra çarpan yüz istemci aynı anda tekrar denerse sınıra yine birlikte çarparlar.

### 5. `stop_reason` ve `refusal` göz ardı edilmez

Bir yanıt "başarılı" dönebilir ama **tamamlanmamış** olabilir:

- `stop_reason = "max_tokens"` → yanıt cümlenin ortasında kesildi. Kullanıcıya eksik bir metni tam gibi göstermek yanlıştır.
- `refusal` dolu → model bilerek yanıtlamadı. Bu bir hata değildir ve "sunucu hatası" diye gösterilmemelidir.

İkisi de ayrı ele alınır ve arayüzde ayrı biçimde gösterilir.

`AI_MAX_TOKENS` varsayılanı bu yüzden **16.000**'dir. Düşük tutmak tasarruf gibi görünür ama kesilen yanıtı baştan sormak zorunda kalırsınız — yani iki kez ödersiniz.

### 6. "Düşünme" özeti saklanır, ama gizlenerek gösterilir

Modelin muhakemesi, çıktıyı değerlendirmenin en pratik yoludur: yanıt yanlışsa nerede saptığını görürsünüz.

Ama her zaman ekranda durması gerekmez; `<details>` etiketiyle katlanır. JavaScript gerekmez, erişilebilirlik kendiliğinden doğrudur.

### 7. Metin `pre-wrap` ile basılır, `nl2br()` ile değil

Model yanıtları kod örneği içerir ve kodun **girintisi** anlamlıdır.

```css
.cy-msg__bubble { white-space: pre-wrap; }
```

`pre-wrap` hem satır sonlarını hem girintileri korur, uzun satırları da kırar. Üstüne `nl2br()` eklemek her satır sonunu **iki kez** uygular ve paragraf araları iki katına çıkar — kod içeren yanıtlarda ekran dağılır.

> Aynı sebeple balonun `<div>` etiketiyle içeriği arasında **boşluk bırakılmaz**: `pre-wrap`, şablonun kendi girintisini de metin sayar ve her balonun ilk satırı sağa kaymış görünürdü.

### 8. Sohbet silinince mesajlar da gider

```sql
CONSTRAINT fk_msg_conv FOREIGN KEY (conversation_id)
    REFERENCES ai_conversations (id) ON DELETE CASCADE
```

Uygulama kodunda "önce mesajları sil, sonra sohbeti sil" yazılsaydı, o iki adımın arasında bir hata oluştuğunda yetim mesajlar kalırdı. Kısıtı veritabanına koymak bunu **imkânsız** kılar.

---

## Neler Var?

<table>
<tr><td valign="top" width="50%">

**API katmanı**

- Messages API'sine `cURL` ile istek — **SDK yok**
- Anahtar yalnızca sunucuda
- Üstel geri çekilme + jitter, en fazla 3 deneme
- Geçici (`429`/`529`/`5xx`) ve kalıcı hata ayrımı
- `stop_reason` ve `refusal` ayrı ele alınır
- Model ve efor seviyesi `.env`'den
- Yapılandırılabilir `max_tokens`

**Muhasebe**

- Mesaj başına giriş/çıkış jetonu
- Mesaj başına maliyet (fiyat tablosundan)
- Sohbet başına toplam jeton ve maliyet
- Panelde dört sayaç

</td><td valign="top" width="50%">

**Sohbet arayüzü**

- Sohbet listesi, jeton ve maliyet sütunlu
- Kullanıcı/model balonları, kod girintisi korunur
- Katlanabilir "düşünme özeti" (`<details>`)
- Mesaj altında jeton ve maliyet satırı
- Sohbet silme (yalnızca kendi sohbeti)
- Anahtar yoksa açıklayıcı uyarı

**Ortak altyapı**

- Oturum girişi, "beni hatırla", hız sınırı, CSRF
- CSP (`script-src 'self'`), `X-Frame-Options: DENY`
- Açık / koyu tema, hesaba kayıtlı
- Mobilde alt navigasyon, yatay kaydırma yok

</td></tr>
</table>

---

## Maliyet nasıl hesaplanıyor?

Fiyatlar milyon jeton başınadır ve `ClaudeClient::PRICING` tablosundadır:

| Model | Giriş (1M jeton) | Çıkış (1M jeton) |
|---|---|---|
| `claude-opus-5` | 5,00 $ | 25,00 $ |
| `claude-sonnet-5` | 2,00 $ | 10,00 $ |
| `claude-haiku-4-5` | 1,00 $ | 5,00 $ |

```php
public static function estimateCost(string $model, array $usage): float
{
    $rates = self::PRICING[$model] ?? null;
    if ($rates === null) { return 0.0; }

    return ($usage['input_tokens']  / 1000000) * $rates['input']
         + ($usage['output_tokens'] / 1000000) * $rates['output'];
}
```

Örnek: 412 giriş + 386 çıkış jetonu →

```
412 / 1.000.000 × 5  = 0,00206
386 / 1.000.000 × 25 = 0,00965
                       ─────────
                       0,01171 $
```

Demodaki sohbetlerde bu hesabı satır satır doğrulayabilirsiniz.

> **Bu bir tahmindir, fatura değildir.** Fiyatlar koda gömülüdür ve değişebilir; ayrıca ön belleğe **yazılan** ve ön bellekten **okunan** jetonlar farklı oranlarla ücretlendirilir — burada yalnızca normal giriş ve çıkış hesaba katılır. Yine de büyüklük mertebesini görmek çok değerlidir: "bu sohbet 0,04 dolar" bilgisi, model veya efor seviyesi değiştirme kararını somut hâle getirir.

Tanımlı olmayan bir model adı için `0.0` döner — yanlış bir rakam göstermektense hiç göstermemek yeğdir.

---

## Hata yönetimi

`ClaudeClient::send()` her durumda **anlaşılır** bir sonuç döndürür; ham bir istisna arayüze sızmaz.

| Durum | Kullanıcı ne görür |
|---|---|
| Anahtar tanımsız | "API anahtarı tanımlı değil" + `.env` satırı |
| `401` | "Anahtar geçersiz" — yeniden denenmez |
| `429` (3 denemeden sonra) | "Hız sınırına takıldınız ve yeniden denemeler de yetmedi." |
| `529` / `5xx` | "Servis geçici olarak yoğun" |
| `stop_reason = max_tokens` | Yanıt gösterilir + **kesildiği** belirtilir |
| `refusal` dolu | Modelin yanıtlamama gerekçesi, hata olarak değil |
| Ağ hatası / zaman aşımı | Geri çekilmeyle yeniden denenir, sonra açıklanır |

---

## Güvenlik: Neyi, Nasıl Kapattık?

| Açık | Tipik hatalı kod | Bu projede |
|---|---|---|
| **API anahtarı sızıntısı** | Anahtarı JavaScript'e koyup tarayıcıdan istek atmak | İstek **yalnızca sunucudan**; anahtar `.env` içinde, `.gitignore`'da |
| **Anahtarın depoya gitmesi** | `config.php` içine yazmak | `.env` dosyası depoya **gönderilmez**; `.env.example` şablondur |
| **Başkasının sohbetini okuma (IDOR)** | `WHERE id = :id` | Sorguya `user_id` de katılır; kullanıcı yalnızca **kendi** sohbetini görür ve siler |
| **Yetim kayıt** | Uygulama kodunda iki adımlı silme | `ON DELETE CASCADE` — veritabanı garanti eder |
| **Sonsuz maliyet** | `max_tokens` sınırsız / geçmiş sınırsız | `AI_MAX_TOKENS` ayarlanabilir; jeton ve maliyet **her mesajda** kaydedilir |
| **Boşuna yeniden deneme** | Her hatada 3 kez denemek | `401`/`400` **kalıcı** sayılır, denenmez |
| **Eşzamanlı yeniden deneme fırtınası** | Sabit aralıklı tekrar | Üstel geri çekilme + **jitter** |
| **XSS** | `echo $message['content']` | Sunucuda `e()`; ayrıca CSP `script-src 'self'` |
| **CSRF** | Gizli alan yok | Her POST'ta jeton; `hash_equals()` |
| **SQL enjeksiyonu** | `"... WHERE id = $id"` | Tüm sorgular hazır ifade; `ATTR_EMULATE_PREPARES = false` |
| **Hata sızıntısı** | İstisna mesajını ekrana basmak | `APP_DEBUG` **ortamdan türetilir**; canlıda ayrıntı gösterilmez |
| **Bozuk UTF-8'de sessiz JSON kaybı** | `json_encode($v)` | `JSON_INVALID_UTF8_SUBSTITUTE` |

---

## Kurulum

### Gereksinimler

| | |
|---|---|
| PHP | 8.0 veya üzeri · **`curl` eklentisi zorunlu** |
| MySQL / MariaDB | 5.7+ / 10.3+ |
| Web sunucusu | Apache (`mod_rewrite`) veya Nginx |
| Anthropic API anahtarı | [console.anthropic.com](https://console.anthropic.com) |

### Adımlar

```bash
git clone https://github.com/CilginYazilim/PHP-MySQL-Yapay-Zeka-Entegrasyonu-Claude-API-Jeton-Maliyet.git
cd PHP-MySQL-Yapay-Zeka-Entegrasyonu-Claude-API-Jeton-Maliyet

mysql -u root -p < database.sql
cp .env.example .env        # Windows: copy .env.example .env
```

`.env` dosyasına anahtarınızı ekleyin:

```env
ANTHROPIC_API_KEY=sk-ant-...
```

Açın: `http://localhost/ai-integration-system/` · Giriş: `admin@cilginyazilim.com` / `Admin1234`

> **Anahtar olmadan da açılır.** Var olan sohbetleri okuyabilir, arayüzü inceleyebilirsiniz; yalnızca yeni yanıt üretilemez. Panel bunu açıkça söyler.

---

## Yapılandırma

```env
APP_DEBUG=true          # silerseniz: yerelde açık, canlıda kapalı
APP_URL=
APP_PRETTY_URLS=true

DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=cy_ai
DB_USER=root
DB_PASS=

# --- ANTHROPIC (CLAUDE) API ---
ANTHROPIC_API_KEY=
AI_MODEL=claude-opus-5
AI_MAX_TOKENS=16000
AI_EFFORT=medium
```

| Ayar | Ne yapar |
|---|---|
| `AI_MODEL` | Kullanılacak model. Maliyet farkı büyüktür: `claude-haiku-4-5` çıkışta `claude-opus-5`'in beşte biri fiyattadır |
| `AI_MAX_TOKENS` | Yanıtın en fazla kaç jeton olacağı. **Düşük tutmayın**: sınıra çarpan yanıt cümlenin ortasında kesilir ve baştan sormanız gerekir |
| `AI_EFFORT` | Düşünme derinliği. Yükseltmek daha iyi yanıt verir ama hem süreyi hem maliyeti artırır |

---

## Dosya Yapısı

```
ai-integration-system/
│
├── index.php                     Ön denetleyici — TEK giriş noktası
├── database.sql                  Şema + 51 kullanıcı + 3 sohbet + 14 mesaj
├── .env.example
│
├── app/
│   ├── Core/
│   │   ├── ClaudeClient.php      ★ cURL isteği · geri çekilme · maliyet hesabı
│   │   ├── Auth.php · Session.php · Csrf.php · RateLimiter.php
│   │   ├── Database.php          PDO (EMULATE_PREPARES = false)
│   │   ├── Env.php               .env okuyucu + isLocalHost()
│   │   └── ...
│   │
│   ├── Http/Controllers/
│   │   ├── ChatController.php    Sohbet listesi, detay, silme
│   │   ├── Api/ChatApiController.php   Mesaj gönderme (AJAX)
│   │   └── Auth · Dashboard · User
│   │
│   ├── Repositories/ConversationRepository.php
│   └── Support/helpers.php
│
├── views/
│   ├── chat/index.php            Sohbet listesi + sayaçlar
│   ├── chat/show.php             ★ Balonlar · düşünme özeti · jeton satırı
│   └── ...
│
├── assets/
│   ├── css/  cilginyazilim.css (marka) · admin.css · feature.css
│   └── js/   chat.js · app.js · login.js
│
├── config/config.php
├── routes/web.php
└── docs/screenshots/
```

---

## Veritabanı Şeması

### `ai_conversations`

| Sütun | Tip | İşi |
|---|---|---|
| `id` | INT UNSIGNED | Birincil anahtar |
| `user_id` | INT UNSIGNED | Sohbet kimin (`ON DELETE CASCADE`) |
| `title` | VARCHAR(150) | Listede görünen ad |
| `total_tokens` | INT UNSIGNED | Giriş + çıkış toplamı |
| `total_cost` | DECIMAL(12,8) | Toplam maliyet (USD) |
| `created_at` · `updated_at` | DATETIME | Açılış ve son mesaj anı |

### `ai_messages`

| Sütun | Tip | İşi |
|---|---|---|
| `id` | BIGINT UNSIGNED | Birincil anahtar |
| `conversation_id` | INT UNSIGNED | Hangi sohbet (`ON DELETE CASCADE`) |
| `role` | ENUM('user','assistant') | Kim yazdı |
| `content` | MEDIUMTEXT | Mesaj metni |
| `thinking` | MEDIUMTEXT NULL | Modelin muhakemesi (varsa) |
| `input_tokens` · `output_tokens` | INT UNSIGNED | API'nin `usage` alanından |
| `cost_usd` | DECIMAL(12,8) | Bu mesajın maliyeti |
| `created_at` | DATETIME | Yazılma anı |

| Karar | Neden |
|---|---|
| `DECIMAL(12,8)`, `FLOAT` değil | Para hiçbir zaman kayan noktalı sayıyla tutulmaz; `FLOAT` toplamları sessizce kaydırır. Sekiz ondalık, tek bir mesajın kesirli sentini taşır |
| Toplamlar sohbet satırında **da** var | Liste sayfası her satır için mesaj tablosunu toplasaydı N+1 sorgu doğardı |
| `thinking` ayrı sütun | Yanıt metniyle karıştırılmamalı; ayrıca isteğe bağlı gösterilir |
| `role` `ENUM` | İki değer vardır ve üçüncüsü bir hatadır; veritabanı bunu kendisi engeller |
| Kullanıcı mesajında jeton `0` | Ücretlendirme, o mesajı da içeren API çağrısının **yanıt** satırında toplanır; iki yerde saymak toplamı bozardı |
| `ON DELETE CASCADE` | Sohbet silinince mesajlar da gider; yetim satır **imkânsız** olur |

---

## SSS

<details>
<summary><b>API anahtarını JavaScript'e koysam ne olur?</b></summary>

Sayfayı açan herkes onu görür. `F12 → Sources` yeterlidir; ağ sekmesinde istek başlıklarında da durur.

Sonuç: anahtarınızla başkaları istek atar, faturayı siz ödersiniz. Anahtarı iptal edip yenisini üretmekten başka çareniz kalmaz.

Kural basit: **API anahtarı sunucudan çıkmaz.** Tarayıcı yalnızca kendi sunucunuzla konuşur.
</details>

<details>
<summary><b>Maliyeti nasıl düşürürüm?</b></summary>

Üç kaldıraç var, etkileri sırasıyla:

1. **Model.** `claude-haiku-4-5` çıkışta `claude-opus-5`'in beşte biri fiyattadır. Sınıflandırma, özetleme, biçimlendirme gibi işlerde aradaki kalite farkı çoğu zaman fark edilmez.
2. **Gönderdiğiniz geçmiş.** Giriş jetonu her mesajda birikir. Uzun sohbetlerde eski mesajları özetleyip özeti gönderin.
3. **Efor seviyesi.** `AI_EFFORT` düşürmek düşünme jetonlarını azaltır.

Hangisinin işe yaradığını görmek için paneldeki maliyet sayacına bakın — ölçmeden optimize etmeyin.
</details>

<details>
<summary><b>Yanıt cümlenin ortasında kesiliyor</b></summary>

`stop_reason = "max_tokens"` demektir: yanıt `AI_MAX_TOKENS` sınırına çarptı.

`.env` içindeki değeri yükseltin. Düşük tutmak tasarruf gibi görünür ama kesilen yanıtı baştan sormak zorunda kalırsınız — yani iki kez ödersiniz. Varsayılan 16.000 çoğu iş için yeterlidir.

Çok uzun yanıtlar için akış (streaming) gerekir; bu örnekte akışsız istek kullanılmıştır.
</details>

<details>
<summary><b>Neden resmi SDK'yı kullanmadınız?</b></summary>

Bu proje Messages API'sinin **nasıl** çalıştığını göstermek için yazıldı: hangi başlıklar gidiyor, yanıt nasıl ayrışıyor, hangi hata kodu geçici.

`ClaudeClient.php` 473 satırdır ve her adımı yorumlarla açıklar. Ayrıca Composer bağımlılığı olmaması, paylaşımlı hosting'e atıp çalıştırabilmeniz demektir.

Üretimde SDK kullanmak isterseniz, artık onun ne yaptığını biliyor olacaksınız.
</details>

<details>
<summary><b>`429` alıyorum, ne yapmalıyım?</b></summary>

`429` hız sınırıdır ve **geçicidir**. Uygulama zaten üstel geri çekilmeyle en fazla 3 kez yeniden dener.

Üç deneme de yetmiyorsa istek hızınız hesabınızın sınırının üstünde demektir. Seçenekler: istekleri kuyruğa alıp yavaşça göndermek, daha küçük bir model kullanmak veya hesap limitinizi yükseltmek.

Kuyruk örneği için [İş Kuyruğu ve Worker Sistemi](https://cilginyazilim.com/kutuphane/php-queue-worker)'ne bakın.
</details>

<details>
<summary><b>Sohbet geçmişini sınırsız göndermek zorunda mıyım?</b></summary>

Hayır ve göndermemelisiniz. Model durum tutmadığı için her istekte tüm geçmiş yeniden ücretlendirilir; 50 mesajlık bir sohbette 51. soru çok pahalıya gelir.

Yaygın kalıp: son N mesajı ham gönderin, daha eskileri tek bir özet mesajına indirin. Özeti üretmek de bir API çağrısıdır ama bir kez ödenir.
</details>

---

## Canlı Ortama Alırken

- [ ] `.env` içinde `APP_DEBUG=false` (veya satırı tümüyle silin)
- [ ] `ANTHROPIC_API_KEY` **yalnızca** `.env` içinde; depoda değil
- [ ] `.env` dosyasının tarayıcıdan erişilemediğini doğrulayın (403 dönmeli)
- [ ] `AI_MODEL` ve `AI_MAX_TOKENS` bütçenize göre ayarlanmış mı?
- [ ] Kullanıcı başına günlük istek sınırı düşünün (bu örnekte yoktur)
- [ ] Maliyet sayaçlarını düzenli izleyin
- [ ] HTTPS zorunlu olsun
- [ ] Veritabanı için **root olmayan** bir kullanıcı açın
- [ ] Demo hesaplarının parolalarını değiştirin veya hesapları silin

---

## Sorun Giderme

| Belirti | Sebep | Çözüm |
|---|---|---|
| "API anahtarı tanımlı değil" | `.env` boş veya okunmuyor | `ANTHROPIC_API_KEY` satırını kontrol edin |
| `401` geliyor | Anahtar geçersiz veya iptal edilmiş | Konsoldan yeni anahtar üretin |
| `429` sürekli | Hız sınırı | İstekleri yavaşlatın veya kuyruğa alın |
| Yanıt kesiliyor | `max_tokens` sınırı | `AI_MAX_TOKENS` değerini yükseltin |
| Maliyet `0,00` görünüyor | `AI_MODEL` fiyat tablosunda yok | `ClaudeClient::PRICING` içine modeli ekleyin |
| Bağlantı zaman aşımı | `curl` eklentisi yok veya giden bağlantı kapalı | `php -m \| grep curl`; sunucu güvenlik duvarını kontrol edin |
| Türkçe karakterler bozuk | Bağlantı karakter kümesi | `charset=utf8mb4` olduğunu doğrulayın |
| Tüm adresler 404 | `mod_rewrite` kapalı | Açın veya `APP_PRETTY_URLS=false` yapın |

---

## Yol Haritası

- [ ] Akış (streaming) desteği — yanıt yazılırken göstermek
- [ ] Kullanıcı başına günlük jeton/maliyet kotası
- [ ] Uzun sohbetlerde otomatik özetleme
- [ ] Sistem istemi (system prompt) yönetimi
- [ ] Araç kullanımı (tool use) örneği
- [ ] Ön bellek (prompt caching) jetonlarının ayrı sayılması

---

## Katkı

Hata bildirimi ve öneriler için [issue açabilirsiniz](https://github.com/CilginYazilim/PHP-MySQL-Yapay-Zeka-Entegrasyonu-Claude-API-Jeton-Maliyet/issues).

## Lisans

[MIT](LICENSE) — ticari projelerinizde de özgürce kullanabilirsiniz.

---

<div align="center">

**[Çılgın Yazılım](https://cilginyazilim.com)** · [Kaynak Kütüphanesi](https://cilginyazilim.com/kutuphane) · [Tüm Örnekler](https://github.com/CilginYazilim)

</div>
