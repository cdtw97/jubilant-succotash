<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuthService;
use MyFrancis\Core\Controller;
use MyFrancis\Core\Enums\HttpStatus;
use MyFrancis\Core\Request;
use MyFrancis\Core\Response;
use MyFrancis\Security\SessionManager;
use Throwable;

final class AuthController extends Controller
{
    public function showLogin(): Response
    {
        return $this->renderLogin();
    }

    public function login(Request $request, AuthService $authService, SessionManager $sessionManager): Response
    {
        $identity = trim((string) $request->input('identity', ''));
        $password = (string) $request->input('password', '');
        $errors = [];

        if ($identity === '') {
            $errors['identity'] = 'Enter your email address or username.';
        }

        if ($password === '') {
            $errors['password'] = 'Enter your password.';
        }

        if ($errors !== []) {
            return $this->renderLogin(
                old: ['identity' => $identity],
                errors: $errors,
                status: HttpStatus::UNPROCESSABLE_ENTITY,
            );
        }

        if (! $authService->attempt($identity, $password)) {
            return $this->renderLogin(
                old: ['identity' => $identity],
                errors: ['form' => 'These credentials do not match our records.'],
                status: HttpStatus::UNPROCESSABLE_ENTITY,
            );
        }

        $user = $authService->currentUser();
        $displayName = is_array($user) ? (string) ($user['username'] ?? 'Player') : 'Player';
        $sessionManager->flash('status', sprintf('Welcome back, %s.', $displayName));
        $sessionManager->flash('status_level', 'success');

        return Response::redirect($authService->pullIntendedPath(route('user.profile')));
    }

    public function showRegister(): Response
    {
        return $this->renderRegister();
    }

    public function register(Request $request, AuthService $authService, SessionManager $sessionManager): Response
    {
        $username = trim((string) $request->input('username', ''));
        $email = strtolower(trim((string) $request->input('email', '')));
        $password = (string) $request->input('password', '');
        $passwordConfirmation = (string) $request->input('password_confirmation', '');
        $errors = [];

        if (! preg_match('/\A[a-zA-Z0-9_-]{3,32}\z/', $username)) {
            $errors['username'] = 'Use 3 to 32 letters, numbers, underscores, or hyphens.';
        } elseif ($authService->usernameExists($username)) {
            $errors['username'] = 'That username is already taken.';
        }

        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'Enter a valid email address.';
        } elseif ($authService->emailExists($email)) {
            $errors['email'] = 'That email address is already in use.';
        }

        if (strlen($password) < 12) {
            $errors['password'] = 'Use at least 12 characters for your password.';
        }

        if ($passwordConfirmation === '') {
            $errors['password_confirmation'] = 'Confirm your password.';
        } elseif ($password !== $passwordConfirmation) {
            $errors['password_confirmation'] = 'Password confirmation does not match.';
        }

        if ($errors !== []) {
            return $this->renderRegister(
                old: [
                    'username' => $username,
                    'email' => $email,
                ],
                errors: $errors,
                status: HttpStatus::UNPROCESSABLE_ENTITY,
            );
        }

        try {
            $user = $authService->register($username, $email, $password);
        } catch (Throwable) {
            return $this->renderRegister(
                old: [
                    'username' => $username,
                    'email' => $email,
                ],
                errors: ['form' => 'Your account could not be created right now. Please try again.'],
                status: HttpStatus::UNPROCESSABLE_ENTITY,
            );
        }

        $displayName = (string) ($user['username'] ?? 'Player');
        $sessionManager->flash('status', sprintf('Account created. Welcome, %s.', $displayName));
        $sessionManager->flash('status_level', 'success');

        return Response::redirect(route('user.profile'));
    }

    public function logout(AuthService $authService, SessionManager $sessionManager): Response
    {
        $authService->logout();
        $sessionManager->flash('status', 'You have been signed out securely.');
        $sessionManager->flash('status_level', 'success');

        return Response::redirect(route('pages.home'));
    }

    /**
     * @param array<string, string> $old
     * @param array<string, string> $errors
     */
    private function renderLogin(array $old = [], array $errors = [], HttpStatus|int $status = HttpStatus::OK): Response
    {
        return $this->view('auth.login', [
            'title' => 'Login',
            'old' => $old,
            'errors' => $errors,
        ], $status);
    }

    /**
     * @param array<string, string> $old
     * @param array<string, string> $errors
     */
    private function renderRegister(array $old = [], array $errors = [], HttpStatus|int $status = HttpStatus::OK): Response
    {
        return $this->view('auth.register', [
            'title' => 'Register',
            'old' => $old,
            'errors' => $errors,
        ], $status);
    }
}
