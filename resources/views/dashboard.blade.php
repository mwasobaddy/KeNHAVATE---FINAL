<?php

// Redirect logic for main dashboard - users should go to role-specific dashboards
$user = auth()->user();
$userRole = $user->roles->first()?->name ?? 'user';

$redirectRoute = match($userRole) {
    'developer', 'administrator' => route('dashboard.admin'),
    'board_member' => route('dashboard.board-member'),
    'manager' => route('dashboard.manager'),
    'sme' => route('dashboard.sme'),
    'challenge_reviewer' => route('dashboard.challenge-reviewer'),
    'idea_reviewer' => route('dashboard.idea-reviewer'),
    default => route('dashboard.user'),
};

// Immediate redirect without rendering
return redirect($redirectRoute);

?>


