<?php

namespace App\Providers;

use App\Models\Challenge;
use App\Models\ChallengeSubmission;
use App\Models\User;
use App\Policies\ChallengePolicy;
use App\Policies\ChallengeSubmissionPolicy;
use App\Policies\UserPolicy;
use App\Policies\RolePolicy;
use Spatie\Permission\Models\Role;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Challenge::class => ChallengePolicy::class,
        ChallengeSubmission::class => ChallengeSubmissionPolicy::class,
        User::class => UserPolicy::class,
        Role::class => RolePolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();
    }
}
