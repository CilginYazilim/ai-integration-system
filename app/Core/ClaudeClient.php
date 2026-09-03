<?php
/**
 * =====================================================================
 *  ClaudeClient – Anthropic Messages API istemcisi (kütüphanesiz)
 * ---------------------------------------------------------------------
 *  Tek bir uç nokta vardır ve her şey oradan geçer:
 *
 *      POST https://api.anthropic.com/v1/messages
 *
 *  Sohbet, araç kullanımı, uzun düşünme — hepsi bu isteğin
 *  parametreleridir; ayrı API'ler değildir.
 *
 *  ---------------------------------------------------------------
 *  NEDEN COMPOSER PAKETİ KULLANMIYORUZ?
 *  Gerçek bir projede resmî SDK doğru tercihtir:
 *
 *      composer require anthropic-ai/sdk
 *
 *  Yeniden deneme, akış (streaming), tipli hatalar ve araç döngüsü
 *  hazır gelir. Bu depo bilinçli olarak SIFIR BAĞIMLILIK ile
 *  çalıştığı için isteği elle kuruyoruz — ve asıl kazanç şu:
 *  hız sınırı, jeton sayımı ve maliyet hesabı gibi konular
 *  "kütüphanenin içinde bir yerde" kalmıyor, gözle görülüyor.
 *
 *  ---------------------------------------------------------------
 *  API ANAHTARI ASLA TARAYICIYA GİTMEZ.
 *  İstek SUNUCUDAN atılır. Anahtarı JavaScript'e koyup doğrudan
 *  Anthropic'e istek atmak, anahtarınızı sayfayı açan herkese
 *  vermek demektir — faturayı da onlar doldurur.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class ClaudeClient
{
    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';

    /**
     * API sürümü. Anthropic bunu ZORUNLU tutar ve tarih biçimindedir.
     * Sabit kalır; yeni özellikler "anthropic-beta" başlığıyla gelir.
     */
    private const API_VERSION = '2023-06-01';

    /**
     * MODEL FİYATLARI (1 milyon jeton başına, ABD doları).
     *
     * Maliyeti göstermek, yapay zekâ entegrasyonlarında en çok
     * atlanan ayrıntıdır: "çalışıyor" diye sevinip ay sonunda
     * faturayı görmek yaygın bir hikâyedir.
     *
     * DİKKAT: Bu değerler koda gömülü bir ANLIK GÖRÜNTÜDÜR ve
     * değişebilir. Güncel liste için:
     *     https://www.anthropic.com/pricing
     *
     * @var array<string,array{input:float,output:float}>
     */
    private const PRICING = [
        'claude-opus-5'   => ['input' => 5.00, 'output' => 25.00],
        'claude-sonnet-5' => ['input' => 2.00, 'output' => 10.00],
        'claude-haiku-4-5' => ['input' => 1.00, 'output' => 5.00],
    ];

    /** Hız sınırına takılınca kaç kez yeniden denensin? */
    private const MAX_RETRIES = 3;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model = 'claude-opus-5',

        /**
         * Yanıtın en fazla kaç jeton olacağı.
         *
         * DÜŞÜK TUTMAYIN: sınıra çarpan yanıt cümlenin ortasında
         * kesilir (stop_reason = "max_tokens") ve baştan sormanız
         * gerekir. Akışsız (non-streaming) istekler için 16.000
         * makul bir varsayılandır; daha büyük değerlerde HTTP zaman
         * aşımına takılmamak için akış kullanmak gerekir.
         */
        private readonly int $maxTokens = 16000,

        /**
         * Düşünme derinliği: low · medium · high · xhigh · max
         *
         * Kaliteyi ve maliyeti birlikte belirler. Sohbet gibi hafif
         * işlerde "low" çoğu zaman yeterlidir ve belirgin biçimde
         * ucuzdur; zor akıl yürütme gerektiren işlerde yükseltin.
         */
        private readonly string $effort = 'medium',

        /** İstek zaman aşımı (saniye). */
        private readonly int $timeout = 120,
    ) {
    }

    /** Ortam değişkenlerinden istemci üretir. */
    public static function fromEnv(): self
    {
        $key = Env::get('ANTHROPIC_API_KEY', '');

        if ($key === '') {
            throw new RuntimeException(
                '.env içinde ANTHROPIC_API_KEY tanımlı değil. '
                . 'Anahtarınızı console.anthropic.com adresinden alabilirsiniz.'
            );
        }

        return new self(
            $key,
            Env::get('AI_MODEL', 'claude-opus-5'),
            (int) Env::get('AI_MAX_TOKENS', '16000'),
            Env::get('AI_EFFORT', 'medium'),
        );
    }

    /** Anahtar tanımlı mı? (Arayüzde kurulum uyarısı göstermek için.) */
    public static function isConfigured(): bool
    {
        return Env::get('ANTHROPIC_API_KEY', '') !== '';
    }

    /* =================================================================
     *  ANA ÇAĞRI
     * ============================================================== */

    /**
     * Sohbeti gönderir ve modelin yanıtını döndürür.
     *
     * @param array<int,array{role:string,content:string}> $messages
     *        Tüm konuşma geçmişi. API DURUMSUZDUR: önceki mesajları
     *        saklamaz. "Model beni hatırlamıyor" şikâyetinin sebebi
     *        neredeyse her zaman geçmişi göndermemektir.
     *
     * @param string $system Sistem yönergesi (modelin rolü/kuralları)
     *
     * @return array{
     *     text:string, thinking:string, stop_reason:string,
     *     refusal:?string, usage:array<string,int>, cost:float, model:string
     * }
     */
    public function send(array $messages, string $system = ''): array
    {
        $payload = [
            'model'      => $this->model,
            'max_tokens' => $this->maxTokens,
            'messages'   => $messages,

            /* UYARLANIR DÜŞÜNME (adaptive thinking)
             * Model ne kadar düşüneceğine kendisi karar verir.
             * Eski "budget_tokens" yaklaşımı güncel modellerde
             * KALDIRILMIŞTIR ve gönderilirse 400 hatası döner.
             *
             * display = "summarized": düşünme özeti yanıtta gelir.
             * Varsayılan "omitted"tır; o zaman düşünme yine yapılır
             * ve ÜCRETLENDİRİLİR, sadece metni boş gelir. */
            'thinking' => [
                'type'    => 'adaptive',
                'display' => 'summarized',
            ],

            /* Efor, düşünme derinliğini ve toplam jeton harcamasını
             * birlikte ayarlar. output_config'in İÇİNDEDİR; üst
             * seviyeye yazmak hata verir. */
            'output_config' => [
                'effort' => $this->effort,
            ],
        ];

        /* DİKKAT: temperature / top_p / top_k GÖNDERİLMEZ.
         * Bu parametreler güncel modellerde kaldırılmıştır ve
         * gönderilirse istek 400 ile reddedilir. Eski örneklerden
         * kopyalanan "temperature: 0.7" satırı, bu API'de en sık
         * karşılaşılan hatalardan biridir. */

        if ($system !== '') {
            /* Sistem yönergesini DİZİ olarak gönderiyoruz; böylece
             * ön belleğe alma (prompt caching) işaretini
             * koyabiliyoruz.
             *
             * ÖN BELLEK NASIL ÇALIŞIR? İstekteki ÖN EK birebir aynı
             * kalırsa model onu yeniden işlemez ve o kısım çok daha
             * ucuza gelir. Bu yüzden DEĞİŞMEYEN içerik (sistem
             * yönergesi) başa, DEĞİŞEN içerik (kullanıcı sorusu)
             * sona konur. Yönergeye tarih/saat gibi her istekte
             * değişen bir şey koyarsanız ön bellek sessizce hiç
             * çalışmaz. */
            $payload['system'] = [[
                'type'          => 'text',
                'text'          => $system,
                'cache_control' => ['type' => 'ephemeral'],
            ]];
        }

        $response = $this->request($payload);

        return $this->normalize($response);
    }

    /* =================================================================
     *  HTTP + YENİDEN DENEME
     * ============================================================== */

    /**
     * İsteği gönderir; geçici hatalarda üstel geri çekilmeyle yeniden dener.
     *
     * HANGİ HATALAR YENİDEN DENENİR?
     *   429 Too Many Requests → hız sınırı (kesinlikle geçici)
     *   529 Overloaded        → servis yoğun (geçici)
     *   500/502/503/504       → sunucu tarafı geçici arıza
     *
     * HANGİLERİ DENENMEZ?
     *   400 → isteğiniz bozuk; aynı isteği tekrar atmak aynı hatayı verir
     *   401 → anahtar geçersiz
     *   404 → model adı yanlış
     * Bunları yeniden denemek yalnızca zaman kaybettirir.
     *
     * @param  array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private function request(array $payload): array
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
                if ($attempt <= self::MAX_RETRIES) {
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

            if ($retryable && $attempt <= self::MAX_RETRIES) {
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
        $curl = curl_init(self::ENDPOINT);

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

            CURLOPT_HTTPHEADER => [
                'content-type: application/json',

                // Kimlik doğrulama: Bearer DEĞİL, x-api-key başlığı.
                'x-api-key: ' . $this->apiKey,

                // Sürüm başlığı ZORUNLUDUR; eksikse istek reddedilir.
                'anthropic-version: ' . self::API_VERSION,
            ],

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
    private function backoff(int $attempt, ?int $retryAfter): void
    {
        if ($retryAfter !== null) {
            sleep(min(60, $retryAfter));

            return;
        }

        // 1s, 2s, 4s … + 0-500 ms sapma
        $seconds = min(30, 2 ** ($attempt - 1));

        usleep($seconds * 1000000 + random_int(0, 500000));
    }

    /** API hatasını okunabilir bir cümleye çevirir. */
    private function describeError(int $status, string $raw): string
    {
        $decoded = json_decode($raw, true);
        $message = is_array($decoded) ? (string) ($decoded['error']['message'] ?? '') : '';

        /* Kullanıcıya ne yapması gerektiğini söylüyoruz; ham hata
         * metni geliştirici için sonuna ekleniyor. */
        $hint = match (true) {
            $status === 401 => 'API anahtarı geçersiz. .env içindeki ANTHROPIC_API_KEY değerini kontrol edin.',
            $status === 400 => 'İstek reddedildi. Model adı veya parametreler hatalı olabilir.',
            $status === 404 => 'Model bulunamadı. AI_MODEL değerini kontrol edin.',
            $status === 413 => 'İstek çok büyük. Sohbet geçmişini kısaltın.',
            $status === 429 => 'Hız sınırına takıldınız ve yeniden denemeler de yetmedi.',
            default         => 'API hatası (HTTP ' . $status . ').',
        };

        return $message === '' ? $hint : $hint . ' — ' . $message;
    }

    /* =================================================================
     *  YANITI SADELEŞTİRME
     * ============================================================== */

    /**
     * API yanıtını uygulamanın kullandığı sade biçime çevirir.
     *
     * @param  array<string,mixed> $response
     * @return array<string,mixed>
     */
    private function normalize(array $response): array
    {
        $text     = '';
        $thinking = '';

        /* İÇERİK BİR DİZİDİR, TEK METİN DEĞİL.
         * content[0]->text yazmak kırılgandır: düşünme açıkken ilk
         * blok "thinking" olur ve yanlış alanı okursunuz. Blokları
         * TÜRÜNE bakarak geziyoruz. */
        foreach ($response['content'] ?? [] as $block) {
            $type = $block['type'] ?? '';

            if ($type === 'text') {
                $text .= $block['text'] ?? '';
            } elseif ($type === 'thinking') {
                $thinking .= $block['thinking'] ?? '';
            }
        }

        $stopReason = (string) ($response['stop_reason'] ?? '');

        /* REDDETME (refusal) BİR HATA DEĞİLDİR.
         * HTTP 200 döner, gövde geçerlidir, ama model isteği
         * yanıtlamamayı seçmiştir. stop_reason'a bakmadan doğrudan
         * metni okursanız kullanıcıya boş bir yanıt gösterirsiniz. */
        $refusal = null;

        if ($stopReason === 'refusal') {
            $refusal = (string) ($response['stop_details']['category'] ?? 'unknown');
        }

        $usage = [
            'input_tokens'  => (int) ($response['usage']['input_tokens'] ?? 0),
            'output_tokens' => (int) ($response['usage']['output_tokens'] ?? 0),

            // Ön bellek sayaçları: sıfırdan büyükse ön bellek çalışıyor.
            'cache_write'   => (int) ($response['usage']['cache_creation_input_tokens'] ?? 0),
            'cache_read'    => (int) ($response['usage']['cache_read_input_tokens'] ?? 0),
        ];

        return [
            'text'        => trim($text),
            'thinking'    => trim($thinking),
            'stop_reason' => $stopReason,
            'refusal'     => $refusal,
            'usage'       => $usage,
            'cost'        => self::estimateCost((string) ($response['model'] ?? $this->model), $usage),
            'model'       => (string) ($response['model'] ?? $this->model),
        ];
    }

    /**
     * Bu isteğin yaklaşık maliyeti (ABD doları).
     *
     * KABA BİR TAHMİNDİR, FATURA DEĞİLDİR:
     *  - Fiyatlar koda gömülüdür ve değişebilir
     *  - Ön belleğe YAZILAN ve ön bellekten OKUNAN jetonlar farklı
     *    oranlarla ücretlendirilir; burada yalnızca normal giriş ve
     *    çıkış jetonları hesaba katılır
     *
     * Yine de büyüklük mertebesini görmek çok değerlidir: "bu sohbet
     * 0,004 dolar" bilgisi, efor seviyesini düşürme kararını somut
     * hale getirir.
     *
     * @param array<string,int> $usage
     */
    public static function estimateCost(string $model, array $usage): float
    {
        $rates = self::PRICING[$model] ?? null;

        if ($rates === null) {
            return 0.0;
        }

        return ($usage['input_tokens'] / 1000000) * $rates['input']
             + ($usage['output_tokens'] / 1000000) * $rates['output'];
    }

    /** @return array<int,string> Arayüzdeki model listesi için. */
    public static function models(): array
    {
        return array_keys(self::PRICING);
    }
}
