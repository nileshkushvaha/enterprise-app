<?php

declare(strict_types=1);

namespace Tests\Feature\Faq;

use App\Enums\FaqAudience;
use App\Models\Faq;
use App\Models\FaqCategory;
use App\Models\User;
use App\Services\Faq\FaqService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FaqAudienceTest extends TestCase
{
    use RefreshDatabase;

    private FaqCategory $category;

    private FaqService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'instructor', 'guard_name' => 'web']);

        $this->category = FaqCategory::create(['name' => 'Test', 'is_active' => true]);
        $this->service = app(FaqService::class);
    }

    private function makeFaq(array $audience, string $question = 'Q?'): Faq
    {
        return Faq::create([
            'faq_category_id' => $this->category->id,
            'question' => $question,
            'answer' => '<p>A.</p>',
            'audience' => $audience,
            'status' => 'published',
            'published_at' => now(),
        ]);
    }

    public function test_scope_for_audience_filters_by_single_audience(): void
    {
        $this->makeFaq(['public'], 'Public only');
        $this->makeFaq(['student'], 'Student only');

        $results = Faq::published()->forAudience(['public'])->get();

        $this->assertTrue($results->contains('question', 'Public only'));
        $this->assertFalse($results->contains('question', 'Student only'));
    }

    public function test_scope_for_audience_returns_multiple_matching_audiences(): void
    {
        $this->makeFaq(['public'], 'Public FAQ');
        $this->makeFaq(['student'], 'Student FAQ');
        $this->makeFaq(['instructor'], 'Instructor FAQ');

        $results = Faq::published()->forAudience(['public', 'student'])->get();

        $this->assertTrue($results->contains('question', 'Public FAQ'));
        $this->assertTrue($results->contains('question', 'Student FAQ'));
        $this->assertFalse($results->contains('question', 'Instructor FAQ'));
    }

    public function test_faq_with_multiple_audiences_appears_for_any_matching_audience(): void
    {
        $this->makeFaq(['public', 'student', 'instructor'], 'All audiences');

        $fromPublic = Faq::published()->forAudience(['public'])->get();
        $fromStudent = Faq::published()->forAudience(['student'])->get();

        $this->assertTrue($fromPublic->contains('question', 'All audiences'));
        $this->assertTrue($fromStudent->contains('question', 'All audiences'));
    }

    public function test_public_faqs_returns_only_public_audience(): void
    {
        $this->makeFaq(['public'], 'Public');
        $this->makeFaq(['student'], 'Student');

        $results = $this->service->publicFaqs();

        $this->assertTrue($results->contains('question', 'Public'));
        $this->assertFalse($results->contains('question', 'Student'));
    }

    public function test_audiences_for_user_includes_public_always(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('student');

        $audiences = $this->service->audiencesForUser($user);

        $this->assertContains(FaqAudience::Public->value, $audiences);
    }

    public function test_audiences_for_user_adds_role_based_audience(): void
    {
        $student = User::factory()->create(['status' => 'active']);
        $student->assignRole('student');

        $instructor = User::factory()->create(['status' => 'active']);
        $instructor->assignRole('instructor');

        $studentAudiences = $this->service->audiencesForUser($student);
        $instructorAudiences = $this->service->audiencesForUser($instructor);

        $this->assertContains('student', $studentAudiences);
        $this->assertNotContains('instructor', $studentAudiences);

        $this->assertContains('instructor', $instructorAudiences);
        $this->assertNotContains('student', $instructorAudiences);
    }

    public function test_for_user_returns_correct_faqs_based_on_roles(): void
    {
        $this->makeFaq(['public'], 'Public');
        $this->makeFaq(['student'], 'Student');
        $this->makeFaq(['instructor'], 'Instructor');

        $student = User::factory()->create(['status' => 'active']);
        $student->assignRole('student');

        $results = $this->service->forUser($student);

        $this->assertTrue($results->contains('question', 'Public'));
        $this->assertTrue($results->contains('question', 'Student'));
        $this->assertFalse($results->contains('question', 'Instructor'));
    }

    public function test_draft_faqs_excluded_regardless_of_audience(): void
    {
        Faq::create([
            'faq_category_id' => $this->category->id,
            'question' => 'Draft public FAQ',
            'answer' => '<p>A.</p>',
            'audience' => ['public'],
            'status' => 'draft',
        ]);

        $results = $this->service->publicFaqs();

        $this->assertFalse($results->contains('question', 'Draft public FAQ'));
    }

    public function test_search_filters_within_audience(): void
    {
        $this->makeFaq(['public'], 'How to reset password?');
        $this->makeFaq(['public'], 'How to update profile?');
        $this->makeFaq(['student'], 'How to enroll in a course?');

        $results = $this->service->publicFaqs('reset');

        $this->assertTrue($results->contains('question', 'How to reset password?'));
        $this->assertFalse($results->contains('question', 'How to update profile?'));
        $this->assertFalse($results->contains('question', 'How to enroll in a course?'));
    }
}
