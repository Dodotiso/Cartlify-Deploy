<?php

namespace App\Controller\Api;

use App\Entity\Cart;
use App\Entity\CartItem;
use App\Entity\Order;
use App\Repository\CartRepository;
use App\Repository\StockRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/cart', name: 'api_mobile_cart_')]
class MobileCartController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private CartRepository $cartRepository,
        private StockRepository $stockRepository,
    ) {}

    // ─────────────────────────────────────────────
    // Helper: get or create cart for logged-in user
    // ─────────────────────────────────────────────
    private function getOrCreateCart(): Cart
    {
        $user = $this->getUser();
        $cart = $this->cartRepository->findOneBy(['user' => $user]);

        if (!$cart) {
            $cart = new Cart();
            $cart->setUser($user);
            $this->em->persist($cart);
            $this->em->flush();
        }

        return $cart;
    }

    // ─────────────────────────────────────────────
    // POST /api/cart/add/{stockId}
    // Body: quantity (int, default 1)
    // ─────────────────────────────────────────────
    #[Route('/add/{stockId}', name: 'add', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function addToCart(int $stockId, Request $request): JsonResponse
    {
        $stock = $this->stockRepository->find($stockId);

        if (!$stock) {
            return $this->json([
                'success' => false,
                'message' => 'Product not found.',
            ], 404);
        }

        // Accept both JSON and form-encoded bodies
        $body     = json_decode($request->getContent(), true);
        $quantity = (int) ($body['quantity'] ?? $request->request->get('quantity', 1));
        $quantity = max(1, $quantity);

        if ($stock->getStock() <= 0) {
            return $this->json([
                'success' => false,
                'message' => 'This product is out of stock.',
            ], 422);
        }

        if ($quantity > $stock->getStock()) {
            return $this->json([
                'success' => false,
                'message' => "Only {$stock->getStock()} item(s) available.",
            ], 422);
        }

        $cart = $this->getOrCreateCart();

        // Check if item already exists in cart
        $existingItem = null;
        foreach ($cart->getCartItems() as $item) {
            if ($item->getStock()->getId() === $stock->getId()) {
                $existingItem = $item;
                break;
            }
        }

        if ($existingItem) {
            $newQty = $existingItem->getQuantity() + $quantity;

            if ($newQty > $stock->getStock()) {
                return $this->json([
                    'success' => false,
                    'message' => "Cannot add more. Only {$stock->getStock()} item(s) in stock.",
                ], 422);
            }

            $existingItem->setQuantity($newQty);
        } else {
            $cartItem = new CartItem();
            $cartItem->setCart($cart);
            $cartItem->setStock($stock);
            $cartItem->setQuantity($quantity);
            $this->em->persist($cartItem);
        }

        $cart->setUpdatedAt(new \DateTime());
        $this->em->flush();

        return $this->json([
            'success'     => true,
            'message'     => "'{$stock->getProductName()}' added to cart!",
            'cart_count'  => $cart->getTotalItems(),
            'product'     => [
                'id'    => $stock->getId(),
                'name'  => $stock->getProductName(),
                'price' => $stock->getProductPrice(),
            ],
        ]);
    }

    // ─────────────────────────────────────────────
    // GET /api/cart
    // Returns full cart with items
    // ─────────────────────────────────────────────
    #[Route('', name: 'get', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function getCart(): JsonResponse
    {
        $cart = $this->getOrCreateCart();
        $items = [];

        foreach ($cart->getCartItems() as $item) {
            $stock   = $item->getStock();
            $product = $stock->getProduct();

            $items[] = [
                'id'           => $item->getId(),
                'stock_id'     => $stock->getId(),
                'product_id'   => $product->getId(),
                'product_name' => $product->getName(),
                'product_image'=> $product->getImage(),
                'unit_price'   => $product->getPrice(),
                'quantity'     => $item->getQuantity(),
                'subtotal'     => $item->getSubtotal(),
                'max_stock'    => $stock->getStock(),
            ];
        }

        return $this->json([
            'success'     => true,
            'items'       => $items,
            'total_items' => $cart->getTotalItems(),
            'subtotal'    => $cart->getSubtotal(),
        ]);
    }

    // ─────────────────────────────────────────────
    // GET /api/cart/count
    // ─────────────────────────────────────────────
    #[Route('/count', name: 'count', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function getCartCount(): JsonResponse
    {
        $cart = $this->cartRepository->findOneBy(['user' => $this->getUser()]);

        return $this->json([
            'count' => $cart ? $cart->getTotalItems() : 0,
        ]);
    }

    // ─────────────────────────────────────────────
    // POST /api/cart/update/{itemId}
    // Body: quantity (int)
    // ─────────────────────────────────────────────
    #[Route('/update/{itemId}', name: 'update', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function updateCartItem(int $itemId, Request $request): JsonResponse
    {
        $cart = $this->getOrCreateCart();
        $cartItem = null;

        foreach ($cart->getCartItems() as $item) {
            if ($item->getId() === $itemId) {
                $cartItem = $item;
                break;
            }
        }

        if (!$cartItem) {
            return $this->json(['success' => false, 'message' => 'Item not found.'], 404);
        }

        $body     = json_decode($request->getContent(), true);
        $quantity = (int) ($body['quantity'] ?? $request->request->get('quantity', 1));
        $quantity = max(1, $quantity);
        $stock    = $cartItem->getStock();

        if ($quantity > $stock->getStock()) {
            return $this->json([
                'success' => false,
                'message' => "Only {$stock->getStock()} item(s) available.",
            ], 422);
        }

        $cartItem->setQuantity($quantity);
        $cart->setUpdatedAt(new \DateTime());
        $this->em->flush();

        return $this->json([
            'success'    => true,
            'message'    => 'Cart updated.',
            'cart_count' => $cart->getTotalItems(),
            'subtotal'   => $cart->getSubtotal(),
        ]);
    }

    // ─────────────────────────────────────────────
    // POST /api/cart/remove/{itemId}
    // ─────────────────────────────────────────────
    #[Route('/remove/{itemId}', name: 'remove', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function removeCartItem(int $itemId): JsonResponse
    {
        $cart = $this->getOrCreateCart();

        foreach ($cart->getCartItems() as $item) {
            if ($item->getId() === $itemId) {
                $cart->removeCartItem($item);
                $this->em->remove($item);
                $cart->setUpdatedAt(new \DateTime());
                $this->em->flush();

                return $this->json([
                    'success'    => true,
                    'message'    => 'Item removed from cart.',
                    'cart_count' => $cart->getTotalItems(),
                    'subtotal'   => $cart->getSubtotal(),
                ]);
            }
        }

        return $this->json(['success' => false, 'message' => 'Item not found.'], 404);
    }

    // ─────────────────────────────────────────────
    // POST /api/cart/clear
    // ─────────────────────────────────────────────
    #[Route('/clear', name: 'clear', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function clearCart(): JsonResponse
    {
        $cart = $this->cartRepository->findOneBy(['user' => $this->getUser()]);

        if ($cart) {
            foreach ($cart->getCartItems() as $item) {
                $this->em->remove($item);
            }
            $cart->setUpdatedAt(new \DateTime());
            $this->em->flush();
        }

        return $this->json(['success' => true, 'message' => 'Cart cleared.']);
    }
}
