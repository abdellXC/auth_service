<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Application\DTO\AuthenticationDTO;
use App\Application\DTO\TokenDTO;
use App\Domain\Repository\IUserRepository;
use App\Domain\ValueObject\Email;
use App\Presentation\Exception\Custom\AuthenticationException;
use Psr\Log\LoggerInterface;

final readonly class AuthenticationService
{
    public function __construct(
        private IUserRepository $userRepository,
        private TokenService $tokenService,
        private LoggerInterface $logger
    ) {
    }

    public function authenticate(AuthenticationDTO $dto): TokenDTO
    {
        $email = Email::fromString($dto->email);

        // Find user by email
        $user = $this->userRepository->findByEmail($email);

        if ($user === null) {
            $this->logger->warning('Authentication attempt with non-existent email', ['email' => $dto->email]);
            throw new AuthenticationException('Invalid credentials');
        }

        // Verify password
        if (!$user->verifyPassword($dto->password)) {
            $this->logger->warning('Authentication attempt with invalid password', [
                'userId' => $user->getId()->getValue(),
                'email' => $dto->email,
            ]);
            throw new AuthenticationException('Invalid credentials');
        }

        // Check if user is deleted
        if ($user->isDeleted()) {
            $this->logger->warning('Authentication attempt for deleted user', [
                'userId' => $user->getId()->getValue(),
                'email' => $dto->email,
            ]);
            throw new AuthenticationException('User account is disabled');
        }

        // Generate tokens
        $tokenDTO = $this->tokenService->createToken($user);

        $this->logger->info('User authenticated successfully', [
            'userId' => $user->getId()->getValue(),
            'email' => $dto->email,
        ]);

        return $tokenDTO;
    }
}