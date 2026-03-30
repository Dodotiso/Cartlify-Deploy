<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/last-active')]
class LastActiveController extends AbstractController
{
    #[Route('/update', name: 'update_last_active', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function updateLastActive(EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $this->getUser();
        
        if ($user instanceof User) {
            $user->setLastActive(new \DateTime());
            $entityManager->flush();
            
            return $this->json([
                'success' => true,
                'message' => 'Last active updated',
                'username' => $user->getUserIdentifier(),
                'lastActive' => $user->getLastActive()?->format('Y-m-d H:i:s')
            ]);
        }
        
        return $this->json(['success' => false, 'message' => 'User not authenticated'], 401);
    }
}