<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\ProfileType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;

#[Route('/profile')]
class ProfileController extends AbstractController
{
    #[Route('/', name: 'app_profile_index', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function index(): Response
    {
        $user = $this->getUser();
        
        // Check if user exists
        if (!$user) {
            $this->addFlash('error', 'You must be logged in to view your profile.');
            return $this->redirectToRoute('app_login');
        }
        
        return $this->render('profile/index.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/edit', name: 'app_profile_edit', methods: ['GET', 'POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function edit(
        Request $request, 
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        $user = $this->getUser();
        
        // Check if user exists
        if (!$user) {
            $this->addFlash('error', 'You must be logged in to edit your profile.');
            return $this->redirectToRoute('app_login');
        }
        
        $form = $this->createForm(ProfileType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Get the password from the form (RepeatedType returns the value in 'plainPassword')
            $plainPassword = $form->get('plainPassword')->getData();
            
            // Check if password is provided
            if ($plainPassword) {
                // Hash and set new password
                $hashedPassword = $passwordHasher->hashPassword($user, $plainPassword);
                $user->setPassword($hashedPassword);
                
                $entityManager->flush();
                
                $this->addFlash('success', '✅ Your password has been updated successfully!');
                return $this->redirectToRoute('app_profile_index');
            }
            
            // No password provided, just redirect
            $this->addFlash('info', 'ℹ️ No changes were made to your password.');
            return $this->redirectToRoute('app_profile_index');
        }
        
        // If form is submitted but invalid, get validation errors
        if ($form->isSubmitted() && !$form->isValid()) {
            $errors = $form->getErrors(true);
            foreach ($errors as $error) {
                $errorMessage = $error->getMessage();
                // Check for specific error messages
                if (str_contains($errorMessage, 'password fields must match')) {
                    $this->addFlash('error', '❌ Passwords do not match! Please make sure both passwords are the same.');
                } elseif (str_contains($errorMessage, 'should be at least')) {
                    $this->addFlash('error', '❌ Password must be at least 6 characters long!');
                } else {
                    $this->addFlash('error', '❌ ' . $errorMessage);
                }
            }
        }

        return $this->render('profile/edit.html.twig', [
            'form' => $form->createView(),
            'user' => $user,
        ]);
    }
}