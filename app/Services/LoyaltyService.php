<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerProfile;
use App\Models\LoyaltyPointTransaction;
use App\Models\Order;
use App\Models\Settings;
use Illuminate\Support\Facades\DB;

class LoyaltyService
{
    public function pointDiscountAmount(int $redeemPoints): float
    {
        $settings = Settings::first();
        if (! $settings || ! $settings->points_enabled) {
            return 0.0;
        }
        if ($settings->redeem_rate_points <= 0) {
            return 0.0;
        }

        return round($redeemPoints * ($settings->redeem_rate_amount / $settings->redeem_rate_points), 2);
    }

    public function pointsEarnedFor(float $payableAmount): int
    {
        $settings = Settings::first();
        if (! $settings || ! $settings->points_enabled || $settings->earn_rate_amount <= 0) {
            return 0;
        }

        return (int) floor($payableAmount / $settings->earn_rate_amount) * (int) $settings->earn_rate_points;
    }

    public function ensureProfileExists(string $customerId): void
    {
        $exists = CustomerProfile::where('customer_id', $customerId)->exists();

        if ($exists) {
            return;
        }

        $customer = Customer::where('id', $customerId)->first();
        if (! $customer) {
            return;
        }

        CustomerProfile::create([
            'customer_id' => $customerId,
            'name' => $customer->name,
            'phone' => $customer->phone,
            'email' => $customer->email,
            'total_points' => 0,
            'membership_level' => 'basic',
        ]);
    }

    public function deductRedeemedPoints(string $customerId, string $orderId, int $points, string $orderNumber): void
    {
        $this->ensureProfileExists($customerId);

        LoyaltyPointTransaction::create([
            'customer_id' => $customerId,
            'order_id' => $orderId,
            'type' => 'redeem',
            'points' => -$points,
            'description' => "Used {$points} points for order {$orderNumber}",
        ]);

        CustomerProfile::where('customer_id', $customerId)
            ->decrement('total_points', $points);
    }

    public function awardEarnedPoints(string $customerId, string $orderId, int $points, string $orderNumber): void
    {
        if ($points <= 0) {
            return;
        }

        $this->ensureProfileExists($customerId);

        LoyaltyPointTransaction::create([
            'customer_id' => $customerId,
            'order_id' => $orderId,
            'type' => 'earn',
            'points' => $points,
            'description' => "Earned {$points} points from order {$orderNumber}",
        ]);

        CustomerProfile::where('customer_id', $customerId)
            ->increment('total_points', $points);
    }

    public function awardForPaidOrder(object $order): int
    {
        if (! $order->customer_id) {
            return 0;
        }

        if ((int) $order->earned_points > 0) {
            return 0;
        }

        $alreadyAwarded = LoyaltyPointTransaction::where('order_id', $order->id)
            ->where('type', 'earn')
            ->exists();

        if ($alreadyAwarded) {
            return 0;
        }

        $settings = Settings::first();
        if (! $settings || ! $settings->points_enabled) {
            return 0;
        }

        if ($order->payment_status !== 'paid') {
            return 0;
        }

        if (in_array($order->status, ['cancelled', 'rejected'])) {
            return 0;
        }

        $earned = $this->calculateEarnedPoints($order, $settings);
        if ($earned <= 0) {
            return 0;
        }

        return DB::transaction(function () use ($order, $earned) {
            $profile = CustomerProfile::where('customer_id', $order->customer_id)
                ->lockForUpdate()
                ->first();

            if (! $profile) {
                $customer = Customer::where('id', $order->customer_id)->first();
                if (! $customer) {
                    return 0;
                }

                CustomerProfile::create([
                    'customer_id' => $order->customer_id,
                    'name' => $customer->name,
                    'phone' => $customer->phone,
                    'email' => $customer->email,
                    'total_points' => 0,
                    'membership_level' => 'basic',
                ]);

                $profile = CustomerProfile::where('customer_id', $order->customer_id)
                    ->lockForUpdate()
                    ->first();
            }

            LoyaltyPointTransaction::create([
                'customer_id' => $order->customer_id,
                'order_id' => $order->id,
                'type' => 'earn',
                'points' => $earned,
                'description' => "Earned from order {$order->order_number}",
            ]);

            CustomerProfile::where('customer_id', $order->customer_id)
                ->increment('total_points', $earned);

            Order::where('id', $order->id)->update([
                'earned_points' => $earned,
            ]);

            return $earned;
        });
    }

    public function calculateEarnedPoints(object $order, object $settings): int
    {
        if ($settings->earn_rate_amount <= 0) {
            return 0;
        }

        return (int) floor((float) $order->payable_amount / $settings->earn_rate_amount) * (int) $settings->earn_rate_points;
    }

    public function processOrderPayment(object $order): int
    {
        $earned = 0;

        if ($order->customer_type === 'member' && $order->customer_id) {
            if ((int) $order->redeemed_points > 0) {
                $this->deductRedeemedPoints($order->customer_id, $order->id, (int) $order->redeemed_points, $order->order_number);
            }

            $earned = $this->pointsEarnedFor((float) $order->payable_amount);
            if ($earned > 0) {
                $this->awardEarnedPoints($order->customer_id, $order->id, $earned, $order->order_number);
            }
        }

        return $earned;
    }
}
