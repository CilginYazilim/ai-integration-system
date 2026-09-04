<?php
/**
 * =====================================================================
 *  GÖRÜNÜM: Sohbet listesi
 * ---------------------------------------------------------------------
 *  @var array<int,array<string,mixed>> $rows
 *  @var App\Core\Paginator             $paginator
 *  @var array{conversations:int,messages:int,tokens:int,cost:float} $stats
 *  @var bool                           $configured
 * =====================================================================
 */

use App\Core\View;
?>

<?php if (!$configured): ?>
    <!-- ==========================================================
         KURULUM UYARISI
         ----------------------------------------------------------
         Anahtar yoksa hiçbir şey çalışmaz. Bunu söylemeyen bir
         demo, kullanıcıyı "gönder"e basıp hata almaya iter.
    ========================================================== -->
    <div class="cy-setup-note mb-3">
        <?= icon('alert', 'cy-icon cy-icon--sm') ?>
        <?php
        /* Uyarı, SEÇİLİ sağlayıcıya göre yazılır. Sabit bir değişken
         * adı yazmak, Gemini kullanan birine Anthropic anahtarı
         * istetirdi — kurulumda en can sıkıcı yanlış yönlendirme. */
        $aiStatus = App\Core\Ai\Ai::status();

        $aiKeyName = match ((string) $aiStatus['provider']) {
            'claude'            => 'ANTHROPIC_API_KEY',
            'groq'              => 'GROQ_API_KEY',
            'openai-compatible' => 'AI_API_KEY',
            default             => 'GEMINI_API_KEY',
        };
        ?>
        <span>
            <strong>API anahtarı tanımlı değil.</strong>
            <code>.env</code> dosyasına <code><?= e($aiKeyName) ?>=…</code>
            satırını ekleyin. Anahtarı
            <code><?= e((string) $aiStatus['console']) ?></code>
            adresinden<?= $aiStatus['free_tier'] ? ' <strong>ücretsiz</strong>' : '' ?>
            alabilirsiniz.
            <br>
            Anahtar <strong>yalnızca sunucuda</strong> durur; tarayıcıya asla gönderilmez.
        </span>
    </div>
<?php endif; ?>

<!-- ==============================================================
     1) KULLANIM ÖZETİ
     --------------------------------------------------------------
     Maliyeti görünür kılmak, yapay zekâ entegrasyonlarında en çok
     atlanan ayrıntıdır. "Çalışıyor" diye sevinip ay sonunda
     faturayı görmek yaygın bir hikâyedir.
============================================================== -->
<div class="cy-stats mb-3">
    <div class="cy-stat">
        <span class="cy-stat__icon cy-stat__icon--brand"><?= icon('mail') ?></span>
        <span>
            <span class="cy-stat__label">Sohbet</span>
            <span class="cy-stat__value"><?= (int) $stats['conversations'] ?></span>
        </span>
    </div>

    <div class="cy-stat">
        <span class="cy-stat__icon cy-stat__icon--success"><?= icon('activity') ?></span>
        <span>
            <span class="cy-stat__label">Mesaj</span>
            <span class="cy-stat__value"><?= (int) $stats['messages'] ?></span>
        </span>
    </div>

    <div class="cy-stat">
        <span class="cy-stat__icon cy-stat__icon--warning"><?= icon('upload') ?></span>
        <span>
            <span class="cy-stat__label">Jeton</span>
            <span class="cy-stat__value"><?= number_format((int) $stats['tokens'], 0, ',', '.') ?></span>
            <span class="cy-stat__hint">giriş + çıkış</span>
        </span>
    </div>

    <div class="cy-stat">
        <span class="cy-stat__icon cy-stat__icon--danger"><?= icon('alert') ?></span>
        <span>
            <span class="cy-stat__label">Tahmini maliyet</span>
            <span class="cy-stat__value">$<?= number_format((float) $stats['cost'], 4, ',', '.') ?></span>
            <span class="cy-stat__hint">liste fiyatlarıyla</span>
        </span>
    </div>
</div>

<!-- ==============================================================
     2) YENİ SOHBET
============================================================== -->
<div class="cy-card">
    <div class="cy-card__head">
        <h2 class="cy-section-title">
            <?= icon('plus', 'cy-icon cy-icon--sm') ?> Yeni Sohbet
        </h2>

        <span class="cy-badge cy-badge--brand"><?= e($model) ?> · efor: <?= e($effort) ?></span>
    </div>

    <div class="cy-card__body">
        <form method="post" action="<?= e(url('chat')) ?>" class="cy-newchat-form">
            <?= csrf_field() ?>

            <input type="text" class="form-control" name="title" maxlength="150"
                   placeholder="Sohbete bir ad verin (boş bırakılabilir)">

            <button type="submit" class="btn cy-btn cy-btn--primary">
                <?= icon('plus', 'cy-icon cy-icon--sm') ?> Başlat
            </button>
        </form>
    </div>
</div>

<!-- ==============================================================
     3) SOHBET LİSTESİ (sayfalanmış)
============================================================== -->
<div class="cy-card mt-3">
    <div class="cy-card__head">
        <h2 class="cy-section-title">
            <?= icon('activity', 'cy-icon cy-icon--sm') ?> Sohbetlerim
        </h2>
    </div>

    <div class="cy-card__body cy-card__body--flush">
        <div class="cy-table-wrap">
            <table class="table cy-table w-100">
                <thead>
                    <tr>
                        <th scope="col">Sohbet</th>
                        <th scope="col" class="cy-hide-sm">Jeton</th>
                        <th scope="col" class="cy-hide-sm">Maliyet</th>
                        <th scope="col" class="text-end">İşlem</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($rows === []): ?>
                        <tr>
                            <td colspan="4" class="text-center py-5 text-muted">
                                Henüz sohbet yok. Yukarıdan bir tane başlatın.
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td>
                                <a class="cy-user-cell text-decoration-none"
                                   href="<?= e(url('chat/show', ['id' => (int) $row['id']])) ?>">
                                    <span class="cy-user-cell__name"><?= e($row['title']) ?></span>
                                    <span class="cy-user-cell__meta">
                                        <?= (int) $row['mesaj_sayisi'] ?> mesaj ·
                                        <?= e(human_date((string) $row['updated_at'])) ?>
                                    </span>
                                </a>
                            </td>

                            <td class="cy-hide-sm">
                                <?= number_format((int) $row['total_tokens'], 0, ',', '.') ?>
                            </td>

                            <td class="cy-hide-sm">
                                $<?= number_format((float) $row['total_cost'], 5, ',', '.') ?>
                            </td>

                            <td class="text-end">
                                <div class="cy-actions justify-content-end">
                                    <a class="btn cy-btn cy-btn--sm cy-btn--primary"
                                       href="<?= e(url('chat/show', ['id' => (int) $row['id']])) ?>">
                                        Aç
                                    </a>

                                    <?php /* SİLME NEDEN FORM (POST)?
                                             Bağlantı (GET) olsaydı, bir arama motoru
                                             botu veya <img> etiketi sohbetlerinizi
                                             silebilirdi. */ ?>
                                    <form method="post" action="<?= e(url('chat/delete')) ?>" class="d-inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                        <button type="submit" class="btn cy-btn cy-btn--sm cy-btn--danger"
                                                data-confirm="Bu sohbet ve tüm mesajları silinsin mi?">
                                            Sil
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php View::partial('partials/pagination', [
            'paginator' => $paginator,
            'route'     => 'chat',
        ]); ?>
    </div>
</div>
