<?php

namespace App\Service;

use App\Entity\Order;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;

class OrderService
{
    private string $adminEmail = 'johnantonyamil@gmail.com';
    private string $senderName = 'Cartlify';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private MailerInterface $mailer
    ) {}

    private function getCustomerEmail(Order $order): string
    {
        $email = $order->getCustomerEmail();
        if (!empty($email)) return $email;
        $customer = $order->getCustomer();
        if ($customer && $customer->getEmail()) return $customer->getEmail();
        return '';
    }

    private function getCustomerName(Order $order): string
    {
        $name = $order->getCustomerName();
        if (!empty($name)) return $name;
        $customer = $order->getCustomer();
        if ($customer && $customer->getUsername()) return $customer->getUsername();
        return 'Customer';
    }

    public function sendOrderConfirmation(Order $order): void
    {
        $customerEmail = $this->getCustomerEmail($order);
        if (empty($customerEmail)) return;
        $customerName = $this->getCustomerName($order);
        
        $email = (new Email())
            ->from(new Address($this->adminEmail, $this->senderName))
            ->to(new Address($customerEmail, $customerName))
            ->subject('🎉 Order Confirmation #' . $order->getId())
            ->html($this->getCustomerOrderEmailTemplate($order));
        
        try { $this->mailer->send($email); } catch (\Exception $e) {}
    }

    public function sendAdminOrderNotification(Order $order): void
    {
        $email = (new Email())
            ->from(new Address($this->adminEmail, $this->senderName . ' Orders'))
            ->to(new Address($this->adminEmail))
            ->subject('📦 New Order #' . $order->getId() . ' - Action Required')
            ->html($this->getAdminOrderEmailTemplate($order));
        
        try { $this->mailer->send($email); } catch (\Exception $e) {}
    }

    public function sendOrderStatusUpdate(Order $order, string $oldStatus, string $newStatus): void
    {
        $customerEmail = $this->getCustomerEmail($order);
        if (empty($customerEmail)) return;
        $customerName = $this->getCustomerName($order);
        
        $statusMessages = [
            'accepted' => '✅ Your order has been accepted and is being prepared!',
            'processing' => '🔄 Your order is now being processed.',
            'packaging' => '📦 Your order is being packed.',
            'ready_for_pickup' => '🎉 Your order is ready for pickup!',
            'shipped' => '🚚 Your order has been shipped!',
            'delivered' => '🏠 Your order has been delivered!',
            'completed' => '✅ Your order has been completed. Thank you for shopping with us!',
            'cancelled' => '❌ Your order has been cancelled.',
            'rejected' => '⚠️ Your order could not be processed.'
        ];
        
        $message = $statusMessages[$newStatus] ?? "Your order status has been updated to: " . ucfirst($newStatus);
        
        $email = (new Email())
            ->from(new Address($this->adminEmail, $this->senderName . ' Updates'))
            ->to(new Address($customerEmail, $customerName))
            ->subject('📧 Order #' . $order->getId() . ' Status Update')
            ->html($this->getStatusUpdateEmailTemplate($order, $message, $newStatus));
        
        try { $this->mailer->send($email); } catch (\Exception $e) {}
    }

    private function getCustomerOrderEmailTemplate(Order $order): string
    {
        $productName = $order->getStock()->getProduct()->getName();
        $quantity = $order->getQuantity();
        $total = $order->getTotalAmount();
        $deliveryType = $order->getDeliveryType() === 'shipping' ? '🚚 Shipping' : '🏪 Pickup';
        $customerName = htmlspecialchars($this->getCustomerName($order));
        
        return '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>body{font-family:Arial,sans-serif;background:#f4f6f9;margin:0;padding:20px}.container{max-width:600px;margin:0 auto;background:white;border-radius:16px;overflow:hidden;box-shadow:0 4px 12px rgba(0,0,0,0.1)}.header{background:linear-gradient(135deg,#0a0a2a 0%,#151535 100%);color:white;padding:30px;text-align:center}.header h1{margin:0;font-size:24px}.content{padding:30px}.order-details{background:#f8f9fa;border-radius:12px;padding:20px;margin:20px 0}.order-item{display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid #e9ecef}.total{font-size:18px;font-weight:bold;color:#28a745;text-align:right;margin-top:15px}.status-badge{display:inline-block;background:#ffc107;color:#856404;padding:5px 12px;border-radius:20px;font-size:12px;font-weight:bold}.footer{background:#f8f9fa;padding:20px;text-align:center;color:#6c757d;font-size:12px}</style></head><body><div class="container"><div class="header"><h1>🎉 Order Confirmed!</h1><p>Order #' . $order->getId() . '</p></div><div class="content"><h2>Hello ' . $customerName . '!</h2><p>Thank you for your order.</p><div class="order-details"><h3>Order Summary</h3><div class="order-item"><span>' . htmlspecialchars($productName) . ' x ' . $quantity . '</span><span>₱' . number_format($total, 2) . '</span></div><div class="total"><strong>Total: ₱' . number_format($total, 2) . '</strong></div></div><p><strong>Delivery Method:</strong> ' . $deliveryType . '</p>' . ($order->getDeliveryAddress() ? '<p><strong>Delivery Address:</strong><br>' . nl2br(htmlspecialchars($order->getDeliveryAddress())) . '</p>' : '') . '<p><strong>Payment Method:</strong> ' . ucfirst(str_replace('_', ' ', $order->getPaymentMethod() ?? 'Cash on Delivery')) . '</p><p><strong>Status:</strong> <span class="status-badge">' . ucfirst($order->getOrderStatus()) . '</span></p></div><div class="footer"><p>Cartlify — Your trusted shopping partner</p></div></div></body></html>';
    }

    private function getAdminOrderEmailTemplate(Order $order): string
    {
        $productName = $order->getStock()->getProduct()->getName();
        $customerEmail = htmlspecialchars($this->getCustomerEmail($order));
        return '<!DOCTYPE html><html><head><style>body{font-family:Arial,sans-serif}.container{max-width:600px;margin:0 auto}.header{background:#0a0a2a;color:white;padding:20px;text-align:center}.content{padding:20px}.info-box{background:#f8f9fa;padding:15px;border-radius:8px;margin:15px 0}.btn{background:#28a745;color:white;padding:10px 20px;text-decoration:none;border-radius:6px;display:inline-block}</style></head><body><div class="container"><div class="header"><h2>📦 New Order Received!</h2><h3>Order #' . $order->getId() . '</h3></div><div class="content"><div class="info-box"><p><strong>Customer:</strong> ' . htmlspecialchars($this->getCustomerName($order)) . '</p><p><strong>Email:</strong> ' . $customerEmail . '</p><p><strong>Phone:</strong> ' . htmlspecialchars($order->getCustomerPhone() ?? 'N/A') . '</p><p><strong>Product:</strong> ' . htmlspecialchars($productName) . '</p><p><strong>Quantity:</strong> ' . $order->getQuantity() . '</p><p><strong>Total:</strong> ₱' . number_format($order->getTotalAmount(), 2) . '</p><p><strong>Delivery:</strong> ' . ucfirst($order->getDeliveryType()) . '</p><p><strong>Payment:</strong> ' . ucfirst(str_replace('_', ' ', $order->getPaymentMethod() ?? 'COD')) . '</p></div><div style="text-align:center"><a href="http://127.0.0.1:8000/order/show/' . $order->getId() . '" class="btn">View Order Details</a></div></div></div></body></html>';
    }

    private function getStatusUpdateEmailTemplate(Order $order, string $message, string $status): string
    {
        return '<!DOCTYPE html><html><head><style>body{font-family:Arial,sans-serif}.container{max-width:600px;margin:0 auto;background:white;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.1)}.header{background:linear-gradient(135deg,#0a0a2a,#151535);color:white;padding:20px;text-align:center}.content{padding:25px}.btn{background:#28a745;color:white;padding:10px 20px;text-decoration:none;border-radius:6px;display:inline-block;margin-top:15px}.footer{background:#f8f9fa;padding:15px;text-align:center;font-size:12px;color:#6c757d}</style></head><body><div class="container"><div class="header"><h2>Order #' . $order->getId() . ' Status Update</h2></div><div class="content"><p>Hello ' . htmlspecialchars($this->getCustomerName($order)) . ',</p><p>' . $message . '</p><div style="text-align:center"><a href="http://127.0.0.1:8000/order/tracker" class="btn">Track Your Order</a></div></div><div class="footer"><p>Cartlify — Your trusted shopping partner</p></div></div></body></html>';
    }
}