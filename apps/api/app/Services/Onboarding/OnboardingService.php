<?php

namespace App\Services\Onboarding;

use App\Models\Chatbot;
use App\Models\Organization;
use App\Models\User;
use App\Services\Knowledge\IngestManualTextService;

/**
 * Seeds a sample chatbot with demo FAQ content for new users.
 * Runs immediately after account creation (in RegisterUserService transaction).
 * Keeps it simple — one chatbot, one FAQ document — so users have something to
 * click on and test before uploading their own content.
 */
class OnboardingService
{
    public function __construct(private readonly IngestManualTextService $ingester) {}

    public function seedForNewOrganization(Organization $org, User $owner): void
    {
        // Create the sample chatbot
        $chatbot = Chatbot::create([
            'organization_id' => $org->id,
            'name'            => 'Acme Support Bot',
            'public_id'       => \Illuminate\Support\Str::random(12),
            'status'          => 'active',
            'language'        => 'en',
        ]);

        // Ensure settings exist (ChatbotSettingsObserver creates them if not)
        $chatbot->load('settings');

        if ($chatbot->settings) {
            $chatbot->settings->update([
                'welcome_message' => "Hi there! I'm Acme's AI assistant. How can I help you today?",
                'fallback_message' => "I don't have enough information to answer that. Would you like to speak with a human?",
                'primary_color'  => '#4F46E5',
            ]);
        }

        // Seed a sample FAQ document
        app()->instance('currentOrganization', $org);

        $this->ingester->execute($chatbot, 'Acme FAQ', self::SAMPLE_FAQ_CONTENT);
    }

    private const SAMPLE_FAQ_CONTENT = <<<'EOF'
# Acme Corporation — Frequently Asked Questions

## Returns & Refunds
Q: What is your return policy?
A: We offer a 30-day return policy on all products. Items must be in their original condition with the receipt. Refunds are processed within 5–7 business days.

## Shipping
Q: How long does shipping take?
A: Standard shipping takes 3–5 business days. Express shipping (1–2 business days) is available for an additional fee at checkout.

Q: Do you ship internationally?
A: Yes, we ship to over 50 countries. International shipping takes 7–14 business days. Import duties may apply.

## Payments
Q: What payment methods do you accept?
A: We accept all major credit cards (Visa, Mastercard, Amex), PayPal, Apple Pay, and Google Pay.

Q: Is my payment information secure?
A: All transactions are encrypted with TLS and processed by Stripe. We never store your card details.

## Products
Q: Are your products covered by a warranty?
A: Yes, all Acme products come with a 1-year manufacturer's warranty covering defects in materials and workmanship.

## Contact
Q: How do I contact support?
A: You can reach us at support@acme.example.com or by calling 1-800-ACME-CORP Monday to Friday, 9am–5pm EST.
EOF;
}
