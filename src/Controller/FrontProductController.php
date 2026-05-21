<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class FrontProductController extends AbstractController
{
    #[Route('/front/product', name: 'app_front_product')]
    public function index(): Response
    {
        return $this->render('front_product/index.html.twig', [
            'controller_name' => 'FrontProductController',
        ]);
    }
}
