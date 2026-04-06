<?php

namespace App\Controller;

use App\Entity\Order;
use App\Repository\OrderRepository;
use App\Repository\StockRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
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
            $orders = $orderRepository->findBy([], ['createdAt' => 'DESC']);
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

            if ($qty > $stock->getStock()) {
                $this->addFlash('error', 'Not enough stock available. Maximum available: ' . $stock->getStock());
                return $this->redirectToRoute('app_order_new', ['search' => $search]);
            }

            $existingOrder = $orderRepository->findOneBy([
                'stock' => $stock,
            ]);

            if ($existingOrder) {
                $this->addFlash('error', 'You already have an active order for this product.');
                return $this->redirectToRoute('app_order_new', ['search' => $search]);
            }

            $stock->setStock($stock->getStock() - $qty);

            $order = new Order();
            $order->setStock($stock);
            $order->setQuantity($qty);
            $order->setUnitPrice($stock->getProduct()->getPrice());
            $order->setTotalAmount($qty * $stock->getProduct()->getPrice());
            $order->setCustomer($this->getUser());

            try {
                $entityManager->persist($stock);
                $entityManager->persist($order);
                $entityManager->flush();

                $this->addFlash('success', 'Order placed successfully!');
                return $this->redirectToRoute('app_order_index');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error placing order: ' . $e->getMessage());
                return $this->redirectToRoute('app_order_new', ['search' => $search]);
            }
        }

        return $this->render('order/new.html.twig', [
            'stocks' => $stocks,
            'search' => $search,
        ]);
    }

    #[Route('/tracker', name: 'app_order_tracker', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function tracker(OrderRepository $orderRepository): Response
    {
        $user = $this->getUser();
        $orders = $orderRepository->findBy(['customer' => $user], ['createdAt' => 'DESC']);
        
        return $this->render('order/tracker.html.twig', [
            'orders' => $orders,
        ]);
    }

    #[Route('/show/{id}', name: 'app_order_show', methods: ['GET'])]
    #[IsGranted('ROLE_STAFF')]
    public function show(Order $order): Response
    {
        return $this->render('order/show.html.twig', [
            'order' => $order,
        ]);
    }

    #[Route('/update-status/{id}', name: 'app_order_update_status', methods: ['POST'])]
    #[IsGranted('ROLE_STAFF')]
    public function updateStatus(Order $order, Request $request, EntityManagerInterface $em): Response
    {
        $newStatus = $request->request->get('order_status');
        if (in_array($newStatus, ['pending', 'complete', 'cancelled', 'accepted', 'rejected', 'completed'])) {
            $oldStatus = $order->getOrderStatus();
            $order->setOrderStatus($newStatus);
            
            // Restore stock if order is being rejected or cancelled
            if (($newStatus === 'rejected' || $newStatus === 'cancelled') && $oldStatus !== 'rejected' && $oldStatus !== 'cancelled') {
                $stock = $order->getStock();
                $stock->setStock($stock->getStock() + $order->getQuantity());
                $em->persist($stock);
                $this->addFlash('warning', 'Stock restored to inventory');
            }
            
            $em->flush();
            $this->addFlash('success', 'Order status updated to: ' . ucfirst($newStatus));
        }
        return $this->redirectToRoute('app_order_index');
    }

    #[Route('/update-process/{id}', name: 'app_order_update_process', methods: ['POST'])]
    #[IsGranted('ROLE_STAFF')]
    public function updateProcess(Order $order, Request $request, EntityManagerInterface $em): Response
    {
        // Check if order is editable (not rejected or completed)
        if (in_array($order->getOrderStatus(), ['rejected', 'completed'])) {
            $this->addFlash('error', 'Cannot update process status for ' . $order->getOrderStatus() . ' orders.');
            return $this->redirectToRoute('app_order_index');
        }
        
        $newStatus = $request->request->get('process_status');
        $validStatuses = ['pending', 'processing', 'packaging', 'ready_for_pickup', 'shipped', 'delivered'];
        
        if (in_array($newStatus, $validStatuses)) {
            $order->setProcessStatus($newStatus);
            $em->flush();
            $this->addFlash('success', 'Process status updated to: ' . ucfirst(str_replace('_', ' ', $newStatus)));
        }
        return $this->redirectToRoute('app_order_index');
    }

    #[Route('/accept/{id}', name: 'app_order_accept', methods: ['POST'])]
    #[IsGranted('ROLE_STAFF')]
    public function acceptOrder(Order $order, EntityManagerInterface $em): Response
    {
        if ($order->getOrderStatus() === Order::ORDER_STATUS_PENDING) {
            $order->setOrderStatus('accepted');
            $em->flush();
            $this->addFlash('success', 'Order #' . $order->getId() . ' accepted!');
        } else {
            $this->addFlash('error', 'Order cannot be accepted in current status');
        }
        return $this->redirectToRoute('app_order_index');
    }

    #[Route('/reject/{id}', name: 'app_order_reject', methods: ['POST'])]
    #[IsGranted('ROLE_STAFF')]
    public function rejectOrder(Order $order, EntityManagerInterface $em): Response
    {
        if (!in_array($order->getOrderStatus(), ['rejected', 'completed'])) {
            // Restore stock
            $stock = $order->getStock();
            $stock->setStock($stock->getStock() + $order->getQuantity());
            $em->persist($stock);
            
            $order->setOrderStatus('rejected');
            $em->flush();
            $this->addFlash('warning', 'Order #' . $order->getId() . ' rejected. Stock restored.');
        } else {
            $this->addFlash('error', 'Order cannot be rejected in current status');
        }
        return $this->redirectToRoute('app_order_index');
    }

    #[Route('/complete/{id}', name: 'app_order_complete', methods: ['POST'])]
    #[IsGranted('ROLE_STAFF')]
    public function completeOrder(Order $order, EntityManagerInterface $em): Response
    {
        if ($order->getOrderStatus() === 'accepted') {
            $order->setOrderStatus('completed');
            $em->flush();
            $this->addFlash('success', 'Order #' . $order->getId() . ' completed!');
        } else {
            $this->addFlash('error', 'Order cannot be completed in current status');
        }
        return $this->redirectToRoute('app_order_index');
    }

    #[Route('/cancel/{id}', name: 'app_order_cancel_by_admin', methods: ['POST'])]
    #[IsGranted('ROLE_STAFF')]
    public function cancelOrder(Order $order, EntityManagerInterface $em): Response
    {
        if (!in_array($order->getOrderStatus(), ['cancelled', 'rejected', 'completed'])) {
            $stock = $order->getStock();
            $stock->setStock($stock->getStock() + $order->getQuantity());
            $em->persist($stock);
            
            $order->setOrderStatus('cancelled');
            $em->flush();
            $this->addFlash('warning', 'Order #' . $order->getId() . ' cancelled. Stock restored.');
        } else {
            $this->addFlash('error', 'Order cannot be cancelled in current status');
        }
        return $this->redirectToRoute('app_order_index');
    }

    #[Route('/delete/{id}', name: 'app_order_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(Request $request, Order $order, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete'.$order->getId(), $request->request->get('_token'))) {
            // Only restore stock if order is not already rejected or completed (stock already restored or not deducted)
            if (!in_array($order->getOrderStatus(), ['rejected', 'completed'])) {
                $stock = $order->getStock();
                $stock->setStock($stock->getStock() + $order->getQuantity());
                $em->persist($stock);
            }
            $em->remove($order);
            $em->flush();
            $this->addFlash('success', 'Order #' . $order->getId() . ' deleted successfully.');
        }
        return $this->redirectToRoute('app_order_index');
    }

    #[Route('/check-updates', name: 'app_order_check_updates', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function checkUpdates(OrderRepository $orderRepository, SessionInterface $session): Response
    {
        $user = $this->getUser();
        $orders = $orderRepository->findBy(['customer' => $user], ['updatedAt' => 'DESC']);
        
        $lastCheck = $session->get('last_order_check', time());
        $updates = [];
        
        foreach ($orders as $order) {
            $updatedAt = $order->getUpdatedAt() ?: $order->getCreatedAt();
            if ($updatedAt && $updatedAt->getTimestamp() > $lastCheck) {
                $updates[] = [
                    'orderId' => $order->getId(),
                    'newStatus' => $order->getProcessStatus(),
                    'orderStatus' => $order->getOrderStatus(),
                    'updatedAt' => $updatedAt->format('Y-m-d H:i:s')
                ];
            }
        }
        
        $session->set('last_order_check', time());
        
        return $this->json(['updates' => $updates]);
    }

    #[Route('/new-orders-count', name: 'app_new_orders_count', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getNewOrdersCount(OrderRepository $orderRepository, SessionInterface $session): Response
    {
        $user = $this->getUser();
        $lastCheck = $session->get('last_order_check', time());
        $count = $orderRepository->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->where('o.customer = :user')
            ->andWhere('o.updatedAt > :lastCheck OR o.createdAt > :lastCheck')
            ->setParameter('user', $user)
            ->setParameter('lastCheck', new \DateTime('@' . $lastCheck))
            ->getQuery()
            ->getSingleScalarResult();
        
        return $this->json(['count' => (int)$count]);
    }
    #[Route('/customer/cancel/{id}', name: 'app_order_customer_cancel', methods: ['POST'])]
