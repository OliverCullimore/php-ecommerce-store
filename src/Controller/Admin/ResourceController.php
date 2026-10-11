<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\ResourceStatus;
use App\Entity\ResourceType;
use App\Repository\ResourceRepository;
use App\Support\Flash;
use App\Support\Input;
use App\Support\Redirect;
use App\Support\View;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class ResourceController
{
    public function __construct(private readonly View $view, private readonly ResourceRepository $resources)
    {
    }

    public function index(Request $req, Response $res): Response
    {
        return $this->view->render($res, 'admin/resources/index.twig', ['resources' => $this->resources->all(), 'resource' => null]);
    }

    /** @param array{id: string} $args */
    public function edit(Request $req, Response $res, array $args): Response
    {
        $r = $this->resources->find((int) $args['id']) ?? throw new \Slim\Exception\HttpNotFoundException($req);

        return $this->view->render($res, 'admin/resources/index.twig', ['resources' => $this->resources->all(), 'resource' => $r]);
    }

    public function create(Request $req, Response $res): Response
    {
        $data = $this->data(Input::body($req));
        if ($data === null) {
            return Redirect::to($res, '/admin/resources');
        }
        $this->resources->create($data);
        Flash::success('Resource created.');

        return Redirect::to($res, '/admin/resources');
    }

    /** @param array{id: string} $args */
    public function update(Request $req, Response $res, array $args): Response
    {
        $data = $this->data(Input::body($req));
        if ($data !== null) {
            $this->resources->update((int) $args['id'], $data);
            Flash::success('Resource saved.');
        }

        return Redirect::to($res, '/admin/resources');
    }

    /**
     * @param array<string, mixed> $b
     * @return array<string, mixed>|null
     */
    private function data(array $b): ?array
    {
        $type = ResourceType::tryFrom(Input::str($b, 'type'));
        if ($type === null || Input::str($b, 'name') === '') {
            Flash::error('Name and type are required.');

            return null;
        }
        $asset = $type === ResourceType::Asset;
        if ($asset && (Input::nullableFloat($b, 'expected_lifetime_hours') ?? 0) <= 0) {
            Flash::error('Assets need an expected lifetime in hours to derive an hourly rate.');

            return null;
        }
        if (!$asset && Input::nullableFloat($b, 'hourly_rate') === null) {
            Flash::error('Service/labour needs an hourly rate.');

            return null;
        }

        return [
            'name' => Input::str($b, 'name'),
            'type' => $type->value,
            'status' => (ResourceStatus::tryFrom(Input::str($b, 'status')) ?? ResourceStatus::Active)->value,
            'purchase_cost' => $asset ? Input::nullableFloat($b, 'purchase_cost') : null,
            'expected_lifetime_hours' => $asset ? Input::nullableFloat($b, 'expected_lifetime_hours') : null,
            'hourly_rate' => $asset ? null : Input::nullableFloat($b, 'hourly_rate'),
            'notes' => Input::nullableStr($b, 'notes'),
        ];
    }
}
