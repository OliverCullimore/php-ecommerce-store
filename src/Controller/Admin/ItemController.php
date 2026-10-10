<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Domain\Catalog\CategoryTreeBuilder;
use App\Repository\CategoryRepository;
use App\Repository\ItemRepository;
use App\Repository\VariantRepository;
use App\Support\Flash;
use App\Support\Input;
use App\Support\Redirect;
use App\Support\View;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class ItemController
{
    public function __construct(
        private readonly View $view,
        private readonly ItemRepository $items,
        private readonly VariantRepository $variants,
        private readonly CategoryRepository $categories,
        private readonly CategoryTreeBuilder $tree,
    ) {
    }

    public function index(Request $req, Response $res): Response
    {
        return $this->view->render($res, 'admin/items/index.twig', ['items' => $this->items->list()]);
    }

    public function createForm(Request $req, Response $res): Response
    {
        return $this->view->render($res, 'admin/items/form.twig', ['item' => null, 'categories' => $this->flatCategories(), 'assigned' => []]);
    }

    public function create(Request $req, Response $res): Response
    {
        $b = Input::body($req);
        $name = Input::str($b, 'name');
        if ($name === '') {
            Flash::error('Name is required.');

            return Redirect::to($res, '/admin/items/new');
        }
        $id = $this->items->create(['name' => $name, 'description' => Input::nullableStr($b, 'description'), 'active' => (int) Input::bool($b, 'active'), 'notes' => Input::nullableStr($b, 'notes')]);
        $this->items->setCategories($id, Input::ints($b, 'categories'));
        Flash::success('Item created. Add a variant next.');

        return Redirect::to($res, '/admin/items/' . $id);
    }

    /** @param array{id: string} $args */
    public function show(Request $req, Response $res, array $args): Response
    {
        $item = $this->items->find((int) $args['id']) ?? throw new \Slim\Exception\HttpNotFoundException($req);

        return $this->view->render($res, 'admin/items/form.twig', [
            'item' => $item,
            'variants' => $this->items->variantsOf($item->id),
            'categories' => $this->flatCategories(),
            'assigned' => $this->items->categoryIds($item->id),
        ]);
    }

    /** @param array{id: string} $args */
    public function update(Request $req, Response $res, array $args): Response
    {
        $id = (int) $args['id'];
        $b = Input::body($req);
        if (Input::str($b, 'name') === '') {
            Flash::error('Name is required.');

            return Redirect::to($res, '/admin/items/' . $id);
        }
        $this->items->update($id, ['name' => Input::str($b, 'name'), 'description' => Input::nullableStr($b, 'description'), 'active' => (int) Input::bool($b, 'active'), 'notes' => Input::nullableStr($b, 'notes')]);
        $this->items->setCategories($id, Input::ints($b, 'categories'));
        Flash::success('Item saved.');

        return Redirect::to($res, '/admin/items/' . $id);
    }

    /** @param array{id: string} $args */
    public function variantForm(Request $req, Response $res, array $args): Response
    {
        $item = $this->items->find((int) $args['id']) ?? throw new \Slim\Exception\HttpNotFoundException($req);

        return $this->view->render($res, 'admin/items/variant_new.twig', ['item' => $item]);
    }

    /** @param array{id: string} $args */
    public function variantCreate(Request $req, Response $res, array $args): Response
    {
        $itemId = (int) $args['id'];
        $b = Input::body($req);
        $sku = Input::str($b, 'sku');
        if ($sku === '' || Input::str($b, 'name') === '') {
            Flash::error('SKU and name are required.');

            return Redirect::to($res, "/admin/items/$itemId/variants/new");
        }
        if ($this->variants->skuExists($sku)) {
            Flash::error('That SKU is already in use.');

            return Redirect::to($res, "/admin/items/$itemId/variants/new");
        }
        $sellable = Input::bool($b, 'sellable');
        $id = $this->variants->create([
            'item_id' => $itemId,
            'sku' => $sku,
            'name' => Input::str($b, 'name'),
            'unit' => Input::str($b, 'unit', 'each') ?: 'each',
            'reorder_threshold' => Input::float($b, 'reorder_threshold'),
            'sellable' => (int) $sellable,
            'sale_price' => $sellable ? Input::nullableFloat($b, 'sale_price') : null,
            'status' => 'active',
            'notes' => Input::nullableStr($b, 'notes'),
        ]);
        Flash::success('Variant created.');

        return Redirect::to($res, '/admin/variants/' . $id);
    }

    /** @return list<array{category: \App\Entity\Category, depth: int}> */
    private function flatCategories(): array
    {
        return $this->tree->flatten($this->tree->build($this->categories->all()));
    }
}
