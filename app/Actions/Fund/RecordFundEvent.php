<?php

namespace App\Actions\Fund;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class RecordFundEvent
{
    /** @param array<string, mixed> $data */
    public function handle(User $actor, string $event, string $subjectType, int $subjectId, array $data = []): void
    {
        DB::table('operation_events')->insert(['actor_id' => $actor->id, 'event' => $event, 'subject_type' => $subjectType, 'subject_id' => $subjectId, 'data' => json_encode($data, JSON_THROW_ON_ERROR), 'created_at' => now()]);
    }
}
