<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('admin_personal_access_tokens', function (Blueprint $table) {
            // 同一次登录会话的 access / refresh 共用同一个 session_id，
            // 登出时凭 refresh token 即可连带吊销 access token
            $table->string('session_id', 40)->nullable()->after('abilities')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admin_personal_access_tokens', function (Blueprint $table) {
            $table->dropColumn('session_id');
        });
    }
};
