<?php

namespace App\Controller;

use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;

class ApiLoginController extends AbstractController
{
    #[Route('/api/login', name: 'api_login', methods: ['POST'])]
    public function login(
        Request $request, 
        UserRepository $userRepository,
        JWTTokenManagerInterface $jwtManager
    ): JsonResponse {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (!$data) {
                return new JsonResponse([
                    'message' => 'Invalid request data'
                ], Response::HTTP_BAD_REQUEST);
            }

            $username = $data['username'] ?? null;
            $password = $data['password'] ?? null;

            if (!$username || !$password) {
                return new JsonResponse([
                    'message' => 'Username and password are required'
                ], Response::HTTP_BAD_REQUEST);
            }

            $user = $userRepository->findOneBy(['username' => $username]);
            
            if (!$user) {
                return new JsonResponse([
                    'message' => 'User not found'
                ], Response::HTTP_NOT_FOUND);
            }

            if (!password_verify($password, $user->getPassword())) {
                return new JsonResponse([
                    'message' => 'Invalid password'
                ], Response::HTTP_UNAUTHORIZED);
            }

            // Generate JWT token
            $token = $jwtManager->create($user);

            return new JsonResponse([
                'token' => $token,
                'user' => [
                    'id' => $user->getId(),
                    'username' => $user->getUsername(),
                    'email' => $user->getEmail(),
                    'roles' => $user->getRoles(),
                    'verified' => $user->isVerified(),
                    'profilePicture' => $user->getProfilePicture(),
                ]
            ], Response::HTTP_OK);
            
        } catch (\Exception $e) {
            error_log('Login error: ' . $e->getMessage());
            return new JsonResponse([
                'message' => 'Server error: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}