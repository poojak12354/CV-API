<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AlterTableQuykCvCurriculumQualifications extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('quyk_cv_curriculum_qualifications', function (Blueprint $table) {
            $table->string('headline')->nullable()->change();
            $table->string('your_text')->nullable()->change();
            
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
            //
        });
    }
}
