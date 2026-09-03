-- =====================================================================
--  Yapay Zekâ (AI) Entegrasyon Sistemi – VERİTABANI KURULUM DOSYASI
--  cilginyazilim.com
-- ---------------------------------------------------------------------
--  KURULUM
--    1. phpMyAdmin → "İçe Aktar" → bu dosyayı seçin
--    2. Ya da komut satırından:
--         mysql -u root -p < database.sql
--
--  Dosya kendi veritabanını OLUŞTURUR; önce elle veritabanı açmanıza
--  gerek yoktur.
--
--  DİKKAT: DROP TABLE komutları vardır. Aynı isimli bir veritabanınız
--  varsa tabloları SİLER. Var olan bir sisteme uygularken bu bölümü
--  atlayın.
-- =====================================================================

-- BAĞLANTI KARAKTER KÜMESİ
-- ---------------------------------------------------------------
--  Bu satır olmadan "mysql -u root < database.sql" komutu, dosyayı
--  sunucunun VARSAYILAN karakter kümesiyle (çoğu Windows kurulumunda
--  latin1) okur. Sonuç sessiz bir bozulmadır: "GÜL" veritabanına
--  "GÃœL" olarak yazılır ve hata da vermez.
--
--  phpMyAdmin bunu kendisi ayarladığı için sorun oradan içe
--  aktarırken görülmez — bu da hatayı bulmayı zorlaştırır.
SET NAMES utf8mb4;

-- utf8mb4: Türkçe karakterlerin yanı sıra emoji de saklanabilir.
-- utf8mb4_unicode_ci: Büyük/küçük harf ve aksan duyarsız karşılaştırma.
CREATE DATABASE IF NOT EXISTS `cy_ai`
    DEFAULT CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `cy_ai`;

-- Yabancı anahtar bağımlılığı olduğu için tabloları silmeden önce
-- yabancı anahtar denetimini kapatıyoruz. Aksi halde "users" tablosunu
-- silmeye çalışırken ona bağlı bir tablo yüzünden hata alırdık ve
-- dosyayı ikinci kez içe aktarmak imkânsız olurdu.
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `activity_log`;
DROP TABLE IF EXISTS `remember_tokens`;
DROP TABLE IF EXISTS `login_attempts`;
DROP TABLE IF EXISTS `users`;

SET FOREIGN_KEY_CHECKS = 1;


