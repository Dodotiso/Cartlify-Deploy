<?php

namespace App\Controller;

use App\Repository\ProductRepository;
use App\Repository\OrderRepository;
use App\Repository\StockRepository;
use App\Repository\UserRepository;
use App\Repository\ActivityLogRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Doctrine\ORM\EntityManagerInterface;

#[Route('/dashboard', name: 'app_dashboard')]
#[IsGranted('ROLE_STAFF')]
class DashboardController extends AbstractController
{
    #[Route('/', name: '', methods: ['GET'])]
    public function index(
        ProductRepository $productRepository,
        OrderRepository $orderRepository,
        StockRepository $stockRepository,
        UserRepository $userRepository,
        ActivityLogRepository $activityLogRepository,
        EntityManagerInterface $entityManager
    ): Response {

        $totalProducts = $productRepository->count([]);
        $totalMarketplaceProducts = $stockRepository->count([]);
        $totalOrders = $orderRepository->count([]);

        $totalIncome = $orderRepository->createQueryBuilder('o')
            ->select('SUM(o.totalAmount) as total')
            ->getQuery()
            ->getSingleScalarResult() ?? 0;

        $totalActivityLogs = $activityLogRepository->count([]);

        // Role-based user count logic
        $users = $userRepository->findAll();

        $totalAdmins = 0;
        $totalStaff = 0;
        $totalUsers = 0; // Only ROLE_USER

        foreach ($users as $user) {
            $roles = $user->getRoles();

            if (in_array('ROLE_ADMIN', $roles)) {
                $totalAdmins++;
            }

            if (in_array('ROLE_STAFF', $roles)) {
                $totalStaff++;
            }

            // Count ROLE_USER only (customer)
            if (in_array('ROLE_USER', $roles) && !in_array('ROLE_ADMIN', $roles) && !in_array('ROLE_STAFF', $roles)) {
                $totalUsers++;
            }
        }

        // Get recent orders for the table
        $recentOrders = $orderRepository->createQueryBuilder('o')
            ->leftJoin('o.stock', 's')
            ->leftJoin('s.product', 'p')
            ->orderBy('o.createdAt', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        // Get monthly sales data for charts (last 6 months)
        $monthlySales = $this->getMonthlySalesData($orderRepository);

        // Get product category distribution
        $productCategories = $this->getProductCategoryData($productRepository, $entityManager);

        // Get stock level data for charts
        $stockLevels = $this->getStockLevelData($stockRepository);

        // Get today's stats
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

        return $this->render('dashboard/index.html.twig', [
            'totalProducts' => $totalProducts,
            'totalMarketplaceProducts' => $totalMarketplaceProducts,
            'totalOrders' => $totalOrders,
            'totalIncome' => $totalIncome ?: 0,
            'totalAdmins' => $totalAdmins,
            'totalStaff' => $totalStaff,
            'totalUsers' => $totalUsers,
            'totalActivityLogs' => $totalActivityLogs,
            
            // Chart data
            'recentOrders' => $recentOrders,
            'monthlySales' => $monthlySales,
            'productCategories' => $productCategories,
            'stockLevels' => $stockLevels,
            
            // Performance metrics
            'todayOrders' => $todayOrders,
            'todaySales' => $todaySales,
            'avgOrderValue' => $avgOrderValue,
            'lowStockCount' => $stockLevels['low_stock'] ?? 0,
        ]);
    }

    /**
     * Real-time updates endpoint for AJAX polling
     */
    #[Route('/realtime-updates', name: '_realtime_updates', methods: ['GET'])]
    public function realtimeUpdates(
        Request $request,
        ProductRepository $productRepository,
        OrderRepository $orderRepository,
        StockRepository $stockRepository,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        
        // Get last update timestamp from request (optional)
        $lastUpdate = $request->headers->get('X-Last-Update', 0);
        
        // Fetch current data
        $totalProducts = $productRepository->count([]);
        $totalOrders = $orderRepository->count([]);
        
        $totalIncome = $orderRepository->createQueryBuilder('o')
            ->select('SUM(o.totalAmount) as total')
            ->getQuery()
            ->getSingleScalarResult() ?? 0;
        
        // Role-based user count logic
        $users = $userRepository->findAll();
        
        $totalAdmins = 0;
        $totalStaff = 0;
        $totalUsers = 0;
        
        foreach ($users as $user) {
            $roles = $user->getRoles();
            
            if (in_array('ROLE_ADMIN', $roles)) {
                $totalAdmins++;
            }
            if (in_array('ROLE_STAFF', $roles)) {
                $totalStaff++;
            }
            if (in_array('ROLE_USER', $roles) && !in_array('ROLE_ADMIN', $roles) && !in_array('ROLE_STAFF', $roles)) {
                $totalUsers++;
            }
        }
        
        // Get today's stats
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
        
        // Get stock levels
        $stockLevels = $this->getStockLevelData($stockRepository);
        
        // Get recent orders for table
        $recentOrdersData = $orderRepository->createQueryBuilder('o')
            ->leftJoin('o.stock', 's')
            ->leftJoin('s.product', 'p')
            ->select('o.id', 'o.quantity', 'o.totalAmount', 'o.createdAt', 'p.name as productName')
            ->orderBy('o.createdAt', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();
        
        // Format recent orders
        $formattedOrders = [];
        foreach ($recentOrdersData as $order) {
            $formattedOrders[] = [
                'id' => $order['id'],
                'productName' => $order['productName'] ?? 'Product Not Available',
                'quantity' => $order['quantity'],
                'totalAmount' => (float) $order['totalAmount'],
                'createdAt' => $order['createdAt'] instanceof \DateTime ? 
                    $order['createdAt']->format('M d, H:i') : 
                    date('M d, H:i', strtotime($order['createdAt']))
            ];
        }
        
        // Get chart data
        $monthlySales = $this->getMonthlySalesData($orderRepository);
        $productCategories = $this->getProductCategoryData($productRepository, $entityManager);
        
        // Generate insight message based on data
        $insightMessage = $this->generateInsightMessage($todayOrders, $todaySales, $stockLevels['low_stock']);
        
        // Prepare response data
        $responseData = [
            'hasUpdates' => true,
            'metrics' => [
                'totalProducts' => $totalProducts,
                'totalOrders' => $totalOrders,
                'totalIncome' => (float) $totalIncome,
                'totalUsers' => $totalUsers,
                'totalAdmins' => $totalAdmins,
                'totalStaff' => $totalStaff,
                'todayOrders' => $todayOrders,
                'todaySales' => (float) $todaySales,
                'avgOrderValue' => (float) $avgOrderValue,
                'lowStockCount' => $stockLevels['low_stock'] ?? 0,
            ],
            'recentOrders' => $formattedOrders,
            'salesTrend' => $monthlySales,
            'productCategories' => $productCategories,
            'stockLevels' => [
                'labels' => ['Low Stock (<10)', 'Medium Stock (10-50)', 'Good Stock (>50)'],
                'data' => [$stockLevels['low_stock'], $stockLevels['medium_stock'], $stockLevels['good_stock']]
            ],
            'insightMessage' => $insightMessage,
        ];
        
        return $this->json($responseData);
    }

    #[Route('/upload-profile-picture', name: 'upload_profile_picture', methods: ['POST'])]
    public function uploadProfilePicture(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $this->getUser();
        
        if (!$user) {
            return $this->json(['success' => false, 'message' => 'User not authenticated'], 401);
        }

        $file = $request->files->get('profile_picture');
        
        if (!$file) {
            return $this->json(['success' => false, 'message' => 'No file uploaded']);
        }

        // Validate file type
        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($file->getMimeType(), $allowedMimeTypes)) {
            return $this->json(['success' => false, 'message' => 'Invalid file type. Please upload JPEG, PNG, GIF, or WebP images.']);
        }

        // Validate file size (2MB max)
        if ($file->getSize() > 2 * 1024 * 1024) {
            return $this->json(['success' => false, 'message' => 'File too large. Maximum size is 2MB.']);
        }

        // Generate unique filename
        $filename = uniqid() . '.' . $file->guessExtension();
        
        // Define upload directory
        $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/profile_pics';
        
        // Create directory if it doesn't exist
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        try {
            // Move file to upload directory
            $file->move($uploadDir, $filename);
            
            // Delete old profile picture if exists
            if ($user->getProfilePicture()) {
                $oldFile = $uploadDir . '/' . $user->getProfilePicture();
                if (file_exists($oldFile)) {
                    unlink($oldFile);
                }
            }
            
            // Update user profile picture in database
            $user->setProfilePicture($filename);
            $entityManager->persist($user);
            $entityManager->flush();
            
            return $this->json([
                'success' => true,
                'message' => 'Profile picture updated successfully!',
                'imageUrl' => '/uploads/profile_pics/' . $filename
            ]);
            
        } catch (\Exception $e) {
            return $this->json([
                'success' => false, 
                'message' => 'Upload failed: ' . $e->getMessage()
            ]);
        }
    }
    #[Route('/update-last-active', name: 'update_last_active', methods: ['POST'])]
     public function updateLastActive(EntityManagerInterface $entityManager): JsonResponse
        {
            $user = $this->getUser();
            if ($user) {
                $user->setLastActive(new \DateTime());
                $entityManager->flush();
                return $this->json(['success' => true]);
            }
    return $this->json(['success' => false], 401);
}

    /**
     * Get monthly sales data for the last 6 months
     */
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
        
        return [
            'labels' => $labels,
            'data' => $data
        ];
    }

