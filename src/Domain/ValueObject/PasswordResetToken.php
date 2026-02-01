<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

use DateTimeImmutable;

final class PasswordResetToken
{
    private const TOKEN_LENGTH = 64;
    private const EXPIRATION_HOURS = 1;

    private string $token;
    private DateTimeImmutable $expiresAt;

    private function __construct(string $token, DateTimeImmutable $expiresAt)
    {
        $this->token = $token;
        $this->expiresAt = $expiresAt;
    }

    public static function generate(): self
    {
        $token = bin2hex(random_bytes(self::TOKEN_LENGTH / 2));
        $expiresAt = new DateTimeImmutable('+' . self::EXPIRATION_HOURS . ' hours');

        return new self($token, $expiresAt);
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getExpiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function isExpired(): bool
    {
        return $this->expiresAt < new DateTimeImmutable();
    }

    public function isValid(string $token): bool
    {
        return hash_equals($this->token, $token) && !$this->isExpired();
    }
}