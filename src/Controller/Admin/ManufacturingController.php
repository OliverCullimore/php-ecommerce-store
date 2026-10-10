<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Domain\Manufacturing\ManufacturingPlanner;
use App\Domain\Manufacturing\ProductionService;
use App\Repository\ProductionRepository;
use App\Support\Flash;
use App\Support\Input;
use App\Support\Redirect;
use App\Support\View;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class ManufacturingController
{
    public function __construct(
        private readonly View $view,
        private readonly ManufacturingPlanner $planner,
        private readonly ProductionService $production,
        private readonly ProductionRepository $batches,
    ) {
    }

    public function index(Request $req, Response $res): Response
    {
        return $this->view->render($res, 'admin/manufacturing/index.twig', ['candidates' => $this->planner->candidates(), 'recent' => $this->batches->recentBatches()]);
    }

    public function produce(Request $req, Response $res): Response
    {
        $b = Input::body($req);
        try {
            $id = $this->production->record(Input::int($b, 'variant_id'), Input::float($b, 'quantity'), Input::nullableStr($b, 'notes'));
            Flash::success('Batch #' . $id . ' recorded.');
        } catch (\DomainException $e) {
            Flash::error($e->getMessage());
        }

        return Redirect::to($res, '/admin/manufacturing');
    }
}
