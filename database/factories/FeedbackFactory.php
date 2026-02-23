<?php

namespace Database\Factories;

use App\Models\Feedback;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Feedback>
 */
class FeedbackFactory extends Factory
{
    protected $model = Feedback::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement(['question', 'bug', 'feature', 'other']);

        return [
            'user_id' => null, // Must be set by caller
            'type' => $type,
            'email' => fake()->email(),
            'subject' => $this->generateSubject($type),
            'message' => $this->generateMessage($type),
            'status' => fake()->randomElement(['new', 'read', 'closed']),
        ];
    }

    /**
     * Set feedback type to question.
     */
    public function question(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'type' => 'question',
                'subject' => $this->generateSubject('question'),
                'message' => $this->generateMessage('question'),
            ];
        });
    }

    /**
     * Set feedback type to bug.
     */
    public function bug(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'type' => 'bug',
                'subject' => $this->generateSubject('bug'),
                'message' => $this->generateMessage('bug'),
            ];
        });
    }

    /**
     * Set feedback type to feature.
     */
    public function feature(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'type' => 'feature',
                'subject' => $this->generateSubject('feature'),
                'message' => $this->generateMessage('feature'),
            ];
        });
    }

    /**
     * Generate subject based on feedback type.
     */
    private function generateSubject(string $type): string
    {
        $subjects = [
            'question' => [
                'How to export PDFs?',
                'CSV import available?',
                'API access available?',
                'Multi-currency support?',
                'Bulk operations possible?',
                'Mobile app planned?',
            ],
            'bug' => [
                'Tax calculation error',
                'PDF download fails',
                'Email not sending',
                'Pagination broken',
                'Session timeout too short',
                'Reports not generating',
            ],
            'feature' => [
                'Recurring invoicing needed',
                'Payment reminders',
                'Stripe integration',
                'Custom templates',
                'Two-factor authentication',
                'Advanced reporting',
            ],
            'other' => [
                'Great application!',
                'Performance issues',
                'UI needs redesign',
                'Documentation unclear',
                'Very useful product',
                'Pricing feedback',
            ],
        ];

        return fake()->randomElement($subjects[$type] ?? ['General feedback']);
    }

    /**
     * Generate message based on feedback type.
     */
    private function generateMessage(string $type): string
    {
        $messages = [
            'question' => [
                'I would like to know if there\'s a way to export invoices as PDF files directly from the dashboard.',
                'Can I import clients and invoices from a CSV file or is there an API for that?',
                'Is there an API available for integration with other systems?',
                'Does the application support multiple currencies for international clients?',
                'Is it possible to perform bulk operations like sending multiple invoices at once?',
                'Are you planning to develop a mobile app for iOS and Android?',
            ],
            'bug' => [
                'The tax calculation seems to be incorrect. The total should be €956.80 but it shows €945.00.',
                'When I try to download the PDF invoice, I get a blank page error.',
                'Invoices are not being sent via email even though the status is updated to "sent".',
                'The pagination doesn\'t work correctly on the invoices list page.',
                'My session times out after 15 minutes of activity, which is too short.',
                'The monthly reports are not generating for some reason.',
            ],
            'feature' => [
                'We need recurring/automatic invoicing for our subscription-based services.',
                'It would be great to have automatic payment reminders sent to clients.',
                'Integration with Stripe would be very helpful for online payments.',
                'Custom invoice templates would allow us to maintain brand consistency.',
                'Please add two-factor authentication for enhanced security.',
                'More advanced reporting features would help us with business analytics.',
            ],
            'other' => [
                'This is exactly the tool we\'ve been looking for! Great job on the development.',
                'The application is slow when loading large datasets with 1000+ invoices.',
                'The user interface looks outdated compared to modern SaaS applications.',
                'The documentation could be more detailed with step-by-step examples.',
                'We\'ve been using this for 6 months and couldn\'t be happier with the results!',
                'The pricing model is reasonable, but I would like to see a discount for annual subscriptions.',
            ],
        ];

        return fake()->randomElement($messages[$type] ?? [fake()->sentence()]);
    }
}
