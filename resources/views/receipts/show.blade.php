<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Receipt - {{ $orderCode }}</title>
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; max-width: 400px; margin: 0 auto; padding: 24px 16px; color: #111; font-size: 13px; }
    .header { text-align: center; margin-bottom: 20px; }
    .shop-name { font-size: 18px; font-weight: 700; margin-bottom: 4px; }
    .shop-info { font-size: 11px; color: #666; line-height: 1.4; }
    .divider { border: none; border-top: 1px dashed #999; margin: 12px 0; }
    .meta { display: flex; justify-content: space-between; margin-bottom: 4px; font-size: 12px; }
    .meta .label { color: #666; }
    .meta .value { font-weight: 500; }
    table { width: 100%; border-collapse: collapse; margin: 8px 0; }
    th { text-align: left; font-size: 11px; color: #666; padding: 6px 0; border-bottom: 1px solid #ddd; }
    td { padding: 6px 0; font-size: 12px; vertical-align: top; }
    .item-name { font-weight: 500; }
    .instruction { font-size: 10px; color: #888; font-weight: 400; margin-top: 2px; }
    .center { text-align: center; }
    .right { text-align: right; }
    th.right, th.center { text-align: right; }
    th.center { text-align: center; }
    .totals { margin-top: 8px; }
    .row { display: flex; justify-content: space-between; padding: 3px 0; font-size: 12px; }
    .row.total { font-size: 15px; font-weight: 700; padding-top: 8px; border-top: 2px solid #111; margin-top: 6px; }
    .footer { text-align: center; margin-top: 20px; font-size: 11px; color: #666; }
    .badge { display: inline-block; padding: 2px 8px; border: 1px solid #111; border-radius: 3px; font-size: 10px; font-weight: 600; }
    @media print {
        body { padding: 0; }
        .no-print { display: none; }
    }
</style>
</head>
<body>
    <div class="header">
        <div class="shop-name">{{ $shopName }}</div>
        <div class="shop-info">
            {{ $shopAddress }}
            {{ $shopPhone }}
        </div>
    </div>

    <hr class="divider">

    <div class="meta">
        <span class="label">Order</span>
        <span class="value">{{ $orderCode }}</span>
    </div>
    <div class="meta">
        <span class="label">Table</span>
        <span class="value">{{ $tableName }}</span>
    </div>
    <div class="meta">
        <span class="label">Customer</span>
        <span class="value">{{ $customerDisplay }}</span>
    </div>
    <div class="meta">
        <span class="label">Date</span>
        <span class="value">{{ $orderDate }}</span>
    </div>

    <hr class="divider">

    <table>
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

    <div class="totals">
        <div class="row">
            <span>Subtotal</span>
            <span>{{ $currency }} {{ number_format($subtotal, 2) }}</span>
        </div>
        @if ($discount > 0)
            <div class="row">
                <span>Points discount</span>
                <span>- {{ $currency }} {{ number_format($discount, 2) }}</span>
            </div>
        @endif
        <div class="row">
            <span>Service charge</span>
            <span>{{ $currency }} {{ number_format($serviceCharge, 2) }}</span>
        </div>
        <div class="row">
            <span>Tax</span>
            <span>{{ $currency }} {{ number_format($tax, 2) }}</span>
        </div>
        <div class="row total">
            <span>Total</span>
            <span>{{ $currency }} {{ number_format($total, 2) }}</span>
        </div>
    </div>

    <hr class="divider">

    <div class="meta">
        <span class="label">Payment</span>
        <span class="value">
            <span class="badge">Paid</span>
            &nbsp;{{ $paymentMethodLabel }}
        </span>
    </div>
    <div class="meta">
        <span class="label">Paid at</span>
        <span class="value">{{ $paidAt }}</span>
    </div>

    <div class="footer">
        Thank you for dining with us.
    </div>

    <button class="no-print" onclick="window.print()" style="display:block;width:100%;margin-top:20px;padding:10px;background:#111;color:#fff;border:none;border-radius:8px;font-size:14px;cursor:pointer;">
        Print / Save as PDF
    </button>
</body>
</html>
