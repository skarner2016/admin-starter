<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_users', function (Blueprint $table) {
            $table->id();
            $table->string('username')->unique()->comment('登录凭据');
            $table->string('real_name')->comment('昵称，响应 realName');
            $table->string('avatar')->nullable();
            $table->string('password');
            $table->string('description')->nullable()->comment('备注信息');
            $table->tinyInteger('status')->default(1)->comment('1 启用 0 禁用');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_users');
    }
};
