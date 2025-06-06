<?php

namespace App\Policies;

use App\Models\ChallengeSubmission;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ChallengeSubmissionPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any challenge submissions.
     */
    public function viewAny(User $user): bool
    {
        // Managers, admins, developers, SMEs, and challenge reviewers can view submissions
        return $user->hasAnyRole(['manager', 'administrator', 'developer', 'sme', 'challenge_reviewer']);
    }

    /**
     * Determine whether the user can view the challenge submission.
     */
    public function view(User $user, ChallengeSubmission $submission): bool
    {
        // Author can always view their own submission
        if ($submission->participant_id === $user->id) {
            return true;
        }

        // Team members can view team submissions
        if ($submission->team_members && in_array($user->id, $submission->team_members)) {
            return true;
        }

        // Challenge creator can view all submissions to their challenge
        if ($submission->challenge->creator_id === $user->id) {
            return true;
        }

        // Reviewers, managers, admins, developers can view submissions
        return $user->hasAnyRole(['manager', 'administrator', 'developer', 'sme', 'challenge_reviewer']);
    }

    /**
     * Determine whether the user can create challenge submissions.
     */
    public function create(User $user): bool
    {
        // All authenticated users can create submissions
        return true;
    }

    /**
     * Determine whether the user can update the challenge submission.
     */
    public function update(User $user, ChallengeSubmission $submission): bool
    {
        // Can only update submissions in draft or submitted status
        if (!in_array($submission->status, ['draft', 'submitted'])) {
            return false;
        }

        // Author can update their own submission
        if ($submission->participant_id === $user->id) {
            return true;
        }

        // Team members can update team submissions
        if ($submission->team_members && in_array($user->id, $submission->team_members)) {
            return true;
        }

        // Admins and developers can update any submission
        return $user->hasAnyRole(['administrator', 'developer']);
    }

    /**
     * Determine whether the user can delete the challenge submission.
     */
    public function delete(User $user, ChallengeSubmission $submission): bool
    {
        // Can only delete submissions in draft status
        if ($submission->status !== 'draft') {
            return false;
        }

        // Author can delete their own draft submission
        if ($submission->participant_id === $user->id) {
            return true;
        }

        // Team members can delete team draft submissions
        if ($submission->team_members && in_array($user->id, $submission->team_members)) {
            return true;
        }

        // Admins and developers can delete any draft submission
        return $user->hasAnyRole(['administrator', 'developer']);
    }

    /**
     * Determine whether the user can review the challenge submission.
     */
    public function review(User $user, ChallengeSubmission $submission): bool
    {
        // Cannot review submissions that are not in review status
        if (!in_array($submission->status, ['submitted', 'under_review'])) {
            return false;
        }

        // Cannot review own submissions (conflict of interest)
        if ($submission->participant_id === $user->id) {
            return false;
        }

        // Cannot review team submissions where user is a team member
        if ($submission->team_members && in_array($user->id, $submission->team_members)) {
            return false;
        }

        // Cannot review submissions for challenges they created
        if ($submission->challenge->creator_id === $user->id) {
            return false;
        }

        // Use the User model's canReview method for additional conflict checks
        if (!$user->canReview($submission)) {
            return false;
        }

        // Role-based review permissions
        switch ($submission->status) {
            case 'submitted':
            case 'under_review':
                return $user->hasAnyRole(['manager', 'sme', 'challenge_reviewer', 'administrator', 'developer']);
            case 'manager_review':
                return $user->hasRole('manager');
            case 'sme_review':
                return $user->hasAnyRole(['sme', 'challenge_reviewer']);
            default:
                return false;
        }
    }

    /**
     * Determine whether the user can update the status of the challenge submission.
     */
    public function updateStatus(User $user, ChallengeSubmission $submission): bool
    {
        // Authors can only update status from draft to submitted
        if ($submission->participant_id === $user->id) {
            return $submission->status === 'draft';
        }

        // Team members can update status from draft to submitted
        if ($submission->team_members && in_array($user->id, $submission->team_members)) {
            return $submission->status === 'draft';
        }

        // Cannot update status of own submissions beyond submission
        if ($submission->participant_id === $user->id || 
            ($submission->team_members && in_array($user->id, $submission->team_members))) {
            return false;
        }

        // Challenge creators can update status of submissions to their challenges
        if ($submission->challenge->creator_id === $user->id) {
            return true;
        }

        // Managers, admins, developers can update any submission status
        return $user->hasAnyRole(['manager', 'administrator', 'developer']);
    }

    /**
     * Determine whether the user can export challenge submissions.
     */
    public function exportSubmissions(User $user): bool
    {
        // Only managers, admins, and developers can export submissions
        return $user->hasAnyRole(['manager', 'administrator', 'developer']);
    }

    /**
     * Determine whether the user can view submissions for a specific challenge.
     */
    public function viewSubmissions(User $user, ChallengeSubmission $submission): bool
    {
        // Challenge creator can view all submissions to their challenge
        if ($submission->challenge->creator_id === $user->id) {
            return true;
        }

        // Managers, admins, developers, SMEs, and challenge reviewers can view submissions
        return $user->hasAnyRole(['manager', 'administrator', 'developer', 'sme', 'challenge_reviewer']);
    }

    /**
     * Determine whether the user can assign reviewers to the submission.
     */
    public function assignReviewer(User $user, ChallengeSubmission $submission): bool
    {
        // Only managers, admins, and developers can assign reviewers
        if (!$user->hasAnyRole(['manager', 'administrator', 'developer'])) {
            return false;
        }

        // Can only assign reviewers to submissions in appropriate status
        return in_array($submission->status, ['submitted', 'under_review', 'manager_review', 'sme_review']);
    }

    /**
     * Determine whether the user can download submission attachments.
     */
    public function downloadAttachments(User $user, ChallengeSubmission $submission): bool
    {
        // Author can download their own submission attachments
        if ($submission->participant_id === $user->id) {
            return true;
        }

        // Team members can download team submission attachments
        if ($submission->team_members && in_array($user->id, $submission->team_members)) {
            return true;
        }

        // Challenge creator can download attachments from submissions to their challenge
        if ($submission->challenge->creator_id === $user->id) {
            return true;
        }

        // Reviewers can download attachments for submissions they can review
        if ($this->review($user, $submission)) {
            return true;
        }

        // Managers, admins, developers can download any attachments
        return $user->hasAnyRole(['manager', 'administrator', 'developer']);
    }

    /**
     * Determine whether the user can collaborate on the submission.
     */
    public function collaborate(User $user, ChallengeSubmission $submission): bool
    {
        // Can only collaborate on submissions that allow collaboration
        if (!$submission->collaboration_enabled) {
            return false;
        }

        // Cannot collaborate on own submissions
        if ($submission->participant_id === $user->id) {
            return false;
        }

        // Cannot collaborate if already a team member
        if ($submission->team_members && in_array($user->id, $submission->team_members)) {
            return false;
        }

        // Can only collaborate on submissions in specific statuses
        if (!in_array($submission->status, ['submitted', 'under_review', 'collaboration'])) {
            return false;
        }

        // All users can request collaboration (subject to invitation approval)
        return true;
    }

    /**
     * Determine whether the user can enable/disable collaboration on the submission.
     */
    public function toggleCollaboration(User $user, ChallengeSubmission $submission): bool
    {
        // Author can toggle collaboration on their own submission
        if ($submission->participant_id === $user->id) {
            return true;
        }

        // Team members can toggle collaboration on team submissions
        if ($submission->team_members && in_array($user->id, $submission->team_members)) {
            return true;
        }

        // Managers, admins, developers can toggle collaboration on any submission
        return $user->hasAnyRole(['manager', 'administrator', 'developer']);
    }

    /**
     * Determine whether the user can view analytics for the submission.
     */
    public function viewAnalytics(User $user, ChallengeSubmission $submission): bool
    {
        // Author can view analytics for their own submission
        if ($submission->participant_id === $user->id) {
            return true;
        }

        // Team members can view analytics for team submissions
        if ($submission->team_members && in_array($user->id, $submission->team_members)) {
            return true;
        }

        // Challenge creator can view analytics for submissions to their challenge
        if ($submission->challenge->creator_id === $user->id) {
            return true;
        }

        // Managers, admins, developers can view analytics for any submission
        return $user->hasAnyRole(['manager', 'administrator', 'developer']);
    }

    /**
     * Determine whether the user can rate or score the submission.
     */
    public function rate(User $user, ChallengeSubmission $submission): bool
    {
        // Can only rate submissions in review status
        if (!in_array($submission->status, ['under_review', 'manager_review', 'sme_review'])) {
            return false;
        }

        // Use the review policy logic for rating permissions
        return $this->review($user, $submission);
    }

    /**
     * Determine whether the user can mark the submission as winner.
     */
    public function markAsWinner(User $user, ChallengeSubmission $submission): bool
    {
        // Only managers, admins, and developers can mark winners
        if (!$user->hasAnyRole(['manager', 'administrator', 'developer'])) {
            return false;
        }

        // Challenge must be in appropriate status for winner selection
        if (!in_array($submission->challenge->status, ['review_completed', 'judging', 'completed'])) {
            return false;
        }

        // Submission must be reviewed and approved
        return in_array($submission->status, ['approved', 'recommended']);
    }

    /**
     * Determine whether the user can view submission history/audit trail.
     */
    public function viewHistory(User $user, ChallengeSubmission $submission): bool
    {
        // Author can view history of their own submission
        if ($submission->participant_id === $user->id) {
            return true;
        }

        // Team members can view history of team submissions
        if ($submission->team_members && in_array($user->id, $submission->team_members)) {
            return true;
        }

        // Challenge creator can view history of submissions to their challenge
        if ($submission->challenge->creator_id === $user->id) {
            return true;
        }

        // Managers, admins, developers can view history of any submission
        return $user->hasAnyRole(['manager', 'administrator', 'developer']);
    }
}
