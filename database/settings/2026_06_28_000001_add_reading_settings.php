<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // 'template' = render home.blade.php | 'static_page' = render chosen CMS page
        $this->migrator->add('general.homepage_display', 'template');
        $this->migrator->add('general.homepage_id', null);
    }
};
