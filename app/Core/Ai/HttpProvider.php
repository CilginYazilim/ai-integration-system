<?php
/**
 * =====================================================================
 *  HttpProvider – sağlayıcıların paylaştığı HTTP katmanı
 * ---------------------------------------------------------------------
 *  Üç sağlayıcı da (Gemini, Claude, OpenAI uyumlu) aynı şeyi yapar:
 *  JSON gönder, JSON al, geçici hatalarda yeniden dene. Farklı olan
 *  yalnızca ADRES, BAŞLIKLAR ve GÖVDENİN BİÇİMİDİR.
 *
 *  Bu yüzden yeniden deneme, geri çekilme ve cURL ayarları BİR KEZ
 *  burada yazıldı. Her sağlayıcıya kopyalansaydı, ilk düzeltme
 *  üçünden ikisinde unutulurdu — kopyalanmış kodun klasik sonu.
 *
 *  Alt sınıflar yalnızca dört soruyu cevaplar:
 *      endpoint()  → nereye?
 *      headers()   → hangi kimlik başlığıyla?
 *      payload()   → hangi gövdeyle?
 *      normalize() → gelen yanıt ortak biçime nasıl çevrilir?
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core\Ai;

use RuntimeException;

abstract class HttpProvider implements Provider
{
    /** Geçici hatalarda kaç kez yeniden denensin? */
    protected const MAX_RETRIES = 3;

    public function __construct(
        protected readonly string $apiKey,
        protected readonly string $modelName,
        protected readonly int $maxTokens = 4096,
        protected readonly int $timeout = 120,
    ) {
    }

    public function model(): string
    {
        return $this->modelName;
    }

    /* =================================================================
     *  ALT SINIFLARIN DOLDURDUĞU YERLER
     * ============================================================== */

    /** İsteğin gideceği tam adres. */
    abstract protected function endpoint(): string;

    /**
     * HTTP başlıkları (kimlik doğrulama dahil).
     *
     * @return array<int,string>
     */
    abstract protected function headers(): array;

    /**
     * @param  array<int,array{role:string,content:string}> $messages
     * @return array<string,mixed>
     */
    abstract protected function payload(array $messages, string $system): array;

    /**
     * @param  array<string,mixed> $response
     * @return array<string,mixed>
     */
    abstract protected function normalize(array $response): array;

    /** Sağlayıcıya özgü hata cümlesi. */
    abstract protected function describeError(int $status, string $raw): string;

    /* =================================================================
     *  ANA ÇAĞRI
     * ============================================================== */

    public function send(array $messages, string $system = ''): array
    {
        return $this->normalize($this->request($this->payload($messages, $system)));
    }

    /* =================================================================
     *  HTTP + YENİDEN DENEME
     * =================================================================
     *  HANGİ HATALAR YENİDEN DENENİR?
     *    429 Too Many Requests → hız sınırı (kesinlikle geçici)
     *    529 Overloaded        → servis yoğun (geçici)
     *    500/502/503/504       → sunucu tarafı geçici arıza
     *
     *  HANGİLERİ DENENMEZ?
     *    400 → isteğiniz bozuk; aynısını tekrar atmak aynı hatayı verir
     *    401/403 → anahtar geçersiz veya yetkisiz
     *    404 → model adı yanlış
     *  Bunları yeniden denemek yalnızca zaman kaybettirir.
     *
     *  ÜCRETSİZ KATMANDA BU ÖNEMLİDİR: Gemini'nin ücretsiz katmanı
     *  dakikada birkaç istekle sınırlıdır ve sınıra çarpmak
     *  OLAĞANDIR, arıza değil. Yeniden deneme olmasaydı, örneği
     *  deneyen kişi ilk hızlı iki soruda "bozuk" sanırdı.
     *
     * @param  array<string,mixed> $payload
     * @return array<string,mixed>
     */
    protected function request(array $payload): array
    {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

        if ($body === false) {
            throw new RuntimeException('İstek JSON’a çevrilemedi.');
        }

        $attempt = 0;

        while (true) {
            $attempt++;

            [$status, $raw, $headers, $curlError] = $this->curl($body);

            if ($curlError !== '') {
                // Ağ hatası da geçicidir; aynı kurallarla yeniden deniyoruz.
                if ($attempt <= static::MAX_RETRIES) {
                    $this->backoff($attempt, null);

                    continue;
                }

                throw new RuntimeException('Bağlantı hatası: ' . $curlError);
            }

            if ($status >= 200 && $status < 300) {
                $decoded = json_decode($raw, true);

                if (!is_array($decoded)) {
                    throw new RuntimeException('API yanıtı çözümlenemedi.');
                }

                return $decoded;
            }

            $retryable = $status === 429 || $status === 529 || $status >= 500;

            if ($retryable && $attempt <= static::MAX_RETRIES) {
                /* SUNUCU "NE KADAR BEKLE" DERSE ONA UYULUR.
                 * Retry-After başlığı varken kendi hesabımızı
                 * dayatmak, hız sınırına tekrar çarpmak demektir. */
                $this->backoff($attempt, $this->retryAfter($headers));

                continue;
            }

            throw new RuntimeException($this->describeError($status, $raw));
        }
    }

    /**
     * Tek bir HTTP isteği.
     *
     * @return array{0:int,1:string,2:array<string,string>,3:string}
     *         [durum kodu, gövde, başlıklar, curl hatası]
     */
    private function curl(string $body): array
    {
        $curl = curl_init($this->endpoint());

        $headers = [];

        curl_setopt_array($curl, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,

            /* Sertifika doğrulaması AÇIK kalmalı. Kapatmak, API
             * anahtarınızı araya giren birine teslim etmek olur.
             * "Çalışmıyor" diye kapatmak yerine CA paketini
             * güncelleyin. */
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,

            CURLOPT_HTTPHEADER => $this->headers(),

            // Yanıt başlıklarını topluyoruz (Retry-After için).
            CURLOPT_HEADERFUNCTION => static function ($handle, string $line) use (&$headers): int {
                $parts = explode(':', $line, 2);

                if (count($parts) === 2) {
                    $headers[strtolower(trim($parts[0]))] = trim($parts[1]);
                }

                return strlen($line);
            },
        ]);

        $raw    = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error  = curl_error($curl);

        curl_close($curl);

        return [$status, is_string($raw) ? $raw : '', $headers, $error];
    }

    /** @param array<string,string> $headers */
    private function retryAfter(array $headers): ?int
    {
        $value = $headers['retry-after'] ?? '';

        return ctype_digit($value) ? (int) $value : null;
    }

    /**
     * Üstel geri çekilme + rastgele sapma (jitter).
     *
     * NEDEN RASTGELE SAPMA? Aynı anda sınıra çarpan on istemci
     * birebir aynı süreyi beklerse, hepsi aynı anda tekrar dener ve
     * sınıra yine birlikte çarpar ("gök gürültüsü sürüsü" sorunu).
     * Küçük bir rastgelelik bu dalgayı dağıtır.
     */
    protected function backoff(int $attempt, ?int $retryAfter): void
    {
        if ($retryAfter !== null) {
            sleep(min(60, $retryAfter));

            return;
        }

        // 1s, 2s, 4s … + 0-500 ms sapma
        $seconds = min(30, 2 ** ($attempt - 1));

        usleep($seconds * 1000000 + random_int(0, 500000));
    }

    /* =================================================================
     *  ORTAK YARDIMCILAR
     * ============================================================== */

    /**
     * Boş bir kullanım sayacı. Sağlayıcı bazı alanları hiç
     * döndürmez; sıfırla başlayıp üzerine yazmak, "tanımsız dizin"
     * hatalarını baştan keser.
     *
     * @return array<string,int>
     */
    protected static function emptyUsage(): array
    {
        return [
            'input_tokens'  => 0,
            'output_tokens' => 0,
            'cache_write'   => 0,
            'cache_read'    => 0,
        ];
    }

    /**
     * 1 milyon jeton başına fiyattan bu isteğin maliyetini hesaplar.
     *
     * @param array<string,int>                        $usage
     * @param array{input:float,output:float}|null     $rates
     */
    protected static function priceFor(array $usage, ?array $rates): float
    {
        if ($rates === null) {
            return 0.0;
        }

        return ($usage['input_tokens'] / 1000000) * $rates['input']
             + ($usage['output_tokens'] / 1000000) * $rates['output'];
    }
}
