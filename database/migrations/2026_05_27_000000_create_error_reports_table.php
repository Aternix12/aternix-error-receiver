<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('error_reports', function (Blueprint $table) {
            $table->id();
            $table->string('project')->default('unknown');
            $table->string('app_version')->nullable();
            $table->string('platform')->nullable();
            $table->string('hostname')->nullable();
            $table->string('report_type')->default('auto'); // 'auto' or 'manual'
            $table->string('summary')->nullable();
            $table->text('user_note')->nullable();
            $table->longText('frontend_report')->nullable();
            $table->longText('log_tail')->nullable();
            $table->string('client_ip')->nullable();
            $table->timestamps();

            $table->index('created_at');
            $table->index(['project', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('error_reports');
    }
};
