<?php

declare(strict_types=1);

use App\Controller\Admin;
use App\Controller\Storefront;
use App\Controller\Webhook;
use App\Middleware\AdminAuthMiddleware;
use App\Middleware\CustomerAuthMiddleware;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

return static function (App $app): void {
    // Storefront
    $app->get('/', [Storefront\CatalogueController::class, 'home']);
    $app->get('/category/{slug}', [Storefront\CatalogueController::class, 'category']);
    $app->get('/product/{id:[0-9]+}', [Storefront\CatalogueController::class, 'product']);

    $app->get('/cart', [Storefront\CartController::class, 'show']);
    $app->post('/cart/add', [Storefront\CartController::class, 'add']);
    $app->post('/cart/update', [Storefront\CartController::class, 'update']);

    $app->get('/checkout', [Storefront\CheckoutController::class, 'start']);
    $app->post('/checkout/email', [Storefront\CheckoutController::class, 'email']);
    $app->post('/checkout/login', [Storefront\CheckoutController::class, 'login']);
    $app->get('/checkout/details', [Storefront\CheckoutController::class, 'details']);
    $app->post('/checkout/pay', [Storefront\CheckoutController::class, 'pay']);
    $app->get('/checkout/return/{id:[0-9]+}', [Storefront\CheckoutController::class, 'returned']);

    $app->get('/account/login', [Storefront\AccountController::class, 'loginForm']);
    $app->post('/account/login', [Storefront\AccountController::class, 'login']);
    $app->post('/account/logout', [Storefront\AccountController::class, 'logout']);
    $app->post('/account/password', [Storefront\AccountController::class, 'setPassword']);
    $app->get('/account', [Storefront\AccountController::class, 'orders'])->add(new CustomerAuthMiddleware());

    $app->map(['GET', 'POST'], '/webhook/payment', [Webhook\PaymentWebhookController::class, 'handle']);

    // Admin
    $app->get('/admin/login', [Admin\AuthController::class, 'loginForm']);
    $app->post('/admin/login', [Admin\AuthController::class, 'login']);

    $app->group('/admin', function (RouteCollectorProxy $g): void {
        $g->post('/logout', [Admin\AuthController::class, 'logout']);
        $g->get('', [Admin\DashboardController::class, 'index']);

        $g->get('/items', [Admin\ItemController::class, 'index']);
        $g->get('/items/new', [Admin\ItemController::class, 'createForm']);
        $g->post('/items', [Admin\ItemController::class, 'create']);
        $g->get('/items/{id:[0-9]+}', [Admin\ItemController::class, 'show']);
        $g->post('/items/{id:[0-9]+}', [Admin\ItemController::class, 'update']);
        $g->get('/items/{id:[0-9]+}/variants/new', [Admin\ItemController::class, 'variantForm']);
        $g->post('/items/{id:[0-9]+}/variants', [Admin\ItemController::class, 'variantCreate']);

        $g->get('/variants/{id:[0-9]+}', [Admin\VariantController::class, 'show']);
        $g->post('/variants/{id:[0-9]+}', [Admin\VariantController::class, 'update']);
        $g->post('/variants/{id:[0-9]+}/bom/component', [Admin\VariantController::class, 'addComponent']);
        $g->post('/variants/{id:[0-9]+}/bom/resource', [Admin\VariantController::class, 'addResource']);
        $g->post('/variants/{id:[0-9]+}/bom/{line:[0-9]+}/delete', [Admin\VariantController::class, 'deleteLine']);
        $g->post('/variants/{id:[0-9]+}/suppliers', [Admin\VariantController::class, 'linkSupplier']);
        $g->post('/variants/{id:[0-9]+}/suppliers/{link:[0-9]+}/delete', [Admin\VariantController::class, 'unlinkSupplier']);

        $g->get('/resources', [Admin\ResourceController::class, 'index']);
        $g->post('/resources', [Admin\ResourceController::class, 'create']);
        $g->get('/resources/{id:[0-9]+}', [Admin\ResourceController::class, 'edit']);
        $g->post('/resources/{id:[0-9]+}', [Admin\ResourceController::class, 'update']);

        $g->get('/categories', [Admin\CategoryController::class, 'index']);
        $g->post('/categories', [Admin\CategoryController::class, 'create']);
        $g->post('/categories/reorder', [Admin\CategoryController::class, 'reorder']);
        $g->post('/categories/{id:[0-9]+}', [Admin\CategoryController::class, 'update']);
        $g->post('/categories/{id:[0-9]+}/delete', [Admin\CategoryController::class, 'delete']);

        $g->get('/attributes', [Admin\AttributeController::class, 'index']);
        $g->post('/attributes', [Admin\AttributeController::class, 'create']);
        $g->post('/attributes/{id:[0-9]+}', [Admin\AttributeController::class, 'update']);
        $g->post('/attributes/{id:[0-9]+}/delete', [Admin\AttributeController::class, 'delete']);
        $g->post('/attributes/{id:[0-9]+}/values', [Admin\AttributeController::class, 'addValue']);
        $g->post('/attribute-values/{id:[0-9]+}/delete', [Admin\AttributeController::class, 'deleteValue']);

        $g->get('/stock', [Admin\StockController::class, 'index']);
        $g->get('/stock/{id:[0-9]+}', [Admin\StockController::class, 'ledger']);

        $g->get('/suppliers', [Admin\SupplierController::class, 'index']);
        $g->post('/suppliers', [Admin\SupplierController::class, 'create']);
        $g->post('/suppliers/{id:[0-9]+}', [Admin\SupplierController::class, 'update']);

        $g->get('/purchase-orders', [Admin\PurchaseOrderController::class, 'index']);
        $g->get('/purchase-orders/new', [Admin\PurchaseOrderController::class, 'createForm']);
        $g->post('/purchase-orders', [Admin\PurchaseOrderController::class, 'create']);
        $g->get('/purchase-orders/{id:[0-9]+}', [Admin\PurchaseOrderController::class, 'show']);
        $g->post('/purchase-orders/{id:[0-9]+}/items', [Admin\PurchaseOrderController::class, 'addItem']);
        $g->post('/purchase-orders/{id:[0-9]+}/items/{item:[0-9]+}/delete', [Admin\PurchaseOrderController::class, 'deleteItem']);
        $g->post('/purchase-orders/{id:[0-9]+}/order', [Admin\PurchaseOrderController::class, 'place']);
        $g->post('/purchase-orders/{id:[0-9]+}/receive/{item:[0-9]+}', [Admin\PurchaseOrderController::class, 'receive']);
        $g->post('/purchase-orders/{id:[0-9]+}/cancel', [Admin\PurchaseOrderController::class, 'cancel']);

        $g->get('/manufacturing', [Admin\ManufacturingController::class, 'index']);
        $g->post('/manufacturing/produce', [Admin\ManufacturingController::class, 'produce']);

        $g->get('/orders', [Admin\SalesOrderController::class, 'index']);
        $g->get('/orders/new', [Admin\SalesOrderController::class, 'createForm']);
        $g->post('/orders', [Admin\SalesOrderController::class, 'create']);
        $g->get('/orders/{id:[0-9]+}', [Admin\SalesOrderController::class, 'show']);
        $g->post('/orders/{id:[0-9]+}/lines', [Admin\SalesOrderController::class, 'addLine']);
        $g->post('/orders/{id:[0-9]+}/lines/{item:[0-9]+}/delete', [Admin\SalesOrderController::class, 'removeLine']);
        $g->post('/orders/{id:[0-9]+}/paid', [Admin\SalesOrderController::class, 'markPaid']);
        $g->post('/orders/{id:[0-9]+}/lines/{item:[0-9]+}/despatch', [Admin\SalesOrderController::class, 'despatch']);
        $g->post('/orders/{id:[0-9]+}/lines/{item:[0-9]+}/cancel', [Admin\SalesOrderController::class, 'cancelLine']);
        $g->post('/orders/{id:[0-9]+}/lines/{item:[0-9]+}/return', [Admin\SalesOrderController::class, 'returnLine']);
        $g->post('/orders/{id:[0-9]+}/payments/{payment:[0-9]+}/refund', [Admin\SalesOrderController::class, 'refund']);
        $g->get('/orders/{id:[0-9]+}/invoice.pdf', [Admin\SalesOrderController::class, 'invoice']);
    })->add(new AdminAuthMiddleware());
};
