<?php
/**
 * =====================================================================
 *  GeminiProvider – Google Gemini API istemcisi (kütüphanesiz)
 * ---------------------------------------------------------------------
 *  Bu deponun VARSAYILAN sağlayıcısıdır; sebebi teknik değil,
 *  pratiktir: Gemini'nin ÜCRETSİZ bir katmanı vardır. Örneği denemek
 *  isteyen biri aistudio.google.com adresinden birkaç saniyede
 *  anahtar alır, kredi kartı girmez.
 *
 *      POST https://generativelanguage.googleapis.com/v1beta/
 *           models/<model>:generateContent
 *
 *  ---------------------------------------------------------------
 *  ÜÇ TUZAK — ÜÇÜ DE BU DOSYADA ÇÖZÜLÜ
 *
 *  1) ROL ADI "assistant" DEĞİL, "model"DİR.
 *     Uygulamanın veritabanında roller 'user' / 'assistant' olarak
 *     durur (bu, sağlayıcıdan bağımsız bir seçim). Gemini
 *     "assistant" görürse 400 döner. Çeviri toMessages() içindedir.
 *
 *  2) SİSTEM YÖNERGESİ MESAJ DİZİSİNİN İÇİNE KONMAZ.
 *     Ayrı bir "systemInstruction" alanı vardır. İlk mesaj olarak
 *     göndermek çalışır gibi görünür ama model onu sıradan bir
 *     kullanıcı sözü sayar ve yönergeyi tartışmaya açar.
 *
 *  3) ANAHTAR SORGU DİZESİNE DEĞİL, BAŞLIĞA KONUR.
 *     Google'ın örnekleri "?key=..." gösterir ve bu çalışır — ama
 *     adres satırındaki anahtar sunucu erişim günlüklerine, vekil
 *     sunucu kayıtlarına ve tarayıcı geçmişine yazılır. Başlık
 *     (x-goog-api-key) hiçbirine düşmez.
 *
 *  ---------------------------------------------------------------
 *  API ANAHTARI ASLA TARAYICIYA GİTMEZ. İstek SUNUCUDAN atılır.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core\Ai;

final class GeminiProvider extends HttpProvider
{
    private const BASE = 'https://generativelanguage.googleapis.com/v1beta/models/';

    /**
     * MODEL FİYATLARI (1 milyon jeton başına, ABD doları).
     *
     * DİKKAT — BU TABLO ÜCRETLİ KATMAN İÇİNDİR.
     * Ücretsiz katmanda gerçek maliyetiniz SIFIRDIR. Rakamı yine de
     * gösteriyoruz, çünkü asıl soru "bugün ne ödedim?" değil,
     * "bu uygulama gerçekten yayına çıkarsa ne öderim?" sorusudur.
     * Maliyeti görünmez kılmak, yapay zekâ entegrasyonlarında en
     * pahalıya patlayan alışkanlıktır.
     *
     * Güncel liste: ai.google.dev/pricing
     *
     * @var array<string,array{input:float,output:float}>
     */
    private const PRICING = [
        'gemini-2.5-flash'      => ['input' => 0.30, 'output' => 2.50],
        'gemini-2.5-flash-lite' => ['input' => 0.10, 'output' => 0.40],
        'gemini-3.5-flash'      => ['input' => 0.50, 'output' => 3.00],
        'gemini-3.5-flash-lite' => ['input' => 0.15, 'output' => 0.60],
        'gemini-3.8-flash'      => ['input' => 0.75, 'output' => 3.75],
    ];

    public function __construct(
        string $apiKey,
        string $model = 'gemini-2.5-flash',
        int $maxTokens = 4096,

        /**
         * Düşünme özeti istensin mi?
         *
         * Gemini 2.5 ve üstü zaten "düşünür"; ama özeti yalnızca
         * açıkça istenirse döner. VARSAYILAN KAPALIDIR: bu alanı
         * desteklemeyen bir modele göndermek 400 verir ve örneği
         * ilk kez kuran kişi sebebini anlayamaz. Açmak isteyen
         * .env içine AI_THINKING=true yazar.
         */
        private readonly bool $thinking = false,

        int $timeout = 120,
    ) {
        parent::__construct($apiKey, $model, $maxTokens, $timeout);
    }

    /* =================================================================
     *  İSTEK
     * ============================================================== */

    protected function endpoint(): string
    {
        /* Model adı ADRESİN İÇİNDEDİR, gövdede değil. Bu, Gemini'yi
         * çoğu sağlayıcıdan ayıran ilk şeydir; gövdeye "model"
         * yazmak sessizce yok sayılır ve yanlış modeli kullandığınızı
         * fark etmezsiniz.
         *
         * rawurlencode: model adı dışarıdan (.env) geliyor; adrese
         * doğrudan yapıştırmak yol enjeksiyonuna açık kapı bırakır. */
        return self::BASE . rawurlencode($this->modelName) . ':generateContent';
    }

    /** @return array<int,string> */
    protected function headers(): array
    {
        return [
            'content-type: application/json',
            'x-goog-api-key: ' . $this->apiKey,
        ];
    }

    /**
     * @param  array<int,array{role:string,content:string}> $messages
     * @return array<string,mixed>
     */
    protected function payload(array $messages, string $system): array
    {
        $payload = [
            'contents' => self::toContents($messages),

            'generationConfig' => [
                /* Yanıtın en fazla kaç jeton olacağı. DÜŞÜK TUTMAYIN:
                 * sınıra çarpan yanıt cümlenin ortasında kesilir ve
                 * finishReason "MAX_TOKENS" döner. */
                'maxOutputTokens' => $this->maxTokens,
            ],
        ];

        if ($system !== '') {
            // Ayrı alan — mesaj dizisine karıştırılmaz (bkz. tuzak 2).
            $payload['systemInstruction'] = ['parts' => [['text' => $system]]];
        }

        if ($this->thinking) {
            $payload['generationConfig']['thinkingConfig'] = ['includeThoughts' => true];
        }

        return $payload;
    }

    /**
     * Uygulamanın mesaj biçimini Gemini'nin biçimine çevirir.
     *
     * @param  array<int,array{role:string,content:string}> $messages
     * @return array<int,array<string,mixed>>
     */
    private static function toContents(array $messages): array
    {
        $out = [];

        foreach ($messages as $message) {
            $out[] = [
                // 'assistant' → 'model' (bkz. tuzak 1)
                'role'  => ($message['role'] ?? 'user') === 'assistant' ? 'model' : 'user',
                'parts' => [['text' => (string) ($message['content'] ?? '')]],
            ];
        }

        return $out;
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
        $usage = self::emptyUsage();

        $usage['input_tokens']  = (int) ($response['usageMetadata']['promptTokenCount'] ?? 0);
        $usage['output_tokens'] = (int) ($response['usageMetadata']['candidatesTokenCount'] ?? 0);
        $usage['cache_read']    = (int) ($response['usageMetadata']['cachedContentTokenCount'] ?? 0);

        /* İSTEM ENGELLENDİYSE HİÇ ADAY GELMEZ.
         * candidates[0] okumaya çalışmak burada "tanımsız dizin"
         * hatası verir; hâlbuki durum bir arıza değil, modelin
         * kararıdır. Önce engeli kontrol ediyoruz. */
        $blockReason = (string) ($response['promptFeedback']['blockReason'] ?? '');

        if ($blockReason !== '') {
            return $this->result('', '', 'refusal', $blockReason, $usage);
        }

        $candidate = $response['candidates'][0] ?? null;

        if (!is_array($candidate)) {
            /* Aday da yok, engel de yok. Bu genelde boş bir yanıttır;
             * kullanıcıya boş balon göstermek yerine açıkça söylüyoruz. */
            return $this->result('', '', 'empty', null, $usage);
        }

        $text     = '';
        $thinking = '';

        /* PARÇALAR BİR DİZİDİR, TEK METİN DEĞİL.
         * parts[0]['text'] yazmak kırılgandır: düşünme özeti açıkken
         * ilk parça "thought" olur ve yanlış alanı okursunuz. */
        foreach ($candidate['content']['parts'] ?? [] as $part) {
            $chunk = (string) ($part['text'] ?? '');

            if (($part['thought'] ?? false) === true) {
                $thinking .= $chunk;
            } else {
                $text .= $chunk;
            }
        }

        $finish = strtoupper((string) ($candidate['finishReason'] ?? ''));

        /* GÜVENLİK ENGELİ BİR HATA DEĞİLDİR.
         * HTTP 200 döner, gövde geçerlidir, ama model yanıtlamamayı
         * seçmiştir. finishReason'a bakmadan metni basarsak kullanıcı
         * boş bir balon görür ve sebebini anlamaz. */
        $refusal = in_array($finish, ['SAFETY', 'RECITATION', 'PROHIBITED_CONTENT', 'BLOCKLIST'], true)
            ? $finish
            : null;

        // Ortak sözlüğe çeviriyoruz; arayüz sağlayıcı adı bilmesin.
        $stopReason = match ($finish) {
            'STOP'       => 'end_turn',
            'MAX_TOKENS' => 'max_tokens',
            ''           => 'end_turn',
            default      => strtolower($finish),
        };

        return $this->result($text, $thinking, $stopReason, $refusal, $usage);
    }

    /**
     * @param  array<string,int> $usage
     * @return array<string,mixed>
     */
    private function result(string $text, string $thinking, string $stop, ?string $refusal, array $usage): array
    {
        return [
            'text'        => trim($text),
            'thinking'    => trim($thinking),
            'stop_reason' => $stop,
            'refusal'     => $refusal,
            'usage'       => $usage,
            'cost'        => self::estimateCost($this->modelName, $usage),
            'model'       => $this->modelName,
        ];
    }

    /* =================================================================
     *  HATALAR
     * ============================================================== */

    protected function describeError(int $status, string $raw): string
    {
        $decoded = json_decode($raw, true);
        $message = is_array($decoded) ? (string) ($decoded['error']['message'] ?? '') : '';

        /* GEÇERSİZ ANAHTAR 401 DEĞİL, 400 DÖNER.
         * ÖLÇÜLEN DAVRANIŞ: Gemini, hatalı anahtarda
         * "400 INVALID_ARGUMENT — API key not valid" veriyor.
         * Yalnızca duruma bakan bir eşleme burada "model adınız
         * yanlış olabilir" derdi ve kullanıcıyı saatlerce yanlış
         * yere baktırırdı. Bu yüzden mesajın içine de bakıyoruz. */
        $badKey = stripos($message, 'API key') !== false
            || stripos($message, 'API_KEY_INVALID') !== false;

        $hint = match (true) {
            $badKey,
            $status === 401,
            $status === 403 => 'API anahtarı geçersiz ya da bu modele erişimi yok. '
                             . '.env içindeki GEMINI_API_KEY değerini kontrol edin. '
                             . 'Ücretsiz anahtar: aistudio.google.com/apikey',

            $status === 400 => 'İstek reddedildi. AI_MODEL değeri veya parametreler hatalı olabilir.',
            $status === 404 => 'Model bulunamadı. AI_MODEL değerini kontrol edin '
                             . '(örn. gemini-2.5-flash).',
            $status === 413 => 'İstek çok büyük. Sohbet geçmişini kısaltın.',

            /* Ücretsiz katmanda 429 OLAĞANDIR — dakikada birkaç
             * istekle sınırlısınız. Kullanıcıya "bozuldu" değil
             * "biraz bekleyin" demek gerekir. */
            $status === 429 => 'Ücretsiz katmanın hız sınırına takıldınız. '
                             . 'Birkaç saniye bekleyip tekrar deneyin.',

            default         => 'API hatası (HTTP ' . $status . ').',
        };

        return $message === '' ? $hint : $hint . ' — ' . $message;
    }

    /* =================================================================
     *  MALİYET
     * ============================================================== */

    /**
     * Bu isteğin ÜCRETLİ KATMANDAKİ yaklaşık maliyeti (ABD doları).
     * Ücretsiz katmanda gerçek tutar sıfırdır.
     *
     * @param array<string,int> $usage
     */
    public static function estimateCost(string $model, array $usage): float
    {
        return self::priceFor($usage, self::PRICING[$model] ?? null);
    }

    /** @return array<int,string> Arayüzdeki model listesi için. */
    public static function models(): array
    {
        return array_keys(self::PRICING);
    }
}
