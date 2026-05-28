<?php

namespace App\Controller\Api;

use App\Entity\Order;
use App\Repository\OrderRepository;
use App\Repository\StockRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/mobile/orders', name: 'api_mobile_orders_')]
class MobileOrderController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private StockRepository $stockRepository,
        private OrderRepository $orderRepository,
    ) {}

    // ─────────────────────────────────────────────
    // POST /api/mobile/orders/buy-now
    // ─────────────────────────────────────────────
    #[Route('/buy-now', name: 'buy_now', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function buyNow(Request $request): JsonResponse
    {
        $body = json_decode($request->getContent(), true) ?? [];

        $stockId         = (int)   ($body['stockId']         ?? 0);
        $quantity        = (int)   ($body['quantity']        ?? 1);
        $customerName    =         ($body['name']            ?? null);
        $customerEmail   =         ($body['email']           ?? null);
        $customerPhone   =         ($body['phone']           ?? null);
        $deliveryType    =         ($body['deliveryType']    ?? 'pickup');
        $deliveryAddress =         ($body['deliveryAddress'] ?? null);
        $paymentMethod   =         ($body['paymentMethod']   ?? 'cod');

        if (!$stockId) {
            return $this->json(['success' => false, 'message' => 'Product is required.'], 422);
        }

        $quantity = max(1, $quantity);

        $stock = $this->stockRepository->find($stockId);
        if (!$stock) {
            return $this->json(['success' => false, 'message' => 'Product not found.'], 404);
        }

        if ($stock->getStock() <= 0) {
            return $this->json(['success' => false, 'message' => 'This product is out of stock.'], 422);
        }

        if ($quantity > $stock->getStock()) {
            return $this->json([
                'success' => false,
                'message' => "Only {$stock->getStock()} item(s) available.",
            ], 422);
        }

        if ($deliveryType === 'shipping' && empty(trim($deliveryAddress ?? ''))) {
            return $this->json(['success' => false, 'message' => 'Delivery address is required for shipping.'], 422);
        }

        $user      = $this->getUser();
        $unitPrice = (float) $stock->getProductPrice();
        $total     = $unitPrice * $quantity;

        $order = new Order();
        $order->setStock($stock);
        $order->setQuantity($quantity);
        $order->setUnitPrice($unitPrice);
        $order->setTotalAmount($total);
        $order->setOrderStatus(Order::ORDER_STATUS_PENDING);
        $order->setProcessStatus(Order::PROCESS_STATUS_PENDING);
        $order->setDeliveryType($deliveryType);
        $order->setDeliveryAddress($deliveryAddress);
        $order->setPaymentMethod($paymentMethod);
        $order->setCustomer($user);
        $order->setCustomerName($customerName ?? ($user?->getUsername()));
        $order->setCustomerEmail($customerEmail ?? ($user?->getEmail() ?? null));
        $order->setCustomerPhone($customerPhone);

        $stock->setStock($stock->getStock() - $quantity);

        $this->em->persist($order);
        $this->em->flush();

        return $this->json([
            'success' => true,
            'message' => "Order placed successfully! Order #{$order->getId()}",
            'order'   => [
                'id'           => $order->getId(),
                'product_name' => $stock->getProductName(),
                'quantity'     => $order->getQuantity(),
                'unit_price'   => $order->getUnitPrice(),
                'total_amount' => $order->getTotalAmount(),
                'order_status' => $order->getOrderStatus(),
                'delivery_type'=> $order->getDeliveryType(),
                'payment'      => $order->getPaymentMethod(),
                'created_at'   => $order->getCreatedAt()?->format('Y-m-d H:i:s'),
            ],
        ], 201);
    }

    // ─────────────────────────────────────────────
    // GET /api/mobile/orders/my
    // ─────────────────────────────────────────────
    #[Route('/my', name: 'my_orders', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function myOrders(): JsonResponse
    {
        $orders = $this->orderRepository->findBy(
            ['customer' => $this->getUser()],
            ['createdAt' => 'DESC']
        );

        $data = array_map(function (Order $order) {
            return [
                'id'            => $order->getId(),
                'product_name'  => $order->getProductName(),
                'product_image' => $order->getProductImage(),
                'quantity'      => $order->getQuantity(),
                'unit_price'    => $order->getUnitPrice(),
                'total_amount'  => $order->getTotalAmount(),
                'order_status'  => $order->getOrderStatus(),
                'process_status'=> $order->getProcessStatus(),
                'delivery_type' => $order->getDeliveryType(),
                'payment'       => $order->getPaymentMethod(),
                'created_at'    => $order->getCreatedAt()?->format('Y-m-d H:i:s'),
            ];
        }, $orders);

        return $this->json(['success' => true, 'orders' => $data]);
    }

    // ─────────────────────────────────────────────
    // GET /api/mobile/orders/new-count
    // ─────────────────────────────────────────────
    #[Route('/new-count', name: 'new_count', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function newOrdersCount(): JsonResponse
    {
        $count = $this->orderRepository->count([
            'customer'    => $this->getUser(),
            'orderStatus' => Order::ORDER_STATUS_PENDING,
        ]);

        return $this->json(['count' => $count]);
    }

    // ─────────────────────────────────────────────
    // POST /api/mobile/orders/{id}/cancel
    // ─────────────────────────────────────────────
    #[Route('/{id}/cancel', name: 'cancel', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function cancelOrder(int $id): JsonResponse
    {
        $order = $this->orderRepository->find($id);

        if (!$order) {
            return $this->json(['success' => false, 'message' => 'Order not found.'], 404);
        }

        if ($order->getCustomer()?->getId() !== $this->getUser()?->getId()) {
            return $this->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $cancellable = [Order::ORDER_STATUS_PENDING, Order::ORDER_STATUS_ACCEPTED];
        if (!in_array($order->getOrderStatus(), $cancellable)) {
            return $this->json([
                'success' => false,
                'message' => 'This order can no longer be cancelled.',
            ], 422);
        }

        $stock = $order->getStock();
        $stock->setStock($stock->getStock() + $order->getQuantity());

        $order->setOrderStatus(Order::ORDER_STATUS_CANCELLED);
        $this->em->flush();

        return $this->json(['success' => true, 'message' => 'Order cancelled successfully.']);
    }
}