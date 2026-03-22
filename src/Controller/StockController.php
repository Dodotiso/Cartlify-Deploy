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
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $stock = new Stock();
        $form = $this->createForm(StockType::class, $stock);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($stock);
            $entityManager->flush();

            $this->addFlash('success', 'Stock created successfully.');
            return $this->redirectToRoute('app_stock_index', [], Response::HTTP_SEE_OTHER);
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
    #[IsGranted('ROLE_STAFF', 'ROLE_ADMIN')]
    public function edit(Request $request, Stock $stock, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(StockType::class, $stock);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'Stock updated successfully.');
            return $this->redirectToRoute('app_stock_index', [], Response::HTTP_SEE_OTHER);
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
                $this->addFlash('error', 'This stock cannot be deleted because it has existing orders. Cancel the orders first.');
                return $this->redirectToRoute('app_stock_index');
            }

            // Safe to delete
            $entityManager->remove($stock);
            $entityManager->flush();
            $this->addFlash('success', 'Stock deleted successfully.');
        }

        return $this->redirectToRoute('app_stock_index');
    }
}