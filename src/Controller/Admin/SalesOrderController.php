<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Domain\Invoice\InvoiceRenderer;
use App\Domain\Order\OrderService;
use App\Domain\Payment\RefundCalculator;
use App\Domain\Payment\RefundService;
use App\Entity\LineStatus;
use App\Entity\OrderStatus;
use App\Repository\CustomerRepository;
use App\Repository\PaymentRepository;
use App\Repository\SalesOrderRepository;
use App\Repository\StockRepository;
use App\Repository\VariantRepository;
use App\Support\Flash;
use App\Support\Input;
use App\Support\Money;
use App\Support\Redirect;
use App\Support\View;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class SalesOrderController
{
    public function __construct(
        private readonly View $view,
        private readonly SalesOrderRepository $orders,
        private readonly OrderService $service,
        private readonly CustomerRepository $customers,
        private readonly VariantRepository $variants,
        private readonly StockRepository $stock,
        private readonly PaymentRepository $payments,
        private readonly RefundService $refunds,
        private readonly InvoiceRenderer $invoices,
    ) {
    }

    public function index(Request $req, Response $res): Response
    {
        $status = OrderStatus::tryFrom((string) ($req->getQueryParams()['status'] ?? ''));

        return $this->view->render($res, 'admin/orders/index.twig', ['orders' => $this->orders->list($status), 'statuses' => OrderStatus::cases(), 'current' => $status?->value]);
    }

    public function createForm(Request $req, Response $res): Response
    {
        return $this->view->render($res, 'admin/orders/new.twig', ['customers' => $this->customers->all()]);
    }

    public function create(Request $req, Response $res): Response
    {
        $b = Input::body($req);
        try {
            $customerId = Input::nullableInt($b, 'customer_id');
            if ($customerId === null) {
                $email = Input::str($b, 'email');
                if (Input::str($b, 'name') === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                    throw new \DomainException('Choose a customer, or enter a name and a valid email.');
                }
                $customerId = $this->customers->findByEmail($email)?->id
                    ?? $this->customers->create(Input::str($b, 'name'), $email, Input::nullableStr($b, 'phone'), Input::nullableStr($b, 'address'));
            } elseif ($this->customers->find($customerId) === null) {
                throw new \DomainException('Customer not found.');
            }
            $id = $this->service->createManual($customerId, Input::nullableStr($b, 'notes'));

            return Redirect::to($res, '/admin/orders/' . $id);
        } catch (\DomainException $e) {
            Flash::error($e->getMessage());

            return Redirect::to($res, '/admin/orders/new');
        }
    }

    /** @param array{id: string} $args */
    public function show(Request $req, Response $res, array $args): Response
    {
        $order = $this->orders->find((int) $args['id']) ?? throw new \Slim\Exception\HttpNotFoundException($req);
        $lines = $this->orders->itemsWithLabels($order->id);
        $balances = $this->stock->balances(array_map(static fn (array $l): int => (int) $l['item_variant_id'], $lines));
        foreach ($lines as &$l) {
            // A despatch box is only offered when stock covers the line.
            $l['can_despatch'] = $order->isPaid() && $l['line_status'] === LineStatus::Pending->value && $balances[(int) $l['item_variant_id']] + 0.00005 >= (float) $l['quantity'];
            $l['line_pence'] = Money::pence((float) $l['quantity'] * (float) $l['unit_price']);
        }
        unset($l);

        $payments = $this->payments->forOrder($order->id);
        $rows = [];
        foreach ($payments as $p) {
            $rows[] = ['p' => $p, 'remaining' => $p->isRefund() ? null : RefundCalculator::remaining($p->amountPence, $this->payments->refundAmounts($p->id))];
        }

        return $this->view->render($res, 'admin/orders/show.twig', [
            'order' => $order,
            'customer' => $this->customers->find($order->customerId),
            'lines' => $lines,
            'total_pence' => $this->orders->totalPence($order->id),
            'payments' => $rows,
            'variant_options' => $this->variants->options(true),
            'all_variant_options' => $this->variants->options(),
        ]);
    }

    /** @param array{id: string} $args */
    public function addLine(Request $req, Response $res, array $args): Response
    {
        $b = Input::body($req);

        return $this->run($res, (int) $args['id'], fn () => $this->service->addLine((int) $args['id'], Input::int($b, 'variant_id'), Input::float($b, 'quantity', 1), Input::nullableFloat($b, 'unit_price')), 'Line added.');
    }

    /** @param array{id: string, item: string} $args */
    public function removeLine(Request $req, Response $res, array $args): Response
    {
        return $this->run($res, (int) $args['id'], fn () => $this->service->removeLine((int) $args['id'], (int) $args['item']), 'Line removed.');
    }

    /** @param array{id: string} $args */
    public function markPaid(Request $req, Response $res, array $args): Response
    {
        return $this->run($res, (int) $args['id'], fn () => $this->service->recordManualPayment((int) $args['id']), 'Payment recorded.');
    }

    /** @param array{id: string, item: string} $args */
    public function despatch(Request $req, Response $res, array $args): Response
    {
        return $this->run($res, (int) $args['id'], fn () => $this->service->despatchLine((int) $args['id'], (int) $args['item']), 'Line despatched.');
    }

    /** @param array{id: string, item: string} $args */
    public function cancelLine(Request $req, Response $res, array $args): Response
    {
        return $this->run($res, (int) $args['id'], fn () => $this->service->cancelLine((int) $args['id'], (int) $args['item']), 'Line cancelled. No refund has been issued.');
    }

    /** @param array{id: string, item: string} $args */
    public function returnLine(Request $req, Response $res, array $args): Response
    {
        return $this->run($res, (int) $args['id'], fn () => $this->service->returnLine((int) $args['id'], (int) $args['item']), 'Line returned to stock. No refund has been issued.');
    }

    /** @param array{id: string, payment: string} $args */
    public function refund(Request $req, Response $res, array $args): Response
    {
        $pence = Money::pence(Input::float(Input::body($req), 'amount'));

        return $this->run($res, (int) $args['id'], fn () => $this->refunds->refund((int) $args['id'], (int) $args['payment'], $pence), 'Refund of ' . Money::format($pence) . ' issued.');
    }

    /** @param array{id: string} $args */
    public function invoice(Request $req, Response $res, array $args): Response
    {
        try {
            $pdf = $this->invoices->render((int) $args['id']);
        } catch (\DomainException $e) {
            Flash::error($e->getMessage());

            return Redirect::to($res, '/admin/orders/' . $args['id']);
        }
        $res->getBody()->write($pdf);

        return $res->withHeader('Content-Type', 'application/pdf')->withHeader('Content-Disposition', 'inline; filename="invoice-' . (int) $args['id'] . '.pdf"');
    }

    private function run(Response $res, int $orderId, callable $action, string $ok): Response
    {
        try {
            $action();
            Flash::success($ok);
        } catch (\DomainException $e) {
            Flash::error($e->getMessage());
        }

        return Redirect::to($res, '/admin/orders/' . $orderId);
    }
}
