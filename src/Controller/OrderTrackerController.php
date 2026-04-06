<?php

namespace App\Controller;

use App\Repository\OrderRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;

#[Route('/orders')]
class OrderTrackerController extends AbstractController
{
    #[Route('/tracker', name: 'app_order_tracker')]
    #[IsGranted('ROLE_USER')]
    public function tracker(OrderRepository $orderRepository): Response
    {
        $user = $this->getUser();
        $orders = $orderRepository->findBy(['customer' => $user], ['createdAt' => 'DESC']);
        
        return $this->render('order/tracker.html.twig', [
            'orders' => $orders,
        ]);
    }
}