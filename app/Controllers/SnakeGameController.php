<?php
declare(strict_types=1);

namespace App\Controllers;

use DateTimeImmutable;
use MyFrancis\Core\Controller;
use MyFrancis\Core\Request;
use MyFrancis\Core\Response;

final class SnakeGameController extends Controller
{
    public function show(): Response
    {
        return $this->view('snake.game', [
            'title' => 'Snake Game',
            'scoreSubmissionUrl' => route('snake.high-score.store'),
            'csrfToken' => csrf_token(),
            'audioBaseUrl' => asset('assets/snake/audio'),
        ]);
    }

    public function submitHighScore(Request $request): Response
    {
        $submission = $this->mapSubmissionPayload($request);

        return $this->json([
            'success' => true,
            'message' => 'High score submission accepted.',
            'submission' => [
                'score' => $submission['score'],
                'best_score' => $submission['score'],
                'duration_seconds' => $submission['duration_seconds'],
                'grid_size' => $submission['grid_size'],
                'speed' => $submission['speed'],
                'apples' => $submission['apples'],
                'walls' => $submission['walls'],
                'persisted' => false,
                'submitted_at' => $submission['submitted_at'],
            ],
        ], $request);
    }

    /**
     * @return array{
     *     score: int,
     *     duration_seconds: int,
     *     grid_size: int,
     *     speed: int,
     *     apples: int,
     *     walls: bool,
     *     submitted_at: string
     * }
     */
    private function mapSubmissionPayload(Request $request): array
    {
        return [
            'score' => max(0, (int) $request->input('score', 0)),
            'duration_seconds' => max(0, (int) $request->input('duration_seconds', 0)),
            'grid_size' => max(0, (int) $request->input('grid_size', 0)),
            'speed' => max(0, (int) $request->input('speed', 0)),
            'apples' => max(0, (int) $request->input('apples', 0)),
            'walls' => filter_var($request->input('walls', false), FILTER_VALIDATE_BOOLEAN),
            'submitted_at' => (new DateTimeImmutable())->format(DATE_ATOM),
        ];
    }
}
