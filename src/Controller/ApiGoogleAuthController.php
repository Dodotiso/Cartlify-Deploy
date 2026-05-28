<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Google_Client;

class ApiGoogleAuthController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private UserRepository $userRepository;
    private LoggerInterface $logger;

    public function __construct(
        EntityManagerInterface $entityManager, 
        UserRepository $userRepository,
        LoggerInterface $logger
    ) {
        $this->entityManager = $entityManager;
        $this->userRepository = $userRepository;
        $this->logger = $logger;
    }

    #[Route('/api/auth/google', name: 'api_google_auth', methods: ['POST'])]
    public function googleAuth(Request $request): JsonResponse
    {
        try {
            $content = $request->getContent();
            
            if (empty($content)) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Request body is empty'
                ], Response::HTTP_BAD_REQUEST);
            }

            $data = json_decode($content, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Invalid JSON format'
                ], Response::HTTP_BAD_REQUEST);
            }

            $idToken = $data['idToken'] ?? null;

            if (!$idToken) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Google ID Token is required'
                ], Response::HTTP_BAD_REQUEST);
            }

            $googleClientId = $_ENV['GOOGLE_CLIENT_ID'] ?? null;
            
            if (!$googleClientId) {
                $this->logger->critical('GOOGLE_CLIENT_ID not configured');
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Server configuration error'
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            // Verify the Google ID Token
            try {
                $client = new Google_Client(['client_id' => $googleClientId]);
                $payload = $client->verifyIdToken($idToken);
            } catch (\Exception $e) {
                $this->logger->error('Token verification failed: ' . $e->getMessage());
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Failed to verify Google token'
                ], Response::HTTP_UNAUTHORIZED);
            }

            if (!$payload) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Invalid Google ID Token'
                ], Response::HTTP_UNAUTHORIZED);
            }

            $email = $payload['email'] ?? null;
            $googleId = $payload['sub'] ?? null;
            $name = $payload['name'] ?? ($email ? explode('@', $email)[0] : 'User');

            if (!$email || !$googleId) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Invalid token payload'
                ], Response::HTTP_BAD_REQUEST);
            }

            // Check allowed email domains
            $allowedDomains = ['gmail.com', 'cartlify.com'];
            $emailDomain = substr(strrchr($email, "@"), 1);

            if (!in_array($emailDomain, $allowedDomains)) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Only staff email addresses are allowed. Your domain: ' . $emailDomain
                ], Response::HTTP_FORBIDDEN);
            }

            // Find or create user
            $user = $this->userRepository->findOneBy(['email' => $email]);

            if (!$user) {
                $user = new User();
                $user->setEmail($email);
                $user->setUsername($name);
                $user->setGoogleId($googleId);
                $user->setPassword('');
                $user->setRoles(['ROLE_STAFF']);
                $user->setCreatedAt(new \DateTimeImmutable());
                
                // DO NOT save Google picture URL - leave profilePicture as NULL
                // The avatar will show the first letter instead
                
                $this->entityManager->persist($user);
            } else {
                if (!$user->getGoogleId()) {
                    $user->setGoogleId($googleId);
                }
            }

            $user->setLastActive(new \DateTime());
            $this->entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Google login successful',
                'user' => [
                    'id' => $user->getId(),
                    'username' => $user->getUsername(),
                    'email' => $user->getEmail(),
                    'roles' => $user->getRoles(),
                    'profilePicture' => $user->getProfilePicture(), // Will be null, so app shows letter
                ]
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            $this->logger->critical('Google Auth Error: ' . $e->getMessage());
            
            return new JsonResponse([
                'success' => false,
                'message' => 'An unexpected error occurred'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}