-- ---------------------------------------------------------------
--  1) users – Uygulama kullanıcıları
-- ---------------------------------------------------------------
CREATE TABLE `users` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,

  `name`          VARCHAR(150) NOT NULL,
  `surname`       VARCHAR(150) NOT NULL,

  -- 190 karakter: utf8mb4 + InnoDB'de bir sütunun UNIQUE indeks
  -- alabileceği güvenli üst sınırdır (eski MySQL sürümlerinde 767 bayt).
  `email`         VARCHAR(190) NOT NULL,

  -- password_hash() çıktısı. Bugün 60 karakter (bcrypt) ama ileride
  -- daha uzun algoritmalara geçilebilsin diye 255 bıraktık.
  -- ASLA düz parola saklanmaz.
  `password`      VARCHAR(255) NOT NULL,

  -- 0 = hesap pasif; giriş yapamaz.
  `is_active`     TINYINT(1)   NOT NULL DEFAULT 1,

  -- Arayüz teması HESABA bağlıdır: kullanıcı hangi bilgisayardan
  -- girerse girsin kendi tercihini görür.
  `theme`         ENUM('light','dark') NOT NULL DEFAULT 'light',

  `last_login_at` DATETIME     NULL DEFAULT NULL,
  `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP    NULL DEFAULT NULL,

  PRIMARY KEY (`id`),

  -- BENZERSİZ E-POSTA: Aynı adresle iki hesap açılmasını veritabanı
  -- seviyesinde engeller. Uygulama da kontrol eder ama son söz
  -- burasıdır; iki istek aynı anda gelse bile çakışma olmaz.
  UNIQUE KEY `uk_users_email` (`email`),

  -- İNDEKSLER: SAYFALAMA için kritiktir.
  -- "ORDER BY id DESC LIMIT ... OFFSET ..." birincil anahtarı kullanır;
  -- ad/soyad aramaları ve durum filtresi de indekslenmiştir.
  KEY `idx_users_name`    (`name`),
  KEY `idx_users_surname` (`surname`),
  KEY `idx_users_active`  (`is_active`),
  KEY `idx_users_created` (`created_at`)
)
ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------
--  2) remember_tokens – "Beni hatırla" anahtarları
-- ---------------------------------------------------------------
--  ÇEREZDE NE VAR?   "selector:validator"
--  TABLODA NE VAR?   selector düz, validator'ın SHA-256 ÖZETİ
--
--  Validator'ı düz saklamıyoruz: veritabanı sızsa bile o özetlerden
--  çalışan bir çereze geri dönülemez. Parolayı neden özetliyorsak
--  aynı gerekçe geçerlidir.
CREATE TABLE `remember_tokens` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`        INT UNSIGNED NOT NULL,
  `selector`       CHAR(18)     NOT NULL,
  `validator_hash` CHAR(64)     NOT NULL,
  `expires_at`     DATETIME     NOT NULL,
  `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_remember_selector` (`selector`),
  KEY `idx_remember_expires` (`expires_at`),

  -- ON DELETE CASCADE: Kullanıcı silinince anahtarları da silinir.
  CONSTRAINT `fk_remember_user`
      FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
)
ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------
--  3) login_attempts – Kaba kuvvet (brute force) koruması
-- ---------------------------------------------------------------
--  Hatalı giriş denemeleri burada sayılır. Sayaç OTURUMDA değil
--  VERİTABANINDA tutulur; aksi halde saldırgan çerezini silerek
--  sayacı sıfırlayabilirdi.
CREATE TABLE `login_attempts` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

  -- E-posta + IP birleşiminin SHA-256 özeti. Düz e-posta yazsaydık,
  -- veritabanı sızdığında "hangi hesaplara saldırıldı" bilgisi de sızardı.
  `identifier`   CHAR(64)     NOT NULL,

  -- 45 karakter: IPv6 adresinin en uzun metin biçimine yeter.
  `ip`           VARCHAR(45)  NOT NULL DEFAULT '',
  `attempted_at` DATETIME     NOT NULL,

  PRIMARY KEY (`id`),
  -- Sorgu her zaman "şu anahtar için son X saniyedeki denemeler"
  -- biçiminde olduğu için bileşik indeks ideal.
  KEY `idx_attempts_lookup` (`identifier`, `attempted_at`)
)
ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------
--  4) activity_log – İşlem günlüğü (audit log)
-- ---------------------------------------------------------------
CREATE TABLE `activity_log` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

  -- NULL olabilir: hatalı giriş denemesinde kullanıcı bilinmez.
  `user_id`     INT UNSIGNED NULL DEFAULT NULL,

  `action`      VARCHAR(40)  NOT NULL,
  `description` VARCHAR(255) NOT NULL DEFAULT '',
  `ip`          VARCHAR(45)  NOT NULL DEFAULT '',
  `created_at`  DATETIME     NOT NULL,

  PRIMARY KEY (`id`),
  KEY `idx_log_user`    (`user_id`),
  KEY `idx_log_created` (`created_at`),

  -- ON DELETE SET NULL: Kullanıcı silinse bile günlük satırı KALIR,
  -- yalnızca kime ait olduğu bilgisi boşalır. Günlük silinmemelidir.
  CONSTRAINT `fk_log_user` FOREIGN KEY (`user_id`)
      REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
)
ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;

-- ===============================================================
--  DEMO VERİLER
-- ---------------------------------------------------------------
--  GİRİŞ BİLGİLERİ
--    Yönetici  : admin@cilginyazilim.com / Admin1234
--    Kullanıcı : demo@cilginyazilim.com  / Demo1234
--    Diğer örnek kullanıcıların tamamı   : Demo1234
--
--  !!! CANLI SUNUCUYA ALIRKEN BU PAROLALARI MUTLAKA DEĞİŞTİRİN !!!
--
--  Parolalar bcrypt ile özetlenmiştir (password_hash / PASSWORD_DEFAULT).
--  Özet geri çevrilemez; veritabanı sızsa bile parolalar okunamaz.
--
--  NEDEN 51 KULLANICI?
--  Sayfalamayı gerçekten görebilmek için. 20'şer kayıtla 3 sayfa
--  oluşur; sayfa boyutunu 10'a düşürürseniz 6 sayfa çıkar ve
--  sayfa numaralarındaki "…" kısaltmasını da görebilirsiniz.
-- ===============================================================

SET @demo_pass  = '$2y$10$Pum/vll0wIHFZF4scJXJTubeqAJSc/lk6rmT8ysG3NEpWXqsdSdua'; -- Demo1234
SET @admin_pass = '$2y$10$3/.dKkIFNGVfVblONMvs.uJhs/7hp4Ivymrl0XxNrG5RnpF8I3nL6'; -- Admin1234

