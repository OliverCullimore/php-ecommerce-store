<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Domain\Catalog\CategoryService;
use App\Domain\Catalog\CategoryTreeBuilder;
use App\Repository\CategoryRepository;
use App\Support\Flash;
use App\Support\Input;
use App\Support\Redirect;
use App\Support\View;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class CategoryController
{
    public function __construct(
        private readonly View $view,
        private readonly CategoryRepository $categories,
        private readonly CategoryService $service,
        private readonly CategoryTreeBuilder $tree,
    ) {
    }

    public function index(Request $req, Response $res): Response
    {
        $nodes = $this->tree->build($this->categories->all());

        return $this->view->render($res, 'admin/categories/index.twig', ['nodes' => $nodes, 'flat' => $this->tree->flatten($nodes)]);
    }

    public function create(Request $req, Response $res): Response
    {
        $b = Input::body($req);
        try {
            $this->service->create(Input::str($b, 'name'), Input::nullableInt($b, 'parent_id'));
            Flash::success('Category created.');
        } catch (\DomainException $e) {
            Flash::error($e->getMessage());
        }

        return Redirect::to($res, '/admin/categories');
    }

    /** Rename/activate and move are separate form fields on separate submit buttons, so one never silently undoes the other. @param array{id: string} $args */
    public function update(Request $req, Response $res, array $args): Response
    {
        $id = (int) $args['id'];
        $b = Input::body($req);
        try {
            if (Input::str($b, 'action') === 'move') {
                $this->service->move($id, Input::nullableInt($b, 'parent_id'));
                Flash::success('Category moved.');
            } else {
                $this->service->rename($id, Input::str($b, 'name'), Input::bool($b, 'active'));
                Flash::success('Category saved.');
            }
        } catch (\DomainException $e) {
            Flash::error($e->getMessage());
        }

        return Redirect::to($res, '/admin/categories');
    }

    /** @param array{id: string} $args */
    public function delete(Request $req, Response $res, array $args): Response
    {
        try {
            $this->service->delete((int) $args['id']);
            Flash::success('Category deleted.');
        } catch (\DomainException $e) {
            Flash::error($e->getMessage());
        }

        return Redirect::to($res, '/admin/categories');
    }

    /** Called by the drag-and-drop script with {parent_id, ids: [...]} (JSON). */
    public function reorder(Request $req, Response $res): Response
    {
        $b = Input::body($req);
        try {
            $this->service->reorder(Input::nullableInt($b, 'parent_id'), Input::ints($b, 'ids'));
            $status = 200;
            $payload = ['ok' => true];
        } catch (\DomainException $e) {
            $status = 422;
            $payload = ['ok' => false, 'error' => $e->getMessage()];
        }
        $res->getBody()->write((string) json_encode($payload));

        return $res->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}
