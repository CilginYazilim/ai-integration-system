<?php
/**
 * =====================================================================
 *  ClaudeProvider – Anthropic Messages API istemcisi (kütüphanesiz)
 * ---------------------------------------------------------------------
 *      POST https://api.anthropic.com/v1/messages
 *
 *  Sohbet, araç kullanımı, uzun düşünme — hepsi bu isteğin
 *  parametreleridir; ayrı API'ler değildir.
 *
 *  Bu sağlayıcı ÜCRETLİDİR. Deponun varsayılanı Gemini'dir (ücretsiz
 *  katmanı olduğu için); Claude, "aynı uygulama farklı bir sağlayıcıya
 *  nasıl bağlanır?" sorusunun cevabı olarak duruyor. AI_PROVIDER=claude
 *  yazmak, arayüzde tek bir satır değiştirmeden onu devreye alır.
 *
 *  ---------------------------------------------------------------
 *  NEDEN COMPOSER PAKETİ KULLANMIYORUZ?
 *  Gerçek bir projede resmî SDK doğru tercihtir. Bu depo bilinçli
 *  olarak SIFIR BAĞIMLILIK ile çalıştığı için isteği elle kuruyoruz
 *  — ve asıl kazanç şu: hız sınırı, jeton sayımı ve maliyet hesabı
 *  "kütüphanenin içinde bir yerde" kalmıyor, gözle görülüyor.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core\Ai;

final class ClaudeProvider extends HttpProvider
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
     * DİKKAT: Bu değerler koda gömülü bir ANLIK GÖRÜNTÜDÜR ve
     * değişebilir. Güncel liste: anthropic.com/pricing
     *
     * @var array<string,array{input:float,output:float}>
     */
    private const PRICING = [
        'claude-opus-5'    => ['input' => 5.00, 'output' => 25.00],
        'claude-sonnet-5'  => ['input' => 2.00, 'output' => 10.00],
        'claude-haiku-4-5' => ['input' => 1.00, 'output' => 5.00],
    ];

    public function __construct(
        string $apiKey,
        string $model = 'claude-sonnet-5',

        /**
         * DÜŞÜK TUTMAYIN: sınıra çarpan yanıt cümlenin ortasında
         * kesilir (stop_reason = "max_tokens") ve baştan sormanız
         * gerekir.
         */
        int $maxTokens = 16000,

        /**
         * Düşünme derinliği: low · medium · high · xhigh · max
         * Kaliteyi ve maliyeti birlikte belirler.
         */
        private readonly string $effort = 'medium',

        int $timeout = 120,
    ) {
        parent::__construct($apiKey, $model, $maxTokens, $timeout);
    }

    /* =================================================================
     *  İSTEK
     * ============================================================== */

    protected function endpoint(): string
    {
        return self::ENDPOINT;
    }

    /** @return array<int,string> */
    protected function headers(): array
    {
        return [
            'content-type: application/json',

            // Kimlik doğrulama: Bearer DEĞİL, x-api-key başlığı.
            'x-api-key: ' . $this->apiKey,

            // Sürüm başlığı ZORUNLUDUR; eksikse istek reddedilir.
            'anthropic-version: ' . self::API_VERSION,
        ];
    }

    /**
     * @param  array<int,array{role:string,content:string}> $messages
     * @return array<string,mixed>
     */
    protected function payload(array $messages, string $system): array
    {
        $payload = [
            'model'      => $this->modelName,
            'max_tokens' => $this->maxTokens,
            'messages'   => $messages,

            /* UYARLANIR DÜŞÜNME (adaptive thinking)
             * Model ne kadar düşüneceğine kendisi karar verir. Eski
             * "budget_tokens" yaklaşımı güncel modellerde KALDIRILMIŞTIR
             * ve gönderilirse 400 hatası döner.
             *
             * display = "summarized": düşünme özeti yanıtta gelir.
             * Varsayılan "omitted"tır; o zaman düşünme yine yapılır ve
             * ÜCRETLENDİRİLİR, sadece metni boş gelir. */
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
            /* Sistem yönergesini DİZİ olarak gönderiyoruz; böylece ön
             * belleğe alma (prompt caching) işaretini koyabiliyoruz.
             *
             * ÖN BELLEK NASIL ÇALIŞIR? İstekteki ÖN EK birebir aynı
             * kalırsa model onu yeniden işlemez ve o kısım çok daha
             * ucuza gelir. Bu yüzden DEĞİŞMEYEN içerik (sistem
             * yönergesi) başa, DEĞİŞEN içerik (kullanıcı sorusu) sona
             * konur. Yönergeye tarih/saat gibi her istekte değişen bir
             * şey koyarsanız ön bellek sessizce hiç çalışmaz. */
            $payload['system'] = [[
                'type'          => 'text',
                'text'          => $system,
                'cache_control' => ['type' => 'ephemeral'],
            ]];
        }

        return $payload;
    }

    /* =================================================================
     *  YANITI SADELEŞTİRME
     * ============================================================== */

    /**
     * @param  array<string,mixed> $response
     * @return array<string,mixed>
     */
    protected function normalize(array $response): array
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
         * yanıtlamamayı seçmiştir. */
        $refusal = $stopReason === 'refusal'
            ? (string) ($response['stop_details']['category'] ?? 'unknown')
            : null;

        $usage = self::emptyUsage();

        $usage['input_tokens']  = (int) ($response['usage']['input_tokens'] ?? 0);
        $usage['output_tokens'] = (int) ($response['usage']['output_tokens'] ?? 0);

        // Ön bellek sayaçları: sıfırdan büyükse ön bellek çalışıyor.
        $usage['cache_write'] = (int) ($response['usage']['cache_creation_input_tokens'] ?? 0);
        $usage['cache_read']  = (int) ($response['usage']['cache_read_input_tokens'] ?? 0);

        $model = (string) ($response['model'] ?? $this->modelName);

        return [
            'text'        => trim($text),
            'thinking'    => trim($thinking),
            'stop_reason' => $stopReason,
            'refusal'     => $refusal,
            'usage'       => $usage,
            'cost'        => self::estimateCost($model, $usage),
            'model'       => $model,
        ];
    }

    /* =================================================================
     *  HATALAR
     * ============================================================== */

    protected function describeError(int $status, string $raw): string
    {
        $decoded = json_decode($raw, true);
        $message = is_array($decoded) ? (string) ($decoded['error']['message'] ?? '') : '';

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
     *  MALİYET
     * ============================================================== */

    /** @param array<string,int> $usage */
    public static function estimateCost(string $model, array $usage): float
    {
        return self::priceFor($usage, self::PRICING[$model] ?? null);
    }

    /** @return array<int,string> */
    public static function models(): array
    {
        return array_keys(self::PRICING);
    }
}
