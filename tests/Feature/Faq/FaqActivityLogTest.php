<?php

declare(strict_types=1);

namespace Tests\Feature\Faq;

use App\Models\Faq;
use App\Models\FaqCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FaqActivityLogTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private FaqCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $this->admin = User::factory()->create(['status' => 'active']);
        $this->admin->assignRole('super_admin');

        $this->category = FaqCategory::create([
            'name' => 'Logging Test',
            'is_active' => true,
        ]);
    }

    public function test_faq_created_event_is_logged(): void
    {
        $this->actingAs($this->admin);

        Faq::create([
            'faq_category_id' => $this->category->id,
            'question' => 'Logged on create?',
            'answer' => '<p>Yes.</p>',
            'audience' => ['public'],
            'status' => 'draft',
        ]);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'faqs',
            'event' => 'created',
        ]);
    }

    public function test_faq_updated_event_is_logged(): void
    {
        $this->actingAs($this->admin);

        $faq = Faq::create([
            'faq_category_id' => $this->category->id,
            'question' => 'Update test?',
            'answer' => '<p>A.</p>',
            'audience' => ['public'],
            'status' => 'draft',
        ]);

        $faq->update(['question' => 'Updated question?']);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'faqs',
            'event' => 'updated',
        ]);
    }

    public function test_faq_published_event_is_logged_separately(): void
    {
        $this->actingAs($this->admin);

        $faq = Faq::create([
            'faq_category_id' => $this->category->id,
            'question' => 'Publish log?',
            'answer' => '<p>A.</p>',
            'audience' => ['public'],
            'status' => 'draft',
        ]);

        $faq->update(['status' => 'published']);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'faqs',
            'event' => 'published',
        ]);
    }

    public function test_faq_deleted_event_is_logged(): void
    {
        $this->actingAs($this->admin);

        $faq = Faq::create([
            'faq_category_id' => $this->category->id,
            'question' => 'Delete log?',
            'answer' => '<p>A.</p>',
            'audience' => ['public'],
            'status' => 'draft',
        ]);

        $faq->delete();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'faqs',
            'event' => 'deleted',
        ]);
    }

    public function test_faq_restored_event_is_logged(): void
    {
        $this->actingAs($this->admin);

        $faq = Faq::create([
            'faq_category_id' => $this->category->id,
            'question' => 'Restore log?',
            'answer' => '<p>A.</p>',
            'audience' => ['public'],
            'status' => 'draft',
        ]);
        $faq->delete();

        Faq::withTrashed()->find($faq->id)->restore();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'faqs',
            'event' => 'restored',
        ]);
    }

    public function test_faq_activity_log_causer_is_authenticated_user(): void
    {
        $this->actingAs($this->admin);

        Faq::create([
            'faq_category_id' => $this->category->id,
            'question' => 'Causer test?',
            'answer' => '<p>A.</p>',
            'audience' => ['public'],
            'status' => 'draft',
        ]);

        $activity = Activity::where('log_name', 'faqs')->where('event', 'created')->first();

        $this->assertNotNull($activity);
        $this->assertEquals($this->admin->id, $activity->causer_id);
    }

    public function test_faq_category_created_event_is_logged(): void
    {
        $this->actingAs($this->admin);

        FaqCategory::create(['name' => 'Logged Category', 'is_active' => true]);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'faq_categories',
            'event' => 'created',
        ]);
    }
}
