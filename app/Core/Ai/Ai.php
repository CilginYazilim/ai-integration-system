<?php
/**
 * =====================================================================
 *  Ai – sağlayıcı seçici (fabrika)
 * ---------------------------------------------------------------------
 *  Uygulamanın yapay zekâya açılan TEK kapısıdır. Denetleyici hangi
 *  sağlayıcının kullanıldığını bilmez, bilmesi de gerekmez:
 *
 *      $result = Ai::fromEnv()->send($history, $systemPrompt);
 *
 *  Seçim .env içindeki AI_PROVIDER değerine bakar:
 *
 *      gemini  (varsayılan)  ücretsiz katmanı var — aistudio.google.com
 *      groq                  ücretsiz katmanı var — console.groq.com
 *      openai-compatible     xAI (Grok), OpenRouter, Ollama …
 *      claude                ücretli — console.anthropic.com
 *
 *  ---------------------------------------------------------------
 *  NEDEN VARSAYILAN ÜCRETSİZ BİR SAĞLAYICI?
 *  Bu bir öğrenme örneğidir. Denemek için kredi kartı istemek,
 *  deponun önündeki en pahalı engeldi: kodu okumak isteyen çoğu
 *  kişi çalışır hâlini hiç görmeden ayrılıyordu. Artık anahtar
 *  almak birkaç saniye sürüyor ve hiçbir şey ödemiyorsunuz.
 *
 *  ---------------------------------------------------------------
 *  ANAHTAR ARAMA SIRASI
 *  Her sağlayıcının kendi alışılmış değişken adı vardır
 *  (GEMINI_API_KEY, GROQ_API_KEY, ANTHROPIC_API_KEY). Hepsinin
 *  yerine geçen ortak bir AI_API_KEY de kabul edilir; böylece
 *  sağlayıcı denerken tek satır değiştirmeniz yeterli olur.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core\Ai;

use App\Core\Env;
use RuntimeException;

final class Ai
{
    /**
     * Sağlayıcı tanımları.
     *
     * key_names : anahtarın aranacağı ortam değişkenleri (sırayla)
     * default_model / base_url : o sağlayıcının makul varsayılanları
     * console   : anahtarın alınacağı adres (hata mesajlarında geçer)
     * free      : ücretsiz katmanı var mı? (arayüzde rozet için)
     *
     * @var array<string,array<string,mixed>>
     */
    private const PROVIDERS = [
        'gemini' => [
            'label'         => 'Google Gemini',
            'key_names'     => ['GEMINI_API_KEY', 'GOOGLE_API_KEY', 'AI_API_KEY'],
            'default_model' => 'gemini-2.5-flash',
            'console'       => 'aistudio.google.com/apikey',
            'free'          => true,
        ],
        'groq' => [
            'label'         => 'Groq',
            'key_names'     => ['GROQ_API_KEY', 'AI_API_KEY'],
            'default_model' => 'llama-3.3-70b-versatile',
            'base_url'      => 'https://api.groq.com/openai/v1',
            'console'       => 'console.groq.com/keys',
            'free'          => true,
        ],
        'openai-compatible' => [
            'label'         => 'OpenAI uyumlu (xAI · OpenRouter · Ollama)',
            'key_names'     => ['AI_API_KEY', 'XAI_API_KEY', 'OPENROUTER_API_KEY'],
            'default_model' => '',
            'base_url'      => '',
            'console'       => 'sağlayıcınızın panelinden',
            'free'          => false,
        ],
        'claude' => [
            'label'         => 'Anthropic Claude',
            'key_names'     => ['ANTHROPIC_API_KEY', 'AI_API_KEY'],
            'default_model' => 'claude-sonnet-5',
            'console'       => 'console.anthropic.com',
            'free'          => false,
        ],
    ];

    private const DEFAULT_PROVIDER = 'gemini';

    /* =================================================================
     *  SEÇİM
     * ============================================================== */

    /** Şu an seçili sağlayıcının anahtarı ("gemini", "claude" …). */
    public static function provider(): string
    {
        $name = strtolower(trim(Env::get('AI_PROVIDER', self::DEFAULT_PROVIDER)));

        // Tanımsız bir ad yazıldıysa sessizce varsayılana dönmek yerine
        // varsayılanı kullanıyoruz ama adı da doğruluyoruz; yazım
        // hatasının bedeli, hiç çalışmayan bir sohbet olmasın.
        return isset(self::PROVIDERS[$name]) ? $name : self::DEFAULT_PROVIDER;
    }

    /** Arayüzde göstermek için okunabilir ad. */
    public static function label(): string
    {
        return (string) self::PROVIDERS[self::provider()]['label'];
    }

    /** Seçili sağlayıcının ücretsiz katmanı var mı? */
    public static function isFree(): bool
    {
        return (bool) self::PROVIDERS[self::provider()]['free'];
    }

    /** Anahtarın alınacağı adres — kurulum uyarısında gösterilir. */
    public static function console(): string
    {
        return (string) self::PROVIDERS[self::provider()]['console'];
    }

    /** Kullanılacak model adı. */
    public static function model(): string
    {
        $model = trim(Env::get('AI_MODEL', ''));

        return $model !== ''
            ? $model
            : (string) self::PROVIDERS[self::provider()]['default_model'];
    }

    /**
     * Anahtar tanımlı mı? (Arayüzde kurulum uyarısı göstermek için.)
     *
     * API ANAHTARININ KENDİSİ HİÇBİR ZAMAN DÖNDÜRÜLMEZ — yalnızca
     * "var mı yok mu" bilgisi.
     */
    public static function isConfigured(): bool
    {
        return self::key() !== '';
    }

    /** Seçili sağlayıcının anahtarını ortamdan okur. */
    private static function key(): string
    {
        foreach ((array) self::PROVIDERS[self::provider()]['key_names'] as $name) {
            $value = trim(Env::get((string) $name, ''));

            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    /* =================================================================
     *  ÜRETİM
     * ============================================================== */

    /**
     * Ortam değişkenlerinden çalışmaya hazır bir sağlayıcı üretir.
     *
     * @throws RuntimeException Anahtar yoksa — mesaj kullanıcıya
     *         doğrudan gösterilebilecek biçimdedir.
     */
    public static function fromEnv(): Provider
    {
        $name = self::provider();
        $key  = self::key();

        if ($key === '') {
            $expected = (string) ((array) self::PROVIDERS[$name]['key_names'])[0];

            throw new RuntimeException(sprintf(
                '.env içinde %s tanımlı değil. Ücretsiz anahtarınızı %s adresinden alabilirsiniz.',
                $expected,
                self::console()
            ));
        }

        $model     = self::model();
        $maxTokens = max(256, (int) Env::get('AI_MAX_TOKENS', '4096'));

        return match ($name) {
            'claude' => new ClaudeProvider(
                $key,
                $model,
                $maxTokens,
                Env::get('AI_EFFORT', 'medium'),
            ),

            'groq', 'openai-compatible' => new OpenAiCompatibleProvider(
                $key,
                $model,
                self::baseUrl($name),
                $maxTokens,
            ),

            default => new GeminiProvider(
                $key,
                $model,
                $maxTokens,
                Env::bool('AI_THINKING', false),
            ),
        };
    }

    /**
     * OpenAI uyumlu sağlayıcılar için taban adres.
     *
     * .env'deki AI_BASE_URL her zaman kazanır; yoksa sağlayıcının
     * bilinen adresi kullanılır. "openai-compatible" seçilip adres
     * verilmediyse tahmin yürütmüyoruz — hangi servise bağlanacağını
     * yalnızca kullanıcı bilir.
     */
    private static function baseUrl(string $name): string
    {
        $url = trim(Env::get('AI_BASE_URL', ''));

        if ($url !== '') {
            return $url;
        }

        $known = (string) (self::PROVIDERS[$name]['base_url'] ?? '');

        if ($known === '') {
            throw new RuntimeException(
                '.env içinde AI_BASE_URL tanımlı değil. '
                . 'AI_PROVIDER=openai-compatible seçildiğinde hangi servise '
                . 'bağlanılacağını bu değer belirler (örn. https://api.x.ai/v1).'
            );
        }

        return $known;
    }

    /* =================================================================
     *  ARAYÜZ İÇİN
     * ============================================================== */

    /**
     * Seçili sağlayıcının bilinen modelleri. Boş dönebilir:
     * OpenAI uyumlu servislerde model listesi sağlayıcıya göre
     * değişir ve koda gömmek yanlış bilgi vermek olurdu.
     *
     * @return array<int,string>
     */
    public static function models(): array
    {
        return match (self::provider()) {
            'gemini' => GeminiProvider::models(),
            'claude' => ClaudeProvider::models(),
            default  => [],
        };
    }

    /**
     * Arayüzün ihtiyaç duyduğu tüm durum bilgisi — anahtar hariç.
     *
     * @return array<string,mixed>
     */
    public static function status(): array
    {
        return [
            'provider'       => self::provider(),
            'provider_label' => self::label(),
            'free_tier'      => self::isFree(),
            'configured'     => self::isConfigured(),
            'model'          => self::model(),
            'console'        => self::console(),
        ];
    }
}
