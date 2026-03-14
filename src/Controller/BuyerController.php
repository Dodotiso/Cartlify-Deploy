<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class BuyerController extends AbstractController
{
    #[Route('/buyer', name: 'app_buyer')]
    #[IsGranted('ROLE_USER')]
    public function index(): Response
    {
        return $this->render('buyer/index.html.twig', [
            'controller_name' => 'BuyerController',
        ]);
    }
}
