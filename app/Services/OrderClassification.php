<?php

namespace App\Services;

class OrderClassification
{
    public function __construct(
        public readonly bool $isUnpaid,
        public readonly bool $isPaid,
        public readonly bool $isWaitingApproval,
        public readonly bool $isRejected,
        public readonly bool $isCompleted,
        public readonly bool $isFinal,
        public readonly bool $isKitchenActive,
        public readonly bool $needsAttention,
    ) {}
}
