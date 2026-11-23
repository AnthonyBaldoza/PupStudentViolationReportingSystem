<?php

namespace App\Policies;

use App\Models\User;
use App\Models\ViolationRecord;

class ViolationRecordPolicy
{
    /** Admins see everything; officers see only the reports they filed. */
    public function view(User $user, ViolationRecord $record): bool
    {
        return $user->is_active && ($user->isAdmin() || $record->reported_by === $user->id);
    }

    /** Only the filing officer, and only after the admin returned it for more information. */
    public function update(User $user, ViolationRecord $record): bool
    {
        return $user->is_active
            && $user->isOfficer()
            && $record->reported_by === $user->id
            && $record->status === ViolationRecord::RETURNED;
    }
}
