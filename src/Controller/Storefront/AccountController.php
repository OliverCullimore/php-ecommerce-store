<?php

declare(strict_types=1);

namespace App\Controller\Storefront;

use App\Repository\CustomerRepository;
use App\Repository\SalesOrderRepository;
use App\Support\Flash;
use App\Support\Input;
use App\Support\Redirect;
use App\Support\View;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class AccountController
{
    public function __construct(private readonly View $view, private readonly CustomerRepository $customers, private readonly SalesOrderRepository $orders)
    {
    }

    public function loginForm(Request $req, Response $res): Response
    {
        return $this->view->render($res, 'storefront/login.twig');
    }

    public function login(Request $req, Response $res): Response
    {
        $b = Input::body($req);
        $c = $this->customers->findByEmail(Input::str($b, 'email'));
        $hash = $c->passwordHash ?? '$2y$12$invalidinvalidinvalidinvalidinvalidinvalidinvalidinvalidinva';
        if (!password_verify(Input::str($b, 'password'), $hash) || $c === null) {
            Flash::error('Invalid email or password.');

            return Redirect::to($res, '/account/login');
        }
        session_regenerate_id(true);
        $_SESSION['customer_id'] = $c->id;
        $_SESSION['customer_name'] = $c->name;

        return Redirect::to($res, '/account');
    }

    public function logout(Request $req, Response $res): Response
    {
        unset($_SESSION['customer_id'], $_SESSION['customer_name']);

        return Redirect::to($res, '/');
    }

    /** Offered after a guest checkout; only for the customer who just paid. */
    public function setPassword(Request $req, Response $res): Response
    {
        $b = Input::body($req);
        $id = (int) ($_SESSION['last_order_customer_id'] ?? 0);
        $customer = $id > 0 ? $this->customers->find($id) : null;
        $password = Input::str($b, 'password');
        if ($customer === null || $customer->isRegistered()) {
            Flash::error('Nothing to save.');
        } elseif (mb_strlen($password) < 10) {
            Flash::error('Use at least 10 characters.');
        } else {
            $this->customers->setPasswordHash($customer->id, password_hash($password, PASSWORD_DEFAULT));
            unset($_SESSION['last_order_customer_id']);
            Flash::success('Account saved. Next time, sign in at checkout.');
        }

        return Redirect::to($res, '/');
    }

    public function orders(Request $req, Response $res): Response
    {
        return $this->view->render($res, 'storefront/account.twig', ['orders' => $this->orders->forCustomer((int) $_SESSION['customer_id'])]);
    }
}
