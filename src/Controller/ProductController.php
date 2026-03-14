<?php

namespace App\Controller;

use App\Entity\Product;
use App\Form\ProductType;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;

#[Route('/product')]
class ProductController extends AbstractController
{
    #[Route('/', name: 'app_product_index', methods: ['GET'])]
    #[IsGranted('ROLE_STAFF')]
    public function index(ProductRepository $productRepository, Request $request): Response
    {
        $search = $request->query->get('search', '');
        
        if ($search) {
            $products = $productRepository->createQueryBuilder('p')
                ->leftJoin('p.category', 'c')
                ->where('p.name LIKE :search')
                ->orWhere('p.description LIKE :search')
                ->orWhere('c.category LIKE :search')
                ->setParameter('search', '%' . $search . '%')
                ->orderBy('p.createdAt', 'DESC')
                ->getQuery()
                ->getResult();
        } else {
            $products = $productRepository->findAll();
        }
        
        return $this->render('product/index.html.twig', [
            'products' => $products,
            'search' => $search,
        ]);
    }

    #[Route('/new', name: 'app_product_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_STAFF')]
    public function new(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $product = new Product();
        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Get image data
            $imageFile = $form->get('imageFile')->getData();
            $imageUrl = $form->get('image')->getData();
            
            // Check if either image file or URL is provided
            if (!$imageFile && empty($imageUrl)) {
                $this->addFlash('error', 'Image is required. Please provide either an image file OR enter an image URL.');
                return $this->render('product/new.html.twig', [
                    'product' => $product,
                    'form' => $form->createView(),
                ]);
            }
            
            // Handle file upload if provided
            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();

                try {
                    // Move the file to the uploads directory
                    $imageFile->move(
                        $this->getParameter('uploads_directory'),
                        $newFilename
                    );
                    // Store just the filename in database
                    $product->setImage($newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'Failed to upload image file. Please try again.');
                    return $this->render('product/new.html.twig', [
                        'product' => $product,
                        'form' => $form->createView(),
                    ]);
                }
            } elseif (!empty($imageUrl)) {
                // Use the provided URL directly
                $product->setImage($imageUrl);
            }

            // Set createdAt if not already set
            if (!$product->getCreatedAt()) {
                $product->setCreatedAt(new \DateTimeImmutable());
            }

            try {
                $entityManager->persist($product);
                $entityManager->flush();

                $this->addFlash('success', 'Product added successfully.');
                return $this->redirectToRoute('app_product_index');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error saving product: ' . $e->getMessage());
                return $this->render('product/new.html.twig', [
                    'product' => $product,
                    'form' => $form->createView(),
                ]);
            }
        }

        return $this->render('product/new.html.twig', [
            'product' => $product,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_product_show', methods: ['GET'])]
    #[IsGranted('ROLE_STAFF')]
    public function show(Product $product): Response
    {
        return $this->render('product/show.html.twig', [
            'product' => $product,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_product_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_STAFF')]
    public function edit(Request $request, Product $product, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Handle file upload
            $imageFile = $form->get('imageFile')->getData();
            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();

                try {
                    $imageFile->move(
                        $this->getParameter('uploads_directory'),
                        $newFilename
                );
                    $product->setImage($newFilename);
                } catch (FileException $e) {
                    throw $e;
                }
            }

            $entityManager->flush();
            $this->addFlash('success', 'Product updated successfully.');

            return $this->redirectToRoute('app_product_index');
        }

        return $this->render('product/edit.html.twig', [
            'product' => $product,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_product_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(Request $request, Product $product, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$product->getId(), $request->request->get('_token'))) {
            // Check for dependent orders via stocks
            foreach ($product->getStocks() as $stock) {
                if (count($stock->getOrders()) > 0) {
                    $this->addFlash('error', 'This product cannot be deleted because some of its stocks have existing orders. Cancel the orders first.');
                    return $this->redirectToRoute('app_product_index');
                }
            }

            // Delete all related stocks
            foreach ($product->getStocks() as $stock) {
                $entityManager->remove($stock);
            }

            // Delete product
            $entityManager->remove($product);
            $entityManager->flush();

            $this->addFlash('success', 'Product and its related stocks have been deleted successfully.');
        }

        return $this->redirectToRoute('app_product_index');
    }
}