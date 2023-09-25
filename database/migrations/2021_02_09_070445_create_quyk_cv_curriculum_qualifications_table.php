<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateQuykCvCurriculumQualificationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('quyk_cv_curriculum_qualifications', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('cv_id')->unsigned();
            $table->string('headline');
            $table->string('record_type')->nullable();
            $table->text('your_text');
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
        Schema::dropIfExists('cv_pages_curriculum_qualifications');
    }
}
