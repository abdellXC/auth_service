<?php

declare(strict_types=1);

namespace App\Presentation\Controller;

use App\Application\DTO\ForgotPasswordDTO;
use App\Application\DTO\ResetPasswordDTO;
use App\Application\Service\PasswordResetService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/password', name: 'api_password_')]
class PasswordResetController extends AbstractController
{
    public function __construct(
        private readonly PasswordResetService $passwordResetService,
        private readonly ValidatorInterface $validator
    ) {
    }

    #[Route('/forgot', name: 'forgot', methods: ['POST'])]
    public function forgotPassword(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['email'])) {
            return new JsonResponse([
                'error' => 'Email is required'
            ], Response::HTTP_BAD_REQUEST);
        }

        $dto = new ForgotPasswordDTO($data['email']);
        $errors = $this->validator->validate($dto);

        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }

            return new JsonResponse([
                'errors' => $errorMessages
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $this->passwordResetService->requestPasswordReset($dto);

            return new JsonResponse([
                'message' => 'If an account exists with this email, a password reset link has been sent.'
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'An error occurred while processing your request'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/reset', name: 'reset', methods: ['POST'])]
    public function resetPassword(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['token'], $data['password'], $data['passwordConfirmation'])) {
            return new JsonResponse([
                'error' => 'Token, password and password confirmation are required'
            ], Response::HTTP_BAD_REQUEST);
        }

        $dto = new ResetPasswordDTO(
            $data['token'],
            $data['password'],
            $data['passwordConfirmation']
        );

        $errors = $this->validator->validate($dto);

        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }

            return new JsonResponse([
                'errors' => $errorMessages
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $this->passwordResetService->resetPassword($dto);

            return new JsonResponse([
                'message' => 'Password has been successfully reset'
            ], Response::HTTP_OK);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse([
                'error' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'An error occurred while resetting your password'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/reset/verify/{token}', name: 'verify', methods: ['GET'])]
    public function verifyResetToken(string $token): JsonResponse
    {
        try {
            $isValid = $this->passwordResetService->verifyResetToken($token);

            return new JsonResponse([
                'valid' => $isValid
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'An error occurred while verifying the token'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/forgot-form', name: 'forgot_form', methods: ['GET'])]
    public function showForgotPasswordForm(): Response
    {
        return $this->render('password/forgot.html.twig');
    }

    #[Route('/reset-form', name: 'reset_form', methods: ['GET'])]
    public function showResetPasswordForm(Request $request): Response
    {
        $token = $request->query->get('token', '');
        
        return $this->render('password/reset.html.twig', [
            'token' => $token
        ]);
    }
}