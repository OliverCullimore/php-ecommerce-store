<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Domain\Item\BomService;
use App\Domain\Item\LowStockEvaluator;
use App\Repository\AttributeRepository;
use App\Repository\BomRepository;
use App\Repository\ResourceRepository;
use App\Repository\StockRepository;
use App\Repository\SupplierRepository;
use App\Repository\VariantRepository;
use App\Support\Flash;
use App\Support\Input;
use App\Support\Redirect;
use App\Support\View;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class VariantController
{
    public function __construct(
        private readonly View $view,
        private readonly VariantRepository $variants,
        private readonly BomRepository $bom,
        private readonly BomService $bomService,
        private readonly ResourceRepository $resources,
        private readonly AttributeRepository $attributes,
        private readonly SupplierRepository $suppliers,
        private readonly StockRepository $stock,
    ) {
    }

    /** @param array{id: string} $args */
    public function show(Request $req, Response $res, array $args): Response
    {
        $id = (int) $args['id'];
        $row = $this->variants->findWithItem($id) ?? throw new \Slim\Exception\HttpNotFoundException($req);
        $manufactured = $this->bom->isManufactured($id);
        $cost = null;
        $costError = null;
        try {
            $cost = $this->bomService->estimatedCost($id);
        } catch (\DomainException $e) {
            $costError = $e->getMessage();
        }
        $stock = $this->stock->balance($id);

        return $this->view->render($res, 'admin/variants/show.twig', [
            'v' => $row,
            'manufactured' => $manufactured,
            'component_lines' => $this->bom->componentLines($id),
            'resource_lines' => $this->bom->resourceLines($id),
            'estimated_cost' => $cost,
            'cost_error' => $costError,
            'stock' => $stock,
            'is_low' => LowStockEvaluator::isLow($stock, (float) $row['reorder_threshold']),
            'component_options' => array_values(array_filter($this->variants->options(), static fn (array $o): bool => (int) $o['id'] !== $id)),
            'resource_options' => $this->resources->all(),
            'attributes' => $this->attributes->allWithValues(),
            'chosen' => array_column($this->variants->attributeValues($id), 'attribute_value_id', 'attribute_id'),
            'supplier_links' => $this->suppliers->linksForVariant($id),
            'supplier_options' => $this->suppliers->all(),
        ]);
    }

    /** @param array{id: string} $args */
    public function update(Request $req, Response $res, array $args): Response
    {
        $id = (int) $args['id'];
        $b = Input::body($req);
        $sku = Input::str($b, 'sku');
        if ($sku === '' || Input::str($b, 'name') === '') {
            Flash::error('SKU and name are required.');
        } elseif ($this->variants->skuExists($sku, $id)) {
            Flash::error('That SKU is already in use.');
        } else {
            $sellable = Input::bool($b, 'sellable');
            $this->variants->update($id, [
                'sku' => $sku,
                'name' => Input::str($b, 'name'),
                'unit' => Input::str($b, 'unit', 'unit') ?: 'unit',
                'reorder_threshold' => Input::float($b, 'reorder_threshold'),
                'sellable' => (int) $sellable,
                'sale_price' => $sellable ? Input::nullableFloat($b, 'sale_price') : null,
                'status' => Input::str($b, 'status') === 'discontinued' ? 'discontinued' : 'active',
                'notes' => Input::nullableStr($b, 'notes'),
            ]);
            $chosen = [];
            foreach (is_array($b['attr'] ?? null) ? $b['attr'] : [] as $attributeId => $valueId) {
                $chosen[(int) $attributeId] = (int) $valueId;
            }
            $this->variants->setAttributeValues($id, $chosen);
            Flash::success('Variant saved.');
        }

        return Redirect::to($res, '/admin/variants/' . $id);
    }

    /** @param array{id: string} $args */
    public function addComponent(Request $req, Response $res, array $args): Response
    {
        $id = (int) $args['id'];
        $b = Input::body($req);
        try {
            $this->bomService->addComponent($id, Input::int($b, 'component_variant_id'), Input::float($b, 'quantity'), Input::nullableStr($b, 'notes'));
            Flash::success('Component added.');
        } catch (\DomainException $e) {
            Flash::error($e->getMessage());
        }

        return Redirect::to($res, '/admin/variants/' . $id);
    }

    /** @param array{id: string} $args */
    public function addResource(Request $req, Response $res, array $args): Response
    {
        $id = (int) $args['id'];
        $b = Input::body($req);
        try {
            $this->bomService->addResource($id, Input::int($b, 'resource_id'), Input::float($b, 'hours_used'), Input::nullableStr($b, 'notes'));
            Flash::success('Resource line added.');
        } catch (\DomainException $e) {
            Flash::error($e->getMessage());
        }

        return Redirect::to($res, '/admin/variants/' . $id);
    }

    /** @param array{id: string, line: string} $args */
    public function deleteLine(Request $req, Response $res, array $args): Response
    {
        $this->bom->deleteLine((int) $args['line'], (int) $args['id']);
        Flash::success('Line removed.');

        return Redirect::to($res, '/admin/variants/' . $args['id']);
    }

    /** @param array{id: string} $args */
    public function linkSupplier(Request $req, Response $res, array $args): Response
    {
        $b = Input::body($req);
        $supplierId = Input::int($b, 'supplier_id');
        if ($supplierId > 0) {
            $this->suppliers->link((int) $args['id'], $supplierId, Input::nullableStr($b, 'supplier_sku'), Input::bool($b, 'is_default'));
            Flash::success('Supplier linked.');
        }

        return Redirect::to($res, '/admin/variants/' . $args['id']);
    }

    /** @param array{id: string, link: string} $args */
    public function unlinkSupplier(Request $req, Response $res, array $args): Response
    {
        $this->suppliers->unlink((int) $args['link']);
        Flash::success('Supplier unlinked.');

        return Redirect::to($res, '/admin/variants/' . $args['id']);
    }
}
