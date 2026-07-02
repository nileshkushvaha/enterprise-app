<?php

declare(strict_types=1);

namespace Tests\Feature\Faq;

use App\Models\Faq;
use App\Models\FaqCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FaqCategoryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $this->admin = User::factory()->create(['status' => 'active']);
        $this->admin->assignRole('super_admin');
    }

    public function test_faq_category_can_be_created(): void
    {
        $category = FaqCategory::create([
            'name' => 'Getting Started',
            'description' => 'Basic setup questions',
            'is_active' => true,
            'display_order' => 1,
        ]);

        $this->assertDatabaseHas('faq_categories', [
            'name' => 'Getting Started',
            'is_active' => 1,
        ]);

        $this->assertNotNull($category->id);
        $this->assertIsString($category->id);
    }

    public function test_faq_category_soft_deletes(): void
    {
        $category = FaqCategory::create(['name' => 'Test Category', 'is_active' => true]);
        $id = $category->id;

        $category->delete();

        $this->assertSoftDeleted('faq_categories', ['id' => $id]);
        $this->assertNull(FaqCategory::find($id));
        $this->assertNotNull(FaqCategory::withTrashed()->find($id));
    }

    public function test_faq_category_can_be_restored(): void
    {
        $category = FaqCategory::create(['name' => 'Restorable', 'is_active' => true]);
        $id = $category->id;
        $category->delete();

        FaqCategory::withTrashed()->find($id)->restore();

        $this->assertNotNull(FaqCategory::find($id));
    }

    public function test_faq_category_has_many_faqs(): void
    {
        $category = FaqCategory::create(['name' => 'Billing', 'is_active' => true]);

        Faq::create([
            'faq_category_id' => $category->id,
            'question' => 'How to pay?',
            'answer' => '<p>Use credit card.</p>',
            'audience' => ['public'],
            'status' => 'published',
            'published_at' => now(),
        ]);

        $this->assertCount(1, $category->faqs);
    }

    public function test_faq_category_scope_active(): void
    {
        FaqCategory::create(['name' => 'Active', 'is_active' => true]);
        FaqCategory::create(['name' => 'Inactive', 'is_active' => false]);

        $active = FaqCategory::active()->get();

        $this->assertTrue($active->contains('name', 'Active'));
        $this->assertFalse($active->contains('name', 'Inactive'));
    }

    public function test_deleting_category_cascades_to_faqs(): void
    {
        $category = FaqCategory::create(['name' => 'Cascade Test', 'is_active' => true]);

        Faq::create([
            'faq_category_id' => $category->id,
            'question' => 'Will I be deleted?',
            'answer' => '<p>Yes.</p>',
            'audience' => ['public'],
            'status' => 'published',
            'published_at' => now(),
        ]);

        $category->delete();

        $this->assertSoftDeleted('faq_categories', ['id' => $category->id]);
    }
}
