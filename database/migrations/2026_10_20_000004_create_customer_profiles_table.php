<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('village_holding');                                  // Vill/Holding (typed by hand)
            $table->string('post_office_area');                                 // PO/Area (typed by hand)
            $table->foreignId('thana_id')->constrained('thanas');               // PS (selected)
            $table->foreignId('district_id')->constrained('districts');         // District (selected)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_profiles');
    }
};
