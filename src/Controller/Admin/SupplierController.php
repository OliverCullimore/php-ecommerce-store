<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Repository\SupplierRepository;
use App\Support\Flash;
use App\Support\Input;
use App\Support\Redirect;
use App\Support\View;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class SupplierController
{
    public function __construct(private readonly View $view, private readonly SupplierRepository $suppliers)
    {
    }

    public function index(Request $req, Response $res): Response
    {
        return $this->view->render($res, 'admin/suppliers/index.twig', ['suppliers' => $this->suppliers->all()]);
    }

    public function create(Request $req, Response $res): Response
    {
        $b = Input::body($req);
        if (Input::str($b, 'name') === '') {
            Flash::error('Name is required.');
        } else {
            $this->suppliers->create(Input::str($b, 'name'), Input::nullableStr($b, 'contact_info'));
            Flash::success('Supplier created.');
        }

        return Redirect::to($res, '/admin/suppliers');
    }

    /** @param array{id: string} $args */
    public function update(Request $req, Response $res, array $args): Response
    {
        $b = Input::body($req);
        $this->suppliers->update((int) $args['id'], Input::str($b, 'name'), Input::nullableStr($b, 'contact_info'));
        Flash::success('Supplier saved.');

        return Redirect::to($res, '/admin/suppliers');
    }
}
