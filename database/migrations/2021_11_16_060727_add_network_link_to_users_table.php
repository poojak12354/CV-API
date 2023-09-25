<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddNetworkLinkToUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('quyk_cv_social_networks', function (Blueprint $table) {
            $table->string('network')->after('cv_id');
            $table->text('url')->after('cv_id');
            $table->dropColumn('xing');
            $table->dropColumn('linkedin');
            $table->dropColumn('instagram');
            $table->dropColumn('twitter');
            $table->dropColumn('extra_data');
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
            $table->string('xing')->nullable();
            $table->text('linkedin')->nullable();
            $table->string('instagram')->nullable();
            $table->string('twitter')->nullable();
            $table->text('extra_data')->nullable();
            $table->dropColumn('network');
            $table->dropColumn('url');
        });
    }
}
