<?php

namespace App\Controller;

use App\Entity\Cart;
use App\Entity\CartItem;
use App\Entity\Order;
use App\Repository\StockRepository;
use App\Repository\CartRepository;
use App\Service\PhoneNumberHelper;
use App\Service\OrderService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;

#[Route('/cart')]
class CartController extends AbstractController
{
    #[Route('/add/{stockId}', name: 'app_cart_add', methods: ['POST'])]
    public function add(
        int $stockId,
        Request $request,
        StockRepository $stockRepo,
        EntityManagerInterface $em,
        SessionInterface $session
    ): Response {
        $stock = $stockRepo->find($stockId);
        if (!$stock || $stock->getStock() <= 0) {
            $this->addFlash('error', 'Product not available');
            return $this->redirectToRoute('app_order_new');
        }

        $quantity = (int) $request->request->get('quantity', 1);
        if ($quantity > $stock->getStock()) {
            $this->addFlash('error', 'Not enough stock. Only ' . $stock->getStock() . ' available.');
            return $this->redirectToRoute('app_order_new');
        }

        $cart = $this->getOrCreateCart($em, $session);
        
        $cartItem = null;
        foreach ($cart->getCartItems() as $item) {
            if ($item->getStock() && $item->getStock()->getId() === $stockId) {
                $cartItem = $item;
                break;
            }
        }

        if ($cartItem) {
            $newQty = $cartItem->getQuantity() + $quantity;
            if ($newQty > $stock->getStock()) {
                $this->addFlash('error', 'Total quantity exceeds available stock');
                return $this->redirectToRoute('app_order_new');
            }
            $cartItem->setQuantity($newQty);
        } else {
            $cartItem = new CartItem();
            $cartItem->setCart($cart);
            $cartItem->setStock($stock);
            $cartItem->setQuantity($quantity);
            $em->persist($cartItem);
        }

        $cart->setUpdatedAt(new \DateTime());
        $em->flush();

        $session->set('cart_count', $cart->getTotalItems());

        $this->addFlash('success', $stock->getProduct()->getName() . ' added to cart!');
        return $this->redirectToRoute('app_cart_view');
    }

    #[Route('/save-checkout-info', name: 'app_save_checkout_info', methods: ['POST'])]
    public function saveCheckoutInfo(Request $request, SessionInterface $session): Response
    {
        $data = json_decode($request->getContent(), true);
        if ($data) {
            $rawPhone = $data['phone'] ?? null;
            $normalizedPhone = null;
            
            // Normalize phone number (already in E.164 from intl-tel-input)
            if ($rawPhone) {
                $normalizedPhone = PhoneNumberHelper::normalizeToE164($rawPhone);
                
                // Validate after normalization
                if (!PhoneNumberHelper::isValidE164($normalizedPhone)) {
                    return $this->json([
                        'success' => false, 
                        'message' => PhoneNumberHelper::getPhoneErrorMessage($rawPhone)
                    ], 400);
                }
            }
            
            // Validate email
            $email = $data['email'] ?? null;
            if ($email && !PhoneNumberHelper::isValidEmailDomain($email)) {
                return $this->json([
                    'success' => false, 
                    'message' => 'Email must be from Gmail, Yahoo, Outlook, or Hotmail.'
                ], 400);
            }
            
            // Validate name
            $name = $data['name'] ?? null;
            if ($name && strlen($name) < 2) {
                return $this->json([
                    'success' => false, 
                    'message' => 'Name must be at least 2 characters.'
                ], 400);
            }
            
            $session->set('checkout_customer_name', $data['name'] ?? null);
            $session->set('checkout_customer_email', $data['email'] ?? null);
            $session->set('checkout_customer_phone', $normalizedPhone);
            $session->set('checkout_delivery_method', $data['delivery'] ?? 'pickup');
            $session->set('checkout_delivery_address', $data['address'] ?? null);
            $session->set('checkout_payment_method', $data['payment'] ?? 'cod');
        }
        return $this->json(['success' => true]);
    }

