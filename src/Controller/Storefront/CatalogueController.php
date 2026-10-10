<?php

declare(strict_types=1);

namespace App\Controller\Storefront;

use App\Domain\Catalog\CategoryTreeBuilder;
use App\Repository\AttributeRepository;
use App\Repository\CatalogueRepository;
use App\Repository\CategoryRepository;
use App\Support\View;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;

final class CatalogueController
{
    public function __construct(
        private readonly View $view,
        private readonly CatalogueRepository $catalogue,
        private readonly CategoryRepository $categories,
        private readonly CategoryTreeBuilder $tree,
        private readonly AttributeRepository $attributes,
    ) {
    }

    public function home(Request $req, Response $res): Response
    {
        return $this->listing($req, $res, null, null);
    }

    /** @param array{slug: string} $args */
    public function category(Request $req, Response $res, array $args): Response
    {
        $all = $this->categories->all();
        $cat = $this->categories->findBySlug($args['slug']);
        if ($cat === null || !$cat->active) {
            throw new HttpNotFoundException($req);
        }

        // Browsing a category includes items assigned to any descendant.
        return $this->listing($req, $res, $cat, $this->tree->selfAndDescendantIds($all, $cat->id));
    }

    /** @param array{id: string} $args */
    public function product(Request $req, Response $res, array $args): Response
    {
        $item = $this->catalogue->item((int) $args['id']) ?? throw new HttpNotFoundException($req);
        $variants = $this->catalogue->sellableVariants((int) $item['id']);
        if ($variants === []) {
            throw new HttpNotFoundException($req);
        }
        $options = [];
        foreach ($variants as $v) {
            foreach ($v['options'] as $name => $value) {
                $options[$name][$value] = true;
            }
        }

        return $this->view->render($res, 'storefront/product.twig', [
            'item' => $item,
            'variants' => $variants,
            'option_names' => array_map('array_keys', $options),
            'nav' => $this->nav(),
        ]);
    }

    /** @param list<int>|null $categoryIds */
    private function listing(Request $req, Response $res, ?\App\Entity\Category $cat, ?array $categoryIds): Response
    {
        $filters = [];
        $raw = $req->getQueryParams()['f'] ?? [];
        foreach (is_array($raw) ? $raw : [] as $attributeId => $valueId) {
            if (is_numeric($attributeId) && is_numeric($valueId) && (int) $valueId > 0) {
                $filters[(int) $attributeId] = (int) $valueId;
            }
        }
        $unfiltered = $this->catalogue->items($categoryIds);
        $items = $filters === [] ? $unfiltered : $this->catalogue->items($categoryIds, $filters);

        return $this->view->render($res, 'storefront/listing.twig', [
            'category' => $cat,
            'breadcrumb' => $cat === null ? [] : $this->tree->breadcrumb($this->categories->all(), $cat->id),
            'items' => $items,
            'filters' => $this->attributes->storefrontFilters(array_map(static fn (array $i): int => (int) $i['id'], $unfiltered)),
            'active_filters' => $filters,
            'nav' => $this->nav(),
        ]);
    }

    /** @return list<\App\Domain\Catalog\CategoryNode> */
    private function nav(): array
    {
        return $this->tree->build(array_values(array_filter($this->categories->all(), static fn ($c): bool => $c->active)));
    }
}
