<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RemoveUserIdInView extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('cv_views', function (Blueprint $table) {
            $table->dropForeign('cv_views_user_id_foreign');
            $table->dropColumn('user_id');
            $table->string('ip_address', 45)->after('id'); 

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('cv_views', function (Blueprint $table) {
            
            $table->bigInteger('user_id')->unsigned();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->dropColumn('ip_address');
            
        });
    }
}
