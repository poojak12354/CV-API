<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateQUYKCVTempPicturesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('quyk_cv_temp_pictures', function (Blueprint $table) {
            $table->id();
            $table->string('location')->comment('file location');
            $table->string('type')->comment('1 for image,2 for video');
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
        Schema::dropIfExists('q_u_y_k_c_v_temp_pictures');
    }
}
