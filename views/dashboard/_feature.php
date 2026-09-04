<?php
/**
 * =====================================================================
 *  PARÇA: Kontrol panelinde yapay zekâ kurulum durumu
 * ---------------------------------------------------------------------
 *  views/dashboard/index.php bu dosyayı VARSA basar.
 *
 *  Bu parça artık HİÇBİR SAĞLAYICI ADI BİLMİYOR. Hepsini Ai::status()
 *  veriyor; sağlayıcı değiştiğinde burada tek bir satır bile
 *  değişmiyor. Kurulum uyarısındaki değişken adı ve anahtarın
 *  alınacağı adres de seçili sağlayıcıya göre kendiliğinden değişir.
 * =====================================================================
 */

use App\Core\Ai\Ai;
use App\Core\Env;

$status = Ai::status();

$configured = (bool) $status['configured'];
$provider   = (string) $status['provider_label'];
$model      = (string) $status['model'];
$console    = (string) $status['console'];
$freeTier   = (bool) $status['free_tier'];
$effort     = Env::get('AI_EFFORT', 'medium');

/* Kurulum uyarısında hangi değişkeni yazmaları gerektiğini
 * söylüyoruz. "API anahtarı ekleyin" demek yetmez; hangi ada,
 * hangi dosyaya yazacağını da söylemek gerekir. */
$keyName = match ((string) $status['provider']) {
    'claude'            => 'ANTHROPIC_API_KEY',
    'groq'              => 'GROQ_API_KEY',
    'openai-compatible' => 'AI_API_KEY',
    default             => 'GEMINI_API_KEY',
};
?>
<div class="cy-card mt-3">
    <div class="cy-card__head">
        <h2 class="cy-section-title">
            <?= icon('shield', 'cy-icon cy-icon--sm') ?> API Kurulumu
        </h2>
    </div>

    <div class="cy-card__body">
        <?php if (!$configured): ?>
            <div class="cy-setup-note mb-3">
                <?= icon('alert', 'cy-icon cy-icon--sm') ?>
                <span>
                    <strong>API anahtarı tanımlı değil.</strong>
                    <code>.env</code> dosyasına şu satırı ekleyin:
                    <code><?= e($keyName) ?>=…</code>
                    <?php if ($freeTier): ?>
                        <br>Anahtar <strong>ücretsizdir</strong>;
                        <code><?= e($console) ?></code> adresinden
                        kredi kartı vermeden alabilirsiniz.
                    <?php else: ?>
                        <br>Anahtarı <code><?= e($console) ?></code>
                        adresinden alabilirsiniz.
                    <?php endif; ?>
                </span>
            </div>
        <?php endif; ?>

        <dl class="cy-detail mb-3">
            <dt>Durum</dt>
            <dd>
                <?php if ($configured): ?>
                    <span class="cy-status is-active">
                        <span class="cy-status__dot"></span> Anahtar tanımlı
                    </span>
                <?php else: ?>
                    <span class="cy-status is-passive">
                        <span class="cy-status__dot"></span> Anahtar yok
                    </span>
                <?php endif; ?>
            </dd>

            <dt>Sağlayıcı</dt>
            <dd>
                <?= e($provider) ?>
                <?php if ($freeTier): ?>
                    <span class="cy-muted">— ücretsiz katmanı var</span>
                <?php endif; ?>
            </dd>

            <dt>Model</dt>
            <dd><code><?= e($model) ?></code></dd>

            <?php if ((string) $status['provider'] === 'claude'): ?>
                <dt>Efor</dt>
                <dd>
                    <code><?= e($effort) ?></code>
                    <span class="cy-muted">— düşünme derinliğini ve maliyeti birlikte belirler</span>
                </dd>
            <?php endif; ?>
        </dl>

        <p class="cy-muted mb-0" style="font-size:.8125rem">
            İstek <strong>sunucudan</strong> atılır; anahtar tarayıcıya asla gönderilmez.
            Anahtarı JavaScript'e koyup doğrudan API'ye istek atmak, onu sayfayı açan
            herkese vermek demektir.
            &nbsp;·&nbsp;
            <a href="<?= e(url('chat')) ?>">Sohbetlere git</a>
        </p>
    </div>
</div>
