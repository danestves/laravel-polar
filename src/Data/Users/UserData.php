<?php

namespace Danestves\LaravelPolar\Data\Users;

use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;

class UserData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $email,
        #[MapName('public_name')]
        public readonly string $publicName,
        #[MapName('avatar_url')]
        public readonly ?string $avatarUrl,
        #[MapName('github_username')]
        public readonly ?string $githubUsername,
    ) {}
}
