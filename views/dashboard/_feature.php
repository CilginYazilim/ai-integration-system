<?php
/**
 * =====================================================================
 *  PARÇA: Kontrol panelinde yapay zekâ kurulum durumu
 * ---------------------------------------------------------------------
 *  views/dashboard/index.php bu dosyayı VARSA basar.
 * =====================================================================
 */

use App\Core\ClaudeClient;
use App\Core\Env;

$configured = ClaudeClient::isConfigured();
$model      = Env::get('AI_MODEL', 'claude-opus-5');
$effort     = Env::get('AI_EFFORT', 'medium');
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
                    <code>ANTHROPIC_API_KEY=sk-ant-...</code>
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

            <dt>Model</dt>
            <dd><code><?= e($model) ?></code></dd>

            <dt>Efor</dt>
            <dd>
                <code><?= e($effort) ?></code>
                <span class="cy-muted">— düşünme derinliğini ve maliyeti birlikte belirler</span>
            </dd>

            <dt>Uç nokta</dt>
            <dd><code>POST https://api.anthropic.com/v1/messages</code></dd>
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