INSERT INTO `users` (`id`, `name`, `surname`, `email`, `password`, `is_active`, `created_at`)
VALUES
(1, 'Sistem', 'Yöneticisi', 'admin@cilginyazilim.com', @admin_pass, 1, '2025-01-01 09:00:00'),
(2, 'Demo', 'Kullanıcı', 'demo@cilginyazilim.com', @demo_pass, 1, '2025-01-01 09:05:00');

-- Sayfalamayı denemek için örnek kullanıcılar.
-- Birkaçı bilerek PASİF bırakılmıştır; durum filtresini deneyebilirsiniz.
INSERT INTO `users` (`id`, `name`, `surname`, `email`, `password`, `is_active`, `created_at`)
VALUES
(3, 'Evren', 'ÇILGIN', 'evren.cilgin@ornek.com', @demo_pass, 1, '2025-01-06 19:34:27'),
(4, 'Taha', 'BAYAR', 'taha.bayar@ornek.com', @demo_pass, 1, '2025-01-07 08:42:28'),
(5, 'Zeynep', 'TURAN', 'zeynep.turan@ornek.com', @demo_pass, 1, '2025-01-08 10:59:56'),
(6, 'Mustafa', 'YILMAZ', 'mustafa.yilmaz@ornek.com', @demo_pass, 1, '2025-01-08 18:44:32'),
(7, 'Elif', 'KAYA', 'elif.kaya@ornek.com', @demo_pass, 1, '2025-01-09 17:47:28'),
(8, 'Ahmet', 'DEMİR', 'ahmet.demir@ornek.com', @demo_pass, 1, '2025-01-11 11:49:05'),
(9, 'Ayşe', 'ŞAHİN', 'ayse.sahin@ornek.com', @demo_pass, 1, '2025-01-12 21:17:02'),
(10, 'Mehmet', 'ÇELİK', 'mehmet.celik@ornek.com', @demo_pass, 1, '2025-01-14 04:24:09'),
(11, 'Fatma', 'YILDIZ', 'fatma.yildiz@ornek.com', @demo_pass, 0, '2025-01-15 23:58:29'),
(12, 'Emre', 'YILDIRIM', 'emre.yildirim@ornek.com', @demo_pass, 1, '2025-01-16 16:30:24'),
(13, 'Selin', 'ÖZTÜRK', 'selin.ozturk@ornek.com', @demo_pass, 1, '2025-01-17 17:06:42'),
(14, 'Burak', 'AYDIN', 'burak.aydin@ornek.com', @demo_pass, 1, '2025-01-17 18:08:06'),
(15, 'Merve', 'ÖZDEMİR', 'merve.ozdemir@ornek.com', @demo_pass, 1, '2025-01-19 01:30:32'),
(16, 'Onur', 'ARSLAN', 'onur.arslan@ornek.com', @demo_pass, 1, '2025-01-19 20:00:51'),
(17, 'Ceren', 'DOĞAN', 'ceren.dogan@ornek.com', @demo_pass, 1, '2025-01-20 14:09:31'),
(18, 'Kaan', 'KILIÇ', 'kaan.kilic@ornek.com', @demo_pass, 1, '2025-01-20 22:05:15'),
(19, 'Büşra', 'ASLAN', 'busra.aslan@ornek.com', @demo_pass, 1, '2025-01-21 08:36:59'),
(20, 'Serkan', 'ÇETİN', 'serkan.cetin@ornek.com', @demo_pass, 1, '2025-01-22 16:06:12'),
(21, 'Gizem', 'KARA', 'gizem.kara@ornek.com', @demo_pass, 1, '2025-01-24 10:30:31'),
(22, 'Barış', 'KOÇ', 'baris.koc@ornek.com', @demo_pass, 1, '2025-01-25 07:19:52'),
(23, 'Deniz', 'KURT', 'deniz.kurt@ornek.com', @demo_pass, 1, '2025-01-26 01:28:52'),
(24, 'Hakan', 'ÖZKAN', 'hakan.ozkan@ornek.com', @demo_pass, 1, '2025-01-27 19:52:10'),
(25, 'İrem', 'ŞİMŞEK', 'irem.simsek@ornek.com', @demo_pass, 1, '2025-01-29 12:43:32'),
(26, 'Yusuf', 'POLAT', 'yusuf.polat@ornek.com', @demo_pass, 1, '2025-01-29 20:10:46'),
(27, 'Melis', 'ÖZER', 'melis.ozer@ornek.com', @demo_pass, 1, '2025-01-30 22:06:37'),
(28, 'Cem', 'KORKMAZ', 'cem.korkmaz@ornek.com', @demo_pass, 1, '2025-01-31 03:44:01'),
(29, 'Esra', 'ÇAKIR', 'esra.cakir@ornek.com', @demo_pass, 0, '2025-01-31 18:25:27'),
(30, 'Volkan', 'ERDOĞAN', 'volkan.erdogan@ornek.com', @demo_pass, 1, '2025-02-01 08:14:52'),
(31, 'Şeyma', 'GÜNEŞ', 'seyma.gunes@ornek.com', @demo_pass, 1, '2025-02-01 14:27:09'),
(32, 'Uğur', 'AKSOY', 'ugur.aksoy@ornek.com', @demo_pass, 1, '2025-02-03 03:12:55'),
(33, 'Pınar', 'BULUT', 'pinar.bulut@ornek.com', @demo_pass, 1, '2025-02-04 20:02:24'),
(34, 'Tolga', 'TAŞ', 'tolga.tas@ornek.com', @demo_pass, 1, '2025-02-04 21:02:35'),
(35, 'Nazlı', 'KAPLAN', 'nazli.kaplan@ornek.com', @demo_pass, 1, '2025-02-06 16:07:07'),
(36, 'Görkem', 'SOYLU', 'gorkem.soylu@ornek.com', @demo_pass, 1, '2025-02-08 01:23:35'),
(37, 'Damla', 'ATEŞ', 'damla.ates@ornek.com', @demo_pass, 1, '2025-02-09 07:56:33'),
(38, 'Berk', 'GÜLER', 'berk.guler@ornek.com', @demo_pass, 1, '2025-02-10 02:16:27'),
(39, 'Sude', 'BOZKURT', 'sude.bozkurt@ornek.com', @demo_pass, 1, '2025-02-10 18:54:39'),
(40, 'Alper', 'TEKİN', 'alper.tekin@ornek.com', @demo_pass, 1, '2025-02-11 10:55:00'),
(41, 'Ebru', 'ACAR', 'ebru.acar@ornek.com', @demo_pass, 1, '2025-02-13 09:17:40'),
(42, 'Sinan', 'BARAN', 'sinan.baran@ornek.com', @demo_pass, 1, '2025-02-15 08:26:15'),
(43, 'Aslı', 'SEZER', 'asli.sezer@ornek.com', @demo_pass, 1, '2025-02-16 06:25:42'),
(44, 'Furkan', 'KOCA', 'furkan.koca@ornek.com', @demo_pass, 0, '2025-02-17 21:37:35'),
(45, 'Nesrin', 'UZUN', 'nesrin.uzun@ornek.com', @demo_pass, 1, '2025-02-18 17:36:38'),
(46, 'Okan', 'AVCI', 'okan.avci@ornek.com', @demo_pass, 1, '2025-02-19 06:17:27'),
(47, 'Tuğçe', 'KESKİN', 'tugce.keskin@ornek.com', @demo_pass, 1, '2025-02-20 05:21:28'),
(48, 'Murat', 'ÜNAL', 'murat.unal@ornek.com', @demo_pass, 1, '2025-02-21 08:10:22'),
(49, 'Yasemin', 'GÜL', 'yasemin.gul@ornek.com', @demo_pass, 1, '2025-02-22 02:55:23'),
(50, 'Halil', 'DURMAZ', 'halil.durmaz@ornek.com', @demo_pass, 1, '2025-02-22 18:23:50'),
(51, 'Beyza', 'SARI', 'beyza.sari@ornek.com', @demo_pass, 1, '2025-02-23 10:36:41');


