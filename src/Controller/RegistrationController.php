<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Security\LoginAuthenticator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $userPasswordHasher,
        Security $security,
        EntityManagerInterface $entityManager
    ): Response {

        // If user already logged in redirect to shop
        if ($this->getUser()) {
            return $this->redirectToRoute('app_shop');
        }

        $user = new User();
        
        // Set the created date - THIS FIXES THE ERROR
        $user->setCreatedAt(new \DateTimeImmutable());

        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            // get plain password
            $plainPassword = $form->get('plainPassword')->getData();

            // hash password
            $user->setPassword(
                $userPasswordHasher->hashPassword($user, $plainPassword)
            );

            // ✅ SET DEFAULT ROLE
            $user->setRoles(['ROLE_USER']);

            // save user
            $entityManager->persist($user);
            $entityManager->flush();

            // auto login after registration
            $security->login($user, LoginAuthenticator::class, 'main');

            // redirect to shop after registration
            return $this->redirectToRoute('app_shop');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }
}