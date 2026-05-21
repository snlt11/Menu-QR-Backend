<?php

namespace App\Console\Commands;

use App\Actions\CreateTenantAction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

#[Signature('tenant:create {name : Shop display name} {owner_email : Owner email} {--slug= : Override the auto-generated slug} {--owner-name= : Owner display name} {--owner-phone= : Owner phone} {--password= : Owner password (random if omitted)}')]
#[Description('Provision a new shop tenant with its own database and an owner row inside the tenant.users table.')]
class CreateTenant extends Command
{
    public function handle(CreateTenantAction $action): int
    {
        $name = (string) $this->argument('name');
        $email = (string) $this->argument('owner_email');
        $slug = $this->option('slug') ?: Str::slug($name);
        $ownerName = $this->option('owner-name') ?: $name.' Owner';
        $ownerPhone = $this->option('owner-phone');
        $password = $this->option('password') ?: Str::random(10);

        $tenant = $action->execute($name, $slug, [
            'name' => $ownerName,
            'email' => $email,
            'phone' => $ownerPhone,
            'password' => $password,
        ]);

        $this->info('Tenant created.');
        $this->line("  id:           {$tenant->id}");
        $this->line("  slug:         {$tenant->slug}");
        $this->line("  database:     {$tenant->database_name}");
        $this->line("  owner email:  {$email}");
        $this->line("  owner pw:     {$password}");

        return self::SUCCESS;
    }
}
