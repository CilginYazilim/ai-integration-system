<?php
/**
 * =====================================================================
 *  GÖRÜNÜM: Tek sohbet
 * ---------------------------------------------------------------------
 *  @var array<string,mixed>            $conversation
 *  @var array<int,array<string,mixed>> $messages
 *  @var bool                           $configured
 * =====================================================================
 */
?>
<div class="cy-page-head cy-page-head--compact">
    <a class="btn cy-btn cy-btn--sm cy-btn--ghost" href="<?= e(url('chat')) ?>">
        ← Sohbetler
    </a>

    <span class="cy-badge cy-badge--brand">
        <?= number_format((int) $conversation['total_tokens'], 0, ',', '.') ?> jeton ·
        $<?= number_format((float) $conversation['total_cost'], 5, ',', '.') ?>
    </span>
</div>

<div class="cy-card cy-chat" id="cy_chat"
     data-conversation="<?= (int) $conversation['id'] ?>">

    <!-- ==========================================================
         MESAJLAR
    ========================================================== -->
    <div class="cy-chat__thread" id="chat_thread">
        <?php if ($messages === []): ?>
            <p class="cy-chat__empty">
                <?= icon('mail', 'cy-icon d-block mx-auto mb-2') ?>
                Sohbet boş. Aşağıya ilk mesajınızı yazın.
            </p>
        <?php endif; ?>

        <?php foreach ($messages as $message): ?>
            <div class="cy-msg cy-msg--<?= e($message['role']) ?>">
                <?php
                /* DİKKAT: aşağıdaki <div> ile içeriği ARASINDA boşluk yok.

                   Balonun CSS'i "white-space: pre-wrap" kullanır; bu,
                   metindeki satır sonlarını ve girintileri OLDUĞU GİBİ
                   korur — kod örneği içeren yanıtlar için tam olarak
                   istediğimiz şey. Ama aynı kural şablonun kendi
                   girintisini de metin sayar: etiketi alt satıra alıp
                   içeriden yazsaydık, her balonun ilk satırı 20 boşlukla
                   başlar ve sağa kaymış görünürdü. Kullanıcı balonları
                   (kısa ve tek satır) bu yüzden bomboş bile görünüyordu.

                   Aynı sebeple nl2br() de KULLANMIYORUZ: pre-wrap satır
                   sonlarını zaten uyguluyor, üstüne <br> eklenirse her
                   boşluk iki katına çıkar.

                   e() tek başına XSS için yeterlidir; trim() ise verinin
                   kendi başındaki/sonundaki boşluğu temizler. */
                ?>
                <div class="cy-msg__bubble"><?= e(trim((string) $message['content'])) ?></div>

                <?php if (trim((string) $message['thinking']) !== ''): ?>
                    <?php /* Düşünme özeti isteğe bağlı olarak açılır.
                             <details> ile yapıyoruz; JavaScript gerekmez. */ ?>
                    <details class="cy-msg__thinking">
                        <summary>düşünme özetini göster</summary>
                        <pre><?= e(trim((string) $message['thinking'])) ?></pre>
                    </details>
                <?php endif; ?>

                <?php if ($message['role'] === 'assistant' && (int) $message['output_tokens'] > 0): ?>
                    <div class="cy-msg__meta">
                        <?= number_format((int) $message['input_tokens'], 0, ',', '.') ?> giriş ·
                        <?= number_format((int) $message['output_tokens'], 0, ',', '.') ?> çıkış jetonu ·
                        $<?= number_format((float) $message['cost_usd'], 6, ',', '.') ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- ==========================================================
         GİRİŞ ALANI
         ----------------------------------------------------------
         Form GÖNDERİLMEZ (JavaScript engeller); istek AJAX ile
         atılır. Model yanıtı saniyeler sürebilir ve bunu sayfa
         yüklemesinin içine koymak kullanıcıyı boş ekrana baktırır.
    ========================================================== -->
    <form class="cy-chat__form" id="chat_form" novalidate>
        <textarea class="form-control" id="chat_input" rows="2" maxlength="8000"
                  placeholder="<?= $configured
                      ? 'Mesajınızı yazın… (Ctrl+Enter ile gönderin)'
                      : 'Önce .env dosyasına ANTHROPIC_API_KEY ekleyin' ?>"
                  <?= $configured ? '' : 'disabled' ?>></textarea>

        <button type="submit" class="btn cy-btn cy-btn--primary" id="chat_send"
                <?= $configured ? '' : 'disabled' ?>>
            <?= icon('chevron', 'cy-icon cy-icon--sm') ?>
            <span>Gönder</span>
        </button>
    </form>
</div>
