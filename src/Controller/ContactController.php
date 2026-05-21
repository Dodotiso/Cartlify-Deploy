<?php

namespace App\Controller;

use App\Service\ContactService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ContactController extends AbstractController
{
    #[Route('/contact', name: 'app_contact')]
    public function index(Request $request, ContactService $contactService): Response
    {
        $success = null;
        $error = null;
        
        if ($request->isMethod('POST')) {
            $name = $request->request->get('name');
            $email = $request->request->get('email');
            $phone = $request->request->get('phone');
            $subject = $request->request->get('subject');
            $message = $request->request->get('message');
            $newsletter = $request->request->get('newsletter') === 'yes';
            
            // Validate
            if (empty($name) || empty($email) || empty($subject) || empty($message)) {
                $error = 'Please fill in all required fields.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Please enter a valid email address.';
            } else {
                try {
                    // Save to database
                    $contact = $contactService->saveContact([
                        'name' => $name,
                        'email' => $email,
                        'phone' => $phone,
                        'subject' => $subject,
                        'message' => $message,
                        'newsletter' => $newsletter
                    ]);
                    
                    // Send admin notification
                    $contactService->sendAdminNotification($contact);
                    
                    // Send user confirmation
                    $contactService->sendUserConfirmation($contact);
                    
                    $success = 'Your message has been sent successfully! We\'ll get back to you soon.';
                    
                    // Redirect to prevent form resubmission
                    return $this->redirectToRoute('app_contact', ['success' => 1]);
                    
                } catch (\Exception $e) {
                    $error = 'There was a problem sending your message. Please try again later.';
                    error_log('Contact Form Error: ' . $e->getMessage());
                }
            }
        }
        
        return $this->render('contact/index.html.twig', [
            'success' => $success ?? ($request->query->get('success') ? 'Your message has been sent successfully! We\'ll get back to you soon.' : null),
            'error' => $error
        ]);
    }
    
    #[Route('/test-mail', name: 'test_mail')]
    public function testMail(ContactService $contactService): Response
    {
        try {
            // Test the mailer
            $testContact = new \App\Entity\Contact();
            $testContact->setName('Test User')
                       ->setEmail('arielbensing22@gmail.com')
                       ->setSubject('Test Message')
                       ->setMessage('This is a test email from Cartlify.');
            
            $contactService->sendUserConfirmation($testContact);
            
            return new Response('Email sent successfully! Check your inbox.');
        } catch (\Exception $e) {
            return new Response('Error: ' . $e->getMessage());
        }
    }
    
}