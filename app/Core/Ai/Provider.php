<?php
/**
 * =====================================================================
 *  Provider – yapay zekâ sağlayıcısı arayüzü
 * ---------------------------------------------------------------------
 *  Bu depo başlangıçta tek bir sağlayıcıya (Anthropic) bağlıydı.
 *  Sorun teknik değil, PRATİKTİ: örneği denemek isteyen birinin
 *  önce kredi kartı girmesi gerekiyordu. Bir öğrenme örneğinin
 *  önündeki en pahalı engel budur.
 *
 *  Artık uygulama sağlayıcıyı BİLMİYOR. Yalnızca şunu biliyor:
 *  "mesaj dizisi ver, normalleştirilmiş yanıt al". Hangi sağlayıcının
 *  çalışacağına `.env` içindeki AI_PROVIDER karar verir.
 *
 *  ---------------------------------------------------------------
 *  NORMALLEŞTİRİLMİŞ YANIT — TEK SÖZLEŞME
 *
 *  Her sağlayıcı kendi biçiminde konuşur; hiçbiri diğerine
 *  benzemez. Denetleyicinin bunu bilmesi gerekmez, çünkü her
 *  sağlayıcı yanıtını şu diziye çevirmek ZORUNDADIR:
 *
 *      [
 *        'text'        => string,   yanıt metni
 *        'thinking'    => string,   düşünme özeti (yoksa '')
 *        'stop_reason' => string,   'end_turn' | 'max_tokens' | …
 *        'refusal'     => ?string,  model yanıtlamadıysa sebep
 *        'usage'       => array{input_tokens:int, output_tokens:int,
 *                               cache_write:int, cache_read:int},
 *        'cost'        => float,    tahmini maliyet (ABD doları)
 *        'model'       => string,   yanıtı üreten model
 *      ]
 *
 *  Bu sözleşme sayesinde sağlayıcı değiştirmek, arayüzde tek bir
 *  satır bile değiştirmeden mümkün oldu.
 *
 *  ---------------------------------------------------------------
 *  ORTAK KURAL — API ANAHTARI ASLA TARAYICIYA GİTMEZ
 *  Hangi sağlayıcı olursa olsun istek SUNUCUDAN atılır. Anahtarı
 *  JavaScript'e koyup doğrudan sağlayıcıya istek atmak, anahtarınızı
 *  sayfayı açan herkese vermek demektir.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core\Ai;

interface Provider
{
    /**
     * Sohbeti gönderir ve normalleştirilmiş yanıtı döndürür.
     *
     * @param array<int,array{role:string,content:string}> $messages
     *        Tüm konuşma geçmişi. API'lerin hepsi DURUMSUZDUR:
     *        önceki mesajları saklamazlar. "Model beni hatırlamıyor"
     *        şikâyetinin sebebi neredeyse her zaman geçmişi
     *        göndermemektir.
     *
     * @param string $system Sistem yönergesi (modelin rolü/kuralları)
     *
     * @return array{
     *     text:string, thinking:string, stop_reason:string,
     *     refusal:?string, usage:array<string,int>, cost:float, model:string
     * }
     *
     * @throws \RuntimeException Kullanıcıya gösterilebilir bir mesajla.
     */
    public function send(array $messages, string $system = ''): array;

    /** Bu isteğin gittiği model. */
    public function model(): string;
}
