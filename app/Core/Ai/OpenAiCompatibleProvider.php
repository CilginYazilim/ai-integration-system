<?php
/**
 * =====================================================================
 *  OpenAiCompatibleProvider – "chat/completions" konuşan her servis
 * ---------------------------------------------------------------------
 *      POST <taban adres>/chat/completions
 *
 *  Bu tek sınıf birden çok sağlayıcıyı karşılar, çünkü hepsi AYNI
 *  tel biçimini konuşur. Değişen tek şey taban adres, anahtar ve
 *  model adıdır:
 *
 *      Groq        https://api.groq.com/openai/v1   ← ücretsiz katman
 *      xAI (Grok)  https://api.x.ai/v1
 *      OpenRouter  https://openrouter.ai/api/v1
 *      Ollama      http://localhost:11434/v1        ← tamamen yerel
 *
 *  BU YÜZDEN AI_BASE_URL BİR AYARDIR, SABİT DEĞİL. Sağlayıcı
 *  değiştirmek için kod yazmanız gerekmiyor; .env içinde üç satır
 *  değiştiriyorsunuz.
 *
 *  ---------------------------------------------------------------
 *  BU BİÇİMİN GEMINI'DEN FARKI
 *    · Sistem yönergesi AYRI BİR ALAN DEĞİL, mesaj dizisinin BAŞINA
 *      role:"system" olarak konur.
 *    · Rol adı "assistant"tır (Gemini'de "model").
 *    · Model adı GÖVDEDE gider (Gemini'de adreste).
 *  Üç fark da küçük, üçü de sessizce yanlış davranışa yol açar —
 *  bu yüzden her sağlayıcı kendi çeviri katmanını taşır.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core\Ai;

final class OpenAiCompatibleProvider extends HttpProvider
{
    public function __construct(
        string $apiKey,
        string $model,

        /** Taban adres — sonundaki eğik çizgi olsa da olmasa da çalışır. */
        private readonly string $baseUrl = 'https://api.groq.com/openai/v1',

        int $maxTokens = 4096,
        int $timeout = 120,
    ) {
        parent::__construct($apiKey, $model, $maxTokens, $timeout);
    }

    /* =================================================================
     *  İSTEK
     * ============================================================== */

    protected function endpoint(): string
    {
        return rtrim($this->baseUrl, '/') . '/chat/completions';
    }

    /** @return array<int,string> */
    protected function headers(): array
    {
        return [
            'content-type: application/json',

            // Bu ailede kimlik doğrulama Bearer jetonuyladır.
            'authorization: Bearer ' . $this->apiKey,
        ];
    }

    /**
     * @param  array<int,array{role:string,content:string}> $messages
     * @return array<string,mixed>
     */
    protected function payload(array $messages, string $system): array
    {
        $chat = [];

        if ($system !== '') {
            // Sistem yönergesi dizinin BAŞINA konur (bkz. üstteki not).
            $chat[] = ['role' => 'system', 'content' => $system];
        }

        foreach ($messages as $message) {
            $role = ($message['role'] ?? 'user') === 'assistant' ? 'assistant' : 'user';

            $chat[] = ['role' => $role, 'content' => (string) ($message['content'] ?? '')];
        }

        return [
            'model'       => $this->modelName,
            'messages'    => $chat,
            'max_tokens'  => $this->maxTokens,
        ];
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

        $usage['input_tokens']  = (int) ($response['usage']['prompt_tokens'] ?? 0);
        $usage['output_tokens'] = (int) ($response['usage']['completion_tokens'] ?? 0);
        $usage['cache_read']    = (int) ($response['usage']['prompt_tokens_details']['cached_tokens'] ?? 0);

        $choice = $response['choices'][0] ?? null;

        if (!is_array($choice)) {
            return $this->result('', '', 'empty', null, $usage);
        }

        $text = (string) ($choice['message']['content'] ?? '');

        /* BAZI SAĞLAYICILAR DÜŞÜNMEYİ AYRI ALANDA DÖNDÜRÜR.
         * Standartta yoktur; varsa alıyoruz, yoksa boş kalıyor. */
        $thinking = (string) ($choice['message']['reasoning_content']
            ?? $choice['message']['reasoning']
            ?? '');

        $finish = (string) ($choice['finish_reason'] ?? '');

        $stopReason = match ($finish) {
            'stop'           => 'end_turn',
            'length'         => 'max_tokens',
            ''               => 'end_turn',
            default          => $finish,
        };

        // İçerik filtresi bir hata değil, sağlayıcının kararıdır.
        $refusal = $finish === 'content_filter' ? 'content_filter' : null;

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

            /* Bu ailede sağlayıcı ve model sayısı sınırsızdır; koda
             * gömülü bir fiyat tablosu tutmak yanlış rakam göstermeye
             * yol açardı. Yanlış bir maliyet, hiç maliyet
             * göstermemekten kötüdür. */
            'cost'        => 0.0,
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

        $hint = match (true) {
            $status === 400 => 'İstek reddedildi. AI_MODEL değeri veya parametreler hatalı olabilir.',
            $status === 401,
            $status === 403 => 'API anahtarı geçersiz. .env içindeki AI_API_KEY değerini kontrol edin.',
            $status === 404 => 'Uç nokta ya da model bulunamadı. AI_BASE_URL ve AI_MODEL değerlerini kontrol edin.',
            $status === 413 => 'İstek çok büyük. Sohbet geçmişini kısaltın.',
            $status === 429 => 'Hız sınırına takıldınız. Birkaç saniye bekleyip tekrar deneyin.',
            default         => 'API hatası (HTTP ' . $status . ').',
        };

        return $message === '' ? $hint : $hint . ' — ' . $message;
    }
}
