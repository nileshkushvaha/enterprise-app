<?php

declare(strict_types=1);

namespace Tests\Feature\Faq;

use App\Enums\FaqStatus;
use App\Models\Faq;
use App\Models\FaqCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FaqCrudTest extends TestCase
{
    use RefreshDatabase;

    private FaqCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $this->category = FaqCategory::create([
            'name' => 'General',
            'is_active' => true,
        ]);
    }

    public function test_faq_can_be_created_with_all_fields(): void
    {
        $faq = Faq::create([
            'faq_category_id' => $this->category->id,
            'question' => 'What is this platform?',
            'answer' => '<p>An enterprise LMS.</p>',
            'audience' => ['public', 'student'],
            'status' => 'draft',
            'featured' => false,
            'display_order' => 1,
        ]);

        $this->assertDatabaseHas('faqs', ['question' => 'What is this platform?']);
        $this->assertIsString($faq->id);
        $this->assertEquals(FaqStatus::Draft, $faq->status);
        $this->assertIsArray($faq->audience);
        $this->assertContains('public', $faq->audience);
    }

    public function test_faq_status_cast_to_enum(): void
    {
        $faq = Faq::create([
            'faq_category_id' => $this->category->id,
            'question' => 'Status test?',
            'answer' => '<p>Answer.</p>',
            'audience' => ['public'],
            'status' => 'published',
            'published_at' => now(),
        ]);

        $this->assertEquals(FaqStatus::Published, $faq->fresh()->status);
    }

    public function test_faq_published_at_auto_set_when_status_changes_to_published(): void
    {
        $faq = Faq::create([
            'faq_category_id' => $this->category->id,
            'question' => 'Auto publish date?',
            'answer' => '<p>Answer.</p>',
            'audience' => ['public'],
            'status' => 'draft',
        ]);

        $this->assertNull($faq->fresh()->published_at);

        $faq->update(['status' => 'published']);

        $this->assertNotNull($faq->fresh()->published_at);
    }

    public function test_faq_soft_deletes(): void
    {
        $faq = Faq::create([
            'faq_category_id' => $this->category->id,
            'question' => 'Delete me?',
            'answer' => '<p>Answer.</p>',
            'audience' => ['public'],
            'status' => 'draft',
        ]);
        $id = $faq->id;

        $faq->delete();

        $this->assertSoftDeleted('faqs', ['id' => $id]);
        $this->assertNull(Faq::find($id));
        $this->assertNotNull(Faq::withTrashed()->find($id));
    }

    public function test_faq_can_be_restored(): void
    {
        $faq = Faq::create([
            'faq_category_id' => $this->category->id,
            'question' => 'Restore me?',
            'answer' => '<p>Answer.</p>',
            'audience' => ['public'],
            'status' => 'draft',
        ]);
        $id = $faq->id;
        $faq->delete();

        Faq::withTrashed()->find($id)->restore();

        $this->assertNotNull(Faq::find($id));
    }

    public function test_faq_belongs_to_category(): void
    {
        $faq = Faq::create([
            'faq_category_id' => $this->category->id,
            'question' => 'Category?',
            'answer' => '<p>Yes.</p>',
            'audience' => ['public'],
            'status' => 'draft',
        ]);

        $this->assertEquals($this->category->id, $faq->category->id);
        $this->assertEquals('General', $faq->category->name);
    }

    public function test_faq_scope_published(): void
    {
        Faq::create([
            'faq_category_id' => $this->category->id,
            'question' => 'Published?',
            'answer' => '<p>Yes.</p>',
            'audience' => ['public'],
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);

        Faq::create([
            'faq_category_id' => $this->category->id,
            'question' => 'Draft?',
            'answer' => '<p>Yes.</p>',
            'audience' => ['public'],
            'status' => 'draft',
        ]);

        $published = Faq::published()->get();

        $this->assertTrue($published->contains('question', 'Published?'));
        $this->assertFalse($published->contains('question', 'Draft?'));
    }

    public function test_faq_scope_featured(): void
    {
        Faq::create([
            'faq_category_id' => $this->category->id,
            'question' => 'Featured?',
            'answer' => '<p>Yes.</p>',
            'audience' => ['public'],
            'status' => 'published',
            'published_at' => now(),
            'featured' => true,
        ]);

        Faq::create([
            'faq_category_id' => $this->category->id,
            'question' => 'Not featured?',
            'answer' => '<p>No.</p>',
            'audience' => ['public'],
            'status' => 'published',
            'published_at' => now(),
            'featured' => false,
        ]);

        $featured = Faq::featured()->get();

        $this->assertTrue($featured->contains('question', 'Featured?'));
        $this->assertFalse($featured->contains('question', 'Not featured?'));
    }

    public function test_faq_has_audience_helper(): void
    {
        $faq = Faq::create([
            'faq_category_id' => $this->category->id,
            'question' => 'Audience?',
            'answer' => '<p>A.</p>',
            'audience' => ['public', 'student'],
            'status' => 'draft',
        ]);

        $this->assertTrue($faq->hasAudience('public'));
        $this->assertTrue($faq->hasAudience('student'));
        $this->assertFalse($faq->hasAudience('instructor'));
    }
}
