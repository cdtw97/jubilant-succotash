<?php
declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\PagesController;
use App\Controllers\SnakeGameController;
use App\Controllers\UserController;
use MyFrancis\Core\Router;

return static function (Router $router): void {
    $router->get('/', [PagesController::class, 'index'], name: 'pages.home', middleware: ['request.id', 'security.headers', 'auth.state', 'csrf']);
    $router->get('/about', [PagesController::class, 'about'], name: 'pages.about', middleware: ['request.id', 'security.headers', 'auth.state', 'csrf']);

    $router->get('/login', [AuthController::class, 'showLogin'], name: 'auth.login', middleware: ['request.id', 'security.headers', 'auth.state', 'guest', 'csrf']);
    $router->post('/login', [AuthController::class, 'login'], name: 'auth.login.store', middleware: ['request.id', 'security.headers', 'auth.state', 'guest', 'csrf']);
    $router->get('/register', [AuthController::class, 'showRegister'], name: 'auth.register', middleware: ['request.id', 'security.headers', 'auth.state', 'guest', 'csrf']);
    $router->post('/register', [AuthController::class, 'register'], name: 'auth.register.store', middleware: ['request.id', 'security.headers', 'auth.state', 'guest', 'csrf']);
    $router->post('/logout', [AuthController::class, 'logout'], name: 'auth.logout', middleware: ['request.id', 'security.headers', 'auth.state', 'auth', 'csrf']);

    $router->get('/profile', [UserController::class, 'profile'], name: 'user.profile', middleware: ['request.id', 'security.headers', 'auth.state', 'auth', 'csrf']);
    $router->get('/profile/high-scores', [UserController::class, 'highScores'], name: 'user.high-scores', middleware: ['request.id', 'security.headers', 'auth.state', 'auth', 'csrf']);

    $router->get('/snake', [SnakeGameController::class, 'show'], name: 'snake.show', middleware: ['request.id', 'security.headers', 'auth.state', 'csrf']);
    $router->post('/snake/runs', [SnakeGameController::class, 'storeRun'], name: 'snake.runs.store', middleware: ['request.id', 'security.headers', 'auth.state', 'auth', 'csrf', 'web.json']);
};
