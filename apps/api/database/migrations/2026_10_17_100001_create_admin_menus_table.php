<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_menus', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pid')->default(0)->index()->comment('父菜单，0 为顶级');
            $table->string('name')->unique()->comment('路由 name，必填唯一');
            $table->string('path')->comment('顶级以 / 开头，子级用相对片段');
            $table->string('component')->nullable()->comment('相对 views/ 的组件路径');
            $table->string('type', 16)->default('menu')->comment('catalog / menu / button');
            $table->string('auth_code')->nullable()->comment('仅 type=button，对应 permission 名');
            $table->string('icon')->nullable();
            $table->integer('sort')->default(0)->comment('映射 meta.order');
            $table->tinyInteger('status')->default(1)->comment('1 启用 0 禁用');
            $table->json('meta')->nullable()->comment('其余 vben 路由 meta 字段');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_menus');
    }
};
