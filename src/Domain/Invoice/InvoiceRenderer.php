<?php

declare(strict_types=1);

namespace App\Domain\Invoice;

use App\Entity\LineStatus;
use App\Repository\CustomerRepository;
use App\Repository\PaymentRepository;
use App\Repository\SalesOrderRepository;
use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

/** Renders from live order data on every request; nothing is stored, so corrections can't leave a stale copy. */
final class InvoiceRenderer
{
    public function __construct(
        private readonly SalesOrderRepository $orders,
        private readonly CustomerRepository $customers,
        private readonly PaymentRepository $payments,
        private readonly Environment $twig,
        private readonly string $shopName,
        private readonly string $shopAddress,
    ) {
    }

    /** @return string PDF bytes */
    public function render(int $orderId): string
    {
        $order = $this->orders->find($orderId) ?? throw new \DomainException('Order not found.');
        if (!$order->isPaid()) {
            throw new \DomainException('Invoices are only available for paid orders.');
        }
        $customer = $this->customers->find($order->customerId) ?? throw new \DomainException('Customer missing.');
        $lines = array_values(array_filter($this->orders->itemsWithLabels($orderId), static fn (array $l): bool => $l['line_status'] !== LineStatus::Cancelled->value));
        $payments = $this->payments->forOrder($orderId);
        $total = 0;
        foreach ($lines as &$l) {
            $l['line_pence'] = (int) round((float) $l['quantity'] * (float) $l['unit_price'] * 100);
            $total += $l['line_pence'];
        }
        unset($l);
        $paid = array_sum(array_map(static fn ($p): int => $p->status->value === 'captured' ? $p->amountPence : 0, $payments));

        $html = $this->twig->render('pdf/invoice.html.twig', [
            'invoice_number' => $order->id,
            'order' => $order,
            'customer' => $customer,
            'lines' => $lines,
            'payments' => $payments,
            'total_pence' => $total,
            'paid_pence' => $paid,
            'shop_name' => $this->shopName,
            'shop_address' => $this->shopAddress,
            'issued' => (new \DateTimeImmutable())->format('j F Y'),
        ]);

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4');
        $dompdf->render();

        $this->orders->stampInvoiceIssued($orderId);

        return (string) $dompdf->output();
    }
}
