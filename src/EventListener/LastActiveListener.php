<?php

namespace App\EventListener;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

#[AsEventListener(event: RequestEvent::class)]
class LastActiveListener
{
    private TokenStorageInterface $tokenStorage;
    private EntityManagerInterface $entityManager;

    public function __construct(TokenStorageInterface $tokenStorage, EntityManagerInterface $entityManager)
    {
        $this->tokenStorage = $tokenStorage;
        $this->entityManager = $entityManager;
    }

    public function __invoke(RequestEvent $event): void
    {
        // Only update for main requests (not sub-requests)
        if (!$event->isMainRequest()) {
            return;
        }

        // Skip for API routes to reduce database writes
        $request = $event->getRequest();
        if (str_starts_with($request->getPathInfo(), '/last-active')) {
            return;
        }

        // Get the token from storage
        $token = $this->tokenStorage->getToken();
        
        if (!$token) {
            return;
        }

        $user = $token->getUser();
        
        // Only update for authenticated users (User object, not string)
        if ($user instanceof User) {
            // Update last active time
            $user->setLastActive(new \DateTime());
            $this->entityManager->flush();
        }
    }
}