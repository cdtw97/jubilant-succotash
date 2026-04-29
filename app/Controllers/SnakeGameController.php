<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\GameRunRepository;
use App\Services\AuthService;
use DateTimeImmutable;
use MyFrancis\Core\Controller;
use MyFrancis\Core\Container;
use MyFrancis\Core\Enums\HttpStatus;
use MyFrancis\Core\Request;
use MyFrancis\Core\Response;

final class SnakeGameController extends Controller
{
    private const ALLOWED_THEMES = [
        'cyber-grid',
        'e-ink',
        'classic-handheld',
        'living-forest',
        'terminal',
    ];

    private const ALLOWED_BOARD_SIZES = ['small', 'medium', 'large'];
    private const ALLOWED_SNAKE_STYLES = ['tube', 'blocks'];

    public function show(Request $request, Container $container): Response
    {
        $authUser = $this->authenticatedUser($request);
        $isAuthenticated = $authUser !== null;
        $initialBestScore = 0;

        if ($isAuthenticated && $container->has(GameRunRepository::class)) {
            /** @var GameRunRepository $gameRunRepository */
            $gameRunRepository = $container->get(GameRunRepository::class);
            $initialBestScore = $gameRunRepository->findUserBestScore((int) $authUser['id']);
        }

        return $this->view('snake.game', [
            'title' => 'Snake Game',
            'telemetrySubmissionUrl' => route('snake.runs.store'),
            'csrfToken' => csrf_token(),
            'audioBaseUrl' => asset('assets/snake/audio'),
            'isAuthenticated' => $isAuthenticated,
            'authUser' => $authUser,
            'loginUrl' => route('auth.login'),
            'profileUrl' => route('user.profile'),
            'initialBestScore' => $initialBestScore,
            'telemetryStatusText' => $isAuthenticated
                ? 'Logged in. Finished runs sync to your profile automatically.'
                : 'Guest mode. Sign in to save runs, scores, and recent history.',
        ]);
    }

    public function storeRun(Request $request, AuthService $authService, GameRunRepository $gameRunRepository): Response
    {
        $authUser = $this->authenticatedUser($request, $authService);

        if ($authUser === null) {
            return Response::json([
                'error' => [
                    'code' => 'authentication_required',
                    'message' => 'Sign in to save completed runs.',
                    'request_id' => $request->requestId(),
                ],
            ], HttpStatus::UNAUTHORIZED);
        }

        [$payload, $errors] = $this->validateRunPayload($request);

        if ($errors !== []) {
            return Response::json([
                'error' => [
                    'code' => 'validation_error',
                    'message' => 'The game telemetry payload is invalid.',
                    'request_id' => $request->requestId(),
                    'details' => $errors,
                ],
            ], HttpStatus::UNPROCESSABLE_ENTITY);
        }

        $runId = $gameRunRepository->createRun((int) $authUser['id'], $payload);
        $personalBestScore = $gameRunRepository->findUserBestScore((int) $authUser['id']);

        return $this->json([
            'saved' => true,
            'message' => 'Run saved to your telemetry history.',
            'run_id' => $runId,
            'personal_best_score' => $personalBestScore,
            'profile_url' => route('user.profile'),
            'run' => [
                'score' => $payload['score'],
                'snake_length' => $payload['snake_length'],
                'duration_seconds' => $payload['duration_seconds'],
                'theme' => $payload['theme'],
                'board_size' => $payload['board_size'],
                'speed_level' => $payload['speed_level'],
                'apple_type' => $payload['apple_type'],
                'apple_count' => $payload['apple_count'],
                'walls_enabled' => $payload['walls_enabled'],
                'snake_style' => $payload['snake_style'],
                'ended_at' => $payload['ended_at'],
            ],
        ], $request, HttpStatus::CREATED);
    }

    /**
     * @return array{0: array{
     *     theme: string,
     *     board_size: string,
     *     grid_size: int,
     *     speed_level: int,
     *     apple_type: string,
     *     apple_count: int,
     *     walls_enabled: bool,
     *     snake_style: string,
     *     score: int,
     *     snake_length: int,
     *     duration_seconds: int,
     *     ended_at: string
     * }, 1: array<string, string>}
     */
    private function validateRunPayload(Request $request): array
    {
        $theme = strtolower(trim((string) $request->input('theme', '')));
        $boardSize = strtolower(trim((string) $request->input('board_size', '')));
        $gridSize = (int) $request->input('grid_size', 0);
        $speedLevel = (int) $request->input('speed_level', 0);
        $appleType = strtolower(trim((string) $request->input('apple_type', 'standard')));
        $appleCount = (int) $request->input('apple_count', 0);
        $wallsEnabled = filter_var($request->input('walls_enabled', false), FILTER_VALIDATE_BOOLEAN);
        $snakeStyle = strtolower(trim((string) $request->input('snake_style', 'tube')));
        $score = (int) $request->input('score', 0);
        $snakeLength = (int) $request->input('length', 0);
        $durationSeconds = (int) $request->input('duration_seconds', 0);
        $errors = [];

        if (! in_array($theme, self::ALLOWED_THEMES, true)) {
            $errors['theme'] = 'The selected theme is invalid.';
        }

        if (! in_array($boardSize, self::ALLOWED_BOARD_SIZES, true)) {
            $errors['board_size'] = 'The selected board size is invalid.';
        }

        if ($gridSize < 10 || $gridSize > 64) {
            $errors['grid_size'] = 'Grid size must be between 10 and 64.';
        }

        if ($speedLevel < 1 || $speedLevel > 30) {
            $errors['speed_level'] = 'Speed level must be between 1 and 30.';
        }

        if (! preg_match('/\A[a-z0-9:_-]{3,32}\z/', $appleType)) {
            $errors['apple_type'] = 'Apple type is invalid.';
        }

        if ($appleCount < 1 || $appleCount > 25) {
            $errors['apple_count'] = 'Apple count must be between 1 and 25.';
        }

        if (! in_array($snakeStyle, self::ALLOWED_SNAKE_STYLES, true)) {
            $errors['snake_style'] = 'Snake style is invalid.';
        }

        if ($score < 0 || $score > 1000000) {
            $errors['score'] = 'Score is out of range.';
        }

        if ($snakeLength < 1 || $snakeLength > 1000000) {
            $errors['length'] = 'Snake length is out of range.';
        }

        if ($durationSeconds < 0 || $durationSeconds > 86400) {
            $errors['duration_seconds'] = 'Duration must be between 0 and 86400 seconds.';
        }

        return [[
            'theme' => in_array($theme, self::ALLOWED_THEMES, true) ? $theme : 'living-forest',
            'board_size' => in_array($boardSize, self::ALLOWED_BOARD_SIZES, true) ? $boardSize : 'medium',
            'grid_size' => max(0, $gridSize),
            'speed_level' => max(0, $speedLevel),
            'apple_type' => $appleType !== '' ? $appleType : 'standard',
            'apple_count' => max(0, $appleCount),
            'walls_enabled' => $wallsEnabled,
            'snake_style' => in_array($snakeStyle, self::ALLOWED_SNAKE_STYLES, true) ? $snakeStyle : 'tube',
            'score' => max(0, $score),
            'snake_length' => max(0, $snakeLength),
            'duration_seconds' => max(0, $durationSeconds),
            'ended_at' => (new DateTimeImmutable('now'))->format('Y-m-d H:i:s'),
        ], $errors];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function authenticatedUser(Request $request, ?AuthService $authService = null): ?array
    {
        $user = $request->attribute('auth.user');

        if (is_array($user) && $user !== []) {
            return $user;
        }

        return $authService?->currentUser();
    }
}
