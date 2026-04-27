<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotent migration for the registration settings table.
 *
 * Filename uses an early date prefix so it sorts before any existing
 * migration in upgraded installs (which previously had this table created
 * by `tallcms-user-registration-plugin` v1.x). The Schema::hasTable() guard
 * makes the migration safe to run on top of an existing table — upgraders
 * keep their data, fresh installs get the table created.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tallcms_registration_settings')) {
            return;
        }

        Schema::create('tallcms_registration_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // Never drop on rollback — other plugins (the TallCMS bridge)
        // depend on this table existing as long as either is installed.
    }
};
