<?php
/**
 * =====================================================================
 *  ChatController – Sohbet ekranları
 * ---------------------------------------------------------------------
 *  Sayfa çizimini yapar; modele istek atma işi AJAX uç noktasındadır
 *  (Api\ChatApiController). Bu ayrım bilinçlidir: model yanıtı
 *  saniyeler sürebilir ve bunu sayfa yüklemesinin içine koymak
 *  kullanıcıyı boş ekrana baktırır.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\Ai\Ai;
use App\Core\Env;
use App\Core\Flash;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Response;
use App\Http\Controller;
use App\Repositories\ConversationRepository;

final class ChatController extends Controller
{
    /* =================================================================
     *  SOHBET LİSTESİ
     * ============================================================== */

    public function index(Request $request): void
    {
        $userId = (int) Auth::id();

        $repository = $this->conversations();

        $total     = $repository->countForUser($userId);
        $paginator = new Paginator($total, Paginator::pageFromRequest($request), 10);
        $rows      = $repository->pageForUser($userId, $paginator->offset(), $paginator->perPage());

        $this->view('chat/index', [
            'title'      => 'Sohbetler',
            'subtitle'   => Ai::label() . ' ile yapılan konuşmalar',
            'rows'       => $rows,
            'paginator'  => $paginator,
            'stats'      => $repository->stats($userId),
            'configured' => Ai::isConfigured(),
            'model'      => Ai::model(),
            'provider'   => Ai::label(),
            'effort'     => Env::get('AI_EFFORT', 'medium'),

            // Silme onayı için; sohbet ekranıyla aynı dosya.
            'scripts'    => ['chat.js'],
        ]);
    }

    /* =================================================================
     *  TEK SOHBET
     * ============================================================== */

    public function show(Request $request): void
    {
        $userId = (int) Auth::id();
        $id     = (int) $request->input('id');

        $repository   = $this->conversations();
        $conversation = $repository->find($userId, $id);

        /* Sahibi değilse "yetkiniz yok" DEMİYORUZ, "bulunamadı"
         * diyoruz. Aradaki fark ince ama önemli: "yetkiniz yok"
         * yanıtı, o ID'de bir sohbetin VAR OLDUĞUNU doğrular. */
        if ($conversation === null) {
            Flash::error('Sohbet bulunamadı.');
            Response::redirect(url('chat'));
        }

        $this->view('chat/show', [
            'title'        => $conversation['title'],
            'subtitle'     => Ai::label() . ' ile sohbet',
            'conversation' => $conversation,
            'messages'     => $repository->messages($id),
            'configured'   => Ai::isConfigured(),
            'scripts'      => ['chat.js'],
        ]);
    }

    /* =================================================================
     *  YENİ SOHBET
     * ============================================================== */

    public function store(Request $request): void
    {
        $userId = (int) Auth::id();

        $title = trim($request->input('title'));

        if ($title === '') {
            $title = 'Yeni sohbet · ' . date('d.m.Y H:i');
        }

        $id = $this->conversations()->create($userId, $title);

        $this->activity()->log($userId, 'ai_conversation_created', 'Yeni sohbet başlatıldı: ' . $title, $request->ip());

        /* POST → Redirect → GET: F5'te ikinci bir sohbet açılmasın. */
        Response::redirect(url('chat/show', ['id' => $id]));
    }

    public function destroy(Request $request): void
    {
        $userId = (int) Auth::id();
        $id     = (int) $request->input('id');

        if ($this->conversations()->delete($userId, $id)) {
            Flash::success('Sohbet silindi.');
        } else {
            Flash::error('Sohbet bulunamadı.');
        }

        Response::redirect(url('chat'));
    }

    private function conversations(): ConversationRepository
    {
        return new ConversationRepository($this->db);
    }
}
