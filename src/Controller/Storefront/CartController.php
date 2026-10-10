<?php

declare(strict_types=1);

namespace App\Controller\Storefront;

use App\Repository\CatalogueRepository;
use App\Support\Cart;
use App\Support\Flash;
use App\Support\Input;
use App\Support\Money;
use App\Support\Redirect;
use App\Support\View;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class CartController
{
    public function __construct(private readonly View $view, private readonly CatalogueRepository $catalogue)
    {
    }

    public function show(Request $req, Response $res): Response
    {
        $lines = [];
        $total = 0;
        foreach (Cart::all() as $variantId => $qty) {
            $v = $this->catalogue->sellableVariant($variantId);
            if ($v === null) {
                Cart::set($variantId, 0);
                continue;
            }
            $pence = Money::pence($qty * (float) $v['sale_price']);
            $total += $pence;
            $lines[] = ['variant' => $v, 'qty' => $qty, 'line_pence' => $pence];
        }

        return $this->view->render($res, 'storefront/cart.twig', ['lines' => $lines, 'total_pence' => $total]);
    }

    public function add(Request $req, Response $res): Response
    {
        $b = Input::body($req);
        $variantId = Input::int($b, 'variant_id');
        $qty = max(1.0, min(99.0, Input::float($b, 'quantity', 1)));
        if ($this->catalogue->sellableVariant($variantId) === null) {
            Flash::error('That item is not available.');

            return Redirect::to($res, '/');
        }
        Cart::add($variantId, floor($qty));
        Flash::success('Added to your basket.');

        return Redirect::to($res, '/cart');
    }

    public function update(Request $req, Response $res): Response
    {
        $q = Input::body($req)['qty'] ?? [];
        foreach (is_array($q) ? $q : [] as $variantId => $qty) {
            Cart::set((int) $variantId, is_numeric($qty) ? floor(min(99.0, (float) $qty)) : 0.0);
        }

        return Redirect::to($res, '/cart');
    }
}
