<?php
/**
 * =====================================================================
 *  DashboardController – Kontrol paneli
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Http\Controller;
use App\Repositories\ConversationRepository;

final class DashboardController extends Controller
{
    public function index(Request $request): void
    {
        $userId = (int) Auth::id();

        $ai = (new ConversationRepository($this->db))->stats($userId);

        $this->view('dashboard/index', [
            'title'    => 'Kontrol Paneli',
            'subtitle' => 'Hoş geldiniz, ' . (Auth::user()?->name ?? ''),

            'stats' => [
                ['label' => 'Sohbet',  'value' => $ai['conversations'], 'icon' => 'mail'],
                ['label' => 'Mesaj',   'value' => $ai['messages'],      'icon' => 'activity'],
                ['label' => 'Jeton',   'value' => $ai['tokens'],        'icon' => 'upload',
                 'hint'  => 'giriş + çıkış'],
            ],

            'activity' => $this->activity()->latest(8),
        ]);
    }
}
