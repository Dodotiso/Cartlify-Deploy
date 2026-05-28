<?php

namespace App\EventListener;

use App\Entity\ActivityLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;

class ActivityLogListener implements EventSubscriberInterface
{
    private EntityManagerInterface $entityManager;
    private TokenStorageInterface $tokenStorage;

    public function __construct(
        EntityManagerInterface $entityManager,
        TokenStorageInterface $tokenStorage
    ) {
        $this->entityManager = $entityManager;
        $this->tokenStorage = $tokenStorage;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onLoginSuccess',
            LogoutEvent::class => 'onLogout',
            KernelEvents::REQUEST => 'onKernelRequest',
        ];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        $this->logActivity($user, 'LOGIN', sprintf('User "%s" logged in', $user->getUserIdentifier()));
    }

    public function onLogout(LogoutEvent $event): void
    {
        $token = $event->getToken();
        if ($token && $token->getUser() && is_object($token->getUser())) {
            $user = $token->getUser();
            $this->logActivity($user, 'LOGOUT', sprintf('User "%s" logged out', $user->getUserIdentifier()));
        }
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $method = $request->getMethod();
        $path = $request->getPathInfo();
        $token = $this->tokenStorage->getToken();

        if (!$token || !$token->getUser() || !is_object($token->getUser())) {
            return;
        }

        $user = $token->getUser();

        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            if (str_contains($path, '/login') || str_contains($path, '/logout')) {
                return;
            }

            $action = match ($method) {
                'POST' => 'ADMIN_CREATES_RECORD',
                'PUT', 'PATCH' => 'ADMIN_UPDATES_RECORD',
                'DELETE' => 'ADMIN_DELETES_RECORD',
                default => 'ADMIN_ACTION',
            };

            $this->logActivity($user, $action, sprintf('%s %s', $method, $path));
        }
    }

    private function logActivity($user, string $action, string $target): void
    {
        $roles = $user->getRoles();
        $role = in_array('ROLE_ADMIN', $roles) ? 'ADMIN' : (in_array('ROLE_STAFF', $roles) ? 'STAFF' : 'USER');

        $log = new ActivityLog();
        $log->setUserId($user->getId());
        $log->setUsername($user->getUserIdentifier());
        $log->setRole($role);
        $log->setAction($action);
        $log->setTarget($target);
        $log->setCreatedAt(new \DateTime());

        $this->entityManager->persist($log);
        $this->entityManager->flush();
    }
}