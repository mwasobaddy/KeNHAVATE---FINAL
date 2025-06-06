<?php

namespace App\Providers;

use App\Models\Challenge;
use App\Models\ChallengeSubmission;
use App\Policies\ChallengePolicy;
use App\Policies\ChallengeSubmissionPolicy;
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
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();
    }
}
