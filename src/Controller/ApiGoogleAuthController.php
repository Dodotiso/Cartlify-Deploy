<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ApiGoogleAuthController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private UserRepository $userRepository;

    public function __construct(EntityManagerInterface $entityManager, UserRepository $userRepository)
    {
        $this->entityManager = $entityManager;
        $this->userRepository = $userRepository;
    }

    #[Route('/api/auth/google', name: 'api_google_auth', methods: ['POST'])]
    public function googleAuth(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $idToken = $data['idToken'] ?? null;

            if (!$idToken) {
                return new JsonResponse([
                    'message' => 'Google ID Token is required'
                ], Response::HTTP_BAD_REQUEST);
            }

            // Verify the Google ID Token
            $client = new \Google_Client(['client_id' => $_ENV['GOOGLE_CLIENT_ID']]);
            $payload = $client->verifyIdToken($idToken);

            if (!$payload) {
                return new JsonResponse([
                    'message' => 'Invalid Google ID Token'
                ], Response::HTTP_UNAUTHORIZED);
            }

            $email = $payload['email'];
            $googleId = $payload['sub'];
            $name = $payload['name'] ?? explode('@', $email)[0];

            // Check allowed email domains
            $allowedDomains = ['gmail.com', 'cartlify.com'];
            $emailDomain = substr(strrchr($email, "@"), 1);

            if (!in_array($emailDomain, $allowedDomains)) {
                return new JsonResponse([
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
                
                $this->entityManager->persist($user);
            } else {
                if (!$user->getGoogleId()) {
                    $user->setGoogleId($googleId);
                }
            }

            $user->setLastActive(new \DateTime());
            $this->entityManager->flush();

            return new JsonResponse([
                'message' => 'Google login successful',
                'user' => [
                    'id' => $user->getId(),
                    'username' => $user->getUsername(),
                    'email' => $user->getEmail(),
                    'roles' => $user->getRoles(),
                    'profilePicture' => $user->getProfilePicture(),
                ]
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            return new JsonResponse([
                'message' => 'Server error: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}