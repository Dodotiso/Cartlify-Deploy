<?php

namespace App\EventListener;

use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\Event\LifecycleEventArgs;

#[AsEntityListener(event: Events::preRemove, entity: Product::class)]
class ProductDeleteListener
{
    public function __construct(private EntityManagerInterface $entityManager) {}

    public function preRemove(Product $product, LifecycleEventArgs $args): void
    {
        // Block delete if any stock has existing orders
        foreach ($product->getStocks() as $stock) {
            if (count($stock->getOrders()) > 0) {
                throw new \RuntimeException(
                    'Cannot delete "' . $product->getName() . '" because it has existing orders.'
                );
            }
        }

        // Delete local image file if it exists
        $image = $product->getImage();
        if ($image && !filter_var($image, FILTER_VALIDATE_URL)) {
            $uploadDir = __DIR__ . '/../../public/uploads';
            $imagePath = $uploadDir . '/' . $image;
            if (file_exists($imagePath)) {
                unlink($imagePath);
            }
        }

        // Remove all linked stock entries first
        foreach ($product->getStocks() as $stock) {
            $this->entityManager->remove($stock);
        }
    }
}