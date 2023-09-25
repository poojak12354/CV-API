<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddNewFieldsToTeamDetailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('quyk_cv_teams_detail', function (Blueprint $table) {
            $table->string('team_overview')->after('company_name')->nullable();
            $table->string('team_picture')->after('company_name')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('quyk_cv_teams_detail', function (Blueprint $table) {
            $table->dropColumn('team_overview');
            $table->dropColumn('team_picture');
        });
    }
}
