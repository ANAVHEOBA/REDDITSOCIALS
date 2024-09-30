<?php

// database/migrations/xxxx_xx_xx_xxxxxx_add_reddit_fields_to_users_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRedditFieldsToUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('reddit_id')->nullable()->unique();
            $table->string('reddit_token')->nullable();
            $table->string('reddit_refresh_token')->nullable();
            $table->string('slack_id')->nullable();
            $table->string('slack_access_token')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('reddit_id');
            $table->dropColumn('reddit_token');
            $table->dropColumn('reddit_refresh_token');
        });
    }
}

