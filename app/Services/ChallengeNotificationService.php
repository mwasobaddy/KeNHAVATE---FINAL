<?php

namespace App\Services;

use App\Models\Challenge;
use App\Models\ChallengeSubmission;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Collection;

class ChallengeNotificationService
{
    protected NotificationService $notificationService;
    protected AuditService $auditService;

    public function __construct(NotificationService $notificationService, AuditService $auditService)
    {
        $this->notificationService = $notificationService;
        $this->auditService = $auditService;
    }

    /**
     * Send challenge deadline reminder notifications
     */
    public function sendDeadlineReminders(): void
    {
        // Get challenges with deadlines in 24 hours
        $urgentChallenges = Challenge::where('status', 'active')
            ->whereBetween('deadline', [now(), now()->addHours(24)])
            ->get();

        // Get challenges with deadlines in 3 days
        $soonChallenges = Challenge::where('status', 'active')
            ->whereBetween('deadline', [now()->addDay(), now()->addDays(3)])
            ->get();

        // Send urgent reminders (24 hours)
        foreach ($urgentChallenges as $challenge) {
            $this->sendUrgentDeadlineReminder($challenge);
        }

        // Send regular reminders (3 days)
        foreach ($soonChallenges as $challenge) {
            $this->sendRegularDeadlineReminder($challenge);
        }
    }

    /**
     * Send urgent deadline reminder (24 hours)
     */
    protected function sendUrgentDeadlineReminder(Challenge $challenge): void
    {
        // Get all users who can participate but haven't submitted
        $eligibleUsers = User::whereHas('roles', function ($query) {
            $query->whereIn('name', ['user', 'manager', 'sme', 'challenge_reviewer']);
        })->whereDoesntHave('challengeSubmissions', function ($query) use ($challenge) {
            $query->where('challenge_id', $challenge->id);
        })->where('id', '!=', $challenge->author_id)->get();

        foreach ($eligibleUsers as $user) {
            $this->notificationService->sendNotification($user, 'deadline_urgent', [
                'title' => 'Urgent: Challenge Deadline Tomorrow',
                'message' => "The challenge '{$challenge->title}' deadline is tomorrow! Don't miss your chance to participate.",
                'related_id' => $challenge->id,
                'related_type' => 'Challenge',
                'action_url' => route('challenges.show', $challenge),
                'deadline' => $challenge->deadline,
                'time_remaining' => '24 hours',
            ]);
        }

        // Log the reminder event
        $this->auditService->log(
            'deadline_reminder_urgent',
            'Challenge',
            $challenge->id,
            null,
            ['recipients_count' => $eligibleUsers->count(), 'deadline' => $challenge->deadline]
        );
    }

    /**
     * Send regular deadline reminder (3 days)
     */
    protected function sendRegularDeadlineReminder(Challenge $challenge): void
    {
        // Get all users who can participate but haven't submitted
        $eligibleUsers = User::whereHas('roles', function ($query) {
            $query->whereIn('name', ['user', 'manager', 'sme', 'challenge_reviewer']);
        })->whereDoesntHave('challengeSubmissions', function ($query) use ($challenge) {
            $query->where('challenge_id', $challenge->id);
        })->where('id', '!=', $challenge->author_id)->get();

        foreach ($eligibleUsers as $user) {
            $this->notificationService->sendNotification($user, 'deadline_reminder', [
                'title' => 'Challenge Deadline Approaching',
                'message' => "The challenge '{$challenge->title}' deadline is in 3 days. Submit your innovative solution!",
                'related_id' => $challenge->id,
                'related_type' => 'Challenge',
                'action_url' => route('challenges.show', $challenge),
                'deadline' => $challenge->deadline,
                'time_remaining' => '3 days',
            ]);
        }

        // Log the reminder event
        $this->auditService->log(
            'deadline_reminder_regular',
            'Challenge',
            $challenge->id,
            null,
            ['recipients_count' => $eligibleUsers->count(), 'deadline' => $challenge->deadline]
        );
    }

    /**
     * Send review assignment notifications
     */
    public function sendReviewAssignmentNotification(ChallengeSubmission $submission, User $reviewer): void
    {
        $this->notificationService->sendNotification($reviewer, 'review_assigned', [
            'title' => 'New Challenge Submission to Review',
            'message' => "A new submission '{$submission->title}' for challenge '{$submission->challenge->title}' has been assigned to you for review.",
            'related_id' => $submission->id,
            'related_type' => 'ChallengeSubmission',
            'action_url' => route('challenge-reviews.show', $submission),
            'challenge_title' => $submission->challenge->title,
            'submission_title' => $submission->title,
            'submitter_name' => $submission->participant->name,
        ]);

        // Log the assignment
        $this->auditService->log(
            'review_assignment',
            'ChallengeSubmission',
            $submission->id,
            null,
            ['reviewer_id' => $reviewer->id, 'reviewer_name' => $reviewer->name]
        );
    }

