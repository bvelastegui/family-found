<?php

namespace App\Actions\Fund;

use App\Models\Evidence;
use App\Models\User;
use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

/** @phpstan-type EvidenceData array{path: string, original_name: string, mime: string, size: int, sha256: string} */
class StoreFundEvidence
{
    /**
     * @param  Closure(array{path: string, original_name: string, mime: string, size: int, sha256: string}): int  $operation
     */
    public function using(UploadedFile $file, Closure $operation): int
    {
        Validator::make(['evidence' => $file], ['evidence' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'extensions:jpg,jpeg,png,pdf', 'max:10240']])->validate();
        $path = $file->store('evidences', 'fund');
        abort_if($path === false, 500, 'No se pudo guardar la evidencia.');
        $data = ['path' => $path, 'original_name' => mb_substr($file->getClientOriginalName(), 0, 255), 'mime' => (string) $file->getMimeType(), 'size' => (int) $file->getSize(), 'sha256' => (string) hash_file('sha256', $file->getPathname())];

        try {
            return $operation($data);
        } finally {
            if (! Evidence::query()->where('path', $path)->exists()) {
                Storage::disk('fund')->delete($path);
            }
        }
    }

    /** @param EvidenceData $data */
    public function persist(User $actor, User $owner, array $data): Evidence
    {
        return Evidence::query()->create([...$data, 'user_id' => $owner->id, 'uploaded_by_id' => $actor->id, 'created_at' => now()]);
    }
}
