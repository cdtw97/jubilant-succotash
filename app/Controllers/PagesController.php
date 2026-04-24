<?php
declare(strict_types=1);

namespace App\Controllers;

use MyFrancis\Core\Controller;
use MyFrancis\Core\Response;

final class PagesController extends Controller
{
    public function index(): Response
    {
        return $this->view('home', [
            'title' => 'Home',
            'text' => 'Switch between Cyber-Grid, E-Ink, Classic Handheld, Living Forest, and Terminal with a single click, then tune speed, grid size, walls, apples, and style for a Snake run built around your instincts.',
        ]);
    }

    public function about(): Response
    {
        return $this->view('about', [
            'title' => 'About',
            'heading' => 'Built on a lightweight MVC core',
            'text' => 'A lightweight, explicit PHP MVC base core for future MyFrancis applications, now wrapped in a single playful Google Snake-style interface.',
        ]);
    }
}
