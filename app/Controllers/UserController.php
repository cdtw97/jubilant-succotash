<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\GameRunRepository;
use App\Services\AuthService;
use MyFrancis\Core\Controller;
use MyFrancis\Core\Request;
use MyFrancis\Core\Response;
use MyFrancis\Http\JsonResponse;

final class UserController extends Controller
{
    public function profile(Request $request, AuthService $authService, GameRunRepository $gameRunRepository): Response
    {
        $user = $this->authenticatedUser($request, $authService);
        $userId = (int) ($user['id'] ?? 0);

        return $this->view('auth.profile', [
            'title' => 'Profile',
            'user' => $user,
            'summary' => $gameRunRepository->findPersonalSummary($userId),
            'recentRuns' => $gameRunRepository->findRecentByUserId($userId),
            'personalTopRuns' => $gameRunRepository->findTopRunsByUserId($userId),
            'globalTopRuns' => $gameRunRepository->findGlobalTopRuns(),
        ]);
    }

    public function highScores(Request $request, AuthService $authService, GameRunRepository $gameRunRepository): JsonResponse
    {
        $user = $this->authenticatedUser($request, $authService);
        $userId = (int) ($user['id'] ?? 0);

        return $this->json([
            'user' => [
                'id' => $userId,
                'username' => (string) ($user['username'] ?? ''),
            ],
            'personal_summary' => $gameRunRepository->findPersonalSummary($userId),
            'personal_top_runs' => $gameRunRepository->findTopRunsByUserId($userId),
            'global_top_runs' => $gameRunRepository->findGlobalTopRuns(),
        ], $request);
    }

    /**
     * @return array<string, mixed>
     */
    private function authenticatedUser(Request $request, AuthService $authService): array
    {
        $user = $request->attribute('auth.user');

        if (is_array($user) && $user !== []) {
            return $user;
        }

        return $authService->currentUser() ?? [];
    }
}
