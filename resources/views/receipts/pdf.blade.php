<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Receipt - {{ $orderCode }}</title>
<style>
    @page { margin: 40px; }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: Helvetica, Arial, sans-serif; color: #111; font-size: 13px; }
    .page { max-width: 460px; margin: 0 auto; padding-top: 20px; }
    .header { text-align: center; margin-bottom: 28px; }
    .shop-name { font-size: 22px; font-weight: 700; color: #111; margin-bottom: 6px; }
    .shop-info { font-size: 12px; color: #666; line-height: 1.6; }
    .title { text-align: center; font-size: 14px; font-weight: 600; color: #999; text-transform: uppercase; letter-spacing: 3px; margin-bottom: 24px; }
    .divider { border: none; border-top: 1px solid #e5e5e5; margin: 16px 0; }
    .meta-table { width: 100%; border-collapse: collapse; }
    .meta-table td { padding: 4px 0; font-size: 13px; vertical-align: top; }
    .meta-table .label { color: #888; width: 120px; }
    .meta-table .value { font-weight: 500; color: #111; }
    table.items { width: 100%; border-collapse: collapse; margin: 12px 0; }
    table.items thead th { text-align: left; font-size: 11px; font-weight: 600; color: #888; text-transform: uppercase; letter-spacing: 0.5px; padding: 8px 0; border-bottom: 2px solid #111; }
    table.items thead th.center { text-align: center; }
    table.items thead th.right { text-align: right; }
    table.items tbody td { padding: 10px 0; font-size: 13px; vertical-align: top; border-bottom: 1px solid #f0f0f0; }
    table.items tbody tr:last-child td { border-bottom: none; }
    .item-name { font-weight: 500; color: #111; }
    .instruction { font-size: 11px; color: #aaa; font-weight: 400; margin-top: 3px; }
    .center { text-align: center; }
    .right { text-align: right; }
    .totals-table { width: 100%; border-collapse: collapse; }
    .totals-table td { padding: 5px 0; font-size: 13px; }
    .totals-table .label { color: #666; }
    .totals-table .value { text-align: right; color: #111; }
    .totals-table tr.total-row td { padding-top: 12px; border-top: 2px solid #111; font-size: 16px; font-weight: 700; color: #111; }
    .payment-table { width: 100%; border-collapse: collapse; }
    .payment-table td { padding: 4px 0; font-size: 13px; }
    .payment-table .label { color: #888; width: 120px; }
    .payment-table .value { font-weight: 500; color: #111; }
    .badge { display: inline-block; padding: 2px 10px; border: 1px solid #111; font-size: 10px; font-weight: 700; letter-spacing: 0.5px; }
    .footer { text-align: center; margin-top: 32px; font-size: 12px; color: #999; }
</style>
</head>
<body>
    <div class="page">
        <div class="header">
            <div class="shop-name">{{ $shopName }}</div>
            <div class="shop-info">
                {{ $shopAddress }}<br>
                {{ $shopPhone }}
            </div>
        </div>

        <div class="title">Receipt</div>

        <table class="meta-table">
            <tr>
                <td class="label">Order</td>
                <td class="value">{{ $orderCode }}</td>
            </tr>
            <tr>
                <td class="label">Table</td>
                <td class="value">{{ $tableName }}</td>
            </tr>
            <tr>
                <td class="label">Customer</td>
                <td class="value">{{ $customerDisplay }}</td>
            </tr>
            <tr>
                <td class="label">Date</td>
                <td class="value">{{ $orderDate }}</td>
            </tr>
        </table>

        <hr class="divider">

        <table class="items">
            <thead>
                <tr>
                    <th>Item</th>
                    <th class="center">Qty</th>
                    <th class="right">Price</th>
                    <th class="right">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($items as $item)
                    <tr>
                        <td class="item-name">
                            {{ $item->snapshot_name }}
                            @if ($item->instruction)
                                <div class="instruction">Note: {{ $item->instruction }}</div>
                            @endif
                        </td>
                        <td class="center">{{ (int) $item->quantity }}</td>
                        <td class="right">{{ $currency }} {{ number_format((float) $item->snapshot_price, 2) }}</td>
                        <td class="right">{{ $currency }} {{ number_format((float) $item->subtotal_amount, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <hr class="divider">

        <table class="totals-table">
            <tr>
                <td class="label">Subtotal</td>
                <td class="value">{{ $currency }} {{ number_format($subtotal, 2) }}</td>
            </tr>
            @if ($discount > 0)
                <tr>
                    <td class="label">Points discount</td>
                    <td class="value">- {{ $currency }} {{ number_format($discount, 2) }}</td>
                </tr>
            @endif
            <tr>
                <td class="label">Service charge</td>
                <td class="value">{{ $currency }} {{ number_format($serviceCharge, 2) }}</td>
            </tr>
            <tr>
                <td class="label">Tax</td>
                <td class="value">{{ $currency }} {{ number_format($tax, 2) }}</td>
            </tr>
            <tr class="total-row">
                <td>Total</td>
                <td class="value">{{ $currency }} {{ number_format($total, 2) }}</td>
            </tr>
        </table>

        <hr class="divider">

        <table class="payment-table">
            <tr>
                <td class="label">Payment status</td>
                <td class="value"><span class="badge">PAID</span></td>
            </tr>
            <tr>
                <td class="label">Payment method</td>
                <td class="value">{{ $paymentMethodLabel }}</td>
            </tr>
            <tr>
                <td class="label">Paid at</td>
                <td class="value">{{ $paidAt }}</td>
            </tr>
        </table>

        <div class="footer">
            Thank you for dining with us.
        </div>
    </div>
</body>
</html>
