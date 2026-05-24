<?php
// src/Controller/Api/ShopApiController.php

namespace App\Controller\Api;

use App\Entity\Cart;
use App\Entity\CartItem;
use App\Entity\Order;
use App\Entity\Stock;
use App\Entity\User;
use App\Repository\CartRepository;
use App\Repository\StockRepository;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api', name: 'api_')]
class ShopApiController extends AbstractController
{
    private EntityManagerInterface $em;
    private SerializerInterface $serializer;

    public function __construct(EntityManagerInterface $em, SerializerInterface $serializer)
    {
        $this->em = $em;
        $this->serializer = $serializer;
    }

    /**
     * GET /api/stocks - Get all stocks for shop display
     */
    #[Route('/stocks', name: 'stocks', methods: ['GET'])]
    public function getStocks(StockRepository $stockRepository, Request $request): JsonResponse
    {
        $search = $request->query->get('search', '');
        $category = $request->query->get('category', '');
        
        $stocks = $stockRepository->findAll();
        
        $member = [];
        foreach ($stocks as $stock) {
            if ($stock->getProduct() && $stock->getStock() >= 0) {
                $productName = $stock->getProductName();
                
                // Apply search filter
                if (!empty($search) && stripos($productName, $search) === false) {
                    continue;
                }
                
                // Apply category filter
                $productCategory = $stock->getProductCategory();
                if (!empty($category) && $category !== 'All' && $productCategory !== $category) {
                    continue;
                }
                
                $member[] = [
                    'id' => $stock->getId(),
                    'stock' => $stock->getStock(),
                    'productName' => $productName,
                    'productImage' => $stock->getProductImage(),
                    'productPrice' => (float)$stock->getProductPrice(),
                    'productDescription' => $stock->getProductDescription(),
                    'productCategory' => $productCategory,
                ];
            }
        }

        return $this->json([
            'member' => $member,
            'totalItems' => count($member)
        ]);
    }

    /**
     * GET /api/stocks/{id} - Get single stock
     */
    #[Route('/stocks/{id}', name: 'stock_show', methods: ['GET'])]
    public function getStock(Stock $stock): JsonResponse
    {
        if (!$stock->getProduct()) {
            return $this->json(['error' => 'Product not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'id' => $stock->getId(),
            'stock' => $stock->getStock(),
            'productName' => $stock->getProductName(),
            'productImage' => $stock->getProductImage(),
            'productPrice' => (float)$stock->getProductPrice(),
            'productDescription' => $stock->getProductDescription(),
            'productCategory' => $stock->getProductCategory(),
        ]);
    }

    /**
     * POST /api/orders - Create order (Buy Now)
     */
    #[Route('/orders', name: 'order_create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function createOrder(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!isset($data['stockId']) || !isset($data['quantity'])) {
            return $this->json([
                'success' => false,
                'error' => 'Missing required fields: stockId and quantity'
            ], Response::HTTP_BAD_REQUEST);
        }

        $stock = $this->em->getRepository(Stock::class)->find($data['stockId']);
        if (!$stock) {
            return $this->json([
                'success' => false,
                'error' => 'Product not found'
            ], Response::HTTP_NOT_FOUND);
        }

        $quantity = (int)$data['quantity'];
        if ($quantity <= 0) {
            return $this->json([
                'success' => false,
                'error' => 'Quantity must be at least 1'
            ], Response::HTTP_BAD_REQUEST);
        }

        if ($quantity > $stock->getStock()) {
            return $this->json([
                'success' => false,
                'error' => 'Not enough stock available'
            ], Response::HTTP_BAD_REQUEST);
        }

        /** @var User $user */
        $user = $this->getUser();
        $unitPrice = (float)$stock->getProductPrice();
        $totalAmount = $unitPrice * $quantity;

        $order = new Order();
        $order->setStock($stock);
        $order->setQuantity($quantity);
        $order->setUnitPrice($unitPrice);
        $order->setTotalAmount($totalAmount);
        $order->setCustomer($user);
        
        // Set customer info from request or user profile
        $order->setCustomerName($data['name'] ?? $user->getUserIdentifier());
        $order->setCustomerEmail($data['email'] ?? $user->getEmail());
        $order->setCustomerPhone($data['phone'] ?? null);
        $order->setDeliveryType($data['deliveryType'] ?? 'pickup');
        $order->setDeliveryAddress($data['deliveryAddress'] ?? null);
        $order->setPaymentMethod($data['paymentMethod'] ?? 'cod');
        $order->setOrderStatus(Order::ORDER_STATUS_PENDING);
        $order->setProcessStatus(Order::PROCESS_STATUS_PENDING);

