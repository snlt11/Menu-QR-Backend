<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Profile;
use App\Models\Table;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

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
        $order = Order::where('id', $orderId)->first();
        if (! $order) {
            return response()->json(['status' => 404, 'message' => 'Order not found.'], 404);
        }

        $accessToken = request()->header('X-Order-Access-Token');
        if ($accessToken && $order->public_access_token !== $accessToken) {
            return response()->json(['status' => 403, 'message' => 'Access denied.'], 403);
        }

        if ($order->payment_status !== 'paid') {
            return response()->json(['status' => 422, 'message' => 'Order is not paid.'], 422);
        }

        $profile = Profile::first();
        $shopName = $profile?->name ?? 'Restaurant';
        $shopAddress = $profile?->address ?? '';
        $shopPhone = $profile?->phone ?? '';
        $currency = $profile?->currency ?? 'MMK';

        $table = $order->table_id ? Table::where('id', $order->table_id)->first() : null;
        $tableName = $table?->table_name ?? ($table?->table_number ? "Table {$table->table_number}" : 'No table');

        $customerName = null;
        if ($order->customer_id) {
            $customer = Customer::where('id', $order->customer_id)->first();
            $customerName = $customer?->name;
        }
        $customerType = $order->customer_type === 'member' ? 'Member' : 'Guest';
        $customerDisplay = $customerName ?? $customerType;

        $payment = Payment::where('order_id', $order->id)->where('status', 'paid')->orderByDesc('updated_at')->first();
        $paymentMethod = $payment?->method ?? 'N/A';
        $paymentMethodLabel = match ($paymentMethod) {
            'cash' => 'Cash',
            'qr_payment' => 'QR Payment',
            default => ucfirst($paymentMethod),
        };

        $items = OrderItem::where('order_id', $order->id)->orderBy('created_at')->get();

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
