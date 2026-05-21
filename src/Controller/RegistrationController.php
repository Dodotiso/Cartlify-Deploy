<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Security\LoginAuthenticator;
use App\Service\EmailVerificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $userPasswordHasher,
        Security $security,
        EntityManagerInterface $entityManager,
        EmailVerificationService $emailVerificationService,
        UrlGeneratorInterface $urlGenerator
    ): Response {

        if ($this->getUser()) {
            return $this->redirectToRoute('app_order_new');
        }

        $user = new User();
        $user->setCreatedAt(new \DateTimeImmutable());

        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $plainPassword = $form->get('plainPassword')->getData();
            $user->setPassword(
                $userPasswordHasher->hashPassword($user, $plainPassword)
            );
            $user->setRoles(['ROLE_USER']);

            // ✅ STEP 1: Generate verification token
            $verificationToken = $emailVerificationService->generateVerificationToken();
            $user->setVerificationToken($verificationToken);
            $user->setIsVerified(false);

            // ✅ STEP 2: Save user to database FIRST (this saves the token)
            $entityManager->persist($user);
            $entityManager->flush();  // CRITICAL: This saves the token to DB

            // ✅ STEP 3: Generate verification URL
            $verificationUrl = $urlGenerator->generate(
                'app_verify_email',
                ['token' => $verificationToken],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            // ✅ STEP 4: Send verification email
            $emailVerificationService->sendVerificationEmail($user, $verificationUrl);

            $this->addFlash('success', 'Registration successful! Please check your email to verify your account.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }
}