-- ---------------------------------------------------------------
--  Örnek işlem günlüğü
-- ---------------------------------------------------------------
INSERT INTO `activity_log` (`user_id`, `action`, `description`, `ip`, `created_at`) VALUES
(1, 'login',            'Panele giriş yapıldı.', '127.0.0.1', NOW() - INTERVAL 5 MINUTE),
(2, 'login',            'Panele giriş yapıldı.', '127.0.0.1', NOW() - INTERVAL 5 HOUR),
(1, 'password_changed', 'Parola değiştirildi.',  '127.0.0.1', NOW() - INTERVAL 3 DAY);



-- ===============================================================
--  PROJEYE ÖZEL TABLOLAR
-- ===============================================================

-- Dosyanın ikinci kez içe aktarılabilmesi için önce siliyoruz.
-- Sıra önemlidir: ai_messages, ai_conversations'a bağlıdır.
DROP TABLE IF EXISTS `ai_messages`;
DROP TABLE IF EXISTS `ai_conversations`;

-- ---------------------------------------------------------------
--  ai_conversations – Sohbetler
-- ---------------------------------------------------------------
--  NEDEN GEÇMİŞİ BİZ SAKLIYORUZ?
--  Messages API DURUMSUZDUR: sunucu önceki mesajlarınızı tutmaz.
--  Her istekte tüm konuşmayı yeniden gönderirsiniz. "Model beni
--  hatırlamıyor" şikâyetinin sebebi neredeyse her zaman geçmişi
--  göndermemektir.
--
--  Bu aynı zamanda MALİYETİN kaynağıdır: konuşma uzadıkça her
--  istekte gönderilen giriş jetonu artar.
-- ---------------------------------------------------------------
CREATE TABLE `ai_conversations` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`      INT UNSIGNED NOT NULL,

  `title`        VARCHAR(150) NOT NULL,

  -- Toplamları burada tutmak, sohbet listesini her açışta tüm
  -- mesajları toplamaktan kurtarır. Liste büyüdükçe fark açılır.
  `total_tokens` INT UNSIGNED NOT NULL DEFAULT 0,

  -- DECIMAL, FLOAT DEĞİL. Para hesabında kayan nokta kullanmak
  -- yuvarlama hatası biriktirir; 0.1 + 0.2 = 0.30000000000000004
  -- klasiği burada da geçerlidir.
  -- (12,8): 9999,99999999 dolara kadar, sekiz basamak hassasiyet —
  -- tek bir istek 0,000015 dolar tutabildiği için gerekli.
  `total_cost`   DECIMAL(12,8) NOT NULL DEFAULT 0,

  `created_at`   DATETIME NOT NULL,
  `updated_at`   DATETIME NOT NULL,

  PRIMARY KEY (`id`),

  -- Liste sorgusu: "bu kullanıcının sohbetleri, en yeniden eskiye".
  -- Bileşik indeks tam bu erişim deseni içindir.
  KEY `idx_conv_user` (`user_id`, `updated_at`),

  CONSTRAINT `fk_conv_user` FOREIGN KEY (`user_id`)
      REFERENCES `users` (`id`) ON DELETE CASCADE
)
ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------
--  ai_messages – Mesajlar
-- ---------------------------------------------------------------
CREATE TABLE `ai_messages` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `conversation_id` INT UNSIGNED NOT NULL,

  -- API yalnızca bu iki rolü kabul eder. ENUM, veritabanı
  -- seviyesinde de beyaz liste sağlar.
  `role`            ENUM('user','assistant') NOT NULL,

  -- MEDIUMTEXT: 16 MB. Uzun kod blokları içeren yanıtlar TEXT'in
  -- 64 KB sınırını zorlayabilir ve MySQL fazlasını SESSİZCE keser.
  `content`         MEDIUMTEXT NOT NULL,

  -- Düşünme özeti (thinking display = "summarized" ile gelir).
  -- Modele GERİ GÖNDERİLMEZ; yalnızca ekranda gösterilir.
  `thinking`        MEDIUMTEXT NULL DEFAULT NULL,

  -- Jeton sayaçları ve maliyet: "bu yanıt bize ne kadara mal oldu?"
  `input_tokens`    INT UNSIGNED NOT NULL DEFAULT 0,
  `output_tokens`   INT UNSIGNED NOT NULL DEFAULT 0,
  `cost_usd`        DECIMAL(12,8) NOT NULL DEFAULT 0,

  `created_at`      DATETIME NOT NULL,

  PRIMARY KEY (`id`),

  -- Hem ekranda gösterme hem bağlam penceresi bu sırayı kullanır.
  KEY `idx_msg_conv` (`conversation_id`, `id`),

  -- Sohbet silinince mesajları da silinir.
  CONSTRAINT `fk_msg_conv` FOREIGN KEY (`conversation_id`)
      REFERENCES `ai_conversations` (`id`) ON DELETE CASCADE
)
ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


-- ===============================================================
--  ÖRNEK SOHBETLER
-- ---------------------------------------------------------------
--  NEDEN VARLAR?
--  Bu uygulama API anahtarı olmadan yanıt üretemez. Anahtarsız
--  açılan bir demo bomboş bir liste gösterir; jeton sayacının,
--  maliyet hesabının ve "düşünme" bloğunun neye benzediğini kimse
--  göremez. Aşağıdaki üç sohbet, arayüzün tamamını dolu hâliyle
--  anlatmak içindir.
--
--  BUNLAR GERÇEK API ÇAĞRISI DEĞİLDİR — elle yazılmış örneklerdir.
--  Kendi anahtarınızı .env dosyasına ekleyip "Yeni sohbet" dediğiniz
--  anda gerçek yanıtlar ve gerçek jeton sayıları gelmeye başlar.
--
--  JETON VE MALİYET SAYILARI UYDURMA DEĞİL, HESAPLANMIŞTIR:
--  ClaudeClient::PRICING tablosundaki claude-opus-5 fiyatlarıyla
--  (1M giriş jetonu 5 $, 1M çıkış jetonu 25 $) birebir tutarlıdır:
--
--      maliyet = giriş/1.000.000 * 5  +  çıkış/1.000.000 * 25
--
--  Böylece ekranda gördüğünüz rakam, kendi sohbetinizde göreceğiniz
--  hesabın aynısıdır. Sohbet satırındaki toplamlar da mesajların
--  toplamına eşittir; sayfa kendi içinde çelişmez.
--
--  GİRİŞ JETONU HER MESAJDA NEDEN ARTIYOR?
--  Model durum tutmaz: her istekte sohbetin TAMAMI yeniden gönderilir.
--  Bu yüzden ikinci sorunun girişi birincinin iki katına yakındır.
--  Uzun sohbetlerin pahalılaşmasının sebebi tam olarak budur ve
--  örnek veri bunu bilerek gösterir.
--
--  Kullanıcı mesajlarında jeton 0'dır: ücretlendirme, o mesajı da
--  içeren API çağrısının yanıt satırında toplanır.
-- ===============================================================

INSERT INTO `ai_conversations`
    (`id`, `user_id`, `title`, `total_tokens`, `total_cost`, `created_at`, `updated_at`)
VALUES
(1, 1, 'PDO ile hazır ifadeler (prepared statements)',
 3977, 0.04332500, NOW() - INTERVAL 2 HOUR,  NOW() - INTERVAL 2 HOUR),
(2, 1, 'N+1 sorgu problemi nasıl fark edilir?',
 2519, 0.03343500, NOW() - INTERVAL 1 DAY,   NOW() - INTERVAL 1 DAY),
(3, 2, 'Cron mu, iş kuyruğu mu?',
 1938, 0.02505000, NOW() - INTERVAL 4 DAY,   NOW() - INTERVAL 4 DAY);

ALTER TABLE `ai_conversations` AUTO_INCREMENT = 11;


INSERT INTO `ai_messages`
    (`conversation_id`, `role`, `content`, `thinking`,
     `input_tokens`, `output_tokens`, `cost_usd`, `created_at`)
VALUES
(1, 'user', 'PHP''de PDO kullanıyorum. Hazır ifade (prepared statement) kullanmak SQL enjeksiyonunu tam olarak nasıl engelliyor? Sadece tırnak kaçışlamaktan farkı ne?', NULL, 0, 0, 0.00000000, NOW() - INTERVAL 2 HOUR),

(1, 'assistant', 'Farkı şu: kaçışlama metni onarmaya çalışır, hazır ifade ise veriyi sorgudan tamamen ayırır.\n\nKaçışlamada sorgu ile veri hâlâ aynı dizede birleşir. Sunucuya giden şey tek bir metindir ve o metni ayrıştıran şey SQL çözümleyicisidir. Kaçışlama fonksiyonu bir karakteri gözden kaçırırsa (karakter kümesi uyuşmazlığı klasik örnektir) veri, sorgunun bir parçası hâline gelir.\n\nHazır ifadede sorgu ve veri sunucuya AYRI GİDER. Önce sorgu şablonu gönderilir ve orada ayrıştırılır: yer tutucunun bir DEĞER olduğu daha o anda kesinleşir. Sonra değerler gönderilir. Değerin içinde ne olursa olsun sorgu planı artık değişemez, çünkü plan zaten kurulmuştur.\n\nBir uyarı: PDO varsayılan olarak EMÜLASYON yapar; yani sorguyu kendisi birleştirip tek parça gönderir. Gerçek ayrımı istiyorsanız bunu kapatmanız gerekir:\n\n    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);', NULL, 412, 386, 0.01171000, NOW() - INTERVAL 2 HOUR),

(1, 'user', 'Emülasyonu kapatınca değişen başka bir şey oluyor mu? Bir yerde LIMIT ile sorun çıktığını okumuştum.', NULL, 0, 0, 0.00000000, NOW() - INTERVAL 2 HOUR),

(1, 'assistant', 'Evet, iki şey değişir ve ikisi de sizi şaşırtabilir.\n\n1) Tipler artık gerçekten tip. Emülasyon açıkken her şey dize olarak gömülür ve LIMIT ? çoğu zaman çalışır. Kapalıyken sürücü değeri dize olarak bağlar, MySQL de LIMIT ''10'' ifadesini reddeder. Çözüm, sayfa boyutunu yer tutucuyla değil, (int) ile doğrulayıp sorguya gömmektir — tamsayıya çevrilmiş bir değer enjeksiyon taşıyamaz.\n\n2) Aynı adlı yer tutucu iki kez kullanılamaz. Emülasyon açıkken :q metinde iki yerde geçebilir; kapalıyken HY093 hatası alırsınız. :q1 ve :q2 diye ayırmanız gerekir. Bu, geliştirmede fark edilmeyip canlıda patlayan tipik bir farktır.\n\nYine de kapatın: emülasyon açıkken hazır ifadenin asıl güvenlik faydasını kullanmıyorsunuz.', NULL, 890, 512, 0.01725000, NOW() - INTERVAL 2 HOUR),

(1, 'user', 'Peki tablo veya sütun adını dışarıdan almam gerekirse? Onu yer tutucuyla bağlayamıyorum.', NULL, 0, 0, 0.00000000, NOW() - INTERVAL 2 HOUR),

(1, 'assistant', 'Bağlayamazsınız, çünkü yer tutucu bir DEĞER içindir; tanımlayıcı sorgunun yapısını değiştirir ve yapı, ayrıştırma anında kesinleşmiş olmalıdır.\n\nTek doğru yöntem BEYAZ LİSTEDİR: dışarıdan gelen adı, kodda yazılı izinli adlar listesinde arayın ve listedeki değeri kullanın.\n\n    $izinli = [''ad'', ''fiyat'', ''olusturma_tarihi''];\n    $sutun  = in_array($gelen, $izinli, true) ? $gelen : ''olusturma_tarihi'';\n\nDikkat: gelen değeri temizleyip kullanmak değil, LİSTEDEKİ değeri kullanmak esastır. Sıralama yönü için de aynısı geçerli: ASC/DESC dışında hiçbir şeyi kabul etmeyin.', NULL, 1503, 274, 0.01436500, NOW() - INTERVAL 2 HOUR),

(2, 'user', 'Listeleme sayfam yavaşladı. 50 kayıt gösteriyorum ama sayfa 3 saniyede açılıyor. Nereden başlamalıyım?', NULL, 0, 0, 0.00000000, NOW() - INTERVAL 1 DAY),

(2, 'assistant', 'Tarif ettiğiniz belirti — az sayıda satır, buna rağmen uzun süre — neredeyse her zaman N+1 sorgu problemidir.\n\nOlan şu: 50 kaydı tek sorguyla çekiyorsunuz, sonra döngünün içinde her kayıt için ilgili veriyi (yazar, kategori, sayaç) ayrı ayrı soruyorsunuz. 1 + 50 = 51 sorgu. Her biri tek başına hızlıdır; toplamı yavaştır, çünkü ödediğiniz şey sorgunun kendisi değil, 51 kez gidiş dönüştür.\n\nÖnce ÖLÇÜN, tahmin etmeyin. Sorgu sayacı koymak yeterlidir: sayfa açılışında kaç sorgu çalıştığını yazdırın. Sayı satır sayınızla birlikte büyüyorsa teşhis kesindir.\n\nÇözüm iki tanedir:\n  • JOIN ile tek sorguda çekmek (ilişki birebirse en temizi)\n  • Toplu yükleme: döngüden önce tüm id''leri toplayıp tek WHERE IN (...) sorgusu yapmak, sonra sonucu id''ye göre dizine almak\n\nİkincisi çoğu listede daha iyidir: JOIN, çoka-bir ilişkide satırları çoğaltır ve bu kez veriyi PHP tarafında tekilleştirmeniz gerekir.', 'Kullanıcı "yavaş" diyor ama satır sayısı düşük. Bu ayrım önemli: satır sayısı yüksek olsaydı indeks veya sayfalama sorunu derdim. 50 satır için 3 saniye, veri hacmiyle değil sorgu SAYISIYLA orantılı bir maliyete işaret eder — yani N+1.\n\nÖnce ölçmesini söylemeliyim; doğrudan çözüm vermek, teşhisi doğrulamadan ilaç yazmak olur. Sonra iki çözümü de vermeli ve hangisinin ne zaman uygun olduğunu ayırt etmeliyim, çünkü JOIN''i körlemesine önermek çoka-bir ilişkilerde satır çoğalması sorununu doğurur.', 356, 604, 0.01688000, NOW() - INTERVAL 1 DAY),

(2, 'user', 'Sayacı koydum, 63 sorgu çıktı. WHERE IN yöntemini nasıl kurgularım?', NULL, 0, 0, 0.00000000, NOW() - INTERVAL 1 DAY),

(2, 'assistant', '63 sorgu teşhisi doğruluyor. Kurgu üç adımdır:\n\n1) Ana listeyi çekin ve ilişkili id''leri toplayın:\n\n    $kayitlar = $stmt->fetchAll();\n    $yazarIds = array_values(array_unique(array_column($kayitlar, ''yazar_id'')));\n\n2) Tek sorguyla hepsini çekin. Yer tutucuları DİNAMİK üretin; diziyi doğrudan gömmeyin:\n\n    $yer  = implode('','', array_fill(0, count($yazarIds), ''?''));\n    $stmt = $pdo->prepare("SELECT id, ad FROM yazarlar WHERE id IN ($yer)");\n    $stmt->execute($yazarIds);\n\n3) Sonucu id''ye göre dizine alın ve döngüde oradan okuyun:\n\n    $yazarlar = $stmt->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);\n\nİki uç durum: dizi BOŞ olabilir — IN () sözdizimi hatasıdır, önce count kontrolü yapın. Ve çok uzun olabilir — binlerce id''yi tek sorguya koymak yerine 500''lük parçalara bölün.\n\n63 sorgu böylece 3''e iner.', NULL, 1121, 438, 0.01655500, NOW() - INTERVAL 1 DAY),

(3, 'user', 'Gece 02:00''de rapor üretmem gerekiyor. Bunun için cron mu kurmalıyım yoksa iş kuyruğu mu?', NULL, 0, 0, 0.00000000, NOW() - INTERVAL 4 DAY),

(3, 'assistant', 'İkisi farklı soruyu yanıtlar; "hangisi daha iyi" diye bir yarış yok.\n\nCRON, ZAMANA bağlıdır: "her gece 02:00''de çalış". Tetikleyicisi takvimdir. Sizin durumunuzda tetikleyici tam olarak budur — cron.\n\nKUYRUK, OLAYA bağlıdır: "kullanıcı kaydolunca hoş geldin e-postası gönder". Tetikleyicisi bir istektir ve amaç, kullanıcıyı bekletmemektir.\n\nGece raporu için cron kurun. Ama pratikte ikisi birlikte kullanılır ve tavsiyem şudur: cron görevi raporu KENDİSİ üretmesin, kuyruğa bir iş bıraksın.\n\nSebebi: cron''un içinde patlayan iş kaybolur, tekrar denenmez ve hatası kimseye görünmez. Kuyruğa bırakırsanız yeniden deneme, geri çekilme ve başarısız iş kutusu bedavaya gelir. Cron zamanı belirler, kuyruk dayanıklılığı sağlar.', NULL, 298, 452, 0.01279000, NOW() - INTERVAL 4 DAY),

(3, 'user', 'Cron her dakika çalışıyor ve bazen önceki çalışma bitmemiş oluyor. Üst üste binmeyi nasıl engellerim?', NULL, 0, 0, 0.00000000, NOW() - INTERVAL 4 DAY),

(3, 'assistant', 'Bunun adı üst üste binme (overlap) ve çözümü KİLİTTİR.\n\nGörev başlarken bir kilit alır, biterken bırakır. Kilit alınamıyorsa çalışmadan çıkar. Kilidi veritabanında tutmak en taşınabilir yoldur: görev satırında bir locked_at sütunu yeterlidir.\n\nKritik nokta ZAMAN AŞIMIDIR. Süreç çökerse kilit sonsuza kadar üzerinde kalır ve görev bir daha hiç çalışmaz — sessizce. Bu yüzden kilit her zaman "şu kadar saniyeden eskiyse geçersiz say" kuralıyla birlikte kurulmalıdır.\n\nBir de yarış koşulu var: "boş mu?" diye bakıp sonra yazarsanız iki süreç aynı anda geçebilir. Kontrol ile yazmayı TEK ifadede yapın:\n\n    UPDATE gorevler\n       SET locked_at = NOW()\n     WHERE id = ?\n       AND (locked_at IS NULL OR locked_at < NOW() - INTERVAL 300 SECOND)\n\nEtkilenen satır 1 ise kilit sizindir, 0 ise başkası çalışıyordur.', NULL, 872, 316, 0.01226000, NOW() - INTERVAL 4 DAY);
