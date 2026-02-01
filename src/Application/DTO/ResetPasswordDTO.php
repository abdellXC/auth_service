<?php

declare(strict_types=1);

namespace App\Application\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final class ResetPasswordDTO
{
    #[Assert\NotBlank(message: 'Token is required')]
    private string $token;

    #[Assert\NotBlank(message: 'Password is required')]
    #[Assert\Length(
        min: 8,
        minMessage: 'Password must be at least {{ limit }} characters long'
    )]
    #[Assert\Regex(
        pattern: '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/',
        message: 'Password must contain at least one uppercase letter, one lowercase letter, one number and one special character'
    )]
    private string $password;

    #[Assert\NotBlank(message: 'Password confirmation is required')]
    #[Assert\IdenticalTo(
        propertyPath: 'password',
        message: 'Passwords do not match'
    )]
    private string $passwordConfirmation;

    public function __construct(string $token, string $password, string $passwordConfirmation)
    {
        $this->token = $token;
        $this->password = $password;
        $this->passwordConfirmation = $passwordConfirmation;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function getPasswordConfirmation(): string
    {
        return $this->passwordConfirmation;
    }
}