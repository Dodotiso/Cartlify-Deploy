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
        $route = $request->attributes->get('_route');
        $method = $request->getMethod();

        if ($method !== 'POST') {
            return;
        }

        if (!$this->isMonitoredRoute($route)) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user) {
            return;
        }

        $this->pendingLogs[] = [
            'user' => $user,
            'request' => $request,
            'route' => $route,
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
                $logData['route']
            );
        }

        $this->pendingLogs = [];
    }

    private function createActivityLog($user, Request $request, string $route): void
    {
        $action = $this->determineAction($route, $user);
        $target = $this->determineTarget($request, $route, $action, $user);

        if (!$action || !$target) {
            return;
        }

        $log = new ActivityLog();
        $log->setUserId($user->getId())
            ->setUsername($user->getUsername())
            ->setRole($this->getUserRole($user))
            ->setAction($action)
            ->setTarget($target);

        try {
            $this->em->persist($log);
            $this->em->flush();
        } catch (\Exception $e) {
            error_log('ActivityLog save error: ' . $e->getMessage());
        }
    }

    private function isMonitoredRoute(string $route): bool
    {
        $monitoredRoutes = [
            'app_user_new',
            'app_user_delete',
            'app_user_edit',
            'app_product_new',
            'app_product_edit',
            'app_product_delete',
            'app_stock_new',
            'app_stock_edit',
            'app_stock_delete',
            'app_order_new',
            'app_order_delete',
            'app_profile_edit',
        ];

        return in_array($route, $monitoredRoutes);
    }

    private function determineAction(string $route, $user): ?string
    {
        $userRoles = $user->getRoles();
        $isAdmin = in_array('ROLE_ADMIN', $userRoles);

        if ($route === 'app_user_new') return 'ADMIN_CREATES_USER';
        if ($route === 'app_user_delete') return 'ADMIN_DELETES_USER';
        if ($route === 'app_user_edit') return 'ADMIN_UPDATES_USER';

        if ($route === 'app_profile_edit') return 'USER_UPDATES_PROFILE';

        if ($route === 'app_product_new') return $isAdmin ? 'ADMIN_CREATES_RECORD' : 'STAFF_CREATES_RECORD';
        if ($route === 'app_product_delete') return $isAdmin ? 'ADMIN_DELETES_RECORD' : 'STAFF_DELETES_RECORD';
        if ($route === 'app_product_edit') return $isAdmin ? 'ADMIN_UPDATES_RECORD' : 'STAFF_EDITS_RECORD';

        if ($route === 'app_stock_new') return $isAdmin ? 'ADMIN_CREATES_RECORD' : 'STAFF_CREATES_RECORD';
        if ($route === 'app_stock_delete') return $isAdmin ? 'ADMIN_DELETES_RECORD' : 'STAFF_DELETES_RECORD';
        if ($route === 'app_stock_edit') return $isAdmin ? 'ADMIN_UPDATES_RECORD' : 'STAFF_EDITS_RECORD';

        if ($route === 'app_order_new') return $isAdmin ? 'ADMIN_CREATES_RECORD' : 'STAFF_CREATES_RECORD';
        if ($route === 'app_order_delete') return $isAdmin ? 'ADMIN_DELETES_RECORD' : 'STAFF_DELETES_RECORD';

        return null;
    }

    private function determineTarget(Request $request, string $route, ?string $action, $user): ?string
    {
        $id = $request->attributes->get('id');
        $isAdmin = in_array('ROLE_ADMIN', $user->getRoles());
        $roleLabel = $isAdmin ? 'Admin' : 'Staff';

        if ($route === 'app_user_delete' && $id) {
            $targetUser = $this->em->getRepository(User::class)->find($id);
            if ($targetUser) {
                return "Admin deleted user: {$targetUser->getUsername()} (ID: {$id})";
            }
            return "Admin deleted user (ID: {$id})";
        }

        if ($route === 'app_user_new') {
            $formData = $request->request->all('user');
            $username = $formData['username'] ?? 'Unknown';
            $role = $formData['roles'] ?? 'ROLE_USER';
            $roleName = $role == 'ROLE_ADMIN' ? 'Admin' : ($role == 'ROLE_STAFF' ? 'Staff' : 'User');
            return "Admin created user: {$username} (Role: {$roleName})";
        }

        if ($route === 'app_user_edit' && $id) {
            $targetUser = $this->em->getRepository(User::class)->find($id);
            if ($targetUser) {
                $formData = $request->request->all('user');
                $newRole = $formData['roles'] ?? null;
                $passwordChanged = !empty($formData['password']) ? ' (Password changed)' : '';
                if ($newRole) {
                    $newRoleName = $newRole == 'ROLE_ADMIN' ? 'Admin' : ($newRole == 'ROLE_STAFF' ? 'Staff' : 'User');
                    $oldRole = $targetUser->getRoles()[0] ?? 'ROLE_USER';
                    $oldRoleName = $oldRole == 'ROLE_ADMIN' ? 'Admin' : ($oldRole == 'ROLE_STAFF' ? 'Staff' : 'User');
                    return "Admin updated user: {$targetUser->getUsername()} → Role changed from {$oldRoleName} to {$newRoleName}{$passwordChanged}";
                }
                return "Admin updated user: {$targetUser->getUsername()}{$passwordChanged}";
            }
        }

        if ($route === 'app_product_delete' && $id) {
            $product = $this->em->getRepository(Product::class)->find($id);
            if ($product) {
                return "{$roleLabel} deleted product: {$product->getName()} (ID: {$id})";
            }
            return "{$roleLabel} deleted product (ID: {$id})";
        }

        if ($route === 'app_product_new') {
            $formData = $request->request->all('product');
            $name = $formData['name'] ?? 'New Product';
            $price = $formData['price'] ?? '0';
            return "{$roleLabel} created product: {$name} (Price: \${$price})";
        }

        if ($route === 'app_product_edit' && $id) {
            $product = $this->em->getRepository(Product::class)->find($id);
            if ($product) {
                $formData = $request->request->all('product');
                $newName = $formData['name'] ?? $product->getName();
                $newPrice = $formData['price'] ?? $product->getPrice();
                return "{$roleLabel} edited product: {$newName} (Price: \${$newPrice})";
            }
        }

        if ($route === 'app_stock_delete' && $id) {
            $stock = $this->em->getRepository(Stock::class)->find($id);
            if ($stock) {
                $productName = $stock->getProduct() ? $stock->getProduct()->getName() : 'Unknown Product';
                return "{$roleLabel} deleted stock: {$productName} (Stock ID: {$id})";
            }
            return "{$roleLabel} deleted stock (ID: {$id})";
        }

        if ($route === 'app_stock_new') {
            $formData = $request->request->all('stock');
            $productId = $formData['product'] ?? null;
            $quantity = $formData['stock'] ?? 0;
            if ($productId) {
                $product = $this->em->getRepository(Product::class)->find($productId);
                if ($product) {
                    return "{$roleLabel} created stock: {$product->getName()} x {$quantity} units";
                }
            }
            return "{$roleLabel} created stock (Quantity: {$quantity})";
        }

        if ($route === 'app_stock_edit' && $id) {
            $stock = $this->em->getRepository(Stock::class)->find($id);
            if ($stock) {
                $productName = $stock->getProduct() ? $stock->getProduct()->getName() : 'Unknown Product';
                $formData = $request->request->all('stock');
                $newQuantity = $formData['stock'] ?? $stock->getStock();
                return "{$roleLabel} edited stock: {$productName} → {$newQuantity} units";
            }
        }

        if ($route === 'app_profile_edit') {
            $username = $user->getUsername();
            $formData = $request->request->all('profile');
            $passwordChanged = !empty($formData['plainPassword']['first']) ? ' (Password changed)' : '';
            return "User updated their profile: {$username}{$passwordChanged}";
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
            ->setTarget('User login: ' . $user->getUsername() . ' (ID: ' . $user->getId() . ')');

        try {
            $this->em->persist($log);
            $this->em->flush();
        } catch (\Exception $e) {
            error_log('ActivityLog login error: ' . $e->getMessage());
        }
    }

    public function onLogout(LogoutEvent $event): void
    {
        $token = $event->getToken();
        if (!$token) return;

        $user = $token->getUser();
        if (!$user || !$user instanceof User) return;

        $log = new ActivityLog();
        $log->setUserId($user->getId())
            ->setUsername($user->getUsername())
            ->setRole($this->getUserRole($user))
            ->setAction('LOGOUT')
            ->setTarget('User logout: ' . $user->getUsername() . ' (ID: ' . $user->getId() . ')');

        try {
            $this->em->persist($log);
            $this->em->flush();
        } catch (\Exception $e) {
            error_log('ActivityLog logout error: ' . $e->getMessage());
        }
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