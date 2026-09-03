<?php
/**
 * =====================================================================
 *  UserRepository – users tablosuna erişimin TEK kapısı
 * ---------------------------------------------------------------------
 *  SQL yalnızca burada yazılır. Denetleyiciler ve görünümler tabloyu,
 *  sütun adlarını, JOIN'leri BİLMEZ. Kazancı:
 *
 *    - Sütun adı değiştiğinde tek dosya düzeltilir
 *    - Her sorgu hazırlanmış ifadeyle (prepared statement) çalışır;
 *      SQL enjeksiyonu tek bir yerde, sistematik olarak kapanır
 *    - Sorguları görmek için tüm projeyi taramak gerekmez
 *
 *  SAYFALAMA BURADA İKİ SORGUYLA YAPILIR:
 *      1) countAll()  → filtreye uyan TOPLAM kayıt (kaç sayfa var?)
 *      2) page()      → yalnızca o sayfanın satırları (LIMIT/OFFSET)
 *
 *  İki sorgu tek sorgudan iyidir: SQL_CALC_FOUND_ROWS artık önerilmez
 *  (MySQL 8'de kullanımdan kaldırıldı) ve indeks kullanan ayrı bir
 *  COUNT sorgusu pratikte daha hızlıdır.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Repositories;

use App\Models\User;
use PDO;

final class UserRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    /* =================================================================
     *  TEKİL OKUMA
     * ============================================================== */

    public function find(int $id): ?User
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);

        $row = $stmt->fetch();

        return $row ? User::fromRow($row) : null;
    }

    /**
     * E-posta ile kullanıcı arar (giriş için).
     *
     * E-postayı küçük harfe çevirip arıyoruz: "Admin@..." ile
     * "admin@..." aynı hesaptır. Kullanıcıya "büyük harf yazmışsınız"
     * demek yerine sorunu sessizce çözmek doğrusudur.
     */
    public function findByEmail(string $email): ?User
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => mb_strtolower(trim($email), 'UTF-8')]);

        $row = $stmt->fetch();

        return $row ? User::fromRow($row) : null;
    }

    /* =================================================================
     *  SAYFALAMA
     * ============================================================== */

    /**
     * Filtreye uyan TOPLAM kayıt sayısı.
     *
     * Paginator kaç sayfa olduğunu bu sayıdan hesaplar. Filtre
     * koşulunu page() ile AYNI tutmak zorunludur; farklı olurlarsa
     * "3 sayfa var" yazıp 2. sayfada boş tablo gösterirsiniz.
     */
    public function countAll(string $search = '', ?bool $activeOnly = null): int
    {
        [$where, $params] = $this->filter($search, $activeOnly);

        $stmt = $this->db->prepare('SELECT COUNT(*) FROM users ' . $where);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Tek bir sayfanın satırları.
     *
     * LIMIT/OFFSET NEDEN PARAMETRE DEĞİL?
     * PDO, emülasyon kapalıyken (ATTR_EMULATE_PREPARES = false) bu
     * konumdaki değerleri metin olarak gönderir ve MySQL
     * "LIMIT '20'" ifadesini sözdizimi hatası sayar. bindValue ile
     * PDO::PARAM_INT vermek de sürücüye göre değişken davranır.
     *
     * Değerler Paginator'dan TAMSAYI olarak gelir ve burada (int) ile
     * bir kez daha zorlanır; dışarıdan gelen metin sorguya asla
     * ulaşmaz, dolayısıyla enjeksiyon riski yoktur.
     *
     * @return array<int,User>
     */
    public function page(int $offset, int $limit, string $search = '', ?bool $activeOnly = null): array
    {
        [$where, $params] = $this->filter($search, $activeOnly);

        $sql = 'SELECT * FROM users '
             . $where
             . ' ORDER BY id DESC'
             . ' LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return array_map(
            static fn (array $row): User => User::fromRow($row),
            $stmt->fetchAll()
        );
    }

    /**
     * WHERE parçasını ve parametrelerini üretir.
     *
     * Tek yerde toplanması ŞART: countAll() ile page() farklı filtre
     * kurarsa sayfalama sessizce yanlış çalışır — bulunması en zor
     * hatalardandır.
     *
     * @return array{0:string,1:array<string,mixed>}
     */
    private function filter(string $search, ?bool $activeOnly): array
    {
        $conditions = [];
        $params     = [];

        $search = trim($search);

        if ($search !== '') {
            /* LIKE deseni parametre olarak gönderilir; % işaretleri
             * DEĞERİN içindedir, SQL metninin değil. Böylece kullanıcı
             * ne yazarsa yazsın sorgunun yapısını değiştiremez.
             *
             * NEDEN ÜÇ AYRI PARAMETRE (:q1, :q2, :q3)?
             * Aynı adlı bir yer tutucuyu sorguda birden fazla kez
             * kullanmak YALNIZCA emülasyon açıkken çalışır. Bu proje
             * PDO::ATTR_EMULATE_PREPARES = false ile çalışır (gerçek
             * hazırlanmış ifade, daha güvenli) ve o modda MySQL sürücüsü
             * her yer tutucu için AYRI değer bekler:
             *     SQLSTATE[HY093]: Invalid parameter number
             * Üç ayrı ad vermek, bu tuzağın en basit çözümüdür. */
            $conditions[] = '(name LIKE :q1 OR surname LIKE :q2 OR email LIKE :q3)';

            $pattern = '%' . $search . '%';

            $params[':q1'] = $pattern;
            $params[':q2'] = $pattern;
            $params[':q3'] = $pattern;
        }

        if ($activeOnly !== null) {
            $conditions[] = 'is_active = :active';
            $params[':active'] = $activeOnly ? 1 : 0;
        }

        return [
            $conditions === [] ? '' : 'WHERE ' . implode(' AND ', $conditions),
            $params,
        ];
    }

    /* =================================================================
     *  YAZMA
     * ============================================================== */

    /** Başarılı girişten sonra son giriş zamanını damgalar. */
    public function touchLogin(int $id): void
    {
        $stmt = $this->db->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    /** Parola özetini yeniler (algoritma güncellendiğinde). */
    public function updatePasswordHash(int $id, string $hash): void
    {
        $stmt = $this->db->prepare('UPDATE users SET password = :hash WHERE id = :id');
        $stmt->execute([':hash' => $hash, ':id' => $id]);
    }

    /** Kullanıcının tema tercihini kaydeder. */
    public function updateTheme(int $id, string $theme): void
    {
        $stmt = $this->db->prepare('UPDATE users SET theme = :theme WHERE id = :id');
        $stmt->execute([':theme' => User::normalizeTheme($theme), ':id' => $id]);
    }
}
