<?php

namespace App\Contracts;

use App\Models\User;

interface BulkSaveHandler
{
    public function authorizeRows(array $rows, User $manager): array;

    public function validateRows(array $rows, User $manager, bool $lock = false): array;

    public function save(array $rows, User $manager, array $uploads = [], ?callable $onProgress = null, ?callable $beforeCommit = null): array;
}
