/* ==================================================================
 *  SOHBET EKRANI
 *  cilginyazilim.com – Yapay Zekâ (AI) Entegrasyon Sistemi
 * ------------------------------------------------------------------
 *  Model yanıtı saniyeler sürer. Bu yüzden istek AJAX ile atılır ve
 *  bu süre boyunca arayüz üç şey yapar:
 *
 *    1. Kullanıcının mesajını HEMEN ekrana koyar (beklemeden)
 *    2. "yazıyor…" göstergesi çıkarır
 *    3. Gönder düğmesini kilitler (çift gönderimi engeller)
 *
 *  Üçüncüsü önemsiz görünür ama değildir: kilitlenmemiş bir düğme,
 *  sabırsız kullanıcının aynı soruyu üç kez sorup üç kez ödemesi
 *  demektir.
 * ================================================================== */

/* global CY, jQuery */
(function ($) {
    'use strict';

    /* Onay: sohbet silmek geri alınamaz. Liste sayfasında da
     * gerektiği için en başta, koşulsuz bağlanıyor. */
    $(document).on('click', '[data-confirm]', function (event) {
        if (!window.confirm($(this).data('confirm'))) {
            event.preventDefault();
        }
    });

    var $chat = $('#cy_chat');
    if (!$chat.length) { return; }

    var $thread = $('#chat_thread');
    var $form   = $('#chat_form');
    var $input  = $('#chat_input');
    var $send   = $('#chat_send');

    var conversationId = $chat.data('conversation');
    var busy = false;

    scrollToEnd();

    /* ---------------------------------------------------------------
     *  Yardımcılar
     * ------------------------------------------------------------- */

    function scrollToEnd() {
        $thread.scrollTop($thread[0].scrollHeight);
    }

    /**
     * Ekrana mesaj balonu ekler.
     *
     * DİKKAT: Metni .text() ile koyuyoruz, .html() ile DEĞİL.
     * Model yanıtı da kullanıcı girdisi de HTML içerebilir; .html()
     * kullanmak doğrudan XSS açığı olurdu. Satır sonlarını CSS'in
     * "white-space: pre-wrap" kuralı gösterir; <br> üretmeye gerek yok.
     */
    function bubble(role, text) {
        var $wrap = $('<div class="cy-msg"></div>').addClass('cy-msg--' + role);
        var $body = $('<div class="cy-msg__bubble"></div>').text(text);

        $wrap.append($body);
        $thread.append($wrap);
        scrollToEnd();

        return $wrap;
    }

    function meta($wrap, response) {
        var parts = [];

        if (response.usage) {
            parts.push(Number(response.usage.input_tokens).toLocaleString('tr-TR') + ' giriş');
            parts.push(Number(response.usage.output_tokens).toLocaleString('tr-TR') + ' çıkış jetonu');

            /* Ön bellek okuması olduysa söylüyoruz: sıfırdan
             * büyükse sistem yönergesi yeniden işlenmemiş,
             * yani o kısım çok daha ucuza gelmiş demektir. */
            if (response.usage.cache_read > 0) {
                parts.push(Number(response.usage.cache_read).toLocaleString('tr-TR') + ' ön bellekten');
            }
        }

        if (typeof response.cost === 'number') {
            parts.push('$' + response.cost.toFixed(6));
        }

        $('<div class="cy-msg__meta"></div>').text(parts.join(' · ')).appendTo($wrap);
    }

    /* ---------------------------------------------------------------
     *  Gönderim
     * ------------------------------------------------------------- */

    function send() {
        if (busy) { return; }

        var text = $.trim($input.val());
        if (!text) { return; }

        busy = true;
        $send.prop('disabled', true);
        $input.prop('disabled', true);

        // Kullanıcının mesajı BEKLEMEDEN ekrana çıkar.
        bubble('user', text);
        $input.val('');

        var $typing = bubble('assistant', 'yazıyor…').addClass('is-typing');

        CY.post('api/chat/send', {
            conversation_id: conversationId,
            message: text
        }).done(function (response) {
            $typing.removeClass('is-typing');

            if (!response.success) {
                /* Reddetme (refusal) bir HTTP hatası değildir:
                 * istek başarıyla ulaştı, model yanıtlamamayı
                 * seçti. Kullanıcıya bunu açıkça söylüyoruz. */
                $typing.addClass('cy-msg--refusal')
                       .find('.cy-msg__bubble').text(response.description);
                return;
            }

            $typing.find('.cy-msg__bubble').text(response.text);
            meta($typing, response);

            if (response.truncated) {
                CY.notify('Yanıt jeton sınırına takıldı ve kesildi. .env içindeki AI_MAX_TOKENS değerini artırabilirsiniz.', 'warning');
            }

            scrollToEnd();
        }).fail(function (xhr) {
            $typing.removeClass('is-typing').addClass('cy-msg--error');

            var message = (xhr.responseJSON && xhr.responseJSON.description)
                || 'Yanıt alınamadı.';

            $typing.find('.cy-msg__bubble').text(message);

            CY.ajaxError(xhr, 'Yanıt alınamadı.');
        }).always(function () {
            busy = false;
            $send.prop('disabled', false);
            $input.prop('disabled', false).trigger('focus');
        });
    }

    $form.on('submit', function (event) {
        event.preventDefault();
        send();
    });

    /* Ctrl+Enter ile gönder. Yalnızca Enter'a bağlamıyoruz: çok
     * satırlı bir metin kutusunda Enter satır atlamalıdır, mesaj
     * göndermemelidir. */
    $input.on('keydown', function (event) {
        if (event.key === 'Enter' && (event.ctrlKey || event.metaKey)) {
            event.preventDefault();
            send();
        }
    });
})(jQuery);