    /**
     * Send winner announcement notifications
     */
    public function sendWinnerAnnouncements(Challenge $challenge, Collection $winners): void
    {
        // Notify winners
        foreach ($winners as $winner) {
            $this->notificationService->sendNotification($winner->participant, 'winner_announced', [
                'title' => 'Congratulations! You Won a Challenge',
                'message' => "Congratulations! Your submission '{$winner->title}' has been selected as a winner for challenge '{$challenge->title}'!",
                'related_id' => $winner->id,
                'related_type' => 'ChallengeSubmission',
                'action_url' => route('challenges.show', $challenge),
                'challenge_title' => $challenge->title,
                'submission_title' => $winner->title,
                'ranking' => $winner->ranking,
            ]);
        }

        // Notify all participants who didn't win
        $nonWinners = $challenge->submissions()
            ->whereNotIn('id', $winners->pluck('id'))
            ->with('participant')
            ->get();

        foreach ($nonWinners as $submission) {
            $this->notificationService->sendNotification($submission->participant, 'challenge_completed', [
                'title' => 'Challenge Results Announced',
                'message' => "Thank you for participating in '{$challenge->title}'. The winners have been announced. Keep innovating!",
                'related_id' => $submission->id,
                'related_type' => 'ChallengeSubmission',
                'action_url' => route('challenges.leaderboard', $challenge),
                'challenge_title' => $challenge->title,
                'submission_title' => $submission->title,
            ]);
        }

        // Notify all managers and admins about completion
        $this->notificationService->sendToRoles(['manager', 'administrator', 'developer'], 'challenge_completed', [
            'title' => 'Challenge Completed: Winners Announced',
            'message' => "Challenge '{$challenge->title}' has been completed. " . $winners->count() . " winners have been announced.",
            'related_id' => $challenge->id,
            'related_type' => 'Challenge',
            'action_url' => route('challenges.leaderboard', $challenge),
            'challenge_title' => $challenge->title,
            'winners_count' => $winners->count(),
        ]);

        // Log the announcement
        $this->auditService->log(
            'winners_announced',
            'Challenge',
            $challenge->id,
            null,
            [
                'winners_count' => $winners->count(),
                'total_participants' => $challenge->submissions()->count(),
                'winner_ids' => $winners->pluck('id')->toArray(),
            ]
        );
    }

    /**
     * Send collaboration invitation notifications
     */
    public function sendCollaborationInvitation(ChallengeSubmission $submission, User $invitee, User $inviter): void
    {
        $this->notificationService->sendNotification($invitee, 'collaboration_invitation', [
            'title' => 'Collaboration Invitation',
            'message' => "{$inviter->name} has invited you to collaborate on their submission '{$submission->title}' for challenge '{$submission->challenge->title}'.",
            'related_id' => $submission->id,
            'related_type' => 'ChallengeSubmission',
            'action_url' => route('challenges.submission.show', $submission),
            'challenge_title' => $submission->challenge->title,
            'submission_title' => $submission->title,
            'inviter_name' => $inviter->name,
        ]);

        // Log the invitation
        $this->auditService->log(
            'collaboration_invitation_sent',
            'ChallengeSubmission',
            $submission->id,
            null,
            ['invitee_id' => $invitee->id, 'inviter_id' => $inviter->id]
        );
    }