    /**
     * Get product category distribution
     */
    private function getProductCategoryData(ProductRepository $productRepository, EntityManagerInterface $entityManager): array
    {
        $labels = [];
        $data = [];
        
        try {
            // Check if Category entity exists
            $metadata = $entityManager->getClassMetadata('App\Entity\Category');
            
            if ($metadata) {
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
            }
            
            // If no categories with products, add a default
            if (empty($labels)) {
                $labels[] = 'All Products';
                $data[] = $productRepository->count([]);
            }
        } catch (\Exception $e) {
            // Fallback if Category entity doesn't exist or query fails
            $totalProducts = $productRepository->count([]);
            if ($totalProducts > 0) {
                $labels = ['All Products'];
                $data = [$totalProducts];
            } else {
                $labels = ['No Products'];
                $data = [0];
            }
        }
        
        return [
            'labels' => $labels,
            'data' => $data
        ];
    }

    /**
     * Get stock level data
     */
    private function getStockLevelData(StockRepository $stockRepository): array
    {
        $allStocks = $stockRepository->findAll();
        $totalStocks = count($allStocks);
        
        $lowStock = 0;
        $mediumStock = 0;
        $goodStock = 0;
        
        foreach ($allStocks as $stock) {
            $stockQuantity = $stock->getStock() ?? 0;
            
            if ($stockQuantity < 10) {
                $lowStock++;
            } elseif ($stockQuantity <= 50) {
                $mediumStock++;
            } else {
                $goodStock++;
            }
        }
        
        return [
            'low_stock' => $lowStock,
            'medium_stock' => $mediumStock,
            'good_stock' => $goodStock,
            'total_stocks' => $totalStocks,
            'data' => [$lowStock, $mediumStock, $goodStock]
        ];
    }

