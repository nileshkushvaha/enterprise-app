<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('seo.meta_title',                          null);
        $this->migrator->add('seo.meta_description',                    null);
        $this->migrator->add('seo.meta_keywords',                       null);
        $this->migrator->add('seo.robots',                              'index,follow');
        $this->migrator->add('seo.canonical_url',                       null);

        $this->migrator->add('seo.google_search_console_verification',  null);
        $this->migrator->add('seo.google_analytics_id',                 null);
        $this->migrator->add('seo.google_tag_manager_id',               null);
        $this->migrator->add('seo.facebook_pixel_id',                   null);

        $this->migrator->add('seo.og_image',                            null);
        $this->migrator->add('seo.twitter_card',                        'summary_large_image');
    }
};
