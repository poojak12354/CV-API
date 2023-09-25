<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddExtraDataToCurriculumQualificationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('quyk_cv_curriculum_qualifications', function (Blueprint $table) {
            $table->text('extra_data')->after('your_text')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('quyk_cv_curriculum_qualifications', function (Blueprint $table) {
            $table->dropColumn('extra_data');
        });
    }
}
