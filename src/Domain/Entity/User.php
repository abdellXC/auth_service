<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use App\Domain\ValueObject\Email;
use App\Domain\ValueObject\Password;
use App\Domain\ValueObject\PasswordResetToken;
use App\Domain\ValueObject\UserId;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity]
#[ORM\Table(name: 'users')]
#[ORM\Index(columns: ['email'], name: 'idx_users_email')]
#[ORM\Index(columns: ['created_at'], name: 'idx_users_created_at')]
#[ORM\Index(columns: ['reset_token'], name: 'idx_users_reset_token')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\Column(type: 'string', length: 255, unique: true)]
    private string $email;

    #[ORM\Column(type: 'string', length: 255)]
    private string $password;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    #[ORM\Column(type: 'json')]
    private array $roles = [];

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $updatedAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $deletedAt = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $resetToken = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $resetTokenExpiresAt = null;

    public function __construct(
        UserId $id,
        Email $email,
        Password $password,
        string $name
    ) {
        $this->id = $id->getValue();
        $this->email = $email->getValue();
        $this->password = $password->getHash();
        $this->name = $name;
        $this->roles = ['ROLE_USER'];
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
    }

    public function getId(): UserId
    {
        return UserId::fromString($this->id);
    }

    public function getEmail(): Email
    {
        return Email::fromString($this->email);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function verifyPassword(string $plainPassword): bool
    {
        return Password::fromHash($this->password)->verify($plainPassword);
    }

    public function changePassword(Password $newPassword): void
    {
        $this->password = $newPassword->getHash();
        $this->updatedAt = new DateTimeImmutable();
    }

    public function addRole(string $role): void
    {
        if (!in_array($role, $this->roles, true)) {
            $this->roles[] = $role;
            $this->updatedAt = new DateTimeImmutable();
        }
    }

    public function removeRole(string $role): void
    {
        $this->roles = array_values(array_filter(
            $this->roles,
            static fn(string $r): bool => $r !== $role
        ));
        $this->updatedAt = new DateTimeImmutable();
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function eraseCredentials(): void
    {
        // Nothing to erase as we don't store plain passwords
    }

    public function softDelete(): void
    {
        $this->deletedAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    public function setResetToken(PasswordResetToken $token): void
    {
        $this->resetToken = $token->getToken();
        $this->resetTokenExpiresAt = $token->getExpiresAt();
        $this->updatedAt = new DateTimeImmutable();
    }

    public function getResetToken(): ?string
    {
        return $this->resetToken;
    }

    public function getResetTokenExpiresAt(): ?DateTimeImmutable
    {
        return $this->resetTokenExpiresAt;
    }

    public function hasValidResetToken(string $token): bool
    {
        if ($this->resetToken === null || $this->resetTokenExpiresAt === null) {
            return false;
        }

        return hash_equals($this->resetToken, $token) 
            && $this->resetTokenExpiresAt > new DateTimeImmutable();
    }

    public function clearResetToken(): void
    {
        $this->resetToken = null;
        $this->resetTokenExpiresAt = null;
        $this->updatedAt = new DateTimeImmutable();
    }
}