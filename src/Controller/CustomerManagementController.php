<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;

#[Route('/customer/management')]
#[IsGranted('ROLE_STAFF')]
final class CustomerManagementController extends AbstractController
{
    #[Route('/', name: 'app_customer_management_index', methods: ['GET'])]
    public function index(EntityManagerInterface $em, Request $request): Response
    {
        $search = $request->query->get('search', '');
        $status = $request->query->get('status', 'all');
        
        $query = $em->getRepository(User::class)->createQueryBuilder('u')
            ->where('u.roles LIKE :role')
            ->setParameter('role', '%ROLE_USER%');
        
        if ($search) {
            $query->andWhere('u.username LIKE :search OR u.email LIKE :search OR u.phone LIKE :search')
                  ->setParameter('search', '%' . $search . '%');
        }
        
        if ($status === 'active') {
            $query->andWhere('u.isActive = :active')
                  ->setParameter('active', true);
        } elseif ($status === 'inactive') {
            $query->andWhere('u.isActive = :active')
                  ->setParameter('active', false);
        }
        
        $customers = $query->orderBy('u.createdAt', 'DESC')
                           ->getQuery()
                           ->getResult();
        
        // Get stats
        $totalCustomers = $em->getRepository(User::class)->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.roles LIKE :role')
            ->setParameter('role', '%ROLE_USER%')
            ->getQuery()
            ->getSingleScalarResult();
            
        $activeCustomers = $em->getRepository(User::class)->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.roles LIKE :role')
            ->andWhere('u.isActive = :active')
            ->setParameter('role', '%ROLE_USER%')
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();
            
        $newThisMonth = $em->getRepository(User::class)->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.roles LIKE :role')
            ->andWhere('u.createdAt >= :startOfMonth')
            ->setParameter('role', '%ROLE_USER%')
            ->setParameter('startOfMonth', new \DateTime('first day of this month'))
            ->getQuery()
            ->getSingleScalarResult();
        
        return $this->render('customer_management/index.html.twig', [
            'customers' => $customers,
            'search' => $search,
            'status' => $status,
            'totalCustomers' => $totalCustomers,
            'activeCustomers' => $activeCustomers,
            'newThisMonth' => $newThisMonth,
        ]);
    }
    
    #[Route('/{id}', name: 'app_customer_management_show', methods: ['GET'])]
    public function show(User $user, OrderRepository $orderRepo): Response
    {
        if (!in_array('ROLE_USER', $user->getRoles())) {
            throw $this->createNotFoundException('Invalid customer');
        }
        
        $orders = $orderRepo->findBy(['customer' => $user], ['createdAt' => 'DESC']);
        $totalSpent = array_sum(array_map(fn($o) => $o->getTotalAmount(), $orders));
        $orderCount = count($orders);
        $completedOrders = count(array_filter($orders, fn($o) => $o->getOrderStatus() === 'completed'));
        
        return $this->render('customer_management/show.html.twig', [
            'customer' => $user,
            'orders' => $orders,
            'totalSpent' => $totalSpent,
            'orderCount' => $orderCount,
            'completedOrders' => $completedOrders,
        ]);
    }
    
    #[Route('/{id}/toggle-status', name: 'app_customer_management_toggle_status', methods: ['POST'])]
    public function toggleStatus(User $user, EntityManagerInterface $em, Request $request): Response
    {
        if (!in_array('ROLE_USER', $user->getRoles())) {
            throw $this->createNotFoundException('Invalid customer');
        }
        
        if ($this->isCsrfTokenValid('toggle' . $user->getId(), $request->request->get('_token'))) {
            $user->setIsActive(!$user->isActive());
            $em->flush();
            
            $status = $user->isActive() ? 'activated' : 'deactivated';
            $this->addFlash('success', 'Customer ' . $status . ' successfully!');
        }
        
        return $this->redirectToRoute('app_customer_management_index');
    }
    
    #[Route('/{id}/delete', name: 'app_customer_management_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(User $user, Request $request, EntityManagerInterface $em): Response
    {
        if (!in_array('ROLE_USER', $user->getRoles())) {
            throw $this->createNotFoundException('Invalid customer');
        }
        
        if ($this->isCsrfTokenValid('delete' . $user->getId(), $request->request->get('_token'))) {
            $em->remove($user);
            $em->flush();
            $this->addFlash('success', 'Customer deleted successfully!');
        }
        
        return $this->redirectToRoute('app_customer_management_index');
    }
    
    #[Route('/export', name: 'app_customer_management_export', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function export(EntityManagerInterface $em): Response
    {
        $customers = $em->getRepository(User::class)->createQueryBuilder('u')
            ->where('u.roles LIKE :role')
            ->setParameter('role', '%ROLE_USER%')
            ->orderBy('u.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
        
        $csvData = "ID,Username,Email,Phone,Address,Status,Registered Date\n";
        foreach ($customers as $customer) {
            $csvData .= sprintf(
                "%d,%s,%s,%s,%s,%s,%s\n",
                $customer->getId(),
                $customer->getUsername(),
                $customer->getEmail(),
                $customer->getPhone() ?? 'N/A',
                str_replace(',', ' ', $customer->getAddress() ?? 'N/A'),
                $customer->isActive() ? 'Active' : 'Inactive',
                $customer->getCreatedAt()->format('Y-m-d')
            );
        }
        
        $response = new Response($csvData);
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="customers_' . date('Y-m-d') . '.csv"');
        
        return $response;
    }
}