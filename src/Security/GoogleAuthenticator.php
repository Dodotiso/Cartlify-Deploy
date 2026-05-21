<?php

namespace App\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class GoogleAuthenticator extends OAuth2Authenticator
{
    private ClientRegistry $clientRegistry;
    private EntityManagerInterface $entityManager;
    private RouterInterface $router;

    public function __construct(
        ClientRegistry $clientRegistry,
        EntityManagerInterface $entityManager,
        RouterInterface $router
    ) {
        $this->clientRegistry = $clientRegistry;
        $this->entityManager = $entityManager;
        $this->router = $router;
    }

    public function supports(Request $request): ?bool
    {
        return $request->attributes->get('_route') === 'google_auth_callback';
    }

    public function authenticate(Request $request): Passport
    {
        $client = $this->clientRegistry->getClient('google');
        $accessToken = $this->fetchAccessToken($client);

        return new SelfValidatingPassport(
            new UserBadge($accessToken->getToken(), function() use ($accessToken, $client) {
                /** @var \League\OAuth2\Client\Provider\GoogleUser $googleUser */
                $googleUser = $client->fetchUserFromToken($accessToken);
                
                $email = $googleUser->getEmail();
                
                // ✅ AUTOMATIC VERIFICATION: Check if email domain is allowed for staff
                $allowedDomains = [
                    'gmail.com',        // For testing - REMOVE IN PRODUCTION
                    'cartlify.com',     // Add your company domain
                ];
                
                $emailDomain = substr(strrchr($email, "@"), 1);
                
                if (!in_array($emailDomain, $allowedDomains)) {
                    throw new AuthenticationException(
                        'Only staff email addresses are allowed. Your email domain: ' . $emailDomain
                    );
                }

                // Check if user already exists by email
                $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
                
                if (!$user) {
                    // ✅ AUTO-REGISTER new staff member
                    $user = new User();
                    $user->setEmail($email);
                    $user->setUsername($googleUser->getName() ?? explode('@', $email)[0]);
                    $user->setCreatedAt(new \DateTimeImmutable());
                    $user->setGoogleId($googleUser->getId());
                    
                    // ✅ FIX: Set empty password for Google OAuth users
                    // Make sure your database allows NULL passwords
                    $user->setPassword(''); // Or null if your entity allows null
                    
                    // ✅ AUTO-ASSIGN STAFF ROLE
                    $user->setRoles(['ROLE_STAFF']);
                    
                    $this->entityManager->persist($user);
                } else {
                    // Update existing user's Google ID if not set
                    if (!$user->getGoogleId()) {
                        $user->setGoogleId($googleUser->getId());
                    }
                }
                
                // ✅ Update last active time for session persistence tracking
                $user->setLastActive(new \DateTime());
                $this->entityManager->flush();
                
                return $user;
            })
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // ✅ SUCCESSFUL USER SESSION PERSISTENCE
        $user = $token->getUser();
        $roles = $user->getRoles();
        
        if (in_array('ROLE_ADMIN', $roles) || in_array('ROLE_STAFF', $roles)) {
            return new RedirectResponse($this->router->generate('app_dashboard'));
        }
        
        return new RedirectResponse($this->router->generate('app_order_new'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $request->getSession()->set('oauth_error', $exception->getMessage());
        return new RedirectResponse($this->router->generate('app_login'));
    }

    public function start(Request $request, AuthenticationException $authException = null): Response
    {
        return new RedirectResponse($this->router->generate('app_login'));
    }
}