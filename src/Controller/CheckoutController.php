<?php

namespace App\Controller;

use App\Entity\Order;
use App\Repository\CartRepository;
use App\Service\OrderService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;

#[Route('/checkout')]
class CheckoutController extends AbstractController
{
    #[Route('/{orderId?}', name: 'app_checkout')]
    #[IsGranted('ROLE_USER')]
    public function checkout(
        ?int $orderId,
        Request $request,
        CartRepository $cartRepo,
        EntityManagerInterface $em,
        SessionInterface $session,
        OrderService $orderService
    ): Response {
        $user = $this->getUser();
        
        // If orderId provided (from Buy Now), use that order
        if ($orderId) {
            $order = $em->getRepository(Order::class)->find($orderId);
            if (!$order || $order->getCustomer() !== $user) {
                throw $this->createNotFoundException('Order not found');
            }
            $orders = [$order];
            $isFromCart = false;
        } else {
            // From cart - create orders for all cart items
            $cart = $cartRepo->findOneBy(['user' => $user]);
            if (!$cart || $cart->getCartItems()->isEmpty()) {
                $this->addFlash('error', 'Your cart is empty');
                return $this->redirectToRoute('app_order_new');
            }
            
            $orders = [];
            foreach ($cart->getCartItems() as $item) {
                $stock = $item->getStock();
                
                // Check stock availability
                if ($item->getQuantity() > $stock->getStock()) {
                    $this->addFlash('error', 'Not enough stock for ' . $stock->getProduct()->getName() . '. Only ' . $stock->getStock() . ' available.');
                    return $this->redirectToRoute('app_cart_view');
                }
                
                $order = new Order();
                $order->setStock($stock);
                $order->setQuantity($item->getQuantity());
                $order->setUnitPrice($stock->getProduct()->getPrice());
                $order->setTotalAmount($item->getQuantity() * $stock->getProduct()->getPrice());
                $order->setCustomer($user);
                $order->setOrderStatus(Order::ORDER_STATUS_PENDING);
                $order->setProcessStatus(Order::PROCESS_STATUS_PENDING);
                $em->persist($order);
                
                $orders[] = $order;
            }
            
            $isFromCart = true;
        }
        
        if ($request->isMethod('POST')) {
            $customerName = $request->request->get('customer_name');
            $customerEmail = $request->request->get('customer_email');
            $contactNumber = $request->request->get('customer_phone');
            $deliveryType = $request->request->get('delivery_type');
            $deliveryAddress = $request->request->get('delivery_address');
            $paymentMethod = $request->request->get('payment_method', 'cod');
            
            foreach ($orders as $order) {
                $order->setCustomerName($customerName);
                $order->setCustomerEmail($customerEmail);
                $order->setCustomerPhone($contactNumber);
                $order->setDeliveryType($deliveryType);
                $order->setPaymentMethod($paymentMethod);
                
                if ($deliveryType === 'shipping') {
                    if (empty($deliveryAddress)) {
                        $this->addFlash('error', 'Delivery address is required for shipping');
                        return $this->redirectToRoute('app_checkout');
                    }
                    $order->setDeliveryAddress($deliveryAddress);
                } else {
                    $order->setDeliveryAddress(null);
                }
                
                // Deduct stock for cart orders (not for buy now since stock already deducted)
                if (!$orderId) {
                    $stock = $order->getStock();
                    $stock->setStock($stock->getStock() - $order->getQuantity());
                    $em->persist($stock);
                }
                
                $em->persist($order);
            }
            
            // Clear cart if from cart
            if (!$orderId) {
                $cart = $cartRepo->findOneBy(['user' => $user]);
                if ($cart) {
                    foreach ($cart->getCartItems() as $item) {
                        $em->remove($item);
                    }
                    $em->remove($cart);
                }
                $session->set('cart_count', 0);
            }
            
            $em->flush();
            
            // Send email notifications for all orders
            foreach ($orders as $order) {
                try {
                    $orderService->sendOrderConfirmation($order);
                    $orderService->sendAdminOrderNotification($order);
                } catch (\Exception $e) {
                    // Log error but don't stop the process
                    error_log('Failed to send order email for Order #' . $order->getId() . ': ' . $e->getMessage());
                }
            }
            
            $orderCount = count($orders);
            $this->addFlash('success', '✅ ' . $orderCount . ' order(s) placed successfully! A confirmation email has been sent to ' . htmlspecialchars($customerEmail) . '. Continue shopping at the marketplace.');
            
            // Redirect to marketplace (order_new) instead of order index
            return $this->redirectToRoute('app_order_new');
        }
        
        $total = array_sum(array_map(fn($o) => $o->getTotalAmount(), $orders));
        
        return $this->render('checkout/index.html.twig', [
            'orders' => $orders,
            'total' => $total,
            'isFromCart' => $isFromCart,
        ]);
    }
}