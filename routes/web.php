<?php
declare(strict_types=1);

use App\Controllers\PagesController;
use App\Controllers\SnakeGameController;
use MyFrancis\Core\Router;

return static function (Router $router): void {
    $router->get('/', [PagesController::class, 'index'], name: 'pages.home', middleware: ['request.id', 'security.headers', 'csrf']);
    $router->get('/about', [PagesController::class, 'about'], name: 'pages.about', middleware: ['request.id', 'security.headers', 'csrf']);
    $router->get('/snake', [SnakeGameController::class, 'show'], name: 'snake.show', middleware: ['request.id', 'security.headers', 'csrf']);
    $router->post('/snake/high-score', [SnakeGameController::class, 'submitHighScore'], name: 'snake.high-score.store', middleware: ['request.id', 'security.headers', 'csrf']);
};
