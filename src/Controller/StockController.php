<?php

namespace App\Controller;

use App\Entity\Stock;
use App\Form\StockType;
use App\Repository\StockRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;

#[Route('/stock')]
final class StockController extends AbstractController
{
    #[Route('/', name: 'app_stock_index', methods: ['GET'])]
    #[IsGranted('ROLE_STAFF')]
    public function index(StockRepository $stockRepository, Request $request): Response
    {
        $search = $request->query->get('search', '');
        
        if ($search) {
            $stocks = $stockRepository->createQueryBuilder('s')
                ->leftJoin('s.product', 'p')
                ->where('p.name LIKE :search')
                ->orWhere('s.stock LIKE :search')
                ->setParameter('search', '%' . $search . '%')
                ->orderBy('s.id', 'DESC')
                ->getQuery()
                ->getResult();
        } else {
            $stocks = $stockRepository->findAll();
        }
        
        return $this->render('stock/index.html.twig', [
            'stocks' => $stocks,
            'search' => $search,
        ]);
    }

    #[Route('/new', name: 'app_stock_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_STAFF')]
    public function new(Request $request, EntityManagerInterface $entityManager, StockRepository $stockRepository): Response
    {
        $stock = new Stock();
        
        $form = $this->createForm(StockType::class, $stock);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $selectedProduct = $stock->getProduct();
            $stockQuantity = $stock->getStock();
            
            // Validation for stock quantity
            if ($stockQuantity === null || $stockQuantity === '') {
                $this->addFlash('error', '❌ Stock quantity is required! Please enter a stock quantity.');
                return $this->render('stock/new.html.twig', [
                    'form' => $form->createView(),
                    'edit' => false,
                ]);
            }
            
            if (!is_numeric($stockQuantity)) {
                $this->addFlash('error', '❌ Stock quantity must contain numbers only! Please enter a valid number (e.g., 100).');
                return $this->render('stock/new.html.twig', [
                    'form' => $form->createView(),
                    'edit' => false,
                ]);
            }
            
            if ($stockQuantity < 0) {
                $this->addFlash('error', '❌ Stock quantity cannot be negative! Please enter a quantity of 0 or greater.');
                return $this->render('stock/new.html.twig', [
                    'form' => $form->createView(),
                    'edit' => false,
                ]);
            }
            
            if (empty($selectedProduct)) {
                $this->addFlash('error', '❌ Product is required! Please select a product.');
                return $this->render('stock/new.html.twig', [
                    'form' => $form->createView(),
                    'edit' => false,
                ]);
            }
            
            // Check if stock already exists for this product
            $existingStock = $stockRepository->findOneBy(['product' => $selectedProduct]);
            
            if ($existingStock) {
                // Update existing stock instead of creating new one
                $oldQuantity = $existingStock->getStock();
                $newQuantity = $oldQuantity + $stockQuantity;
                $existingStock->setStock($newQuantity);
                $existingStock->setCreatedAt(new \DateTime());
                $entityManager->flush();
                
                $this->addFlash('success', '✅ Stock updated for "' . $selectedProduct->getName() . '"! Added ' . $stockQuantity . ' units. New total: ' . $newQuantity . ' units.');
                return $this->redirectToRoute('app_stock_index');
            }
            
            // No existing stock, create new
            $entityManager->persist($stock);
            $entityManager->flush();

            $this->addFlash('success', '✅ Stock for "' . $stock->getProduct()->getName() . '" created successfully! Quantity: ' . $stockQuantity . ' units.');
            return $this->redirectToRoute('app_stock_index');
        }
        
        // Show ONLY ONE error message at a time
        if ($form->isSubmitted() && !$form->isValid()) {
            $errors = $form->getErrors(true);
            $errorMessage = null;
            
            foreach ($errors as $error) {
                $errorMessage = $error->getMessage();
                break; // Only get the first error
            }
            
            if ($errorMessage) {
                $this->addFlash('error', '❌ ' . $errorMessage);
            } else {
                $this->addFlash('error', '❌ Please check the form. Stock quantity must be a valid number greater than 0.');
            }
        }

        return $this->render('stock/new.html.twig', [
            'form' => $form->createView(),
            'edit' => false,
        ]);
    }

    #[Route('/{id}', name: 'app_stock_show', methods: ['GET'])]
    #[IsGranted('ROLE_STAFF')]
    public function show(Stock $stock): Response
    {
        return $this->render('stock/show.html.twig', [
            'stock' => $stock,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_stock_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_STAFF')]
    public function edit(Request $request, Stock $stock, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(StockType::class, $stock);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $stockQuantity = $stock->getStock();
            
            // Validation for stock quantity
            if ($stockQuantity === null || $stockQuantity === '') {
                $this->addFlash('error', '❌ Stock quantity is required! Please enter a stock quantity.');
                return $this->render('stock/edit.html.twig', [
                    'form' => $form->createView(),
                    'edit' => true,
                    'stock' => $stock,
                ]);
            }
            
            if (!is_numeric($stockQuantity)) {
                $this->addFlash('error', '❌ Stock quantity must contain numbers only! Please enter a valid number (e.g., 100).');
                return $this->render('stock/edit.html.twig', [
                    'form' => $form->createView(),
                    'edit' => true,
                    'stock' => $stock,
                ]);
            }
            
            if ($stockQuantity < 0) {
                $this->addFlash('error', '❌ Stock quantity cannot be negative! Please enter a quantity of 0 or greater.');
                return $this->render('stock/edit.html.twig', [
                    'form' => $form->createView(),
                    'edit' => true,
                    'stock' => $stock,
                ]);
            }
            
            if (empty($stock->getProduct())) {
                $this->addFlash('error', '❌ Product is required! Please select a product.');
                return $this->render('stock/edit.html.twig', [
                    'form' => $form->createView(),
                    'edit' => true,
                    'stock' => $stock,
                ]);
            }
            
            $stock->setCreatedAt(new \DateTime());
            $entityManager->flush();
            $this->addFlash('success', '✅ Stock for "' . $stock->getProduct()->getName() . '" updated successfully! New quantity: ' . $stockQuantity . ' units.');
            return $this->redirectToRoute('app_stock_index');
        }
        
        // Show ONLY ONE error message at a time
        if ($form->isSubmitted() && !$form->isValid()) {
            $errors = $form->getErrors(true);
            $errorMessage = null;
            
            foreach ($errors as $error) {
                $errorMessage = $error->getMessage();
                break; // Only get the first error
            }
            
            if ($errorMessage) {
                $this->addFlash('error', '❌ ' . $errorMessage);
            } else {
                $this->addFlash('error', '❌ Please check the form. Stock quantity must be a valid number.');
            }
        }

        return $this->render('stock/edit.html.twig', [
            'form' => $form->createView(),
            'edit' => true,
            'stock' => $stock,
        ]);
    }

    #[Route('/{id}', name: 'app_stock_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(Request $request, Stock $stock, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$stock->getId(), $request->request->get('_token'))) {

            // Check if stock has existing orders
            $orders = $stock->getOrders();
            if ($orders && count($orders) > 0) {
                $this->addFlash('error', '⚠️ This stock cannot be deleted because it has existing orders. Cancel the orders first.');
                return $this->redirectToRoute('app_stock_index');
            }

            $productName = $stock->getProduct()->getName();
            
            // Safe to delete
            $entityManager->remove($stock);
            $entityManager->flush();
            $this->addFlash('success', '🗑️ Stock for "' . $productName . '" deleted successfully!');
        }

        return $this->redirectToRoute('app_stock_index');
    }
}