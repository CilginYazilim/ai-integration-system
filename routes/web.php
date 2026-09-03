<?php
/**
 * =====================================================================
 *  ROTA TABLOSU
 * ---------------------------------------------------------------------
 *  Üçüncü parametre ARA KATMANLARDIR:
 *      'guest' → yalnızca giriş YAPMAMIŞ ziyaretçi
 *      'auth'  → giriş zorunlu
 *      'csrf'  → sahte istek koruması (veri değiştiren her POST)
 *
 *  MODELE MESAJ GÖNDERMEK PARA HARCAR.
 *  Bu yüzden uç nokta hem 'auth' hem 'csrf' ister. GET ile
 *  açılabilseydi, kötü niyetli bir sitedeki <img> etiketi kurbanın
 *  hesabından istek attırıp faturasını şişirebilirdi.
 * =====================================================================
 */

declare(strict_types=1);

use App\Core\Request;
use App\Core\Router;
use App\Core\View;
use App\Http\Controllers\Api\ChatApiController;
use App\Http\Controllers\Api\PreferenceApiController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\UserController;

$router = new Router();

/* ---------------------------------------------------------------------
 *  KİMLİK DOĞRULAMA
 * ------------------------------------------------------------------ */
$router->get('',        AuthController::class, 'showLogin', ['guest']);
$router->get('home',    AuthController::class, 'showLogin', ['guest']);
$router->get('login',   AuthController::class, 'showLogin', ['guest']);
$router->post('login',  AuthController::class, 'login',     ['guest', 'csrf']);
$router->post('logout', AuthController::class, 'logout',    ['auth', 'csrf']);

/* ---------------------------------------------------------------------
 *  PANEL
 * ------------------------------------------------------------------ */
$router->get('dashboard', DashboardController::class, 'index', ['auth']);

// SAYFALAMA ÖRNEĞİ: filtreler ve sayfa numarası adres çubuğunda taşınır.
$router->get('users', UserController::class, 'index', ['auth']);

/* ---------------------------------------------------------------------
 *  SOHBET
 * ------------------------------------------------------------------ */
$router->get('chat',       ChatController::class, 'index', ['auth']);
$router->get('chat/show',  ChatController::class, 'show',  ['auth']);

$router->post('chat',        ChatController::class, 'store',   ['auth', 'csrf']);
$router->post('chat/delete', ChatController::class, 'destroy', ['auth', 'csrf']);

/* ---------------------------------------------------------------------
 *  AJAX UÇ NOKTALARI (hepsi POST + CSRF)
 * ------------------------------------------------------------------ */
$router->post('api/preferences/theme', PreferenceApiController::class, 'theme', ['auth', 'csrf']);

$router->post('api/chat/send',   ChatApiController::class, 'send',   ['auth', 'csrf']);
$router->post('api/chat/status', ChatApiController::class, 'status', ['auth', 'csrf']);

/* ---------------------------------------------------------------------
 *  BULUNAMAYAN ADRESLER
 * ------------------------------------------------------------------ */
$router->fallback(static function (Request $request, string $path): void {
    if ($request->isAjax()) {
        App\Core\Response::error('İstenen uç nokta bulunamadı.', 404);
    }

    http_response_code(404);

    View::render('errors/404', [
        'title' => 'Sayfa Bulunamadı',
        'path'  => $path,
    ], App\Core\Auth::check() ? 'layouts/admin' : 'layouts/plain');
});

return $router;
