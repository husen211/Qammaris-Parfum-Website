<?php

namespace App\Actions\Blog;

use App\Models\BlogAutomationActor;
use App\Models\BlogPost;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use LogicException;
use Throwable;

final class IdempotentBlogWrite
{
    /** The completion callback runs in the shared write action's transaction, not an outer transaction. */
    public function handle(BlogAutomationActor $actor, string $scope, ?string $key, array $data, ?UploadedFile $image, Closure $write, Closure $read): array
    {
        if (! is_string($key) || ! preg_match('/\A[A-Za-z0-9_.:-]{8,128}\z/D', $key)) {
            throw ValidationException::withMessages(['Idempotency-Key' => 'Supply a unique 8–128 character key (letters, numbers, underscore, period, colon or hyphen).']);
        }
        $identity = ['actor_id' => $actor->id, 'scope' => $scope, 'key_hash' => hash('sha256', $key)];
        $fingerprint = ['data' => $this->canonical($data), 'image' => $image ? ['checksum' => hash_file('sha256', $image->getRealPath()), 'mime' => $image->getMimeType()] : null];
        $payloadHash = hash('sha256', json_encode($fingerprint, JSON_THROW_ON_ERROR));
        // Unique DB key arbitrates concurrent requests, including across multiple PHP workers.
        $claimed = DB::table('blog_automation_requests')->insertOrIgnore($identity + ['payload_hash' => $payloadHash, 'state' => 'pending', 'created_at' => now(), 'updated_at' => now()]) === 1;
        $record = DB::table('blog_automation_requests')->where($identity)->first();
        if (! $record) {
            throw new LogicException('Idempotency reservation was not persisted.');
        }
        abort_unless(hash_equals($record->payload_hash, $payloadHash), 409);
        if (! $claimed) {
            abort_unless($record->state === 'completed', 409, 'Request pending.', ['Retry-After' => '5']);

            return [$read((int) $record->resource_id), ['replayed' => true, 'original_revision' => (int) $record->revision]];
        }

        try {
            $completed = false;
            $result = $write(function (Model $resource) use ($record, &$completed): void {
                if (DB::transactionLevel() < 1) {
                    throw new LogicException('Idempotency completion must commit with the mutation.');
                }
                $revision = $resource instanceof BlogPost ? $resource->revision : BlogPost::findOrFail($resource->blog_post_id)->revision;
                $updated = DB::table('blog_automation_requests')->where('id', $record->id)->where('state', 'pending')
                    ->update(['state' => 'completed', 'resource_id' => $resource->id, 'revision' => $revision, 'updated_at' => now()]);
                if ($updated !== 1) {
                    throw new LogicException('Idempotency completion failed.');
                }
                $completed = true;
            });
            if (! $completed) {
                throw new LogicException('Write operation did not complete its reservation.');
            }

            return [$result, ['replayed' => false]];
        } catch (Throwable $error) {
            // Handled failures roll back mutation and media before releasing the reservation.
            // A hard process crash leaves pending; never assume it is safe to run it again.
            try {
                DB::table('blog_automation_requests')->where('id', $record->id)->where('state', 'pending')->delete();
            } catch (Throwable $cleanup) {
                Log::error('blog.idempotency_cleanup_failed', ['exception' => $cleanup::class]);
            }
            throw $error;
        }
    }

    private function canonical(array $value): array
    {
        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(fn ($item) => is_array($item) ? $this->canonical($item) : $item, $value);
    }
}