        // Reduce stock
        $stock->setStock($stock->getStock() - $quantity);

        $this->em->persist($order);
        $this->em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Order placed successfully',
            'order' => [
                'id' => $order->getId(),
                'status' => $order->getOrderStatus(),
                'processStatus' => $order->getProcessStatus(),
                'totalAmount' => $order->getTotalAmount()
            ]
        ], Response::HTTP_CREATED);
    }

    /**
     * GET /api/orders/my - Get user's orders
     */
    #[Route('/orders/my', name: 'user_orders', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getUserOrders(OrderRepository $orderRepository): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $orders = $orderRepository->findBy(['customer' => $user], ['createdAt' => 'DESC']);
        
        $member = [];
        foreach ($orders as $order) {
            $member[] = [
                'id' => $order->getId(),
                'productName' => $order->getProductName(),
                'productImage' => $order->getProductImage(),
                'quantity' => $order->getQuantity(),
                'unitPrice' => (float)$order->getUnitPrice(),
                'totalAmount' => (float)$order->getTotalAmount(),
                'orderStatus' => $order->getOrderStatus(),
                'processStatus' => $order->getProcessStatus(),
                'deliveryType' => $order->getDeliveryType(),
                'deliveryAddress' => $order->getDeliveryAddress(),
                'paymentMethod' => $order->getPaymentMethod(),
                'createdAt' => $order->getCreatedAt()?->format('Y-m-d H:i:s'),
                'updatedAt' => $order->getUpdatedAt()?->format('Y-m-d H:i:s'),
            ];
        }

        return $this->json([
            'member' => $member,
            'totalItems' => count($member)
        ]);
    }

    /**
     * GET /api/orders/{id} - Get single order
     */
    #[Route('/orders/{id}', name: 'order_show', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getOrder(Order $order): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        
        // Security: User can only view their own orders
        if ($order->getCustomer() !== $user) {
            return $this->json(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        return $this->json([
            'id' => $order->getId(),
            'productName' => $order->getProductName(),
            'productImage' => $order->getProductImage(),
            'quantity' => $order->getQuantity(),
            'unitPrice' => (float)$order->getUnitPrice(),
            'totalAmount' => (float)$order->getTotalAmount(),
            'orderStatus' => $order->getOrderStatus(),
            'processStatus' => $order->getProcessStatus(),
            'deliveryType' => $order->getDeliveryType(),
            'deliveryAddress' => $order->getDeliveryAddress(),
            'paymentMethod' => $order->getPaymentMethod(),
            'customerName' => $order->getCustomerName(),
            'customerEmail' => $order->getCustomerEmail(),
            'customerPhone' => $order->getCustomerPhone(),
            'createdAt' => $order->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updatedAt' => $order->getUpdatedAt()?->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * POST /api/orders/{id}/cancel - Cancel order
     */
    #[Route('/orders/{id}/cancel', name: 'order_cancel', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function cancelOrder(Order $order): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        
        // Security: User can only cancel their own orders
        if ($order->getCustomer() !== $user) {
            return $this->json(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        // Only pending orders can be cancelled
        if ($order->getOrderStatus() !== Order::ORDER_STATUS_PENDING) {
            return $this->json([
                'success' => false,
                'error' => 'This order cannot be cancelled'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Return stock
        $stock = $order->getStock();
        $stock->setStock($stock->getStock() + $order->getQuantity());
        
        $order->setOrderStatus(Order::ORDER_STATUS_CANCELLED);
        $order->setProcessStatus('cancelled');

        $this->em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Order cancelled successfully'
        ]);
    }

    /**
     * GET /api/cart - Get user's cart
     */
    #[Route('/cart', name: 'cart_get', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getCart(CartRepository $cartRepository): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $cart = $cartRepository->findOneBy(['user' => $user]);
        
        if (!$cart) {
            return $this->json([
                'items' => [],
                'subtotal' => 0,
                'total_items' => 0
            ]);
        }

        $items = [];
        foreach ($cart->getCartItems() as $item) {
            $stock = $item->getStock();
            if ($stock && $stock->getProduct()) {
                $items[] = [
                    'id' => $item->getId(),
                    'stock_id' => $stock->getId(),
                    'product_name' => $stock->getProductName(),
                    'product_image' => $stock->getProductImage(),
                    'unit_price' => (float)$stock->getProductPrice(),
                    'quantity' => $item->getQuantity(),
                    'subtotal' => (float)$item->getSubtotal(),
                    'available_stock' => $stock->getStock(),
                ];
            }
        }

        return $this->json([
            'items' => $items,
            'subtotal' => (float)$cart->getSubtotal(),
            'total_items' => $cart->getTotalItems()
        ]);
    }

    /**
     * POST /api/cart/add/{stockId} - Add to cart
     */
    #[Route('/cart/add/{stockId}', name: 'cart_add', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function addToCart(int $stockId, Request $request): JsonResponse
    {
        $quantity = (int)$request->request->get('quantity', 1);
        
        if ($quantity <= 0) {
            return $this->json([
                'success' => false,
                'message' => 'Quantity must be at least 1'
            ], Response::HTTP_BAD_REQUEST);
        }
        
        $stock = $this->em->getRepository(Stock::class)->find($stockId);
        if (!$stock) {
            return $this->json([
                'success' => false,
                'message' => 'Product not found'
            ], Response::HTTP_NOT_FOUND);
        }

        if ($quantity > $stock->getStock()) {
            return $this->json([
                'success' => false,
                'message' => 'Not enough stock available'
            ], Response::HTTP_BAD_REQUEST);
        }

        /** @var User $user */
        $user = $this->getUser();
        $cart = $this->em->getRepository(Cart::class)->findOneBy(['user' => $user]);
        
        if (!$cart) {
            $cart = new Cart();
            $cart->setUser($user);
            $this->em->persist($cart);
        }

        // Check if item already in cart
        $existingItem = null;
        foreach ($cart->getCartItems() as $item) {
            if ($item->getStock() === $stock) {
                $existingItem = $item;
                break;
            }
        }

        if ($existingItem) {
            $newQuantity = $existingItem->getQuantity() + $quantity;
            if ($newQuantity > $stock->getStock()) {
                return $this->json([
                    'success' => false,
                    'message' => 'Not enough stock available'
                ], Response::HTTP_BAD_REQUEST);
            }
            $existingItem->setQuantity($newQuantity);
        } else {
            $cartItem = new CartItem();
            $cartItem->setCart($cart);
            $cartItem->setStock($stock);
            $cartItem->setQuantity($quantity);
            $this->em->persist($cartItem);
        }

        $cart->setUpdatedAt(new \DateTime());
        $this->em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Product added to cart',
            'cart_count' => $cart->getTotalItems()
        ]);
    }

    /**
     * POST /api/cart/update/{itemId} - Update cart item quantity
     */
    #[Route('/cart/update/{itemId}', name: 'cart_update', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function updateCartItem(int $itemId, Request $request): JsonResponse
    {
        $quantity = (int)$request->request->get('quantity', 1);
        
        $cartItem = $this->em->getRepository(CartItem::class)->find($itemId);
        if (!$cartItem) {
            return $this->json([
                'success' => false,
                'message' => 'Item not found'
            ], Response::HTTP_NOT_FOUND);
        }

        /** @var User $user */
        $user = $this->getUser();
        
        // Security: Check if cart belongs to user
        if ($cartItem->getCart()->getUser() !== $user) {
            return $this->json(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        $stock = $cartItem->getStock();
        if ($quantity > $stock->getStock()) {
            return $this->json([
                'success' => false,
                'message' => 'Not enough stock available'
            ], Response::HTTP_BAD_REQUEST);
        }

        if ($quantity <= 0) {
            $this->em->remove($cartItem);
        } else {
            $cartItem->setQuantity($quantity);
        }

        $cart = $cartItem->getCart();
        $cart->setUpdatedAt(new \DateTime());
        $this->em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Cart updated',
            'cart_count' => $cart->getTotalItems(),
            'subtotal' => (float)$cart->getSubtotal()
        ]);
    }

    /**
     * POST /api/cart/remove/{itemId} - Remove item from cart
     */
    #[Route('/cart/remove/{itemId}', name: 'cart_remove', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function removeCartItem(int $itemId): JsonResponse
    {
        $cartItem = $this->em->getRepository(CartItem::class)->find($itemId);
        if (!$cartItem) {
            return $this->json([
                'success' => false,
                'message' => 'Item not found'
            ], Response::HTTP_NOT_FOUND);
        }

        /** @var User $user */
        $user = $this->getUser();
        
        // Security: Check if cart belongs to user
        if ($cartItem->getCart()->getUser() !== $user) {
            return $this->json(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        $cart = $cartItem->getCart();
        $this->em->remove($cartItem);
        $this->em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Item removed from cart',
            'cart_count' => $cart->getTotalItems(),
            'subtotal' => (float)$cart->getSubtotal()
        ]);
    }

    /**
     * POST /api/cart/clear - Clear entire cart
     */
    #[Route('/cart/clear', name: 'cart_clear', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function clearCart(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $cart = $this->em->getRepository(Cart::class)->findOneBy(['user' => $user]);
        
        if ($cart) {
            foreach ($cart->getCartItems() as $item) {
                $this->em->remove($item);
            }
            $this->em->flush();
        }

        return $this->json([
            'success' => true,
            'message' => 'Cart cleared successfully'
        ]);
    }

    /**
     * GET /api/cart/count - Get cart count for badge
     */
    #[Route('/cart/count', name: 'cart_count', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getCartCount(CartRepository $cartRepository): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $cart = $cartRepository->findOneBy(['user' => $user]);
        
        $count = $cart ? $cart->getTotalItems() : 0;
        
        return $this->json(['count' => $count]);
    }

    /**
     * POST /api/cart/checkout - Checkout entire cart
     */
    #[Route('/cart/checkout', name: 'cart_checkout', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function checkout(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        /** @var User $user */
        $user = $this->getUser();
        
        $cart = $this->em->getRepository(Cart::class)->findOneBy(['user' => $user]);
        if (!$cart || $cart->getCartItems()->count() === 0) {
            return $this->json([
                'success' => false,
                'error' => 'Cart is empty'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Validate stock availability
        foreach ($cart->getCartItems() as $item) {
            $stock = $item->getStock();
            if ($item->getQuantity() > $stock->getStock()) {
                return $this->json([
                    'success' => false,
                    'error' => "Not enough stock for {$stock->getProductName()}"
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        // Create orders from cart items
        $orders = [];
        foreach ($cart->getCartItems() as $item) {
            $stock = $item->getStock();
            $unitPrice = (float)$stock->getProductPrice();
            $totalAmount = $unitPrice * $item->getQuantity();

            $order = new Order();
            $order->setStock($stock);
            $order->setQuantity($item->getQuantity());
            $order->setUnitPrice($unitPrice);
            $order->setTotalAmount($totalAmount);
            $order->setCustomer($user);
            $order->setCustomerName($data['name'] ?? $user->getUserIdentifier());
            $order->setCustomerEmail($data['email'] ?? $user->getEmail());
            $order->setCustomerPhone($data['phone'] ?? null);
            $order->setDeliveryType($data['deliveryType'] ?? 'pickup');
            $order->setDeliveryAddress($data['deliveryAddress'] ?? null);
            $order->setPaymentMethod($data['paymentMethod'] ?? 'cod');
            $order->setOrderStatus(Order::ORDER_STATUS_PENDING);
            $order->setProcessStatus(Order::PROCESS_STATUS_PENDING);

            // Reduce stock
            $stock->setStock($stock->getStock() - $item->getQuantity());

            $this->em->persist($order);
            $orders[] = $order;
        }

        // Clear cart
        foreach ($cart->getCartItems() as $item) {
            $this->em->remove($item);
        }

        $this->em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Orders placed successfully',
            'orders' => array_map(fn($o) => ['id' => $o->getId()], $orders)
        ]);
    }

    /**
     * POST /api/cart/save-checkout-info - Save checkout information
     */
    #[Route('/cart/save-checkout-info', name: 'cart_save_info', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function saveCheckoutInfo(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        // You can store this in session or a temporary table
        // For now, just return success
        return $this->json(['success' => true]);
    }

    /**
     * GET /api/orders/new-count - Get new orders count for badge
     */
    #[Route('/orders/new-count', name: 'new_orders_count', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getNewOrdersCount(OrderRepository $orderRepository): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        
        // Count orders that are pending
        $count = $orderRepository->count([
            'customer' => $user,
            'orderStatus' => Order::ORDER_STATUS_PENDING
        ]);
        
        return $this->json(['count' => $count]);
    }
}