<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');                                   // Name of Customer (mandatory)
            $table->string('mobile', 11)->unique();                   // Mobile Number = User ID (mandatory)
            $table->string('email')->nullable()->unique();            // optional
            $table->string('facebook_id')->nullable();                // optional
            $table->string('password');                               // Hashed; minimum length is enforced by registration validation.
            $table->enum('role', ['super_admin', 'customer'])->default('customer')->index();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
