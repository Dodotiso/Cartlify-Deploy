<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

class ProfileUploadController extends AbstractController
{
    #[Route('/profile/upload-picture', name: 'app_profile_upload_picture', methods: ['POST'])]
    public function uploadProfilePicture(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): JsonResponse
    {
        $user = $this->getUser();
        
        if (!$user) {
            return $this->json(['success' => false, 'message' => 'User not authenticated'], 401);
        }

        $uploadedFile = $request->files->get('profile_picture');
        
        if (!$uploadedFile) {
            return $this->json(['success' => false, 'message' => 'No file uploaded'], 400);
        }

        // Validate file type
        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($uploadedFile->getMimeType(), $allowedMimeTypes)) {
            return $this->json([
                'success' => false, 
                'message' => 'Invalid file type. Please upload JPEG, PNG, GIF, or WebP images.'
            ], 400);
        }

        // Validate file size (2MB max)
        if ($uploadedFile->getSize() > 2 * 1024 * 1024) {
            return $this->json([
                'success' => false, 
                'message' => 'File too large. Maximum size is 2MB.'
            ], 400);
        }

        try {
            // Generate unique filename
            $originalFilename = pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME);
            $safeFilename = $slugger->slug($originalFilename);
            $newFilename = $safeFilename . '-' . uniqid() . '.' . $uploadedFile->guessExtension();
            
            // Define upload directory
            $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/profile_pics';
            
            // Create directory if it doesn't exist
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            // Move file to upload directory
            $uploadedFile->move($uploadDir, $newFilename);
            
            // Delete old profile picture if exists
            if ($user->getProfilePicture()) {
                $oldFile = $uploadDir . '/' . $user->getProfilePicture();
                if (file_exists($oldFile)) {
                    unlink($oldFile);
                }
            }
            
            // Update user profile picture in database
            $user->setProfilePicture($newFilename);
            $entityManager->persist($user);
            $entityManager->flush();
            
            return $this->json([
                'success' => true,
                'message' => 'Profile picture updated successfully!',
                'imageUrl' => '/uploads/profile_pics/' . $newFilename
            ]);
            
        } catch (\Exception $e) {
            return $this->json([
                'success' => false, 
                'message' => 'Upload failed: ' . $e->getMessage()
            ], 500);
        }
    }
}