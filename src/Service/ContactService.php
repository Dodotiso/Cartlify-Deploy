<?php

namespace App\Service;

use App\Entity\Contact;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;

class ContactService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MailerInterface $mailer
    ) {}

    public function saveContact(array $data): Contact
    {
        $contact = new Contact();
        $contact->setName($data['name']);
        $contact->setEmail($data['email']);
        $contact->setPhone($data['phone'] ?? null);
        $contact->setSubject($data['subject']);
        $contact->setMessage($data['message']);
        $contact->setNewsletter($data['newsletter'] ?? false);
        
        $this->entityManager->persist($contact);
        $this->entityManager->flush();
        
        return $contact;
    }

    public function sendAdminNotification(Contact $contact): void
    {
        $fromEmail = 'johnantonyamil@gmail.com';
        
        $email = (new Email())
            ->from(new Address($fromEmail, 'Cartlify'))
            ->to(new Address('johnantonyamil@gmail.com'))
            ->replyTo(new Address($contact->getEmail(), $contact->getName()))
            ->subject("📬 New Contact Form Submission: {$contact->getSubject()}")
            ->html($this->getAdminEmailTemplate($contact));
        
        $this->mailer->send($email);
    }

    public function sendUserConfirmation(Contact $contact): void
    {
        $fromEmail = 'johnantonyamil@gmail.com';
        
        $email = (new Email())
            ->from(new Address($fromEmail, 'Cartlify'))
            ->to(new Address($contact->getEmail(), $contact->getName()))
            ->subject('✨ Thank You for Contacting Cartlify')
            ->html($this->getUserEmailTemplate($contact));
        
        $this->mailer->send($email);
    }

    private function getAdminEmailTemplate(Contact $contact): string
    {
        return '
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>New Contact Submission</title>
            <style>
                @import url("https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap");
                
                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                }
                
                body {
                    font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
                    background: linear-gradient(135deg, #f5f7fa 0%, #e9ecef 100%);
                    padding: 40px 20px;
                }
                
                .email-wrapper {
                    max-width: 680px;
                    margin: 0 auto;
                    background: #ffffff;
                    border-radius: 24px;
                    overflow: hidden;
                    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
                }
                
                /* Header Section */
                .email-header {
                    background: linear-gradient(135deg, #0a0a2a 0%, #151535 100%);
                    padding: 40px 40px 30px;
                    text-align: center;
                    position: relative;
                    overflow: hidden;
                }
                
                .email-header::before {
                    content: "";
                    position: absolute;
                    top: -50%;
                    right: -50%;
                    width: 200%;
                    height: 200%;
                    background: radial-gradient(circle, rgba(230,126,34,0.1) 0%, transparent 70%);
                    animation: pulse 8s ease-in-out infinite;
                }
                
                @keyframes pulse {
                    0%, 100% { transform: translate(0, 0); }
                    50% { transform: translate(-10%, -10%); }
                }
                
                .logo {
                    position: relative;
                    z-index: 1;
                    margin-bottom: 20px;
                }
                
                .logo-circle {
                    width: 70px;
                    height: 70px;
                    background: rgba(255, 255, 255, 0.15);
                    border-radius: 50%;
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    backdrop-filter: blur(10px);
                }
                
                .logo-circle span {
                    font-size: 32px;
                }
                
                .email-header h1 {
                    color: #ffffff;
                    font-size: 28px;
                    font-weight: 700;
                    margin: 20px 0 8px;
                    position: relative;
                    z-index: 1;
                }
                
                .badge {
                    display: inline-block;
                    background: rgba(230, 126, 34, 0.2);
                    border: 1px solid rgba(230, 126, 34, 0.4);
                    color: #e67e22;
                    padding: 6px 14px;
                    border-radius: 50px;
                    font-size: 13px;
                    font-weight: 600;
                    position: relative;
                    z-index: 1;
                }
                
                /* Content Section */
                .email-content {
                    padding: 40px;
                    background: #ffffff;
                }
                
                .info-grid {
                    background: #f8f9fa;
                    border-radius: 16px;
                    padding: 24px;
                    margin-bottom: 24px;
                }
                
                .info-row {
                    display: flex;
                    padding: 12px 0;
                    border-bottom: 1px solid #e9ecef;
                }
                
                .info-row:last-child {
                    border-bottom: none;
                }
                
                .info-label {
                    width: 100px;
                    font-weight: 600;
                    color: #0a0a2a;
                    font-size: 14px;
                }
                
                .info-value {
                    flex: 1;
                    color: #495057;
                    font-size: 14px;
                    word-break: break-word;
                }
                
                .message-box {
                    background: #f8f9fa;
                    border-radius: 16px;
                    padding: 24px;
                    margin-bottom: 24px;
                }
                
                .message-box h3 {
                    color: #0a0a2a;
                    font-size: 16px;
                    font-weight: 600;
                    margin-bottom: 12px;
                    display: flex;
                    align-items: center;
                    gap: 8px;
                }
                
                .message-text {
                    background: white;
                    padding: 20px;
                    border-radius: 12px;
                    color: #495057;
                    line-height: 1.6;
                    font-size: 14px;
                    border-left: 3px solid #e67e22;
                }
                
                .newsletter-tag {
                    display: inline-flex;
                    align-items: center;
                    gap: 6px;
                    padding: 6px 12px;
                    border-radius: 20px;
                    font-size: 12px;
                    font-weight: 600;
                }
                
                .newsletter-yes {
                    background: #d4edda;
                    color: #155724;
                }
                
                .newsletter-no {
                    background: #f8d7da;
                    color: #721c24;
                }
                
                /* Action Buttons */
                .action-buttons {
                    display: flex;
                    gap: 16px;
                    margin-top: 24px;
                }
                
                .btn {
                    flex: 1;
                    text-align: center;
                    padding: 14px 24px;
                    border-radius: 12px;
                    text-decoration: none;
                    font-weight: 600;
                    font-size: 14px;
                    transition: all 0.3s ease;
                }
                
                .btn-primary {
                    background: linear-gradient(135deg, #0a0a2a 0%, #151535 100%);
                    color: white;
                }
                
                .btn-primary:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 4px 12px rgba(10, 10, 42, 0.3);
                }
                
                .btn-secondary {
                    background: #f8f9fa;
                    color: #0a0a2a;
                    border: 1px solid #e9ecef;
                }
                
                .btn-secondary:hover {
                    background: #e9ecef;
                }
                
                /* Footer */
                .email-footer {
                    background: #f8f9fa;
                    padding: 30px 40px;
                    text-align: center;
                    border-top: 1px solid #e9ecef;
                }
                
                .footer-text {
                    color: #6c757d;
                    font-size: 12px;
                    line-height: 1.6;
                }
                
                .social-links {
                    margin-top: 16px;
                    display: flex;
                    justify-content: center;
                    gap: 12px;
                }
                
                .social-links a {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    width: 36px;
                    height: 36px;
                    background: white;
                    border-radius: 50%;
                    text-decoration: none;
                    font-size: 18px;
                    transition: all 0.3s ease;
                }
                
                .social-links a:hover {
                    transform: translateY(-2px);
                }
                
                hr {
                    border: none;
                    border-top: 1px solid #e9ecef;
                    margin: 20px 0;
                }
                
                @media (max-width: 600px) {
                    .email-content, .email-header, .email-footer {
                        padding: 24px;
                    }
                    
                    .info-row {
                        flex-direction: column;
                        gap: 4px;
                    }
                    
                    .info-label {
                        width: auto;
                    }
                    
                    .action-buttons {
                        flex-direction: column;
                    }
                }
            </style>
        </head>
        <body>
            <div class="email-wrapper">
                <div class="email-header">
                    <div class="logo">
                        <div class="logo-circle">
                            <span>🛍️</span>
                        </div>
                    </div>
                    <h1>New Contact Submission</h1>
                    <div class="badge">Priority: High</div>
                </div>
                
                <div class="email-content">
                    <div class="info-grid">
                        <div class="info-row">
                            <div class="info-label">👤 Name</div>
                            <div class="info-value">' . htmlspecialchars($contact->getName()) . '</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">📧 Email</div>
                            <div class="info-value">' . htmlspecialchars($contact->getEmail()) . '</div>
                        </div>
                        ' . ($contact->getPhone() ? '
                        <div class="info-row">
                            <div class="info-label">📞 Phone</div>
                            <div class="info-value">' . htmlspecialchars($contact->getPhone()) . '</div>
                        </div>
                        ' : '') . '
                        <div class="info-row">
                            <div class="info-label">🏷️ Subject</div>
                            <div class="info-value">' . htmlspecialchars($contact->getSubject()) . '</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">📅 Submitted</div>
                            <div class="info-value">' . $contact->getCreatedAt()->format('F j, Y \a\t g:i A') . '</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">📰 Newsletter</div>
                            <div class="info-value">
                                <span class="newsletter-tag ' . ($contact->isNewsletter() ? 'newsletter-yes' : 'newsletter-no') . '">
                                    ' . ($contact->isNewsletter() ? '✓ Subscribed' : '✗ Not Subscribed') . '
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="message-box">
                        <h3>📝 Message Content</h3>
                        <div class="message-text">
                            ' . nl2br(htmlspecialchars($contact->getMessage())) . '
                        </div>
                    </div>
                    
                    <div class="action-buttons">
                        <a href="#" class="btn btn-primary">📩 Reply to Customer</a>
                        <a href="#" class="btn btn-secondary">👁️ View Details</a>
                    </div>
                </div>
                
                <div class="email-footer">
                    <div class="footer-text">
                        <strong>Cartlify</strong> — Your trusted shopping partner<br>
                        This is an automated notification from your contact form system.
                    </div>
                    <div class="social-links">
                        <a href="#" style="color: #1877f2;">📘</a>
                        <a href="#" style="color: #e4405f;">📷</a>
                        <a href="#" style="color: #1da1f2;">🐦</a>
                        <a href="#" style="color: #0077b5;">🔗</a>
                    </div>
                    <hr>
                    <div class="footer-text">
                        &copy; ' . date('Y') . ' Cartlify. All rights reserved.<br>
                        Contact ID: #' . $contact->getId() . '
                    </div>
                </div>
            </div>
        </body>
        </html>
        ';
    }

    private function getUserEmailTemplate(Contact $contact): string
    {
        return '
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Thank You - Cartlify</title>
            <style>
                @import url("https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap");
                
                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                }
                
                body {
                    font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
                    background: linear-gradient(135deg, #f5f7fa 0%, #e9ecef 100%);
                    padding: 40px 20px;
                }
                
                .email-wrapper {
                    max-width: 600px;
                    margin: 0 auto;
                    background: #ffffff;
                    border-radius: 24px;
                    overflow: hidden;
                    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
                }
                
                .email-header {
                    background: linear-gradient(135deg, #0a0a2a 0%, #151535 100%);
                    padding: 50px 40px 40px;
                    text-align: center;
                    position: relative;
                    overflow: hidden;
                }
                
                .email-header::before {
                    content: "✨";
                    position: absolute;
                    font-size: 200px;
                    opacity: 0.05;
                    bottom: -50px;
                    right: -50px;
                    transform: rotate(-15deg);
                }
                
                .checkmark {
                    width: 80px;
                    height: 80px;
                    background: rgba(40, 167, 69, 0.15);
                    border-radius: 50%;
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    margin-bottom: 24px;
                }
                
                .checkmark span {
                    font-size: 40px;
                }
                
                .email-header h1 {
                    color: #ffffff;
                    font-size: 28px;
                    font-weight: 700;
                    margin-bottom: 8px;
                }
                
                .email-header p {
                    color: rgba(255, 255, 255, 0.8);
                    font-size: 16px;
                }
                
                .email-content {
                    padding: 40px;
                }
                
                .greeting {
                    margin-bottom: 24px;
                }
                
                .greeting h2 {
                    color: #0a0a2a;
                    font-size: 22px;
                    font-weight: 600;
                    margin-bottom: 8px;
                }
                
                .greeting p {
                    color: #6c757d;
                    line-height: 1.6;
                }
                
                .message-copy {
                    background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
                    border-radius: 16px;
                    padding: 24px;
                    margin: 24px 0;
                    border: 1px solid #e9ecef;
                }
                
                .message-copy h3 {
                    color: #0a0a2a;
                    font-size: 14px;
                    font-weight: 600;
                    text-transform: uppercase;
                    letter-spacing: 1px;
                    margin-bottom: 16px;
                }
                
                .message-preview {
                    background: white;
                    padding: 20px;
                    border-radius: 12px;
                    border-left: 3px solid #e67e22;
                    color: #495057;
                    line-height: 1.6;
                    font-size: 14px;
                }
                
                .info-box {
                    background: #f8f9fa;
                    border-radius: 12px;
                    padding: 20px;
                    margin: 24px 0;
                }
                
                .info-item {
                    display: flex;
                    align-items: center;
                    gap: 12px;
                    margin-bottom: 12px;
                    font-size: 14px;
                }
                
                .info-item:last-child {
                    margin-bottom: 0;
                }
                
                .info-icon {
                    width: 32px;
                    height: 32px;
                    background: white;
                    border-radius: 50%;
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 16px;
                }
                
                .info-text {
                    color: #495057;
                }
                
                .info-text strong {
                    color: #0a0a2a;
                }
                
                .btn {
                    display: inline-block;
                    width: 100%;
                    text-align: center;
                    padding: 16px 24px;
                    background: linear-gradient(135deg, #0a0a2a 0%, #151535 100%);
                    color: white;
                    text-decoration: none;
                    border-radius: 12px;
                    font-weight: 600;
                    margin: 16px 0;
                    transition: all 0.3s ease;
                }
                
                .btn:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 4px 12px rgba(10, 10, 42, 0.3);
                }
                
                .response-time {
                    background: linear-gradient(135deg, #fff8e7 0%, #ffffff 100%);
                    border-radius: 12px;
                    padding: 16px;
                    text-align: center;
                    margin: 24px 0;
                }
                
                .response-time span {
                    font-size: 24px;
                    display: block;
                    margin-bottom: 8px;
                }
                
                .response-time p {
                    color: #e67e22;
                    font-weight: 600;
                    font-size: 14px;
                }
                
                .email-footer {
                    background: #f8f9fa;
                    padding: 30px 40px;
                    text-align: center;
                    border-top: 1px solid #e9ecef;
                }
                
                .footer-text {
                    color: #6c757d;
                    font-size: 12px;
                    line-height: 1.6;
                }
                
                .social-links {
                    margin-top: 16px;
                    display: flex;
                    justify-content: center;
                    gap: 12px;
                }
                
                .social-links a {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    width: 36px;
                    height: 36px;
                    background: white;
                    border-radius: 50%;
                    text-decoration: none;
                    font-size: 18px;
                    transition: all 0.3s ease;
                }
                
                hr {
                    border: none;
                    border-top: 1px solid #e9ecef;
                    margin: 20px 0;
                }
                
                @media (max-width: 600px) {
                    .email-content, .email-header, .email-footer {
                        padding: 24px;
                    }
                }
            </style>
        </head>
        <body>
            <div class="email-wrapper">
                <div class="email-header">
                    <h1>Message Received!</h1>
                    <p>We\'re on it, ' . htmlspecialchars($contact->getName()) . '!</p>
                </div>
                
                <div class="email-content">
                    <div class="greeting">
                        <h2>Hello ' . htmlspecialchars($contact->getName()) . '! 👋</h2>
                        <p>Thank you for reaching out to Cartlify. We\'ve received your message and our support team is already reviewing it.</p>
                    </div>
                    
                    <div class="message-copy">
                        <h3>📋 Your Message Summary</h3>
                        <div class="message-preview">
                            <strong>Subject:</strong> ' . htmlspecialchars($contact->getSubject()) . '<br><br>
                            ' . nl2br(htmlspecialchars($contact->getMessage())) . '
                        </div>
                    </div>
                    
                    <div class="info-box">
                        <div class="info-item">
                            <div class="info-icon">📝</div>
                            <div class="info-text"><strong>Reference ID:</strong> #' . $contact->getId() . '</div>
                        </div>
                        <div class="info-item">
                            <div class="info-icon">📅</div>
                            <div class="info-text"><strong>Submitted:</strong> ' . $contact->getCreatedAt()->format('F j, Y \a\t g:i A') . '</div>
                        </div>
                        <div class="info-item">
                            <div class="info-icon">⏱️</div>
                            <div class="info-text"><strong>Response Time:</strong> Within 24 hours</div>
                        </div>
                    </div>
                    
                    <div class="response-time">
                        <span>⏰</span>
                        <p>We typically respond within 24 hours during business days</p>
                    </div>
                    
                    
                    <div class="info-box">
                        <div class="info-item">
                            <div class="info-icon">💡</div>
                            <div class="info-text">
                                <strong>Need immediate help?</strong><br>
                                Check our FAQ page for quick answers to common questions.
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="email-footer">
                    <div class="footer-text">
                        <strong>Cartlify</strong> — Your trusted shopping partner<br>
                        Making online shopping easy and secure since 2024
                    </div>
                    <div class="social-links">
                        <a href="#" style="color: #1877f2;">📘</a>
                        <a href="#" style="color: #e4405f;">📷</a>
                        <a href="#" style="color: #1da1f2;">🐦</a>
                        <a href="#" style="color: #0077b5;">🔗</a>
                    </div>
                    <hr>
                    <div class="footer-text">
                        &copy; ' . date('Y') . ' Cartlify. All rights reserved.<br>
                        This is an automated confirmation. Please do not reply directly to this email.
                    </div>
                </div>
            </div>
        </body>
        </html>
        ';
    }
}