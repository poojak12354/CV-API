<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddThumbOrderToPicturesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('quyk_cv_profile_pictures_videos', function (Blueprint $table) {
            $table->tinyInteger('order')->default(1)->after('active');
            $table->string('thumb')->nullable()->after('location');
            
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('quyk_cv_profile_pictures_videos', function (Blueprint $table) {
            $table->dropColumn('order');
            $table->dropColumn('thumb');

        });
    }
}
