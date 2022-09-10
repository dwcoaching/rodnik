<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('spring_revisions', function (Blueprint $table) {
            $table->id();

            $table->decimal('latitude', 9, 6)->nullable();
            $table->decimal('longitude', 9, 6)->nullable();
            $table->string('name')->nullable();
            $table->string('type')->nullable();
            $table->boolean('seasonal')->nullable();

            $table->foreignId('user_id')->nullable();
            $table->foreignId('spring_id')->nullable();

            $table->boolean('current')->default(false);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('spring_revisions');
    }
};
