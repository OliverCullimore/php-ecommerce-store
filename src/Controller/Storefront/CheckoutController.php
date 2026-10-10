<?php

declare(strict_types=1);

namespace App\Controller\Storefront;

use App\Domain\Payment\CheckoutService;
use App\Entity\Customer;
use App\Entity\OrderStatus;
use App\Repository\CustomerRepository;
use App\Repository\SalesOrderRepository;
use App\Support\Cart;
use App\Support\Flash;
use App\Support\Input;
use App\Support\Redirect;
use App\Support\View;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;

final class CheckoutController
{
    public function __construct(
        private readonly View $view,
        private readonly CustomerRepository $customers,
        private readonly SalesOrderRepository $orders,
        private readonly CheckoutService $checkout,
    ) {
    }

    /** Step 1 of 2: email. A signed-in customer skips straight to details. */
    public function start(Request $req, Response $res): Response
    {
        if (Cart::all() === []) {
            Flash::error('Your basket is empty.');

            return Redirect::to($res, '/cart');
        }
        if (!empty($_SESSION['customer_id'])) {
            return Redirect::to($res, '/checkout/details');
        }

        return $this->view->render($res, 'storefront/checkout_email.twig', ['email' => $_SESSION['checkout_email'] ?? '']);
    }

    public function email(Request $req, Response $res): Response
    {
        $email = strtolower(Input::str(Input::body($req), 'email'));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            Flash::error('Enter a valid email address.');

            return Redirect::to($res, '/checkout');
        }
        $_SESSION['checkout_email'] = $email;
        $existing = $this->customers->findByEmail($email);
        if ($existing !== null && $existing->isRegistered() && Input::str(Input::body($req), 'guest') !== '1') {
            return $this->view->render($res, 'storefront/checkout_login.twig', ['email' => $email]);
        }

        return Redirect::to($res, '/checkout/details');
    }

    public function login(Request $req, Response $res): Response
    {
        $b = Input::body($req);
        $email = (string) ($_SESSION['checkout_email'] ?? '');
        $customer = $this->customers->findByEmail($email);
        $hash = $customer->passwordHash ?? '$2y$12$invalidinvalidinvalidinvalidinvalidinvalidinvalidinvalidinva';
        if (!password_verify(Input::str($b, 'password'), $hash) || $customer === null) {
            Flash::error('That password is not right. You can also continue as a guest.');

            return $this->view->render($res, 'storefront/checkout_login.twig', ['email' => $email]);
        }
        $this->signIn($customer);

        return Redirect::to($res, '/checkout/details');
    }

    public function details(Request $req, Response $res): Response
    {
        if (Cart::all() === []) {
            return Redirect::to($res, '/cart');
        }
        $customer = $this->currentCustomer();
        $email = $customer->email ?? (string) ($_SESSION['checkout_email'] ?? '');
        if ($email === '') {
            return Redirect::to($res, '/checkout');
        }

        return $this->view->render($res, 'storefront/checkout_details.twig', ['customer' => $customer, 'email' => $email]);
    }

    public function pay(Request $req, Response $res): Response
    {
        $b = Input::body($req);
        $signedIn = $this->currentCustomer();
        $email = $signedIn->email ?? (string) ($_SESSION['checkout_email'] ?? '');
        $name = Input::str($b, 'name');
        $address = Input::nullableStr($b, 'address');
        if ($email === '' || $name === '' || $address === null) {
            Flash::error('Name and delivery address are required.');

            return Redirect::to($res, '/checkout/details');
        }

        try {
            $customer = $signedIn ?? $this->customers->findByEmail($email);
            if ($customer === null) {
                $customer = $this->customers->find($this->customers->create($name, $email, Input::nullableStr($b, 'phone'), $address));
            } elseif ($signedIn !== null) {
                $this->customers->updateDetails($customer->id, $name, Input::nullableStr($b, 'phone'), $address);
            }
            \assert($customer instanceof Customer);
            $orderId = $this->checkout->createDraftFromCart($customer, Cart::all());
            $initiation = $this->checkout->initiatePayment($orderId, $customer->email);
        } catch (\DomainException $e) {
            Flash::error($e->getMessage());

            return Redirect::to($res, '/cart');
        }
        $_SESSION['last_order_id'] = $orderId;
        $_SESSION['last_order_customer_id'] = $customer->id;

        return $this->view->render($res, 'storefront/pay.twig', ['initiation' => $initiation, 'order_id' => $orderId]);
    }

    /** @param array{id: string} $args */
    public function returned(Request $req, Response $res, array $args): Response
    {
        $id = (int) $args['id'];
        $order = $this->orders->find($id);
        $mine = ($_SESSION['last_order_id'] ?? null) === $id || (isset($_SESSION['customer_id']) && $order?->customerId === $_SESSION['customer_id']);
        if ($order === null || !$mine) {
            throw new HttpNotFoundException($req);
        }
        $customer = $this->customers->find($order->customerId);
        $paid = $order->isPaid();
        if ($paid) {
            Cart::clear();
        }

        return $this->view->render($res, 'storefront/return.twig', [
            'order' => $order,
            'paid' => $paid,
            'failed' => $order->status === OrderStatus::PaymentPending,
            'offer_password' => $paid && $customer !== null && !$customer->isRegistered() && ($_SESSION['last_order_customer_id'] ?? null) === $customer->id,
            'total_pence' => $this->orders->totalPence($id),
        ]);
    }

    private function currentCustomer(): ?Customer
    {
        return empty($_SESSION['customer_id']) ? null : $this->customers->find((int) $_SESSION['customer_id']);
    }

    private function signIn(Customer $c): void
    {
        session_regenerate_id(true);
        $_SESSION['customer_id'] = $c->id;
        $_SESSION['customer_name'] = $c->name;
    }
}
