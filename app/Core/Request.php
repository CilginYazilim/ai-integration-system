<?php
/**
 * =====================================================================
 *  Request – Gelen HTTP isteğini temsil eder
 * ---------------------------------------------------------------------
 *  $_POST / $_GET / $_FILES süper globallerine kodun her yerinden
 *  dokunmak yerine tek kapıdan geçiyoruz. Faydası:
 *
 *   - Girdi her zaman aynı biçimde temizlenir
 *   - Kodu test etmek kolaylaşır (sahte Request üretilebilir)
 *   - "Bu değer nereden geldi?" sorusunun cevabı tek yerdedir
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core;

final class Request
{
    /** @var array<string,mixed> */
    private array $query;

    /** @var array<string,mixed> */
    private array $body;

    /** @var array<string,mixed> */
    private array $files;

    public function __construct()
    {
        $this->query = $_GET;
        $this->body  = $_POST;
        $this->files = $_FILES;
    }

    public function method(): string
    {
        return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    }

    public function isPost(): bool
    {
        return $this->method() === 'POST';
    }

    /**
     * İstek AJAX ile mi geldi?
     * jQuery her AJAX isteğine X-Requested-With başlığını ekler.
     */
    public function isAjax(): bool
    {
        return strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
    }

    /**
     * POST veya GET'ten metin okur; baştaki/sondaki boşlukları temizler.
     * Dizi gelirse (beklenmedik girdi) boş string döner.
     */
    public function input(string $key, string $default = ''): string
    {
        $value = $this->body[$key] ?? $this->query[$key] ?? $default;

        return is_scalar($value) ? trim((string) $value) : $default;
    }

    /** Ham değeri döndürür (dizi olabilir; DataTables parametreleri gibi). */
    public function raw(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    /**
     * Tam sayı okur. Geçersizse (örn. "5abc", "1 OR 1=1") null döner.
     * filter_var, elle (int) dönüşümünden çok daha katıdır:
     *   (int) "5abc"        → 5   (sessizce yanlış)
     *   filter_var("5abc")  → false (hata yakalanır)
     */
    public function int(string $key, ?int $min = null, ?int $max = null): ?int
    {
        $raw = $this->body[$key] ?? $this->query[$key] ?? null;

        if ($raw === null || is_array($raw)) {
            return null;
        }

        $options = [];

        if ($min !== null) {
            $options['min_range'] = $min;
        }
        if ($max !== null) {
            $options['max_range'] = $max;
        }

        $value = filter_var((string) $raw, FILTER_VALIDATE_INT, ['options' => $options]);

        return $value === false ? null : $value;
    }

    /** Onay kutusu okuma: "1", "on", "true" → true */
    public function bool(string $key): bool
    {
        $raw = strtolower($this->input($key));

        return in_array($raw, ['1', 'on', 'true', 'yes'], true);
    }

    /** @return array<string,mixed>|null Yüklenen dosya bilgisi */
    public function file(string $key): ?array
    {
        $file = $this->files[$key] ?? null;

        return is_array($file) ? $file : null;
    }

    /**
     * Dosya alanı gerçekten doldurulmuş mu?
     * UPLOAD_ERR_NO_FILE, "kullanıcı dosya seçmedi" demektir; bu bir
     * hata değildir, çoğu formda görsel zorunlu değildir.
     */
    public function hasFile(string $key): bool
    {
        $file = $this->file($key);

        return $file !== null
            && isset($file['error'])
            && !is_array($file['error'])
            && (int) $file['error'] !== UPLOAD_ERR_NO_FILE;
    }

    /**
     * İstemcinin IP adresi.
     *
     * DİKKAT: X-Forwarded-For başlığı KOLAYCA SAHTE ÜRETİLİR. Yalnızca
     * güvendiğiniz bir vekil sunucu (Cloudflare, nginx) arkasındaysanız
     * anlamlıdır. Biz bunu sadece kaba kuvvet sayacı için kullanıyoruz;
     * yetkilendirme kararı asla IP'ye dayandırılmamalıdır.
     */
    public function ip(): string
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');

        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }

    public function userAgent(): string
    {
        // Veritabanı sütununu taşırmamak için kırpıyoruz.
        return mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    }
}
