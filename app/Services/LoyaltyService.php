<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LoyaltyService
{
    public function pointDiscountAmount(int $redeemPoints): float
    {
        $settings = DB::table('settings')->first();
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
        $settings = DB::table('settings')->first();
        if (! $settings || ! $settings->points_enabled || $settings->earn_rate_amount <= 0) {
            return 0;
        }
        return (int) floor($payableAmount / $settings->earn_rate_amount) * (int) $settings->earn_rate_points;
    }

    public function deductRedeemedPoints(string $customerId, string $orderId, int $points, string $orderNumber): void
    {
        DB::table('loyalty_point_transactions')->insert([
            'id' => (string) Str::uuid(),
            'customer_id' => $customerId,
            'order_id' => $orderId,
            'type' => 'redeem',
            'points' => -$points,
            'description' => "Used {$points} points for order {$orderNumber}",
            'created_at' => now(),
        ]);
        DB::table('customer_profiles')
            ->where('customer_id', $customerId)
            ->decrement('total_points', $points);
    }

    public function awardEarnedPoints(string $customerId, string $orderId, int $points, string $orderNumber): void
    {
        if ($points <= 0) {
            return;
        }
        DB::table('loyalty_point_transactions')->insert([
            'id' => (string) Str::uuid(),
            'customer_id' => $customerId,
            'order_id' => $orderId,
            'type' => 'earn',
            'points' => $points,
            'description' => "Earned {$points} points from order {$orderNumber}",
            'created_at' => now(),
        ]);
        DB::table('customer_profiles')
            ->where('customer_id', $customerId)
            ->increment('total_points', $points);
    }
}
