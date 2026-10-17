<?php

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Enums\ReviewLevel;
use App\Exceptions\InvalidWorkflowTransitionException;
use App\Models\Application;
use App\Models\User;
use App\Models\WorkflowHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ApplicationWorkflowService
{
    public function submit(User $user, Application $application, ?string $note = null): Application
    {
        return $this->transition($user, $application, 'submit', ApplicationStatus::SUBMITTED, [
            [ApplicationStatus::DRAFT, ReviewLevel::VILLAGE],
            [ApplicationStatus::REVISION_REQUIRED, ReviewLevel::VILLAGE],
        ], ReviewLevel::DISTRICT, $note);
    }

    public function requestRevision(User $user, Application $application, string $note): Application
    {
        $this->requireNote($note, 'request_revision');

        return $this->transition($user, $application, 'request_revision', ApplicationStatus::REVISION_REQUIRED, [
            [ApplicationStatus::SUBMITTED, ReviewLevel::DISTRICT],
            [ApplicationStatus::VALIDATED, ReviewLevel::REGENCY],
        ], ReviewLevel::VILLAGE, $note);
    }

    public function validate(User $user, Application $application, ?string $note = null): Application
    {
        return $this->transition($user, $application, 'validate', ApplicationStatus::VALIDATED, [
            [ApplicationStatus::SUBMITTED, ReviewLevel::DISTRICT],
        ], ReviewLevel::REGENCY, $note);
    }

    public function verify(User $user, Application $application, ?string $note = null): Application
    {
        return $this->transition($user, $application, 'verify', ApplicationStatus::VERIFIED, [
            [ApplicationStatus::VALIDATED, ReviewLevel::REGENCY],
        ], null, $note);
    }

    public function reject(User $user, Application $application, string $note): Application
    {
        $this->requireNote($note, 'reject');

        return $this->transition($user, $application, 'reject', ApplicationStatus::REJECTED, [
            [ApplicationStatus::SUBMITTED, ReviewLevel::DISTRICT],
            [ApplicationStatus::VALIDATED, ReviewLevel::REGENCY],
        ], null, $note);
    }

    private function transition(
        User $user,
        Application $application,
        string $action,
        ApplicationStatus $toStatus,
        array $fromTransitions,
        ?ReviewLevel $toReviewLevel,
        ?string $note,
    ): Application {
        return DB::transaction(function () use ($user, $application, $action, $toStatus, $fromTransitions, $toReviewLevel, $note) {
            $locked = Application::query()->whereKey($application->getKey())->lockForUpdate()->firstOrFail();
            $current = $locked->status instanceof ApplicationStatus
                ? $locked->status
                : ApplicationStatus::from((string) $locked->status);
            $currentReviewLevel = $locked->current_review_level instanceof ReviewLevel
                ? $locked->current_review_level
                : ReviewLevel::tryFrom((string) $locked->current_review_level);

            $allowedFromState = false;
            foreach ($fromTransitions as [$fromStatus, $fromReviewLevel]) {
                if ($current === $fromStatus && $currentReviewLevel === $fromReviewLevel) {
                    $allowedFromState = true;
                    break;
                }
            }

            $ability = $action === 'request_revision' ? 'requestRevision' : $action;
            if (! $allowedFromState || ! Gate::forUser($user)->allows($ability, $locked)) {
                throw new InvalidWorkflowTransitionException($locked->getKey(), $current->value, $action);
            }

            $locked->status = $toStatus;
            $locked->current_review_level = $toReviewLevel;
            if ($action === 'submit') {
                $locked->submitted_at = now();
            } elseif ($action === 'validate') {
                $locked->validated_at = now();
            } elseif ($action === 'verify') {
                $locked->verified_at = now();
            }
            $locked->save();

            WorkflowHistory::create([
                'application_id' => $locked->getKey(),
                'from_status' => $current,
                'to_status' => $toStatus,
                'action' => $action,
                'note' => $note,
                'acted_by' => $user->getKey(),
                'acted_at' => now(),
            ]);

            return $locked;
        });
    }

    private function requireNote(string $note, string $action): void
    {
        if (trim($note) === '') {
            throw ValidationException::withMessages([
                'note' => sprintf('A note is required for %s.', $action),
            ]);
        }
    }
}