    #[Route('/buy-now/{stockId}', name: 'app_buy_now', methods: ['POST'])]
    public function buyNow(
        int $stockId,
        Request $request,
        StockRepository $stockRepo,
        EntityManagerInterface $em,
        SessionInterface $session,
        OrderService $orderService
    ): Response {
        $stock = $stockRepo->find($stockId);
        $quantity = (int) $request->request->get('quantity', 1);

        if (!$stock || $quantity <= 0) {
            $this->addFlash('error', 'Invalid product');
            return $this->redirectToRoute('app_order_new');
        }

        if ($quantity > $stock->getStock()) {
            $this->addFlash('error', 'Not enough stock. Only ' . $stock->getStock() . ' available.');
            return $this->redirectToRoute('app_order_new');
        }

        // Get customer info from session
        $customerName = $session->get('checkout_customer_name');
        $customerEmail = $session->get('checkout_customer_email');
        $customerPhone = $session->get('checkout_customer_phone');
        $deliveryType = $session->get('checkout_delivery_method', 'pickup');
        $deliveryAddress = $session->get('checkout_delivery_address');
        $paymentMethod = $session->get('checkout_payment_method', 'cod');

        // Validate Name
        if (empty($customerName) || strlen($customerName) < 2) {
            $this->addFlash('error', 'Full name is required.');
            return $this->redirectToRoute('app_order_new');
        }

        // Validate Email
        if (empty($customerEmail)) {
            $this->addFlash('error', 'Email address is required.');
            return $this->redirectToRoute('app_order_new');
        }

        if (!filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
            $this->addFlash('error', 'Please enter a valid email address.');
            return $this->redirectToRoute('app_order_new');
        }

        if (!PhoneNumberHelper::isValidEmailDomain($customerEmail)) {
            $this->addFlash('error', 'Email must be from Gmail, Yahoo, Outlook, or Hotmail.');
            return $this->redirectToRoute('app_order_new');
        }

        // Validate Phone (supports all countries via E.164 format)
        if (empty($customerPhone)) {
            $this->addFlash('error', 'Contact number is required.');
            return $this->redirectToRoute('app_order_new');
        }

        if (!PhoneNumberHelper::isValidE164($customerPhone)) {
            $this->addFlash('error', 'Invalid contact number. Please enter a valid international phone number with country code (e.g., +639171234567).');
            return $this->redirectToRoute('app_order_new');
        }

        // Validate Delivery Address if shipping
        if ($deliveryType === 'shipping' && empty($deliveryAddress)) {
            $this->addFlash('error', 'Delivery address is required for shipping.');
            return $this->redirectToRoute('app_order_new');
        }

        // Deduct stock
        $stock->setStock($stock->getStock() - $quantity);
        
        // Create order
        $order = new Order();
        $order->setStock($stock);
        $order->setQuantity($quantity);
        $order->setUnitPrice($stock->getProduct()->getPrice());
        $order->setTotalAmount($quantity * $stock->getProduct()->getPrice());
        $order->setCustomer($this->getUser());
        $order->setOrderStatus(Order::ORDER_STATUS_PENDING);
        $order->setProcessStatus(Order::PROCESS_STATUS_PENDING);
        $order->setDeliveryType($deliveryType);
        $order->setDeliveryAddress($deliveryAddress);
        $order->setCustomerName($customerName);
        $order->setCustomerEmail($customerEmail);
        $order->setCustomerPhone($customerPhone);
        $order->setPaymentMethod($paymentMethod);

        $em->persist($stock);
        $em->persist($order);
        $em->flush();

        // Send email notifications
        try {
            $orderService->sendOrderConfirmation($order);
            $orderService->sendAdminOrderNotification($order);
        } catch (\Exception $e) {
            error_log('Failed to send order email for Order #' . $order->getId() . ': ' . $e->getMessage());
        }

        // Clear session data
        $session->remove('checkout_customer_name');
        $session->remove('checkout_customer_email');
        $session->remove('checkout_customer_phone');
        $session->remove('checkout_delivery_method');
        $session->remove('checkout_delivery_address');
        $session->remove('checkout_payment_method');

        $this->addFlash('success', 'Order #' . $order->getId() . ' placed successfully! A confirmation email has been sent to ' . htmlspecialchars($customerEmail) . '.');
        return $this->redirectToRoute('app_order_index');
    }

