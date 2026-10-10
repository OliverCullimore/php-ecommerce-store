<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Domain\Item\LowStockEvaluator;
use App\Entity\OrderStatus;
use App\Repository\SalesOrderRepository;
use App\Repository\StockRepository;
use App\Support\View;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class DashboardController
{
    public function __construct(private readonly View $view, private readonly StockRepository $stock, private readonly SalesOrderRepository $orders)
    {
    }

    public function index(Request $req, Response $res): Response
    {
        $low = array_values(array_filter($this->stock->overview(), static fn (array $r): bool => LowStockEvaluator::isLow((float) $r['balance'], (float) $r['reorder_threshold'])));
        $counts = [];
        foreach ([OrderStatus::AwaitingDespatch, OrderStatus::Unallocated, OrderStatus::PartiallyDespatched, OrderStatus::PaymentPending] as $s) {
            $counts[$s->value] = count($this->orders->list($s));
        }

        return $this->view->render($res, 'admin/dashboard.twig', ['low_stock' => $low, 'order_counts' => $counts]);
    }
}