#[IsGranted('ROLE_USER')]
public function customerCancelOrder(Order $order, Request $request, EntityManagerInterface $em): Response
{
    // Verify CSRF token
    if (!$this->isCsrfTokenValid('cancel' . $order->getId(), $request->request->get('_token'))) {
        $this->addFlash('error', 'Invalid request.');
        return $this->redirectToRoute('app_order_tracker');
    }
    
    // Check if order belongs to current user
    if ($order->getCustomer() !== $this->getUser()) {
        $this->addFlash('error', 'You do not have permission to cancel this order.');
        return $this->redirectToRoute('app_order_tracker');
    }
    
    // Only pending or accepted orders can be cancelled by customer
    if (!in_array($order->getOrderStatus(), ['pending', 'accepted'])) {
        $this->addFlash('error', 'This order cannot be cancelled at this stage.');
        return $this->redirectToRoute('app_order_tracker');
    }
    
    // Restore stock
    $stock = $order->getStock();
    $stock->setStock($stock->getStock() + $order->getQuantity());
    $em->persist($stock);
    
    $order->setOrderStatus('cancelled');
    $em->flush();
    
    $this->addFlash('success', 'Order #' . $order->getId() . ' has been cancelled successfully. Stock has been restored.');
    return $this->redirectToRoute('app_order_tracker');
}