    #[Route('/', name: 'app_cart_view')]
    public function viewCart(EntityManagerInterface $em, SessionInterface $session): Response
    {
        $cart = $this->getOrCreateCart($em, $session, false);
        $cartItems = $cart ? $cart->getCartItems() : [];
        $subtotal = $cart ? $cart->getSubtotal() : 0;
        
        return $this->render('cart/index.html.twig', [
            'cartItems' => $cartItems,
            'subtotal' => $subtotal,
            'cart' => $cart,
        ]);
    }

    #[Route('/update/{itemId}', name: 'app_cart_update', methods: ['POST'])]
    public function update(
        int $itemId,
        Request $request,
        EntityManagerInterface $em,
        SessionInterface $session
    ): Response {
        $cartItem = $em->getRepository(CartItem::class)->find($itemId);
        if ($cartItem) {
            $newQty = (int) $request->request->get('quantity', 1);
            if ($newQty <= 0) {
                $em->remove($cartItem);
            } else {
                $stock = $cartItem->getStock();
                if ($newQty <= $stock->getStock()) {
                    $cartItem->setQuantity($newQty);
                } else {
                    $this->addFlash('error', 'Not enough stock for ' . $stock->getProduct()->getName());
                }
            }
            $em->flush();
            
            if ($cartItem->getCart()) {
                $session->set('cart_count', $cartItem->getCart()->getTotalItems());
            }
        }
        return $this->redirectToRoute('app_cart_view');
    }

    #[Route('/remove/{itemId}', name: 'app_cart_remove', methods: ['POST'])]
    public function remove(int $itemId, EntityManagerInterface $em, SessionInterface $session): Response
    {
        $cartItem = $em->getRepository(CartItem::class)->find($itemId);
        if ($cartItem) {
            $cart = $cartItem->getCart();
            $em->remove($cartItem);
            $em->flush();
            
            if ($cart) {
                $session->set('cart_count', $cart->getTotalItems());
            }
            $this->addFlash('success', 'Item removed from cart');
        }
        return $this->redirectToRoute('app_cart_view');
    }

    #[Route('/clear', name: 'app_cart_clear', methods: ['POST'])]
    public function clear(EntityManagerInterface $em, SessionInterface $session): Response
    {
        $cart = $this->getOrCreateCart($em, $session, false);
        if ($cart) {
            foreach ($cart->getCartItems() as $item) {
                $em->remove($item);
            }
            $em->flush();
            $session->set('cart_count', 0);
            $this->addFlash('success', 'Cart cleared');
        }
        return $this->redirectToRoute('app_cart_view');
    }

    #[Route('/count', name: 'app_cart_count', methods: ['GET'])]
    public function getCartCount(SessionInterface $session): Response
    {
        $count = $session->get('cart_count', 0);
        return $this->json(['count' => $count]);
    }

    private function getOrCreateCart(EntityManagerInterface $em, SessionInterface $session, bool $create = true): ?Cart
    {
        $user = $this->getUser();
        
        if ($user) {
            $cart = $em->getRepository(Cart::class)->findOneBy(['user' => $user]);
            if ($cart || !$create) return $cart;
        } else {
            $sessionId = $session->getId();
            $cart = $em->getRepository(Cart::class)->findOneBy(['sessionId' => $sessionId]);
            if ($cart || !$create) return $cart;
        }

        if (!$create) return null;

        $cart = new Cart();
        
        if ($user) {
            $cart->setUser($user);
        } else {
            $cart->setSessionId($session->getId());
        }
        
        $em->persist($cart);
        $em->flush();
        
        return $cart;
    }
}