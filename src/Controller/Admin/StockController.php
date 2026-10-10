<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Domain\Item\LowStockEvaluator;
use App\Repository\StockRepository;
use App\Repository\VariantRepository;
use App\Support\View;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class StockController
{
    public function __construct(private readonly View $view, private readonly StockRepository $stock, private readonly VariantRepository $variants)
    {
    }

    public function index(Request $req, Response $res): Response
    {
        $rows = array_map(static function (array $r): array {
            $r['low'] = LowStockEvaluator::isLow((float) $r['balance'], (float) $r['reorder_threshold']);

            return $r;
        }, $this->stock->overview());

        return $this->view->render($res, 'admin/stock/index.twig', ['rows' => $rows]);
    }

    /** @param array{id: string} $args */
    public function ledger(Request $req, Response $res, array $args): Response
    {
        $id = (int) $args['id'];
        $v = $this->variants->findWithItem($id) ?? throw new \Slim\Exception\HttpNotFoundException($req);

        return $this->view->render($res, 'admin/stock/ledger.twig', ['v' => $v, 'entries' => $this->stock->ledger($id), 'balance' => $this->stock->balance($id)]);
    }
}
