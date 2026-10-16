<?php

declare(strict_types=1);

namespace App\Domains\LegacyMigration\DTOs;

use InvalidArgumentException;
use Ramsey\Uuid\Uuid;

final readonly class SourceSnapshot
{
    public function __construct(public string $sourceId, public array $branches, public array $tables)
    {
        if (! preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/D', $sourceId)) {
            throw new InvalidArgumentException('source_id must be a stable lowercase identifier.');
        }
        foreach ($branches as $legacyId => $uuid) {
            if (! preg_match('/^[1-9][0-9]*$/D', (string) $legacyId) || ! is_string($uuid) || ! Uuid::isValid($uuid)) {
                throw new InvalidArgumentException('Invalid explicit branch mapping.');
            }
        }
        foreach ($tables as $name => $rows) {
            if (! is_string($name) || ! preg_match('/^[a-z_]+$/D', $name) || ! is_array($rows) || ! array_is_list($rows)) {
                throw new InvalidArgumentException('Tables must contain row lists.');
            }
            foreach ($rows as $row) {
                if (! is_array($row) || (! is_string($row['id'] ?? null) && ! is_int($row['id'] ?? null))
                    || ! preg_match('/^[1-9][0-9]*$/D', (string) $row['id'])) {
                    throw new InvalidArgumentException('Each row requires a positive integer legacy id.');
                }
            }
        }
    }
}
