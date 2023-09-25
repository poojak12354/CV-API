<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSettingsToQuykCvTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('quyk_cv', function (Blueprint $table) {
            $table->tinyInteger('active')->after('cv_url')->default(1);
            $table->tinyInteger('external')->after('active')->default(1);
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
            $table->dropColumn('active');
            $table->dropColumn('external');
        });
    }
}
