<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;

#[Route('/user')]
#[IsGranted('ROLE_ADMIN')]
class UserController extends AbstractController
{
    #[Route('/', name: 'app_user_index', methods: ['GET'])]
    public function index(UserRepository $userRepository, Request $request): Response
    {
        $search = $request->query->get('search', '');
        
        if ($search) {
            $users = $userRepository->createQueryBuilder('u')
                ->where('u.username LIKE :search')
                ->orWhere('u.roles LIKE :search')
                ->setParameter('search', '%' . $search . '%')
                ->orderBy('u.createdAt', 'DESC')
                ->getQuery()
                ->getResult();
        } else {
            $users = $userRepository->findAll();
        }
        
        return $this->render('user/index.html.twig', [
            'users' => $users,
            'search' => $search,
        ]);
    }

    #[Route('/new', name: 'app_user_new', methods: ['GET','POST'])]
    public function new(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $hasher, UserRepository $userRepository): Response
    {
        $user = new User();
        $user->setCreatedAt(new \DateTimeImmutable());
        $user->setLastActive(new \DateTime());

        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainUsername = $form->get('username')->getData();

            // Check if username already exists
            if ($userRepository->findOneBy(['username' => $plainUsername])) {
                $this->addFlash('error', '❌ This username is already taken. Please choose another.');
                return $this->render('user/new.html.twig', [
                    'form' => $form->createView(),
                ]);
            }

            $plainPassword = $form->get('password')->getData();
            if (!$plainPassword) {
                $this->addFlash('error', '❌ Password is required.');
                return $this->render('user/new.html.twig', [
                    'form' => $form->createView(),
                ]);
            }
            
            if (strlen($plainPassword) < 6) {
                $this->addFlash('error', '❌ Password must be at least 6 characters long.');
                return $this->render('user/new.html.twig', [
                    'form' => $form->createView(),
                ]);
            }
            
            $hashed = $hasher->hashPassword($user, $plainPassword);
            $user->setPassword($hashed);

            $em->persist($user);
            $em->flush();

            $this->addFlash('success', '✅ User "' . $user->getUsername() . '" created successfully!');
            return $this->redirectToRoute('app_user_index');
        }
        
        // Show ONE error message at a time
        if ($form->isSubmitted() && !$form->isValid()) {
            $errors = $form->getErrors(true);
            $errorMessage = null;
            
            foreach ($errors as $error) {
                $errorMessage = $error->getMessage();
                break;
            }
            
            if ($errorMessage) {
                $this->addFlash('error', '❌ ' . $errorMessage);
            } else {
                $this->addFlash('error', '❌ Please check the form. Username is required.');
            }
        }

        return $this->render('user/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_user_show', methods: ['GET'])]
    public function show(User $user): Response
    {
        return $this->render('user/show.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_user_edit', methods: ['GET','POST'])]
    public function edit(Request $request, User $user, EntityManagerInterface $em, UserPasswordHasherInterface $hasher): Response
    {
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('password')->getData();
            if ($plainPassword) {
                if (strlen($plainPassword) < 6) {
                    $this->addFlash('error', '❌ Password must be at least 6 characters long.');
                    return $this->render('user/edit.html.twig', [
                        'form' => $form->createView(),
                        'user' => $user,
                    ]);
                }
                $hashed = $hasher->hashPassword($user, $plainPassword);
                $user->setPassword($hashed);
            }

            $em->flush();
            $this->addFlash('success', '✅ User "' . $user->getUsername() . '" updated successfully!');
            return $this->redirectToRoute('app_user_index');
        }
        
        // Show ONE error message at a time
        if ($form->isSubmitted() && !$form->isValid()) {
            $errors = $form->getErrors(true);
            $errorMessage = null;
            
            foreach ($errors as $error) {
                $errorMessage = $error->getMessage();
                break;
            }
            
            if ($errorMessage) {
                $this->addFlash('error', '❌ ' . $errorMessage);
            }
        }

        return $this->render('user/edit.html.twig', [
            'form' => $form->createView(),
            'user' => $user,
        ]);
    }

    #[Route('/{id}', name: 'app_user_delete', methods: ['POST'])]
    public function delete(Request $request, User $user, EntityManagerInterface $em): Response
    {
        // Prevent deleting yourself
        if ($user->getId() === $this->getUser()->getId()) {
            $this->addFlash('error', '❌ You cannot delete your own account.');
            return $this->redirectToRoute('app_user_index');
        }
        
        if ($this->isCsrfTokenValid('delete'.$user->getId(), $request->request->get('_token'))) {
            $username = $user->getUsername();
            $em->remove($user);
            $em->flush();
            $this->addFlash('success', '🗑️ User "' . $username . '" deleted successfully!');
        }

        return $this->redirectToRoute('app_user_index');
    }
}