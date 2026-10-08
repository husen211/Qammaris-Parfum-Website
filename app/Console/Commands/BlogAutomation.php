<?php

namespace App\Console\Commands;

use App\Models\BlogAutomationActor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class BlogAutomation extends Command
{
    protected $signature = 'blog:automation {action : create, issue, revoke or disable} {actor? : Machine actor ID} {--name= : Actor or token label} {--ability=* : Scoped token ability} {--token= : Owned token ID to revoke} {--activate-production : Explicit production activation, separately approved}';

    protected $description = 'Manage separate Journal machine actors and expiring draft-only tokens';

    public function handle(): int
    {
        $action = $this->argument('action');
        if (! in_array($action, ['create', 'issue', 'revoke', 'disable'], true)) {
            $this->error('Choose create, issue, revoke or disable.');

            return self::FAILURE;
        }
        if (in_array($action, ['create', 'issue'], true) && (! config('blog_automation.enabled') || ($this->laravel->environment('production') && ! $this->option('activate-production')))) {
            $this->error('Activation is disabled. Complete staging review and obtain separate production activation approval first.');

            return self::FAILURE;
        }
        $abilities = $this->option('ability') ?: BlogAutomationActor::ABILITIES;
        $validator = Validator::make(['name' => $this->option('name'), 'abilities' => $abilities], [
            'name' => [in_array($action, ['create', 'issue'], true) ? 'required' : 'nullable', 'string', 'max:100'],
            'abilities' => ['array', 'min:1', 'max:4'], 'abilities.*' => ['string', 'distinct', Rule::in(BlogAutomationActor::ABILITIES)],
        ]);
        if ($validator->fails()) {
            $this->error('Supply a name up to 100 characters and only supported abilities.');

            return self::FAILURE;
        }
        if ($action === 'create') {
            $actor = BlogAutomationActor::create(['name' => $this->option('name')]);
            $this->info('Machine actor created: '.$actor->id.'. No token issued.');

            return self::SUCCESS;
        }
        $actor = BlogAutomationActor::find($this->argument('actor'));
        if (! $actor) {
            $this->error('Machine actor not found.');

            return self::FAILURE;
        }
        if ($action === 'issue') {
            if (! $actor->is_active) {
                $this->error('Machine actor is disabled.');

                return self::FAILURE;
            }
            $token = $actor->createToken($this->option('name'), $abilities, now()->addDays(90));
            $this->warn('Secret shown once below. Store in the agent secret manager; do not commit, paste into logs or reuse feed credentials.');
            $this->line($token->plainTextToken);
        } elseif ($action === 'revoke') {
            $id = $this->option('token');
            if (! is_string($id) || ! ctype_digit($id) || $actor->tokens()->whereKey($id)->delete() !== 1) {
                $this->error('Select an existing token ID belonging to this actor.');

                return self::FAILURE;
            }
            $this->info('Token revoked.');
        } else {
            $actor->update(['is_active' => false]);
            $this->info('Machine actor disabled. Existing tokens no longer grant API access; attribution retained.');
        }

        return self::SUCCESS;
    }
}
