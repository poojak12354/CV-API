<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ChangesInQuykCvContactDetailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('quyk_cv_contact_details', function (Blueprint $table) {
            $table->string('network')->after('cv_id');
            $table->text('url')->after('cv_id');
            $table->boolean('isvisible')->default(1)->after('url');
            $table->dropColumn('extra_data');
            $table->dropColumn('phone');
            $table->dropColumn('mobile');
            $table->dropColumn('email');
            $table->dropColumn('website');
            
            
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('quyk_cv_contact_details', function (Blueprint $table) {
            $table->string('phone')->nullable();
            $table->string('mobile')->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->text('extra_data')->nullable();
            $table->dropColumn('network');
            $table->dropColumn('url');
        });
    }
}