    /**
     * Generate dynamic insight message based on current data
     */
    private function generateInsightMessage(int $todayOrders, float $todaySales, int $lowStockCount): string
    {
        $messages = [];
        
        if ($todayOrders > 10) {
            $messages[] = "🔥 Great sales day! {$todayOrders} orders processed today.";
        } elseif ($todayOrders > 5) {
            $messages[] = "📈 Good sales activity with {$todayOrders} orders today.";
        } elseif ($todayOrders > 0) {
            $messages[] = "🛍️ {$todayOrders} orders placed today. Keep promoting your products!";
        } else {
            $messages[] = "📊 No orders yet today. Consider running a promotion to boost sales.";
        }
        
        if ($todaySales > 5000) {
            $messages[] = "💰 Excellent revenue today! Total: ₱" . number_format($todaySales, 2);
        } elseif ($todaySales > 2000) {
            $messages[] = "💵 Good revenue today: ₱" . number_format($todaySales, 2);
        }
        
        if ($lowStockCount > 5) {
            $messages[] = "⚠️ {$lowStockCount} products are low on stock! Restock soon to avoid shortages.";
        } elseif ($lowStockCount > 0) {
            $messages[] = "📦 {$lowStockCount} items need restocking. Check inventory management.";
        }
        
        if (empty($messages)) {
            return "Store performance is stable. Consider adding more products to boost sales.";
        }
        
        return implode(' ', array_slice($messages, 0, 2)); // Return top 2 insights
    }
}