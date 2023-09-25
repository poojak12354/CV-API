<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddHeadingDescriptionToTeamGlobalRecordsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('team_global_records', function (Blueprint $table) {
            $table->text('heading_description')->after('heading_text')->nullable();
            $table->string('heading_text',255)->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('team_global_records', function (Blueprint $table) {
            $table->dropColumn('heading_description');
            $table->text('heading_text')->change();
        });
    }
}
