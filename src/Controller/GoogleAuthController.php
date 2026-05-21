<?php

namespace App\Controller;

use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class GoogleAuthController extends AbstractController
{
    #[Route('/connect/google', name: 'google_auth_connect')]
    public function connect(ClientRegistry $clientRegistry): RedirectResponse
    {
        // Redirect to Google for authentication
        return $clientRegistry
            ->getClient('google')
            ->redirect([
                'email', 
                'profile'
            ]);
    }

    #[Route('/connect/google/check', name: 'google_auth_callback')]
    public function callback(Request $request): void
    {
        // This method is handled by the GoogleAuthenticator
        // It will never be called directly
    }
}