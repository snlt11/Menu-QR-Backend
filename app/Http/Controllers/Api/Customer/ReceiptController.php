<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ReceiptController extends Controller
{
    public function show(string $tenant_slug, string $orderId)
    {
        $data = $this->getReceiptData($orderId);
        if ($data instanceof JsonResponse) {
            return $data;
        }

        return response()->view('receipts.show', $data)->header('Content-Type', 'text/html');
    }

    public function download(string $tenant_slug, string $orderId)
    {
        $data = $this->getReceiptData($orderId);
        if ($data instanceof JsonResponse) {
            return $data;
        }

        $filename = "receipt-{$data['orderCode']}.pdf";

        return Pdf::loadView('receipts.pdf', $data)
            ->setPaper('a4', 'portrait')
            ->download($filename);
    }

    private function getReceiptData(string $orderId): array|JsonResponse
    {
        $order = DB::table('orders')->where('id', $orderId)->first();
        if (! $order) {
            return response()->json(['status' => 404, 'message' => 'Order not found.'], 404);
        }

        if ($order->payment_status !== 'paid') {
            return response()->json(['status' => 422, 'message' => 'Order is not paid.'], 422);
        }

        $profile = DB::table('profile')->first();
        $shopName = $profile?->name ?? 'Restaurant';
        $shopAddress = $profile?->address ?? '';
        $shopPhone = $profile?->phone ?? '';
        $currency = $profile?->currency ?? 'MMK';

        $table = $order->table_id ? DB::table('tables')->where('id', $order->table_id)->first() : null;
        $tableName = $table?->table_name ?? ($table?->table_number ? "Table {$table->table_number}" : 'No table');

        $customerName = null;
        if ($order->customer_id) {
            $customer = DB::table('customers')->where('id', $order->customer_id)->first();
            $customerName = $customer?->name;
        }
        $customerType = $order->customer_type === 'member' ? 'Member' : 'Guest';
        $customerDisplay = $customerName ?? $customerType;

        $payment = DB::table('payments')->where('order_id', $order->id)->where('status', 'paid')->orderByDesc('updated_at')->first();
        $paymentMethod = $payment?->method ?? 'N/A';
        $paymentMethodLabel = match ($paymentMethod) {
            'cash' => 'Cash',
            'qr_payment' => 'QR Payment',
            default => ucfirst($paymentMethod),
        };

        $items = DB::table('order_items')->where('order_id', $order->id)->orderBy('created_at')->get();

        $orderDate = Carbon::parse($order->created_at)->format('M d, Y H:i');
        $paidAt = $order->paid_at ? Carbon::parse($order->paid_at)->format('M d, Y H:i') : 'N/A';

        return [
            'orderCode' => $order->order_number,
            'shopName' => $shopName,
            'shopAddress' => $shopAddress,
            'shopPhone' => $shopPhone,
            'currency' => $currency,
            'tableName' => $tableName,
            'customerDisplay' => $customerDisplay,
            'paymentMethodLabel' => $paymentMethodLabel,
            'items' => $items,
            'orderDate' => $orderDate,
            'paidAt' => $paidAt,
            'subtotal' => (float) $order->subtotal_amount,
            'serviceCharge' => (float) $order->service_charge_amount,
            'tax' => (float) $order->tax_amount,
            'discount' => (float) $order->point_discount_amount,
            'total' => (float) $order->payable_amount,
        ];
    }
}
