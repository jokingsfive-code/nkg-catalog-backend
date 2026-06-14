<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('designs', function (Blueprint $table) {
            $table->id();
$table->foreignId('category_id')->constrained()->cascadeOnDelete();

$table->string('name')->nullable();

$table->string('image_url');

$table->string('image_public_id')->nullable();

$table->integer('sort_order')->default(0);

$table->boolean('is_featured')->default(false);

$table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('designs');
    }
};