<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCompanyUrlToQuykCvTeamsDetailTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('quyk_cv_teams_detail', function (Blueprint $table) {
            $table->string('company_url')->nullable()->after('team_url');
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
            $table->dropColumn('company_url');
        });
    }
}
