<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddThumbToTempPicturesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('quyk_cv_temp_pictures', function (Blueprint $table) {
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
        Schema::table('quyk_cv_temp_pictures', function (Blueprint $table) {
            $table->dropColumn('thumb');
            
        });
    }
}
