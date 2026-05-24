<?php

namespace App\Actions;

use App\Models\Customer;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class ResolveCustomerAction
{
    public function execute(Request $request): ?Customer
    {
        $user = $request->user();
        if ($user instanceof Customer) {
            return $user;
        }

        $header = $request->bearerToken();
        if (! $header) {
            return null;
        }

        $token = PersonalAccessToken::findToken($header);
        if (! $token) {
            return null;
        }

        $owner = $token->tokenable;
        if ($owner instanceof Customer && $token->can('customer')) {
            return $owner;
        }

        return null;
    }

    public function resolveId(Request $request): ?string
    {
        return $this->execute($request)?->id;
    }
}
