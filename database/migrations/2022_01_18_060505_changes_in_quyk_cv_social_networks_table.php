<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ChangesInQuykCvSocialNetworksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('quyk_cv_social_networks', function (Blueprint $table) {
            $table->boolean('isvisible')->default(1)->after('network');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('quyk_cv_social_networks', function (Blueprint $table) {
            $table->dropColumn('isvisible');
        });
    }
}
