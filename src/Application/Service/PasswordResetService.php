<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Application\DTO\ForgotPasswordDTO;
use App\Application\DTO\ResetPasswordDTO;
use App\Domain\Repository\IUserRepository;
use App\Domain\ValueObject\Email;
use App\Domain\ValueObject\Password;
use App\Domain\ValueObject\PasswordResetToken;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;

class PasswordResetService
{
    private const MAX_ATTEMPTS = 3;
    private const RATE_LIMIT_WINDOW = 900; // 15 minutes in seconds

    public function __construct(
        private readonly IUserRepository $userRepository,
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
        private readonly RequestStack $requestStack,
        private readonly RateLimiterFactory $passwordResetLimiter,
        private readonly string $appUrl
    ) {
    }

    public function requestPasswordReset(ForgotPasswordDTO $dto): void
    {
        // Rate limiting
        $request = $this->requestStack->getCurrentRequest();
        $limiter = $this->passwordResetLimiter->create($request?->getClientIp() ?? 'unknown');
        
        if (!$limiter->consume(1)->isAccepted()) {
            $this->logger->warning('Password reset rate limit exceeded', [
                'ip' => $request?->getClientIp(),
                'email' => $dto->getEmail()
            ]);
            
            // Still return success to prevent email enumeration
            return;
        }

        $email = Email::fromString($dto->getEmail());
        $user = $this->userRepository->findByEmail($email);

        // Always return success to prevent email enumeration
        if ($user === null) {
            $this->logger->info('Password reset requested for non-existent email', [
                'email' => $dto->getEmail()
            ]);
            return;
        }

        // Generate reset token
        $resetToken = PasswordResetToken::generate();
        $user->setResetToken($resetToken);
        $this->userRepository->save($user);

        // Send email
        try {
            $resetUrl = sprintf(
                '%s/reset-password?token=%s',
                rtrim($this->appUrl, '/'),
                $resetToken->getToken()
            );

            $email = (new TemplatedEmail())
                ->from('noreply@example.com')
                ->to($user->getEmail()->getValue())
                ->subject('Password Reset Request')
                ->htmlTemplate('emails/password_reset.html.twig')
                ->context([
                    'resetUrl' => $resetUrl,
                    'userName' => $user->getName(),
                    'expiresAt' => $resetToken->getExpiresAt()
                ]);

            $this->mailer->send($email);

            $this->logger->info('Password reset email sent', [
                'email' => $user->getEmail()->getValue()
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to send password reset email', [
                'email' => $user->getEmail()->getValue(),
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Failed to send password reset email');
        }
    }

    public function resetPassword(ResetPasswordDTO $dto): void
    {
        $user = $this->userRepository->findByResetToken($dto->getToken());

        if ($user === null || !$user->hasValidResetToken($dto->getToken())) {
            throw new \InvalidArgumentException('Invalid or expired reset token');
        }

        // Update password
        $newPassword = Password::fromPlain($dto->getPassword());
        $user->changePassword($newPassword);
        $user->clearResetToken();
        $this->userRepository->save($user);

        $this->logger->info('Password successfully reset', [
            'userId' => $user->getId()->getValue()
        ]);
    }

    public function verifyResetToken(string $token): bool
    {
        $user = $this->userRepository->findByResetToken($token);

        if ($user === null) {
            return false;
        }

        return $user->hasValidResetToken($token);
    }
}