    /**
     * Send submission status change notifications
     */
    public function sendStatusChangeNotification(ChallengeSubmission $submission, string $oldStatus, string $newStatus, ?string $comments = null): void
    {
        $statusMessages = [
            'submitted' => 'Your submission has been received and is pending review.',
            'under_review' => 'Your submission is now under review by our expert panel.',
            'manager_review' => 'Your submission is being reviewed by the management team.',
            'sme_review' => 'Your submission is being evaluated by subject matter experts.',
            'approved' => 'Congratulations! Your submission has been approved.',
            'rejected' => 'Thank you for your submission. Unfortunately, it was not selected for the next stage.',
            'needs_revision' => 'Your submission requires some revisions. Please review the feedback and resubmit.',
            'winner' => 'Congratulations! Your submission has been selected as a winner!',
            'archived' => 'Your submission has been archived as the challenge has concluded.',
        ];

        $message = $statusMessages[$newStatus] ?? "Your submission status has been updated to: " . ucfirst(str_replace('_', ' ', $newStatus));
        
        if ($comments) {
            $message .= "\n\nReviewer comments: " . $comments;
        }

        $this->notificationService->sendNotification($submission->participant, 'status_change', [
            'title' => 'Submission Status Updated',
            'message' => $message,
            'related_id' => $submission->id,
            'related_type' => 'ChallengeSubmission',
            'action_url' => route('challenges.show', $submission->challenge),
            'challenge_title' => $submission->challenge->title,
            'submission_title' => $submission->title,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'comments' => $comments,
        ]);

        // If it's a team submission, notify all team members
        if ($submission->team_members) {
            $teamMemberIds = is_array($submission->team_members) ? $submission->team_members : json_decode($submission->team_members, true);
            $teamMembers = User::whereIn('id', array_filter($teamMemberIds))->get();

            foreach ($teamMembers as $member) {
                if ($member->id !== $submission->participant_id) { // Don't notify the main participant twice
                    $this->notificationService->sendNotification($member, 'team_status_change', [
                        'title' => 'Team Submission Status Updated',
                        'message' => "The team submission '{$submission->title}' status has been updated: " . $message,
                        'related_id' => $submission->id,
                        'related_type' => 'ChallengeSubmission',
                        'action_url' => route('challenges.show', $submission->challenge),
                        'challenge_title' => $submission->challenge->title,
                        'submission_title' => $submission->title,
                        'team_lead' => $submission->participant->name,
                        'old_status' => $oldStatus,
                        'new_status' => $newStatus,
                    ]);
                }
            }
        }

        // Log the status change
        $this->auditService->log(
            'status_change',
            'ChallengeSubmission',
            $submission->id,
            ['status' => $oldStatus],
            ['status' => $newStatus, 'comments' => $comments]
        );
    }

    /**
     * Send challenge phase transition notifications
     */
    public function sendPhaseTransitionNotification(Challenge $challenge, string $oldPhase, string $newPhase): void
    {
        $phaseMessages = [
            'draft' => 'is in draft mode and being prepared',
            'active' => 'is now open for submissions! Submit your innovative solutions.',
            'review' => 'submission period has ended and is now under review',
            'judging' => 'is in the judging phase. Winners will be announced soon',
            'completed' => 'has been completed. Thank you to all participants!',
            'cancelled' => 'has been cancelled',
        ];

        $message = "Challenge '{$challenge->title}' " . ($phaseMessages[$newPhase] ?? "phase has changed to: " . ucfirst($newPhase));

        // Notify all relevant users based on phase
        $roles = [];
        switch ($newPhase) {
            case 'active':
                $roles = ['user', 'manager', 'sme', 'challenge_reviewer'];
                break;
            case 'review':
            case 'judging':
                $roles = ['manager', 'sme', 'challenge_reviewer', 'administrator'];
                break;
            case 'completed':
            case 'cancelled':
                $roles = ['user', 'manager', 'sme', 'challenge_reviewer', 'administrator'];
                break;
            default:
                $roles = ['administrator', 'developer'];
        }

        if (!empty($roles)) {
            $this->notificationService->sendToRoles($roles, 'challenge_phase_change', [
                'title' => 'Challenge Phase Updated',
                'message' => $message,
                'related_id' => $challenge->id,
                'related_type' => 'Challenge',
                'action_url' => route('challenges.show', $challenge),
                'challenge_title' => $challenge->title,
                'old_phase' => $oldPhase,
                'new_phase' => $newPhase,
            ]);
        }

        // Log the phase transition
        $this->auditService->log(
            'challenge_phase_transition',
            'Challenge',
            $challenge->id,
            ['status' => $oldPhase],
            ['status' => $newPhase]
        );
    }

    /**
     * Send daily digest of challenge activities
     */
    public function sendDailyDigest(): void
    {
        // Get managers and admins for daily digest
        $recipients = User::whereHas('roles', function ($query) {
            $query->whereIn('name', ['manager', 'administrator', 'developer']);
        })->get();

        $yesterday = now()->subDay();

        // Collect statistics
        $stats = [
            'new_submissions' => ChallengeSubmission::whereDate('created_at', $yesterday)->count(),
            'completed_reviews' => \App\Models\ChallengeReview::whereDate('created_at', $yesterday)->count(),
            'active_challenges' => Challenge::where('status', 'active')->count(),
            'pending_reviews' => ChallengeSubmission::whereIn('status', ['under_review', 'manager_review', 'sme_review'])->count(),
        ];

        if ($stats['new_submissions'] > 0 || $stats['completed_reviews'] > 0) {
            foreach ($recipients as $recipient) {
                $this->notificationService->sendNotification($recipient, 'daily_digest', [
                    'title' => 'Daily Challenge Activity Digest',
                    'message' => "Daily summary: {$stats['new_submissions']} new submissions, {$stats['completed_reviews']} reviews completed, {$stats['pending_reviews']} pending reviews.",
                    'related_id' => null,
                    'related_type' => 'Challenge',
                    'action_url' => route('dashboard'),
                    'stats' => $stats,
                    'date' => $yesterday->format('Y-m-d'),
                ]);
            }
        }
    }
}
