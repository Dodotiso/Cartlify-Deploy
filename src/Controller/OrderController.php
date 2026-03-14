<?php

namespace App\Controller;

use App\Entity\Order;
use App\Repository\OrderRepository;
use App\Repository\StockRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;

#[Route('/order')]
class OrderController extends AbstractController
{
    #[Route('/', name: 'app_order_index', methods: ['GET'])]
    #[IsGranted('ROLE_STAFF')]
    public function index(OrderRepository $orderRepository, Request $request): Response
    {
        $search = $request->query->get('search', '');
        
        if ($search) {
            $orders = $orderRepository->createQueryBuilder('o')
                ->leftJoin('o.stock', 's')
                ->leftJoin('s.product', 'p')
                ->where('p.name LIKE :search')
                ->orWhere('p.description LIKE :search')
                ->orWhere('o.id = :id')
                ->setParameter('search', '%' . $search . '%')
                ->setParameter('id', is_numeric($search) ? (int)$search : 0)
                ->orderBy('o.createdAt', 'DESC')
                ->getQuery()
                ->getResult();
        } else {
            $orders = $orderRepository->findAll();
        }
        
        return $this->render('order/index.html.twig', [
            'orders' => $orders,
            'search' => $search,
        ]);
    }

    #[Route('/new', name: 'app_order_new', methods: ['GET','POST'])]
    #[IsGranted('ROLE_STAFF')]
    public function new(
        Request $request,
        StockRepository $stockRepository,
        OrderRepository $orderRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $search = $request->query->get('search', '');
        
        if ($search) {
            $stocks = $stockRepository->createQueryBuilder('s')
                ->leftJoin('s.product', 'p')
                ->where('p.name LIKE :search')
                ->orWhere('p.description LIKE :search')
                ->setParameter('search', '%' . $search . '%')
                ->getQuery()
                ->getResult();
        } else {
            $stocks = $stockRepository->findAll();
        }

        if ($request->isMethod('POST')) {
            $stockId = $request->request->get('stock_id');
            $qty = (int) $request->request->get('quantity');

            $stock = $stockRepository->find($stockId);

            if (!$stock || $qty <= 0) {
                $this->addFlash('error', 'Invalid product or quantity.');
                return $this->redirectToRoute('app_order_new', ['search' => $search]);
            }

            // Ensure the customer cannot order more than available stock
            if ($qty > $stock->getStock()) {
                $this->addFlash('error', 'Not enough stock available. Maximum available: ' . $stock->getStock());
                return $this->redirectToRoute('app_order_new', ['search' => $search]);
            }

            // Check if user already has an active order for this stock
            $existingOrder = $orderRepository->findOneBy([
                'stock' => $stock,
                // Add user association if you have it, otherwise use current user
            ]);

            if ($existingOrder) {
                $this->addFlash('error', 'You already have an active order for this product. Cancel it first to place a new order.');
                return $this->redirectToRoute('app_order_new', ['search' => $search]);
            }

            // Deduct stock
            $stock->setStock($stock->getStock() - $qty);

            // Create order
            $order = new Order();
            $order->setStock($stock);
            $order->setQuantity($qty);
            $order->setUnitPrice($stock->getProduct()->getPrice());
            $order->setTotalAmount($qty * $stock->getProduct()->getPrice());

            try {
                $entityManager->persist($stock);
                $entityManager->persist($order);
                $entityManager->flush();

                $this->addFlash('success', 'Order placed successfully!');
                return $this->redirectToRoute('app_order_new', ['search' => $search]);
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error placing order: ' . $e->getMessage());
                return $this->redirectToRoute('app_order_new', ['search' => $search]);
            }
        }

        return $this->render('order/new.html.twig', [
            'stocks' => $stocks,
            'orders' => $orderRepository->findAll(),
            'search' => $search,
        ]);
    }

    #[Route('/{id}', name: 'app_order_delete', methods: ['POST'])]
    #[IsGranted('ROLE_STAFF')]
    public function delete(Request $request, Order $order, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$order->getId(), $request->request->get('_token'))) {
            try {
                // Return stock back when order is deleted
                $stock = $order->getStock();
                $stock->setStock($stock->getStock() + $order->getQuantity());

                $entityManager->remove($order);
                $entityManager->flush();

                $this->addFlash('success', 'Order canceled and stock restored.');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error canceling order: ' . $e->getMessage());
            }
        }

        return $this->redirectToRoute('app_order_new');
    }
}