<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('slug', 191)->nullable()->unique()->after('name');
        });

        // Back-fill existing rows with a unique slug derived from their name
        DB::table('users')->orderBy('id')->each(function (object $user): void {
            $base = Str::slug($user->name);
            $slug = $base;
            $i = 1;

            while (DB::table('users')->where('slug', $slug)->where('id', '!=', $user->id)->exists()) {
                $slug = $base.'_'.$i++;
            }

            DB::table('users')->where('id', $user->id)->update(['slug' => $slug]);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('slug');
        });
    }
};