#[Route('/customer/delete/{id}', name: 'app_order_customer_delete', methods: ['POST'])]
#[IsGranted('ROLE_USER')]
public function customerDeleteOrder(Order $order, Request $request, EntityManagerInterface $em): Response
{
    // Verify CSRF token
    if (!$this->isCsrfTokenValid('delete' . $order->getId(), $request->request->get('_token'))) {
        $this->addFlash('error', 'Invalid request.');
        return $this->redirectToRoute('app_order_tracker');
    }
    
    // Check if order belongs to current user
    if ($order->getCustomer() !== $this->getUser()) {
        $this->addFlash('error', 'You do not have permission to delete this order.');
        return $this->redirectToRoute('app_order_tracker');
    }
    
    // Only completed, rejected, or cancelled orders can be deleted by customer
    if (!in_array($order->getOrderStatus(), ['completed', 'rejected', 'cancelled'])) {
        $this->addFlash('error', 'This order cannot be deleted. Only completed, rejected, or cancelled orders can be deleted.');
        return $this->redirectToRoute('app_order_tracker');
    }
    
    $em->remove($order);
    $em->flush();
    
    $this->addFlash('success', 'Order #' . $order->getId() . ' has been deleted successfully.');
    return $this->redirectToRoute('app_order_tracker');
}
#[Route('/tracker/updates', name: 'app_order_tracker_updates', methods: ['GET'])]
#[IsGranted('ROLE_USER')]
public function getTrackerUpdates(OrderRepository $orderRepository, SessionInterface $session): Response
{
    $user = $this->getUser();
    $lastCheck = $session->get('last_order_tracker_check', time());
    
    $updates = $orderRepository->createQueryBuilder('o')
        ->where('o.customer = :user')
        ->andWhere('o.updatedAt > :lastCheck OR o.createdAt > :lastCheck')
        ->setParameter('user', $user)
        ->setParameter('lastCheck', new \DateTime('@' . $lastCheck))
        ->orderBy('o.updatedAt', 'DESC')
        ->getQuery()
        ->getResult();
    
    $session->set('last_order_tracker_check', time());
    
    return $this->json([
        'updates' => array_map(function($order) {
            return [
                'id' => $order->getId(),
                'orderStatus' => $order->getOrderStatus(),
                'processStatus' => $order->getProcessStatus(),
                'updatedAt' => $order->getUpdatedAt()->format('Y-m-d H:i:s')
            ];
        }, $updates)
    ]);
}
}