<?php
/**
 * =====================================================================
 *  Router – İsteği doğru denetleyiciye (controller) yönlendirir
 * ---------------------------------------------------------------------
 *  Eski yapıda her iş için ayrı bir PHP dosyası vardı (ajax.php,
 *  index.php…). Artık TEK GİRİŞ NOKTASI var: kökteki index.php.
 *  Tüm istekler oradan geçer, güvenlik kontrolleri tek yerde yapılır.
 *
 *  ROTA BİÇİMİ
 *      index.php?r=users          → GET  users
 *      index.php?r=api/users/list → POST api/users/list
 *
 *  Rotalar "GET /users" gibi metot+yol çiftiyle tanımlanır; aynı adres
 *  GET'te formu gösterip POST'ta kaydedebilir.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core;

final class Router
{
    /** @var array<string,array{0:string,1:string,2:array<int,string>}> */
    private array $routes = [];

    private ?\Closure $notFound = null;

    /**
     * @param string            $path       "users/create"
     * @param string            $controller Tam sınıf adı
     * @param string            $method     Çalıştırılacak metot
     * @param array<int,string> $middleware ['auth', 'role:admin'] gibi
     */
    public function get(string $path, string $controller, string $method, array $middleware = []): self
    {
        return $this->add('GET', $path, $controller, $method, $middleware);
    }

    /** @param array<int,string> $middleware */
    public function post(string $path, string $controller, string $method, array $middleware = []): self
    {
        return $this->add('POST', $path, $controller, $method, $middleware);
    }

    /** Hem GET hem POST kabul eden rota. @param array<int,string> $middleware */
    public function any(string $path, string $controller, string $method, array $middleware = []): self
    {
        $this->add('GET', $path, $controller, $method, $middleware);

        return $this->add('POST', $path, $controller, $method, $middleware);
    }

    /** @param array<int,string> $middleware */
    private function add(string $verb, string $path, string $controller, string $method, array $middleware): self
    {
        $this->routes[$verb . ' ' . trim($path, '/')] = [$controller, $method, $middleware];

        return $this;
    }

    /** Rota bulunamazsa çalışacak fonksiyon. */
    public function fallback(\Closure $handler): self
    {
        $this->notFound = $handler;

        return $this;
    }

    /**
     * Adres çubuğundaki rotayı okur ve temizler.
     *
     * GÜVENLİK: Yalnızca harf, rakam, tire, alt çizgi ve eğik çizgiye
     * izin veriyoruz. Böylece "../" gibi yol kaçışları en baştan elenir.
     */
    public static function currentPath(): string
    {
        $raw = (string) ($_GET['r'] ?? '');

        // Temiz adres (pretty URL) kullanılıyorsa .htaccess bunu doldurur.
        if ($raw === '' && isset($_SERVER['PATH_INFO'])) {
            $raw = (string) $_SERVER['PATH_INFO'];
        }

        $clean = preg_replace('#[^a-zA-Z0-9/_-]#', '', $raw) ?? '';
        $clean = trim(preg_replace('#/+#', '/', $clean) ?? '', '/');

        /* Kök adres ("/") tanıtım sayfasına gider.
         * Önceden doğrudan "dashboard"a eşlenirdi; artık karşılama
         * kararını HomeController veriyor: giriş yapmış kullanıcıyı
         * panele yönlendiriyor, ziyaretçiye tanıtımı gösteriyor. */
        return $clean === '' ? 'home' : $clean;
    }

    /**
     * Uygulamanın tarayıcıdaki KÖK YOLU.
     *
     *   http://localhost/rbac-login-system/index.php  →  "/rbac-login-system"
     *   http://example.com/index.php            →  ""
     *
     * NEDEN GEREKLİ?
     * Temiz adres modunda bağlantıları GÖRELİ üretirsek ("users"),
     * tek seviyeli sayfalarda çalışır ama "users/detay" gibi iki
     * seviyeli bir adreste tarayıcı tabanı "/rbac-login-system/users/"
     * sanar ve tüm bağlantılar (CSS/JS dahil) kırılır.
     *
     * Mutlak yol üreterek bu tuzağı tamamen ortadan kaldırıyoruz.
     * Sonuç, projenin hangi alt klasörde durduğundan bağımsız çalışır.
     */
    public static function basePath(): string
    {
        static $base = null;

        if ($base !== null) {
            return $base;
        }

        // "/rbac-login-system/index.php" → "/rbac-login-system"
        $dir = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php')));

        // Kök dizinde çalışıyorsa dirname "/" döner; sondaki eğik çizgiyi atıyoruz.
        return $base = ($dir === '/' || $dir === '.') ? '' : rtrim($dir, '/');
    }

    /**
     * İsteği eşleşen denetleyiciye devreder.
     */
    public function dispatch(Request $request): void
    {
        $path = self::currentPath();

        /* HEAD, "gövdesiz GET" demektir; tarayıcılar, arama motorları ve
         * izleme araçları bunu kullanır. Ayrı rota tanımlamak yerine
         * GET'e eşliyoruz. PHP gövdeyi zaten kendisi atar. */
        $verb = $request->method() === 'HEAD' ? 'GET' : $request->method();

        $key = $verb . ' ' . $path;

        if (!isset($this->routes[$key])) {
            /* Adres var ama HTTP metodu yanlışsa (örn. silme rotasına
             * GET ile gelinmişse) bunu 405 ile ayırt etmek, hata
             * ayıklamayı kolaylaştırır. */
            $existsWithOtherVerb = isset($this->routes['GET ' . $path])
                || isset($this->routes['POST ' . $path]);

            if ($existsWithOtherVerb) {
                if ($request->isAjax()) {
                    Response::error('Bu adres için geçersiz istek yöntemi.', 405);
                }

                http_response_code(405);
            }

            if ($this->notFound !== null) {
                ($this->notFound)($request, $path);
                return;
            }

            http_response_code(404);
            echo 'Sayfa bulunamadı.';
            return;
        }

        [$controllerClass, $method, $middleware] = $this->routes[$key];

        // Ara katmanlar (giriş kontrolü, rol kontrolü) önce çalışır.
        foreach ($middleware as $rule) {
            Middleware::handle($rule, $request);
        }

        $controller = new $controllerClass();
        $controller->{$method}($request);
    }
}
