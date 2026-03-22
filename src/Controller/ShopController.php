<?php

namespace App\Controller;

use App\Entity\Product;
use App\Repository\ProductRepository;
use App\Repository\CategoryRepository;
use App\Repository\StockRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class ShopController extends AbstractController
{
    #[Route('/shop', name: 'app_shop')]
    public function index(
        Request $request, 
        ProductRepository $productRepository, 
        CategoryRepository $categoryRepository,
        StockRepository $stockRepository
    ): Response {
        $search = $request->query->get('search', '');
        $categoryId = $request->query->get('category', '');
        $sort = $request->query->get('sort', 'newest');
        $minPrice = $request->query->get('min_price');
        $maxPrice = $request->query->get('max_price');
        
        // Build query with filters
        $queryBuilder = $productRepository->createQueryBuilder('p')
            ->leftJoin('p.category', 'c')
            ->addSelect('c')
            ->leftJoin('p.stocks', 's')
            ->addSelect('s');
        
        if ($search) {
            $queryBuilder->andWhere('p.name LIKE :search OR p.description LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }
        
        if ($categoryId) {
            $queryBuilder->andWhere('p.category = :category')
                ->setParameter('category', $categoryId);
        }
        
        if ($minPrice !== null && $minPrice !== '') {
            $queryBuilder->andWhere('p.price >= :minPrice')
                ->setParameter('minPrice', $minPrice);
        }
        
        if ($maxPrice !== null && $maxPrice !== '') {
            $queryBuilder->andWhere('p.price <= :maxPrice')
                ->setParameter('maxPrice', $maxPrice);
        }
        
        // Apply sorting
        switch ($sort) {
            case 'price_asc':
                $queryBuilder->orderBy('p.price', 'ASC');
                break;
            case 'price_desc':
                $queryBuilder->orderBy('p.price', 'DESC');
                break;
            case 'name_asc':
                $queryBuilder->orderBy('p.name', 'ASC');
                break;
            case 'name_desc':
                $queryBuilder->orderBy('p.name', 'DESC');
                break;
            case 'newest':
            default:
                $queryBuilder->orderBy('p.createdAt', 'DESC');
                break;
        }
        
        $products = $queryBuilder->getQuery()->getResult();
        $categories = $categoryRepository->findAll();
        
        // Get stock statistics for each product
        $productStock = [];
        foreach ($products as $product) {
            $totalStock = 0;
            foreach ($product->getStocks() as $stock) {
                $totalStock += $stock->getStock();
            }
            $productStock[$product->getId()] = $totalStock;
        }
        
        return $this->render('shop/index.html.twig', [
            'products' => $products,
            'categories' => $categories,
            'productStock' => $productStock,
            'search' => $search,
            'selectedCategory' => $categoryId,
            'currentSort' => $sort,
            'minPrice' => $minPrice,
            'maxPrice' => $maxPrice,
        ]);
    }

    #[Route('/shop/product/{id}', name: 'app_shop_product_show')]
    public function show(Product $product, StockRepository $stockRepository): Response
    {
        // Get all stocks for this product
        $stocks = $stockRepository->findBy(['product' => $product]);
        
        // Calculate total available stock
        $totalStock = 0;
        foreach ($stocks as $stock) {
            $totalStock += $stock->getStock();
        }
        
        return $this->render('shop/product_show.html.twig', [
            'product' => $product,
            'stocks' => $stocks,
            'totalStock' => $totalStock,
        ]);
    }
}