<?php

namespace App\Controller;

use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

class ApiLoginController extends AbstractController
{
    #[Route('/api/login', name: 'api_login', methods: ['POST'])]
    public function login(Request $request, UserRepository $userRepository): JsonResponse
    {
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

            error_log("Attempting login for username: " . $username);
            
            $user = $userRepository->findOneBy(['username' => $username]);
            
            if (!$user) {
                error_log('User not found: ' . $username);
                return new JsonResponse([
                    'message' => 'User not found'
                ], Response::HTTP_NOT_FOUND);
            }

            error_log('User found, verifying password');
            
            if (!password_verify($password, $user->getPassword())) {
                error_log('Invalid password for user: ' . $username);
                return new JsonResponse([
                    'message' => 'Invalid password'
                ], Response::HTTP_UNAUTHORIZED);
            }

            error_log('Login successful for: ' . $username);
            
            // Return the exact format your React Native app expects
            return new JsonResponse([
                'message' => 'Login successful',
                'user' => [
                    'id' => $user->getId(),
                    'username' => $user->getUsername(),
                    'roles' => $user->getRoles()
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