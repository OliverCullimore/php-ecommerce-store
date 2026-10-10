<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Domain\Item\PurchasingService;
use App\Entity\ResourceStatus;
use App\Repository\PurchaseOrderRepository;
use App\Repository\ResourceRepository;
use App\Repository\SupplierRepository;
use App\Repository\VariantRepository;
use App\Support\Flash;
use App\Support\Input;
use App\Support\Redirect;
use App\Support\View;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class PurchaseOrderController
{
    public function __construct(
        private readonly View $view,
        private readonly PurchaseOrderRepository $pos,
        private readonly SupplierRepository $suppliers,
        private readonly VariantRepository $variants,
        private readonly ResourceRepository $resources,
        private readonly PurchasingService $purchasing,
    ) {
    }

    public function index(Request $req, Response $res): Response
    {
        return $this->view->render($res, 'admin/purchase_orders/index.twig', ['orders' => $this->pos->list()]);
    }

    /** Pre-selects the default supplier of ?variant=ID. */
    public function createForm(Request $req, Response $res): Response
    {
        $variantId = (int) ($req->getQueryParams()['variant'] ?? 0);

        return $this->view->render($res, 'admin/purchase_orders/new.twig', [
            'suppliers' => $this->suppliers->all(),
            'selected_supplier' => $variantId > 0 ? $this->suppliers->defaultSupplierId($variantId) : null,
            'variant_id' => $variantId ?: null,
        ]);
    }

    public function create(Request $req, Response $res): Response
    {
        $b = Input::body($req);
        $supplierId = Input::int($b, 'supplier_id');
        if ($this->suppliers->find($supplierId) === null) {
            Flash::error('Choose a supplier.');

            return Redirect::to($res, '/admin/purchase-orders/new');
        }
        $id = $this->pos->create($supplierId, Input::nullableStr($b, 'notes'));

        return Redirect::to($res, '/admin/purchase-orders/' . $id . (Input::nullableInt($b, 'variant_id') ? '?variant=' . Input::int($b, 'variant_id') : ''));
    }

    /** @param array{id: string} $args */
    public function show(Request $req, Response $res, array $args): Response
    {
        $po = $this->pos->find((int) $args['id']) ?? throw new \Slim\Exception\HttpNotFoundException($req);

        return $this->view->render($res, 'admin/purchase_orders/show.twig', [
            'po' => $po,
            'supplier' => $this->suppliers->find($po->supplierId),
            'items' => $this->pos->itemsWithLabels($po->id),
            'variant_options' => $this->variants->options(),
            'resource_options' => array_values(array_filter($this->resources->all(), static fn ($r): bool => $r->status === ResourceStatus::OnOrder)),
            'preselect_variant' => (int) ($req->getQueryParams()['variant'] ?? 0),
        ]);
    }

    /** @param array{id: string} $args */
    public function addItem(Request $req, Response $res, array $args): Response
    {
        $id = (int) $args['id'];
        $b = Input::body($req);
        try {
            if ($this->pos->find($id)?->status->value !== 'draft') {
                throw new \DomainException('Lines can only be added while the order is a draft.');
            }
            $cost = Input::float($b, 'unit_cost');
            if ($cost < 0) {
                throw new \DomainException('Unit cost cannot be negative.');
            }
            if (Input::nullableInt($b, 'resource_id') !== null) {
                $this->pos->addResourceItem($id, Input::int($b, 'resource_id'), $cost);
            } else {
                $qty = Input::float($b, 'quantity');
                if ($this->variants->find(Input::int($b, 'variant_id')) === null || $qty <= 0) {
                    throw new \DomainException('Choose a variant and a positive quantity.');
                }
                $this->pos->addVariantItem($id, Input::int($b, 'variant_id'), $qty, $cost);
            }
        } catch (\DomainException $e) {
            Flash::error($e->getMessage());
        }

        return Redirect::to($res, '/admin/purchase-orders/' . $id);
    }

    /** @param array{id: string, item: string} $args */
    public function deleteItem(Request $req, Response $res, array $args): Response
    {
        if ($this->pos->find((int) $args['id'])?->status->value === 'draft') {
            $this->pos->deleteItem((int) $args['item']);
        } else {
            Flash::error('Only draft orders can be edited.');
        }

        return Redirect::to($res, '/admin/purchase-orders/' . $args['id']);
    }

    /** @param array{id: string} $args */
    public function place(Request $req, Response $res, array $args): Response
    {
        return $this->run($res, (int) $args['id'], fn () => $this->purchasing->markOrdered((int) $args['id']), 'Order placed.');
    }

    /** @param array{id: string, item: string} $args */
    public function receive(Request $req, Response $res, array $args): Response
    {
        return $this->run($res, (int) $args['id'], fn () => $this->purchasing->receiveItem((int) $args['item']), 'Received into stock.');
    }

    /** @param array{id: string} $args */
    public function cancel(Request $req, Response $res, array $args): Response
    {
        return $this->run($res, (int) $args['id'], fn () => $this->purchasing->cancel((int) $args['id']), 'Order cancelled.');
    }

    private function run(Response $res, int $id, callable $action, string $ok): Response
    {
        try {
            $action();
            Flash::success($ok);
        } catch (\DomainException $e) {
            Flash::error($e->getMessage());
        }

        return Redirect::to($res, '/admin/purchase-orders/' . $id);
    }
}
