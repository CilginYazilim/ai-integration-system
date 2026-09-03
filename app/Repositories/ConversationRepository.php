<?php
/**
 * =====================================================================
 *  ConversationRepository – Sohbetler ve mesajlar
 * ---------------------------------------------------------------------
 *  NEDEN GEÇMİŞİ VERİTABANINDA TUTUYORUZ?
 *  Messages API DURUMSUZDUR: sunucu önceki mesajlarınızı saklamaz.
 *  Her istekte tüm konuşmayı yeniden gönderirsiniz. Dolayısıyla
 *  "hafıza" tamamen sizin sorumluluğunuzdadır.
 *
 *  Bu aynı zamanda MALİYETİN de kaynağıdır: konuşma uzadıkça her
 *  istekte gönderilen giriş jetonu artar. 50 mesajlık bir sohbette
 *  51. soruyu sormak, ilk soruyu sormaktan kat kat pahalıdır.
 *  contextWindow() bu yüzden son N mesajı döndürür.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class ConversationRepository
{
    /**
     * Modele gönderilecek en fazla mesaj sayısı.
     *
     * NEDEN SINIR VAR?
     *  1. MALİYET: her mesaj her istekte yeniden gönderilir
     *  2. BAĞLAM PENCERESİ: sonsuz değildir; aşılırsa istek reddedilir
     *
     * Daha akıllı yöntemler var (eski mesajları özetlemek, sunucu
     * taraflı sıkıştırma), ama "son N mesaj" en basit ve en
     * öngörülebilir olanıdır.
     */
    private const CONTEXT_MESSAGES = 20;

    public function __construct(private readonly PDO $db)
    {
    }

    /* =================================================================
     *  SOHBETLER
     * ============================================================== */

    public function create(int $userId, string $title): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO ai_conversations (user_id, title, created_at, updated_at)
             VALUES (:user, :title, NOW(), NOW())'
        );

        $stmt->execute([
            ':user'  => $userId,
            ':title' => mb_substr($title, 0, 150),
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Sohbeti getirir — YALNIZCA SAHİBİNE.
     *
     * user_id koşulu şarttır: ID'yi bilen biri başkasının sohbetini
     * okuyabilirdi (IDOR). Sohbetler kişisel içerik taşır.
     *
     * @return array<string,mixed>|null
     */
    public function find(int $userId, int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM ai_conversations WHERE id = :id AND user_id = :user'
        );
        $stmt->execute([':id' => $id, ':user' => $userId]);

        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function countForUser(int $userId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM ai_conversations WHERE user_id = :user');
        $stmt->execute([':user' => $userId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Kullanıcının sohbetleri, sayfa sayfa.
     *
     * LIMIT/OFFSET tamsayı olarak gömülür; bkz. UserRepository::page().
     *
     * @return array<int,array<string,mixed>>
     */
    public function pageForUser(int $userId, int $offset, int $limit): array
    {
        $sql = 'SELECT c.*,
                       (SELECT COUNT(*) FROM ai_messages m WHERE m.conversation_id = c.id) AS mesaj_sayisi
                  FROM ai_conversations c
                 WHERE c.user_id = :user
                 ORDER BY c.updated_at DESC
                 LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user' => $userId]);

        return $stmt->fetchAll();
    }

    /** Sohbeti ve (yabancı anahtar sayesinde) mesajlarını siler. */
    public function delete(int $userId, int $id): bool
    {
        $stmt = $this->db->prepare(
            'DELETE FROM ai_conversations WHERE id = :id AND user_id = :user'
        );
        $stmt->execute([':id' => $id, ':user' => $userId]);

        return $stmt->rowCount() > 0;
    }

    /* =================================================================
     *  MESAJLAR
     * ============================================================== */

    /**
     * Mesajı kaydeder ve sohbetin toplamlarını günceller.
     *
     * @param array<string,int> $usage
     */
    public function addMessage(
        int $conversationId,
        string $role,
        string $content,
        string $thinking = '',
        array $usage = [],
        float $cost = 0.0,
    ): int {
        $this->db->beginTransaction();

        try {
            $stmt = $this->db->prepare(
                'INSERT INTO ai_messages
                     (conversation_id, role, content, thinking,
                      input_tokens, output_tokens, cost_usd, created_at)
                 VALUES
                     (:conversation, :role, :content, :thinking,
                      :input, :output, :cost, NOW())'
            );

            $stmt->execute([
                ':conversation' => $conversationId,

                /* Rol beyaz listeden geçer. Sütun zaten ENUM ama
                 * uygulama tarafında da kapıda durmak, hatayı
                 * veritabanına kadar taşımaktan iyidir. */
                ':role'     => $role === 'assistant' ? 'assistant' : 'user',
                ':content'  => $content,
                ':thinking' => $thinking,
                ':input'    => $usage['input_tokens'] ?? 0,
                ':output'   => $usage['output_tokens'] ?? 0,
                ':cost'     => $cost,
            ]);

            $id = (int) $this->db->lastInsertId();

            /* Sohbetin toplamlarını güncelliyoruz. Her listelemede
             * mesajları toplamak yerine sayacı burada tutmak,
             * sohbet listesini büyüdükçe yavaşlamaktan kurtarır. */
            $update = $this->db->prepare(
                'UPDATE ai_conversations
                    SET updated_at    = NOW(),
                        total_tokens  = total_tokens + :tokens,
                        total_cost    = total_cost + :cost
                  WHERE id = :id'
            );

            $update->execute([
                ':tokens' => ($usage['input_tokens'] ?? 0) + ($usage['output_tokens'] ?? 0),
                ':cost'   => $cost,
                ':id'     => $conversationId,
            ]);

            $this->db->commit();

            return $id;
        } catch (\Throwable $e) {
            $this->db->rollBack();

            throw $e;
        }
    }

    /**
     * Sohbetin TÜM mesajları (ekranda göstermek için).
     *
     * @return array<int,array<string,mixed>>
     */
    public function messages(int $conversationId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM ai_messages WHERE conversation_id = :id ORDER BY id ASC'
        );
        $stmt->execute([':id' => $conversationId]);

        return $stmt->fetchAll();
    }

    /**
     * MODELE GÖNDERİLECEK geçmiş.
     *
     * Ekranda gösterdiğimiz geçmişten FARKLIDIR:
     *  - Yalnızca son N mesaj (maliyet ve bağlam sınırı)
     *  - Yalnızca "role" ve "content" (API başka alan beklemez)
     *  - Düşünme metni GÖNDERİLMEZ: modelin kendi düşünme blokları
     *    aynı model üzerinde aynen geri gönderilebilir, ama biz
     *    onları düz metne çevirip sakladığımız için geri
     *    göndermiyoruz — yanlış biçimde göndermek hataya yol açar
     *
     * SIRA ÖNEMLİDİR: son N mesajı alıp ESKİDEN YENİYE sıralıyoruz.
     * Ters sırada göndermek konuşmayı anlamsız hale getirir.
     *
     * @return array<int,array{role:string,content:string}>
     */
    public function contextWindow(int $conversationId, int $limit = self::CONTEXT_MESSAGES): array
    {
        $limit = max(2, min(100, $limit));

        $stmt = $this->db->prepare(
            'SELECT role, content
               FROM ai_messages
              WHERE conversation_id = :id
              ORDER BY id DESC
              LIMIT ' . $limit
        );
        $stmt->execute([':id' => $conversationId]);

        $rows = array_reverse($stmt->fetchAll());

        $messages = [];

        foreach ($rows as $row) {
            $messages[] = [
                'role'    => (string) $row['role'],
                'content' => (string) $row['content'],
            ];
        }

        /* API, konuşmanın KULLANICI mesajıyla başlamasını bekler.
         * Pencere bir asistan yanıtının ortasından başlarsa istek
         * 400 ile reddedilir; baştaki asistan mesajlarını atıyoruz. */
        while ($messages !== [] && $messages[0]['role'] !== 'user') {
            array_shift($messages);
        }

        return $messages;
    }

    /* =================================================================
     *  ÖZET
     * ============================================================== */

    /**
     * Panel kartları için toplamlar.
     *
     * @return array{conversations:int,messages:int,tokens:int,cost:float}
     */
    public function stats(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) AS sohbet,
                    COALESCE(SUM(total_tokens), 0) AS jeton,
                    COALESCE(SUM(total_cost), 0)   AS maliyet
               FROM ai_conversations
              WHERE user_id = :user'
        );
        $stmt->execute([':user' => $userId]);

        $row = $stmt->fetch() ?: [];

        $messages = $this->db->prepare(
            'SELECT COUNT(*) FROM ai_messages m
               INNER JOIN ai_conversations c ON c.id = m.conversation_id
              WHERE c.user_id = :user'
        );
        $messages->execute([':user' => $userId]);

        return [
            'conversations' => (int) ($row['sohbet'] ?? 0),
            'messages'      => (int) $messages->fetchColumn(),
            'tokens'        => (int) ($row['jeton'] ?? 0),
            'cost'          => (float) ($row['maliyet'] ?? 0),
        ];
    }
}
