<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Repository\UserRepository;
use App\Support\Flash;
use App\Support\Input;
use App\Support\Redirect;
use App\Support\View;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class AuthController
{
    public function __construct(private readonly View $view, private readonly UserRepository $users)
    {
    }

    public function loginForm(Request $req, Response $res): Response
    {
        return $this->view->render($res, 'admin/login.twig');
    }

    public function login(Request $req, Response $res): Response
    {
        $b = Input::body($req);
        $user = $this->users->findByEmail(Input::str($b, 'email'));
        // Always run a hash check so response time doesn't reveal whether the email exists.
        $hash = $user->passwordHash ?? '$2y$12$invalidinvalidinvalidinvalidinvalidinvalidinvalidinvalidinva';
        $ok = password_verify(Input::str($b, 'password'), $hash) && $user !== null;
        if (!$ok) {
            Flash::error('Invalid email or password.');

            return Redirect::to($res, '/admin/login');
        }
        session_regenerate_id(true);
        $_SESSION['admin_id'] = $user->id;
        $_SESSION['admin_email'] = $user->email;
        $this->users->touchLogin($user->id);

        return Redirect::to($res, '/admin');
    }

    public function logout(Request $req, Response $res): Response
    {
        unset($_SESSION['admin_id'], $_SESSION['admin_email']);
        session_regenerate_id(true);

        return Redirect::to($res, '/admin/login');
    }
}
