<?php

namespace App\Controller;

use App\Repository\ProductRepository;
use App\Repository\OrderRepository;
use App\Repository\StockRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;

class ApiDashboardController extends AbstractController
{
    #[Route('/api/dashboard/stats', name: 'api_dashboard_stats', methods: ['GET'])]
    public function stats(
        ProductRepository $productRepository,
        OrderRepository $orderRepository,
        StockRepository $stockRepository,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        
        $totalProducts = $productRepository->count([]);
        $totalOrders = $orderRepository->count([]);
        
        $totalIncome = $orderRepository->createQueryBuilder('o')
            ->select('SUM(o.totalAmount) as total')
            ->getQuery()
            ->getSingleScalarResult() ?? 0;
        
        // Count users by role
        $users = $userRepository->findAll();
        $totalStaff = 0;
        $totalUsers = 0;
        
        foreach ($users as $user) {
            $roles = $user->getRoles();
            if (in_array('ROLE_STAFF', $roles) || in_array('ROLE_ADMIN', $roles)) {
                $totalStaff++;
            } else {
                $totalUsers++;
            }
        }
        
        // Today's stats
        $today = new \DateTime('today');
        $tomorrow = new \DateTime('tomorrow');
        
        $todayOrders = $orderRepository->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->where('o.createdAt BETWEEN :start AND :end')
            ->setParameter('start', $today)
            ->setParameter('end', $tomorrow)
            ->getQuery()
            ->getSingleScalarResult() ?? 0;
        
        $todaySales = $orderRepository->createQueryBuilder('o')
            ->select('SUM(o.totalAmount)')
            ->where('o.createdAt BETWEEN :start AND :end')
            ->setParameter('start', $today)
            ->setParameter('end', $tomorrow)
            ->getQuery()
            ->getSingleScalarResult() ?? 0;
        
        $avgOrderValue = $totalOrders > 0 ? $totalIncome / $totalOrders : 0;
        
        // Low stock count
        $allStocks = $stockRepository->findAll();
        $lowStockCount = 0;
        $mediumStockCount = 0;
        $goodStockCount = 0;
        foreach ($allStocks as $stock) {
            $qty = $stock->getStock() ?? 0;
            if ($qty < 10) {
                $lowStockCount++;
            } elseif ($qty <= 50) {
                $mediumStockCount++;
            } else {
                $goodStockCount++;
            }
        }
        
        // Monthly sales (last 6 months)
        $monthlySales = $this->getMonthlySalesData($orderRepository);
        
        // Product categories
        $productCategories = $this->getProductCategoryData($productRepository, $entityManager);
        
        return $this->json([
            'metrics' => [
                'totalProducts' => $totalProducts,
                'totalOrders' => $totalOrders,
                'totalIncome' => (float) $totalIncome,
                'totalUsers' => $totalUsers,
                'totalStaff' => $totalStaff,
                'todayOrders' => $todayOrders,
                'todaySales' => (float) $todaySales,
                'avgOrderValue' => (float) $avgOrderValue,
                'lowStockCount' => $lowStockCount,
            ],
            'salesTrend' => $monthlySales,
            'productCategories' => $productCategories,
            'stockLevels' => [
                'labels' => ['Low Stock (<10)', 'Medium Stock (10-50)', 'Good Stock (>50)'],
                'data' => [$lowStockCount, $mediumStockCount, $goodStockCount]
            ],
            'insightMessage' => $lowStockCount > 0 
                ? "⚠️ {$lowStockCount} products are low on stock!" 
                : "✅ All stock levels are good."
        ]);
    }

    private function getMonthlySalesData(OrderRepository $orderRepository): array
    {
        $currentDate = new \DateTime();
        $labels = [];
        $data = [];
        
        for ($i = 5; $i >= 0; $i--) {
            $monthDate = clone $currentDate;
            $monthDate->modify("-$i months");
            
            $monthStart = new \DateTime($monthDate->format('Y-m-01 00:00:00'));
            $monthEnd = new \DateTime($monthDate->format('Y-m-t 23:59:59'));
            
            $monthlyTotal = $orderRepository->createQueryBuilder('o')
                ->select('SUM(o.totalAmount) as total')
                ->where('o.createdAt BETWEEN :start AND :end')
                ->setParameter('start', $monthStart)
                ->setParameter('end', $monthEnd)
                ->getQuery()
                ->getSingleScalarResult() ?? 0;
            
            $labels[] = $monthDate->format('M');
            $data[] = (float) $monthlyTotal;
        }
        
        return ['labels' => $labels, 'data' => $data];
    }

    private function getProductCategoryData(ProductRepository $productRepository, EntityManagerInterface $entityManager): array
    {
        $labels = [];
        $data = [];
        
        try {
            $categories = $entityManager->createQuery(
                'SELECT c.category as name, COUNT(p.id) as count 
                 FROM App\Entity\Category c 
                 LEFT JOIN c.products p 
                 GROUP BY c.id
                 ORDER BY count DESC'
            )->getResult();
            
            foreach ($categories as $category) {
                if ($category['count'] > 0) {
                    $labels[] = $category['name'];
                    $data[] = $category['count'];
                }
            }
            
            if (empty($labels)) {
                $labels[] = 'All Products';
                $data[] = $productRepository->count([]);
            }
        } catch (\Exception $e) {
            $labels = ['All Products'];
            $data = [$productRepository->count([])];
        }
        
        return ['labels' => $labels, 'data' => $data];
    }
}