<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateQuykCvProfilePicturesVideosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('quyk_cv_profile_pictures_videos', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('cv_id')->unsigned();
            $table->string('location')->comment('file location');
            $table->string('type')->comment('1 for image,2 for video');
            $table->tinyInteger('active')->comment('1 for active,0 for not active');
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
        Schema::dropIfExists('cv_pages_profile_pictures_videos');
    }
}
