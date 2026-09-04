<?php
/**
 * =====================================================================
 *  ChatApiController – Modele mesaj gönderme uç noktası
 * ---------------------------------------------------------------------
 *  AKIŞ
 *      1. Kullanıcı mesajını doğrula ve KAYDET
 *      2. Sohbet geçmişinden bağlam penceresini çıkar
 *      3. Seçili sağlayıcıya gönder (yeniden denemeler HttpProvider'da)
 *      4. Yanıtı, jeton kullanımını ve maliyeti KAYDET
 *      5. Tarayıcıya döndür
 *
 *  KULLANICI MESAJI NEDEN ÖNCE KAYDEDİLİR?
 *  API çağrısı 30 saniye sürebilir ve başarısız olabilir. Mesajı
 *  sonra kaydetseydik, hata durumunda kullanıcının yazdığı metin
 *  kaybolurdu. Önce kaydetmek, "yeniden dene" düğmesini de mümkün
 *  kılar.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Core\Auth;
use App\Core\Ai\Ai;
use App\Core\Env;
use App\Core\Request;
use App\Core\Response;
use App\Http\Controller;
use App\Repositories\ConversationRepository;
use RuntimeException;

final class ChatApiController extends Controller
{
    /**
     * Sistem yönergesi.
     *
     * DEĞİŞMEYEN İÇERİK BAŞA KONUR. Ön belleğe alma (prompt caching)
     * yalnızca istekteki ÖN EK birebir aynı kaldığında çalışır.
     * Buraya tarih/saat veya kullanıcı adı gibi her istekte değişen
     * bir şey koyarsanız ön bellek sessizce hiç devreye girmez ve
     * her istek tam ücretten döner.
     */
    private const SYSTEM_PROMPT = <<<'TEXT'
    Sen Türkçe konuşan, yardımsever bir yazılım asistanısın.

    Kurallar:
    - Yanıtlarını Türkçe ver.
    - Kısa ve net ol; gereksiz giriş cümlesi kurma.
    - Kod örneği verirken hangi dilde olduğunu belirt.
    - Emin olmadığın bir şeyi biliyormuş gibi anlatma; bilmiyorsan söyle.
    TEXT;

    /** Kullanıcı mesajı için üst sınır (karakter). */
    private const MAX_INPUT = 8000;

    public function send(Request $request): void
    {
        $userId = Auth::id();

        if ($userId === null) {
            Response::error('Oturumunuz sonlandı.', 401);
        }

        /* --- 1) Doğrulama -------------------------------------------- */
        $conversationId = (int) $request->input('conversation_id');
        $text           = trim($request->input('message'));

        if ($text === '') {
            Response::error('Mesaj boş olamaz.', 422);
        }

        if (mb_strlen($text) > self::MAX_INPUT) {
            /* Sınırı KAPIDA koyuyoruz. Aksi halde çok uzun bir metin
             * API'ye gider, jeton harcar ve orada reddedilir —
             * yani parası ödenmiş bir hata alırsınız. */
            Response::error(
                sprintf('Mesaj çok uzun (en fazla %d karakter).', self::MAX_INPUT),
                422
            );
        }

        $repository   = new ConversationRepository($this->db);
        $conversation = $repository->find($userId, $conversationId);

        // Sahiplik kontrolü: kimse başkasının sohbetine yazamaz (IDOR).
        if ($conversation === null) {
            Response::error('Sohbet bulunamadı.', 404);
        }

        /* --- 2) Kullanıcı mesajını kaydet ---------------------------- */
        $repository->addMessage($conversationId, 'user', $text);

        /* --- 3) Bağlam penceresi ------------------------------------- */
        $history = $repository->contextWindow($conversationId);

        if ($history === []) {
            Response::error('Gönderilecek geçmiş oluşturulamadı.', 500);
        }

        /* --- 4) Modele gönder ---------------------------------------- */
        try {
            $client = Ai::fromEnv();
            $result = $client->send($history, self::SYSTEM_PROMPT);
        } catch (RuntimeException $e) {
            /* Hata mesajını kullanıcıya OLDUĞU GİBİ gösteriyoruz:
             * Sağlayıcı onu zaten "ne yapmalısınız" diline
             * çevirmiş durumda. API anahtarı gibi hassas bir değer
             * bu metne hiçbir zaman girmez. */
            Response::error($e->getMessage(), 502);
        }

        /* --- 5) Reddetme durumu -------------------------------------
         * HTTP 200 döndü ama model isteği yanıtlamamayı seçti.
         * stop_reason'a bakmadan metni basarsak kullanıcı boş bir
         * balon görür ve neden olduğunu anlamaz. */
        if ($result['refusal'] !== null) {
            $repository->addMessage(
                $conversationId,
                'assistant',
                'Bu istek yanıtlanmadı (güvenlik değerlendirmesi: ' . $result['refusal'] . ').',
                '',
                $result['usage'],
                $result['cost']
            );

            Response::error(
                'Model bu isteği yanıtlamamayı seçti. Sorunuzu farklı bir biçimde sormayı deneyin.',
                200,
                ['refusal' => $result['refusal']]
            );
        }

        /* --- 6) Yanıtı kaydet ---------------------------------------- */
        $repository->addMessage(
            $conversationId,
            'assistant',
            $result['text'],
            $result['thinking'],
            $result['usage'],
            $result['cost']
        );

        Response::json([
            'success'  => true,
            'text'     => $result['text'],
            'thinking' => $result['thinking'],

            /* Jeton ve maliyeti HER YANITTA döndürüyoruz. Maliyeti
             * görünür kılmak, "efor seviyesini düşürsem ne olur?"
             * sorusunu somut bir karara dönüştürür. */
            'usage'    => $result['usage'],
            'cost'     => round($result['cost'], 6),
            'model'    => $result['model'],

            /* max_tokens sınırına çarpıldıysa yanıt cümlenin
             * ortasında kesilmiştir; arayüz bunu belirtmeli. */
            'truncated' => $result['stop_reason'] === 'max_tokens',
        ]);
    }

    /**
     * Kurulum durumu (arayüzdeki uyarı için).
     *
     * API ANAHTARININ KENDİSİNİ ASLA DÖNDÜRMEYİZ — yalnızca
     * "tanımlı mı" bilgisini.
     */
    public function status(Request $request): void
    {
        /* Ai::status() sağlayıcı adını, modeli, ücretsiz katman
         * bilgisini ve anahtarın tanımlı olup olmadığını verir.
         * ANAHTARIN KENDİSİ ORADA DA YOKTUR. */
        Response::json(Ai::status() + [
            'success' => true,
            'effort'  => Env::get('AI_EFFORT', 'medium'),
        ]);
    }
}
