<?php

namespace EvoDevOps\Base\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role;

#[Signature('evodevops:user:promote {email : The email address of the user to promote}')]
#[Description('Grant the admin role to a user (uses the host\'s configured auth user model).')]
class PromoteUserCommand extends Command
{
    public function handle(): int
    {
        $email = (string) $this->argument('email');

        /** @var class-string<Model> $userClass */
        $userClass = config('auth.providers.users.model');

        /** @var Model|null $user */
        $user = $userClass::query()->where('email', $email)->first();

        if (! $user) {
            $this->error("No user found with email: {$email}");

            return self::FAILURE;
        }

        if (! method_exists($user, 'assignRole')) {
            $this->error('The user model does not use the Spatie HasRoles trait.');

            return self::FAILURE;
        }

        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user->assignRole($adminRole);

        $name = $user->name ?? $email;
        $this->info("Promoted {$name} ({$email}) to admin.");

        return self::SUCCESS;
    }
}
