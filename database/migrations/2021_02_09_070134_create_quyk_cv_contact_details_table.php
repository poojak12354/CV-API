<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateQuykCvContactDetailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('quyk_cv_contact_details', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('cv_id')->unsigned();
            $table->string('phone')->nullable();
            $table->string('mobile');
            $table->string('email');
            $table->string('website')->nullable();
            $table->foreign('cv_id')->references('id')->on('quyk_cv')->onDelete('cascade');
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
        Schema::dropIfExists('cv_pages_contact_details');
    }
}
