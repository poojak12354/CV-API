<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMediaTypeQuykCvTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('quyk_cv', function (Blueprint $table) {
            $table->enum('media_type', ['image', 'video'])->default('image')->after('percentage');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('quyk_cv', function (Blueprint $table) {

            $table->dropColumn('media_type');
            
        });
    }
}
