<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Repository\AttributeRepository;
use App\Support\Flash;
use App\Support\Input;
use App\Support\Redirect;
use App\Support\View;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class AttributeController
{
    public function __construct(private readonly View $view, private readonly AttributeRepository $attributes)
    {
    }

    public function index(Request $req, Response $res): Response
    {
        return $this->view->render($res, 'admin/attributes/index.twig', ['attributes' => $this->attributes->allWithValues()]);
    }

    public function create(Request $req, Response $res): Response
    {
        $b = Input::body($req);
        if (Input::str($b, 'name') === '') {
            Flash::error('Name is required.');
        } else {
            $this->attributes->createAttribute(['name' => Input::str($b, 'name'), 'display_on_storefront' => (int) Input::bool($b, 'display_on_storefront'), 'sort_order' => Input::int($b, 'sort_order')]);
            Flash::success('Attribute created.');
        }

        return Redirect::to($res, '/admin/attributes');
    }

    /** @param array{id: string} $args */
    public function update(Request $req, Response $res, array $args): Response
    {
        $b = Input::body($req);
        $this->attributes->updateAttribute((int) $args['id'], ['name' => Input::str($b, 'name'), 'display_on_storefront' => (int) Input::bool($b, 'display_on_storefront'), 'sort_order' => Input::int($b, 'sort_order')]);
        Flash::success('Attribute saved.');

        return Redirect::to($res, '/admin/attributes');
    }

    /** @param array{id: string} $args */
    public function delete(Request $req, Response $res, array $args): Response
    {
        try {
            $this->attributes->deleteAttribute((int) $args['id']);
            Flash::success('Attribute deleted.');
        } catch (\Doctrine\DBAL\Exception $e) {
            Flash::error('Could not delete the attribute.');
        }

        return Redirect::to($res, '/admin/attributes');
    }

    /** @param array{id: string} $args */
    public function addValue(Request $req, Response $res, array $args): Response
    {
        $value = Input::str(Input::body($req), 'value');
        if ($value !== '') {
            $this->attributes->addValue((int) $args['id'], $value);
        }

        return Redirect::to($res, '/admin/attributes');
    }

    /** @param array{id: string} $args */
    public function deleteValue(Request $req, Response $res, array $args): Response
    {
        $this->attributes->deleteValue((int) $args['id']);

        return Redirect::to($res, '/admin/attributes');
    }
}
