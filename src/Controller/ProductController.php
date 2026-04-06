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
        $product->setCreatedAt(new \DateTimeImmutable());
        
        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $imageFile = $form->get('imageFile')->getData();
            $imageUrl = $form->get('image')->getData();
            
            // Check if both image file and image URL are provided
            if ($imageFile && !empty($imageUrl)) {
                $this->addFlash('error', '⚠️ Please use ONLY ONE image source. Either upload a file OR provide an image URL, not both.');
                return $this->render('product/new.html.twig', [
                    'product' => $product,
                    'form' => $form->createView(),
                ]);
            }
            
            // Validation for required fields
            if (empty($product->getName())) {
                $this->addFlash('error', '❌ Product name is required. Please fill in the product name field.');
                return $this->render('product/new.html.twig', [
                    'product' => $product,
                    'form' => $form->createView(),
                ]);
            }
            
            if (empty($product->getDescription())) {
                $this->addFlash('error', '❌ Product description is required. Please fill in the product description field.');
                return $this->render('product/new.html.twig', [
                    'product' => $product,
                    'form' => $form->createView(),
                ]);
            }
            
            // Check if description contains numbers
            if (preg_match('/\d/', $product->getDescription())) {
                $this->addFlash('error', '❌ Numbers are not allowed in Product Description! Please use words only.');
                return $this->render('product/new.html.twig', [
                    'product' => $product,
                    'form' => $form->createView(),
                ]);
            }
            
            if (empty($product->getPrice()) || $product->getPrice() <= 0) {
                $this->addFlash('error', '❌ Product price is required. Please enter a valid price greater than 0.');
                return $this->render('product/new.html.twig', [
                    'product' => $product,
                    'form' => $form->createView(),
                ]);
            }
            
            // Check if price contains letters
            if (is_string($product->getPrice()) && preg_match('/[a-zA-Z]/', $product->getPrice())) {
                $this->addFlash('error', '❌ Product price must contain numbers only! Please enter a valid price (e.g., 99.99).');
                return $this->render('product/new.html.twig', [
                    'product' => $product,
                    'form' => $form->createView(),
                ]);
            }
            
            if (empty($product->getCategory())) {
                $this->addFlash('error', '❌ Category is required. Please select a category for the product.');
                return $this->render('product/new.html.twig', [
                    'product' => $product,
                    'form' => $form->createView(),
                ]);
            }
            
            if (!$imageFile && empty($imageUrl)) {
                $this->addFlash('error', '❌ Image is required. Please upload an image file OR enter an image URL.');
                return $this->render('product/new.html.twig', [
                    'product' => $product,
                    'form' => $form->createView(),
                ]);
            }
            
            // Validate URL format if provided
            if (!empty($imageUrl) && !filter_var($imageUrl, FILTER_VALIDATE_URL)) {
                $this->addFlash('error', '❌ Invalid image URL! Please enter a valid URL starting with http:// or https://');
                return $this->render('product/new.html.twig', [
                    'product' => $product,
                    'form' => $form->createView(),
                ]);
            }
            
            if ($imageFile) {
                // Validate file type
                $allowedTypes = ['image/png', 'image/jpeg', 'image/jpg', 'image/gif', 'image/webp', 'image/avif'];
                if (!in_array($imageFile->getMimeType(), $allowedTypes)) {
                    $this->addFlash('error', '❌ Invalid file type! Only PNG, JPEG, JPG, GIF, WEBP, and AVIF images are allowed.');
                    return $this->render('product/new.html.twig', [
                        'product' => $product,
                        'form' => $form->createView(),
                    ]);
                }
                
                // Validate file size (max 5MB)
                if ($imageFile->getSize() > 5 * 1024 * 1024) {
                    $this->addFlash('error', '❌ Image file is too large! Maximum size is 5MB.');
                    return $this->render('product/new.html.twig', [
                        'product' => $product,
                        'form' => $form->createView(),
                    ]);
                }
                
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();

                try {
                    $uploadDir = $this->getParameter('uploads_directory');
                    if (!file_exists($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }
                    
                    $imageFile->move($uploadDir, $newFilename);
                    $product->setImage($newFilename);
                    
                    $entityManager->persist($product);
                    $entityManager->flush();
                    
                    $this->addFlash('success', '✅ Product "' . $product->getName() . '" created successfully!');
                    return $this->redirectToRoute('app_product_index');
                    
                } catch (FileException $e) {
                    $this->addFlash('error', '❌ Failed to upload image: ' . $e->getMessage());
                    return $this->render('product/new.html.twig', [
                        'product' => $product,
                        'form' => $form->createView(),
                    ]);
                }
            } elseif (!empty($imageUrl)) {
                $product->setImage($imageUrl);
                $entityManager->persist($product);
                $entityManager->flush();
                
                $this->addFlash('success', '✅ Product "' . $product->getName() . '" created successfully!');
                return $this->redirectToRoute('app_product_index');
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
        // Store the current image BEFORE form binding
        $currentImage = $product->getImage();
        
        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $imageFile = $form->get('imageFile')->getData();
            $imageUrl = $form->get('image')->getData();
            
            // Check if both image file and image URL are provided
            if ($imageFile && !empty($imageUrl)) {
                $this->addFlash('error', '⚠️ Please use ONLY ONE image source. Either upload a file OR provide an image URL, not both.');
                $product->setImage($currentImage);
                return $this->render('product/edit.html.twig', [
                    'product' => $product,
                    'form' => $form->createView(),
                ]);
            }
            
            // Validation for required fields
            if (empty($product->getName())) {
                $this->addFlash('error', '❌ Product name is required. Please fill in the product name field.');
                $product->setImage($currentImage);
                return $this->render('product/edit.html.twig', [
                    'product' => $product,
                    'form' => $form->createView(),
                ]);
            }
            
            if (empty($product->getDescription())) {
                $this->addFlash('error', '❌ Product description is required. Please fill in the product description field.');
                $product->setImage($currentImage);
                return $this->render('product/edit.html.twig', [
                    'product' => $product,
                    'form' => $form->createView(),
                ]);
            }
            
            // Check if description contains numbers
            if (preg_match('/\d/', $product->getDescription())) {
                $this->addFlash('error', '❌ Numbers are not allowed in Product Description! Please use words only.');
                $product->setImage($currentImage);
                return $this->render('product/edit.html.twig', [
                    'product' => $product,
                    'form' => $form->createView(),
                ]);
            }
            
            if (empty($product->getPrice()) || $product->getPrice() <= 0) {
                $this->addFlash('error', '❌ Product price is required. Please enter a valid price greater than 0.');
                $product->setImage($currentImage);
                return $this->render('product/edit.html.twig', [
                    'product' => $product,
                    'form' => $form->createView(),
                ]);
            }
            
            // Check if price contains letters
            if (is_string($product->getPrice()) && preg_match('/[a-zA-Z]/', $product->getPrice())) {
                $this->addFlash('error', '❌ Product price must contain numbers only! Please enter a valid price (e.g., 99.99).');
                $product->setImage($currentImage);
                return $this->render('product/edit.html.twig', [
                    'product' => $product,
                    'form' => $form->createView(),
                ]);
            }
            
            if (empty($product->getCategory())) {
                $this->addFlash('error', '❌ Category is required. Please select a category for the product.');
                $product->setImage($currentImage);
                return $this->render('product/edit.html.twig', [
                    'product' => $product,
                    'form' => $form->createView(),
                ]);
            }
            
            // CHECK IF IMAGE IS EMPTY - SHOW FLASH MESSAGE
            // If no file uploaded AND no URL provided AND no existing image
            if (!$imageFile && (empty($imageUrl) || $imageUrl === '') && empty($currentImage)) {
                $this->addFlash('error', '❌ Image is required! Please upload an image file OR enter an image URL.');
                return $this->render('product/edit.html.twig', [
                    'product' => $product,
                    'form' => $form->createView(),
                ]);
            }
            
            // If no file uploaded AND no URL provided BUT has existing image - keep existing image (no error)
            if (!$imageFile && (empty($imageUrl) || $imageUrl === '') && !empty($currentImage)) {
                // Keep existing image - this is fine, no error needed
                $product->setImage($currentImage);
                $entityManager->flush();
                $this->addFlash('success', '✅ Product "' . $product->getName() . '" updated successfully!');
                return $this->redirectToRoute('app_product_index');
            }
            
            // Validate URL format if provided
            if (!empty($imageUrl) && !filter_var($imageUrl, FILTER_VALIDATE_URL)) {
                $this->addFlash('error', '❌ Invalid image URL! Please enter a valid URL starting with http:// or https://');
                $product->setImage($currentImage);
                return $this->render('product/edit.html.twig', [
                    'product' => $product,
                    'form' => $form->createView(),
                ]);
            }
            
            // Handle image upload
            if ($imageFile) {
                // Validate file type
                $allowedTypes = ['image/png', 'image/jpeg', 'image/jpg', 'image/gif', 'image/webp', 'image/avif'];
                if (!in_array($imageFile->getMimeType(), $allowedTypes)) {
                    $this->addFlash('error', '❌ Invalid file type! Only PNG, JPEG, JPG, GIF, WEBP, and AVIF images are allowed.');
                    $product->setImage($currentImage);
                    return $this->render('product/edit.html.twig', [
                        'product' => $product,
                        'form' => $form->createView(),
                    ]);
                }
                
                // Validate file size (max 5MB)
                if ($imageFile->getSize() > 5 * 1024 * 1024) {
                    $this->addFlash('error', '❌ Image file is too large! Maximum size is 5MB.');
                    $product->setImage($currentImage);
                    return $this->render('product/edit.html.twig', [
                        'product' => $product,
                        'form' => $form->createView(),
                    ]);
                }
                
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();

                try {
                    $uploadDir = $this->getParameter('uploads_directory');
                    if (!file_exists($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }
                    
                    $imageFile->move($uploadDir, $newFilename);
                    $product->setImage($newFilename);
                    
                    // Delete old image file if it exists and is not a URL
                    if ($currentImage && !filter_var($currentImage, FILTER_VALIDATE_URL)) {
                        $oldFilePath = $uploadDir . '/' . $currentImage;
                        if (file_exists($oldFilePath)) {
                            unlink($oldFilePath);
                        }
                    }
                    
                    $entityManager->flush();
                    $this->addFlash('success', '✅ Product "' . $product->getName() . '" updated successfully!');
                    return $this->redirectToRoute('app_product_index');
                    
                } catch (FileException $e) {
                    $this->addFlash('error', '❌ Failed to upload image: ' . $e->getMessage());
                    $product->setImage($currentImage);
                    return $this->render('product/edit.html.twig', [
                        'product' => $product,
                        'form' => $form->createView(),
                    ]);
                }
            } elseif (!empty($imageUrl)) {
                // URL provided - use it
                $product->setImage($imageUrl);
                
                // Delete old image file if it exists and is not a URL
                if ($currentImage && !filter_var($currentImage, FILTER_VALIDATE_URL)) {
                    $oldFilePath = $this->getParameter('uploads_directory') . '/' . $currentImage;
                    if (file_exists($oldFilePath)) {
                        unlink($oldFilePath);
                    }
                }
                
                $entityManager->flush();
                $this->addFlash('success', '✅ Product "' . $product->getName() . '" updated successfully!');
                return $this->redirectToRoute('app_product_index');
            }
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
            $productName = $product->getName();
            
            foreach ($product->getStocks() as $stock) {
                if (count($stock->getOrders()) > 0) {
                    $this->addFlash('error', '⚠️ Cannot delete "' . $productName . '" because it has existing orders!');
                    return $this->redirectToRoute('app_product_index');
                }
            }

            $image = $product->getImage();
            if ($image && !filter_var($image, FILTER_VALIDATE_URL)) {
                $imagePath = $this->getParameter('uploads_directory') . '/' . $image;
                if (file_exists($imagePath)) {
                    unlink($imagePath);
                }
            }

            foreach ($product->getStocks() as $stock) {
                $entityManager->remove($stock);
            }

            $entityManager->remove($product);
            $entityManager->flush();

            $this->addFlash('success', '🗑️ Product "' . $productName . '" deleted successfully!');
        }

        return $this->redirectToRoute('app_product_index');
    }

    #[Route('/debug/uploads', name: 'app_debug_uploads')]
    #[IsGranted('ROLE_ADMIN')]
    public function debugUploads(ProductRepository $productRepository): Response
    {
        $uploadDir = $this->getParameter('uploads_directory');
        $files = [];
        
        if (file_exists($uploadDir)) {
            $files = scandir($uploadDir);
            $files = array_diff($files, ['.', '..']);
        }
        
        $products = $productRepository->findAll();
        $productImages = [];
        foreach ($products as $product) {
            $image = $product->getImage();
            $productImages[] = [
                'id' => $product->getId(),
                'name' => $product->getName(),
                'image' => $image,
                'is_url' => $image && filter_var($image, FILTER_VALIDATE_URL) ? 'Yes' : 'No',
                'file_exists' => ($image && !filter_var($image, FILTER_VALIDATE_URL)) ? 
                    (file_exists($uploadDir . '/' . $image) ? 'Yes' : 'No') : 'N/A'
            ];
        }
        
        return $this->json([
            'upload_directory' => $uploadDir,
            'directory_exists' => file_exists($uploadDir) ? 'Yes' : 'No',
            'directory_writable' => is_writable($uploadDir) ? 'Yes' : 'No',
            'files_in_directory' => array_values($files),
            'products' => $productImages
        ]);
    }
}