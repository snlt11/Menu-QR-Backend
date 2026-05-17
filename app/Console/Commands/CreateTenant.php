<?php

namespace App\Console\Commands;

use App\Actions\CreateTenantAction;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('tenant:create {name : Shop display name} {owner_email : Email of the central user that owns the shop} {--slug= : Override the auto-generated slug}')]
#[Description('Create a new tenant shop with its own database, default profile, settings, and owner shop_user.')]
class CreateTenant extends Command
{
    public function handle(CreateTenantAction $action): int
    {
        $name = $this->argument('name');
        $email = $this->argument('owner_email');
        $slug = $this->option('slug');

        $owner = User::where('email', $email)->first();
        if (! $owner) {
            $this->error("No central user found with email [{$email}].");
            return self::FAILURE;
        }

        $tenant = $action->execute($owner, $name, $slug);

        $this->info("Tenant created.");
        $this->line("  id:            {$tenant->id}");
        $this->line("  slug:          {$tenant->slug}");
        $this->line("  database:      {$tenant->database_name}");
        $this->line("  owner_user_id: {$tenant->owner_user_id}");

        return self::SUCCESS;
    }
}
