<?php

namespace App\EventSubscriber;

use App\Entity\ActivityLog;
use App\Entity\User;
use App\Entity\Product;
use App\Entity\Stock;
use App\Entity\Order;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;
use Symfony\Component\Security\Http\SecurityEvents;

class ActivityLogSubscriber implements EventSubscriberInterface
{
    private EntityManagerInterface $em;
    private Security $security;
    private array $pendingLogs = [];

    public function __construct(EntityManagerInterface $em, Security $security)
    {
        $this->em = $em;
        $this->security = $security;
    }

    public function onControllerEvent(ControllerEvent $event): void
    {
        $request = $event->getRequest();
        
        // Only track POST, PUT, PATCH, DELETE requests (actions that modify data)
        if (!in_array($request->getMethod(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            return;
        }

        // Check if this is one of our monitored routes/controllers
        $route = $request->attributes->get('_route');
        if (!$this->isMonitoredRoute($route)) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user) {
            return;
        }

        // Store log data for later processing in TerminateEvent
        $this->pendingLogs[] = [
            'user' => $user,
            'request' => $request,
            'route' => $route,
            'method' => $request->getMethod(),
        ];
    }

    public function onTerminate(TerminateEvent $event): void
    {
        if (empty($this->pendingLogs)) {
            return;
        }

        foreach ($this->pendingLogs as $logData) {
            $this->createActivityLog(
                $logData['user'],
                $logData['request'],
                $logData['route'],
                $logData['method']
            );
        }
        
        $this->pendingLogs = [];
    }

    private function createActivityLog($user, Request $request, string $route, string $method): void
    {
        $action = $this->determineAction($route, $method, $user);
        $target = $this->determineTarget($request, $route, $action);
        
        if (!$action || !$target) {
            return; // Skip if not a required event
        }

        $log = new ActivityLog();
        $log->setUserId($user->getId())
            ->setUsername($user->getUsername())
            ->setRole($this->getUserRole($user))
            ->setAction($action)
            ->setTarget($target);

        $this->em->persist($log);
        $this->em->flush();
    }

    private function isMonitoredRoute(string $route): bool
    {
        $monitoredRoutes = [
            'app_user_new', 'app_user_delete', 'app_user_edit',
            'app_product_new', 'app_product_edit', 'app_product_delete',
            'app_stock_new', 'app_stock_edit', 'app_stock_delete',
            'app_order_new', 'app_order_delete',
        ];
        
        return in_array($route, $monitoredRoutes);
    }

    private function determineAction(string $route, string $method, $user): ?string
    {
        $userRoles = $user->getRoles();
        $isAdmin = in_array('ROLE_ADMIN', $userRoles);
        $isStaff = in_array('ROLE_STAFF', $userRoles);
        
        // User management (Admin only)
        if (str_contains($route, 'app_user')) {
            if (str_contains($route, 'new')) return 'CREATE';
            if (str_contains($route, 'delete')) return 'DELETE';
            if (str_contains($route, 'edit')) {
                if ($isAdmin) {
                    return 'ADMIN_UPDATE';
                } elseif ($isStaff) {
                    return 'STAFF_UPDATE';
                }
                return 'UPDATE';
            }
        }
        
        // Product management
        if (str_contains($route, 'app_product')) {
            if ($method === 'POST' && str_contains($route, 'new')) {
                return 'CREATE';
            }
            
            if ($method === 'POST' && str_contains($route, 'delete')) {
                return 'DELETE';
            }
            
            if (in_array($method, ['POST', 'PUT', 'PATCH']) && str_contains($route, 'edit')) {
                if ($isAdmin) {
                    return 'ADMIN_UPDATE';
                } elseif ($isStaff) {
                    return 'STAFF_UPDATE';
                }
                return 'UPDATE';
            }
        }
        
        // Stock management
        if (str_contains($route, 'app_stock')) {
            if ($method === 'POST' && str_contains($route, 'new')) {
                return 'CREATE';
            }
            
            if ($method === 'POST' && str_contains($route, 'delete')) {
                return 'DELETE';
            }
            
            if (in_array($method, ['POST', 'PUT', 'PATCH']) && str_contains($route, 'edit')) {
                if ($isAdmin) {
                    return 'ADMIN_UPDATE';
                } elseif ($isStaff) {
                    return 'STAFF_UPDATE';
                }
                return 'UPDATE';
            }
        }
        
        // Order management
        if (str_contains($route, 'app_order')) {
            if ($method === 'POST' && str_contains($route, 'new')) {
                return 'CREATE';
            }
            
            if ($method === 'POST' && str_contains($route, 'delete')) {
                return 'DELETE';
            }
            
            // Note: OrderController doesn't have edit method, only new and delete
        }
        
        return null;
    }

    private function determineTarget(Request $request, string $route, string $action): ?string
    {
        $id = $request->attributes->get('id');
        
        // Handle USER actions
        if (str_contains($route, 'app_user')) {
            if ($action === 'CREATE') {
                $formData = $request->request->get('user');
                $username = $formData['username'] ?? 'New User';
                $email = $formData['email'] ?? '';
                $role = $formData['roles'] ?? 'ROLE_USER';
                return "Created User: {$username} ({$email}) with role: {$role}";
            }
            
            if ($id) {
                $user = $this->em->getRepository(User::class)->find($id);
                if ($user) {
                    if ($action === 'DELETE') {
                        return "Deleted User: {$user->getUsername()} ({$user->getEmail()})";
                    } else {
                        // For UPDATE/EDIT actions
                        return "Updated User: {$user->getUsername()} ({$user->getEmail()})";
                    }
                }
            }
            return "User (ID: {$id})";
        }
        
        // Handle PRODUCT actions - using 'product' as form prefix
        if (str_contains($route, 'app_product')) {
            if ($action === 'CREATE') {
                $formData = $request->request->get('product');
                $name = $formData['name'] ?? 'New Product';
                $price = $formData['price'] ?? '0.00';
                return "Created Product: {$name} (Price: \${$price})";
            }
            
            if ($id) {
                $product = $this->em->getRepository(Product::class)->find($id);
                if ($product) {
                    if ($action === 'DELETE') {
                        return "Deleted Product: {$product->getName()} (ID: {$id})";
                    } else {
                        // For UPDATE/EDIT actions
                        $formData = $request->request->get('product');
                        $newName = $formData['name'] ?? $product->getName();
                        $newPrice = $formData['price'] ?? $product->getPrice();
                        return "Updated Product: {$newName} (Price: \${$newPrice})";
                    }
                }
            }
            return "Product (ID: {$id})";
        }
        
        // Handle STOCK actions - using 'stock' as form prefix
        if (str_contains($route, 'app_stock')) {
            if ($action === 'CREATE') {
                $formData = $request->request->get('stock');
                $productId = $formData['product'] ?? null;
                $quantity = $formData['stock'] ?? 0; // Note: field is named 'stock' not 'quantity'
                
                if ($productId) {
                    $product = $this->em->getRepository(Product::class)->find($productId);
                    if ($product) {
                        return "Created Stock: {$product->getName()} x {$quantity} units";
                    }
                }
                return "Created Stock (Quantity: {$quantity})";
            }
            
            if ($id) {
                $stock = $this->em->getRepository(Stock::class)->find($id);
                if ($stock) {
                    $productName = $stock->getProduct() ? $stock->getProduct()->getName() : 'Unknown Product';
                    
                    if ($action === 'DELETE') {
                        return "Deleted Stock: {$productName} (Stock ID: {$id})";
                    } else {
                        // For UPDATE/EDIT actions
                        $formData = $request->request->get('stock');
                        $newQuantity = $formData['stock'] ?? $stock->getStock();
                        return "Updated Stock: {$productName} → {$newQuantity} units";
                    }
                }
            }
            return "Stock (ID: {$id})";
        }
        
        // Handle ORDER actions
        if (str_contains($route, 'app_order')) {
            if ($action === 'CREATE') {
                $stockId = $request->request->get('stock_id');
                $quantity = $request->request->get('quantity') ?? 1;
                
                if ($stockId) {
                    $stock = $this->em->getRepository(Stock::class)->find($stockId);
                    if ($stock && $stock->getProduct()) {
                        return "Created Order: {$stock->getProduct()->getName()} x {$quantity} units";
                    }
                    return "Created Order: Stock ID {$stockId} x {$quantity} units";
                }
                return "Created Order";
            }
            
            if ($id && $action === 'DELETE') {
                $order = $this->em->getRepository(Order::class)->find($id);
                if ($order) {
                    $productName = $order->getStock() && $order->getStock()->getProduct() 
                        ? $order->getStock()->getProduct()->getName() 
                        : 'Unknown Product';
                    return "Deleted Order: {$productName} (Order ID: {$id})";
                }
                return "Deleted Order (ID: {$id})";
            }
        }
        
        return null;
    }

    public function onLogin(InteractiveLoginEvent $event): void
    {
        $user = $event->getAuthenticationToken()->getUser();

        $log = new ActivityLog();
        $log->setUserId($user->getId())
            ->setUsername($user->getUsername())
            ->setRole($this->getUserRole($user))
            ->setAction('LOGIN')
            ->setTarget('User: ' . $user->getUsername() . ' (ID: ' . $user->getId() . ')');

        $this->em->persist($log);
        $this->em->flush();
    }

    public function onLogout(LogoutEvent $event): void
    {
        $user = $event->getToken() ? $event->getToken()->getUser() : null;
        
        if (!$user || !$user instanceof User) {
            return;
        }

        $log = new ActivityLog();
        $log->setUserId($user->getId())
            ->setUsername($user->getUsername())
            ->setRole($this->getUserRole($user))
            ->setAction('LOGOUT')
            ->setTarget('User: ' . $user->getUsername() . ' (ID: ' . $user->getId() . ')');

        $this->em->persist($log);
        $this->em->flush();
    }

    private function getUserRole($user): string
    {
        $roles = $user->getRoles();
        return $roles[0] ?? 'ROLE_USER';
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ControllerEvent::class => 'onControllerEvent',
            TerminateEvent::class => 'onTerminate',
            SecurityEvents::INTERACTIVE_LOGIN => 'onLogin',
            LogoutEvent::class => 'onLogout',
        ];
    }
}