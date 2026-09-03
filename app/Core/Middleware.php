<?php
/**
 * =====================================================================
 *  Middleware – Rota öncesi kontroller
 * ---------------------------------------------------------------------
 *  "Ara katman", denetleyici çalışmadan ÖNCE devreye giren bir
 *  kontroldür. Giriş kontrolünü her metodun başına elle yazmak yerine
 *  rota tanımında belirtiriz:
 *
 *      $router->get('users', UserController::class, 'index', ['auth']);
 *
 *  Bir kontrol başarısız olursa istek burada DURUR; denetleyici hiç
 *  çalışmaz. Kuralları tek yerde toplamak, "bir sayfada unutmak"
 *  riskini ortadan kaldırır.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core;

final class Middleware
{
    /**
     * @param string $rule "auth" | "guest" | "csrf"
     */
    public static function handle(string $rule, Request $request): void
    {
        [$name] = array_pad(explode(':', $rule, 2), 2, null);

        match ($name) {
            'auth'  => self::auth($request),
            'guest' => self::guest(),
            'csrf'  => self::csrf($request),
            default => null,
        };
    }

    /** Giriş yapılmamışsa devam ettirme. */
    private static function auth(Request $request): void
    {
        if (Auth::check()) {
            return;
        }

        if ($request->isAjax()) {
            Response::error('Oturumunuz sonlandı. Lütfen tekrar giriş yapın.', 401);
        }

        /* Kullanıcı giriş yaptıktan sonra gitmek istediği sayfaya
         * dönebilsin diye adresi saklıyoruz. */
        Session::set('_intended', $request->raw('r', 'dashboard'));

        Response::redirect(url('login'));
    }

    /** Zaten giriş yapmış kullanıcı login sayfasını görmesin. */
    private static function guest(): void
    {
        if (Auth::check()) {
            Response::redirect(url('dashboard'));
        }
    }

    /**
     * Veri değiştiren isteklerde CSRF anahtarı zorunludur.
     * 419 kodu Laravel'den gelen bir gelenektir: "oturum süresi doldu".
     */
    private static function csrf(Request $request): void
    {
        if (Csrf::check()) {
            return;
        }

        if ($request->isAjax()) {
            Response::error('Oturum doğrulaması başarısız. Lütfen sayfayı yenileyin.', 419);
        }

        Flash::error('Güvenlik doğrulaması başarısız oldu. Lütfen tekrar deneyin.');
        Response::redirect(url('dashboard'));
    }

}
