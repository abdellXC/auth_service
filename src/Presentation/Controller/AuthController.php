<?php

declare(strict_types=1);

namespace App\Presentation\Controller;

use App\Application\DTO\AuthenticationDTO;
use App\Application\DTO\RegisterUserDTO;
use App\Application\Service\AuthenticationService;
use App\Application\Service\RateLimiter;
use App\Application\Service\RegistrationService;
use App\Application\Service\TokenService;
use App\Presentation\Exception\Custom\RateLimitException;
use OpenApi\Attributes as OA;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api', name: 'api_')]
#[OA\Tag(name: 'Authentication')]
final class AuthController extends AbstractController
{
    public function __construct(
        private readonly RegistrationService $registrationService,
        private readonly AuthenticationService $authenticationService,
        private readonly TokenService $tokenService,
        private readonly RateLimiter $rateLimiter,
        private readonly ValidatorInterface $validator,
        private readonly LoggerInterface $logger
    ) {
    }

    #[Route('/register', name: 'register', methods: ['POST'])]
    #[OA\Post(
        path: '/api/register',
        summary: 'Register a new user',
        description: 'Creates a new user account and returns user information with authentication tokens',
        tags: ['Authentication']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['email', 'password', 'name'],
            properties: [
                new OA\Property(property: 'email', type: 'string', format: 'email', example: 'user@example.com', description: 'User email address'),
                new OA\Property(property: 'password', type: 'string', format: 'password', example: 'SecurePass123!', description: 'Password (min 8 chars, must contain uppercase, lowercase, number, and special character)'),
                new OA\Property(property: 'name', type: 'string', example: 'John Doe', description: 'User full name')
            ]
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'User successfully registered',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'user',
                    properties: [
                        new OA\Property(property: 'id', type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000'),
                        new OA\Property(property: 'email', type: 'string', example: 'user@example.com'),
                        new OA\Property(property: 'name', type: 'string', example: 'John Doe'),
                        new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'string'), example: ['ROLE_USER'])
                    ],
                    type: 'object'
                ),
                new OA\Property(
                    property: 'token',
                    properties: [
                        new OA\Property(property: 'accessToken', type: 'string', example: 'eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9...'),
                        new OA\Property(property: 'refreshToken', type: 'string', example: 'def50200a1b2c3d4e5f6...'),
                        new OA\Property(property: 'expiresIn', type: 'integer', example: 900, description: 'Token expiration time in seconds'),
                        new OA\Property(property: 'tokenType', type: 'string', example: 'Bearer')
                    ],
                    type: 'object'
                )
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Validation error',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'errors', type: 'string', example: 'Email is already registered')
            ]
        )
    )]
    #[OA\Response(
        response: 429,
        description: 'Too many registration attempts',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'Rate limit exceeded'),
                new OA\Property(property: 'message', type: 'string', example: 'Too many registration attempts. Please try again later.'),
                new OA\Property(property: 'retryAfter', type: 'integer', example: 900, description: 'Seconds to wait before retry')
            ]
        )
    )]
    public function register(Request $request): JsonResponse
    {
        $clientIp = $request->getClientIp() ?? 'unknown';
        $rateLimitKey = 'register:' . $clientIp;

        if (!$this->rateLimiter->attempt($rateLimitKey)) {
            throw new RateLimitException(
                'Too many registration attempts. Please try again later.',
                $this->rateLimiter->availableIn($rateLimitKey)
            );
        }

        $data = json_decode($request->getContent(), true);

        $dto = new RegisterUserDTO(
            $data['email'] ?? '',
            $data['password'] ?? '',
            $data['name'] ?? ''
        );

        $errors = $this->validator->validate($dto);
        if (count($errors) > 0) {
            return $this->json(['errors' => (string) $errors], Response::HTTP_BAD_REQUEST);
        }

        $user = $this->registrationService->register($dto);
        $tokenDTO = $this->tokenService->createToken($user);

        return $this->json([
            'user' => [
                'id' => $user->getId()->getValue(),
                'email' => $user->getEmail()->getValue(),
                'name' => $user->getName(),
                'roles' => $user->getRoles(),
            ],
            'token' => $tokenDTO->toArray(),
        ], Response::HTTP_CREATED);
    }

    #[Route('/login', name: 'login', methods: ['POST'])]
    #[OA\Post(
        path: '/api/login',
        summary: 'Authenticate user',
        description: 'Authenticates a user with email and password, returns JWT tokens',
        tags: ['Authentication']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['email', 'password'],
            properties: [
                new OA\Property(property: 'email', type: 'string', format: 'email', example: 'user@example.com'),
                new OA\Property(property: 'password', type: 'string', format: 'password', example: 'SecurePass123!')
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Successfully authenticated',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'accessToken', type: 'string', example: 'eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9...'),
                new OA\Property(property: 'refreshToken', type: 'string', example: 'def50200a1b2c3d4e5f6...'),
                new OA\Property(property: 'expiresIn', type: 'integer', example: 900),
                new OA\Property(property: 'tokenType', type: 'string', example: 'Bearer')
            ]
        )
    )]
    #[OA\Response(
        response: 401,
        description: 'Invalid credentials',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'Authentication failed'),
                new OA\Property(property: 'message', type: 'string', example: 'Invalid credentials')
            ]
        )
    )]
    #[OA\Response(
        response: 429,
        description: 'Too many login attempts',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'Rate limit exceeded'),
                new OA\Property(property: 'retryAfter', type: 'integer', example: 900)
            ]
        )
    )]
    public function login(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? '';

        $rateLimitKey = 'login:' . $email;

        if (!$this->rateLimiter->attempt($rateLimitKey)) {
            throw new RateLimitException(
                'Too many login attempts. Please try again later.',
                $this->rateLimiter->availableIn($rateLimitKey)
            );
        }

        $dto = new AuthenticationDTO(
            $email,
            $data['password'] ?? ''
        );

        $errors = $this->validator->validate($dto);
        if (count($errors) > 0) {
            return $this->json(['errors' => (string) $errors], Response::HTTP_BAD_REQUEST);
        }

        $tokenDTO = $this->authenticationService->authenticate($dto);

        // Clear rate limit on successful login
        $this->rateLimiter->clear($rateLimitKey);

        return $this->json($tokenDTO->toArray(), Response::HTTP_OK);
    }

    #[Route('/token/refresh', name: 'token_refresh', methods: ['POST'])]
    #[OA\Post(
        path: '/api/token/refresh',
        summary: 'Refresh access token',
        description: 'Generates a new access token using a valid refresh token',
        tags: ['Token Management']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['refreshToken'],
            properties: [
                new OA\Property(property: 'refreshToken', type: 'string', example: 'def50200a1b2c3d4e5f6...', description: 'Valid refresh token')
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Token successfully refreshed',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'accessToken', type: 'string', example: 'eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9...'),
                new OA\Property(property: 'refreshToken', type: 'string', example: 'ghi78900xyz...'),
                new OA\Property(property: 'expiresIn', type: 'integer', example: 900),
                new OA\Property(property: 'tokenType', type: 'string', example: 'Bearer')
            ]
        )
    )]
    #[OA\Response(
        response: 401,
        description: 'Invalid or expired refresh token',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'Invalid or expired refresh token')
            ]
        )
    )]
    #[OA\Response(
        response: 429,
        description: 'Too many refresh attempts'
    )]
    public function refresh(Request $request): JsonResponse
    {
        $clientIp = $request->getClientIp() ?? 'unknown';
        $rateLimitKey = 'refresh:' . $clientIp;

        if (!$this->rateLimiter->attempt($rateLimitKey)) {
            throw new RateLimitException(
                'Too many token refresh attempts. Please try again later.',
                $this->rateLimiter->availableIn($rateLimitKey)
            );
        }

        $data = json_decode($request->getContent(), true);
        $refreshToken = $data['refreshToken'] ?? '';

        if (empty($refreshToken)) {
            return $this->json(['error' => 'Refresh token is required'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $tokenDTO = $this->tokenService->refreshToken($refreshToken);
            return $this->json($tokenDTO->toArray(), Response::HTTP_OK);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_UNAUTHORIZED);
        }
    }

    #[Route('/logout', name: 'logout', methods: ['POST'])]
    #[OA\Post(
        path: '/api/logout',
        summary: 'Logout user',
        description: 'Logs out the current user. In stateless JWT architecture, this is handled client-side by removing the token.',
        security: [['Bearer' => []]],
        tags: ['Authentication']
    )]
    #[OA\Response(
        response: 200,
        description: 'Successfully logged out',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'message', type: 'string', example: 'Logged out successfully')
            ]
        )
    )]
    public function logout(): JsonResponse
    {
        // In stateless JWT, logout is handled client-side by removing the token
        // Optionally, implement token blacklisting here
        return $this->json(['message' => 'Logged out successfully'], Response::HTTP_OK);
    }
}