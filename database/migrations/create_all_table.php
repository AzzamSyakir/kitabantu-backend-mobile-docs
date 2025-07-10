<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up()
  {
    Schema::create('sessions', function (Blueprint $table) {
      $table->string('id')->primary();
      $table->foreignId('user_id')->nullable()->index();
      $table->string('ip_address', 45)->nullable();
      $table->text('user_agent')->nullable();
      $table->longText('payload');
      $table->integer('last_activity')->index();
    });
    Schema::create('users', function (Blueprint $table) {
      $table->uuid('id')->primary();
      $table->string('email')->unique();
      $table->string('password')->nullable();
      $table->enum('auth_method', ['google', 'facebook', 'manual'])->default('manual');
      $table->boolean('email_verified')->default(false);
      $table->timestamp('email_verified_at')->nullable();
      $table->timestamps();
    });
    Schema::create('profiles', function (Blueprint $table) {
      $table->uuid('id')->primary();
      $table->uuid('user_id');
      $table->string('full_name');
      $table->string('phone_number', 20)->nullable();
      $table->string('nick_name')->nullable();
      $table->enum('gender', ['male', 'female'])->nullable();
      $table->string('domicile')->nullable();
      $table->enum('preferred_service', [
        'baby_sitter',
        'akuntan',
        'supir',
        'reviewer',
        'arsitek',
        'teknisi',
        'buruh_harian',
        'catering',
        'tukang',
        'translator',
        'kurir',
      ])->nullable();
      $table->string('picture_url')->nullable();
      $table->timestamps();

      $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
    });
    Schema::create('orders', function (Blueprint $table) {
      $table->uuid('id')->primary();
      $table->uuid('freelancer_id');
      $table->uuid('client_id')->nullable();
      $table->string('work_location');
      $table->dateTime('work_start_time');
      $table->dateTime('work_end_time');
      $table->integer('estimated_travel_time');
      $table->decimal('hourly_rate', 10, 2);
      $table->decimal('total_cost', 12, 2);
      $table->text('note')->nullable();
      $table->enum('status', ['pending', 'processing', 'on_going', 'completed', 'failed', 'canceled', 'refunded'])->default('pending');
      $table->boolean('is_sent_to_server')->default(false);
      $table->timestamp('sent_to_server_at')->nullable();
      $table->string('server_response_status')->nullable();

      $table->timestamps();

      $table->foreign('client_id')->references('id')->on('users')->onDelete('set null');
    });
    Schema::create('order_negotiations', function (Blueprint $table) {
      $table->uuid('id')->primary();
      $table->uuid('order_id');
      $table->dateTime('work_start_time');
      $table->dateTime('work_end_time');
      $table->decimal('working_hours_duration', 5, 2);
      $table->decimal('hourly_rate', 10, 2);
      $table->decimal('total_cost', 12, 2);
      $table->text('note')->nullable();
      $table->timestamps();

      $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
    });
    Schema::create('order_reviews', function (Blueprint $table) {
      $table->uuid('id')->primary();
      $table->uuid('order_id');
      $table->tinyInteger('rating_star')->unsigned();
      $table->text('rating_text')->nullable();
      $table->string('review_file_url')->nullable();
      $table->timestamps();

      $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
    });
    Schema::create('order_complaints', function (Blueprint $table) {
      $table->uuid('id')->primary();
      $table->uuid('order_id');
      $table->enum('complaint_type', [
        'pekerjaan_tidak_selesai',
        'hasil_tidak_sesuai_brief',
        'freelancer_tidak_merespons',
        'komunikasi_tidak_profesional',
        'dugaan_penipuan_atau_penyalahgunaan',
        'lainnya',
      ]);
      $table->text('description')->nullable();
      $table->string('evidence_url')->nullable();
      $table->dateTime('event_time')->nullable();
      $table->string('contact_info')->nullable();
      $table->timestamps();

      $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
    });
    Schema::create('wallets', function (Blueprint $table) {
      $table->uuid('id')->primary();
      $table->uuid('user_id');
      $table->string('pin')->nullable();
      $table->decimal('balance', 15, 2)->default(0);
      $table->timestamps();

      $table->foreign('user_id')
        ->references('id')->on('users')
        ->onDelete('cascade');
    });
    Schema::create('chat_rooms', function (Blueprint $table) {
      $table->uuid('id')->primary();
      $table->uuid('client_id');
      $table->uuid('freelancer_id');
      $table->timestamps();

      $table->foreign('client_id')
        ->references('id')->on('users')
        ->onDelete('cascade');

      $table->unique(['client_id', 'freelancer_id']);
    });
    Schema::create('chat_histories', function (Blueprint $table) {
      $table->uuid('id')->primary();
      $table->uuid('chat_room_id');
      $table->string('chat')->nullable();
      $table->string('file')->nullable();
      $table->uuid('sender_id');
      $table->timestamps();

      $table->foreign('chat_room_id')
        ->references('id')->on('chat_rooms')
        ->onDelete('cascade');
    });

    Schema::create('payments', function (Blueprint $table) {
      $table->uuid('id')->primary();
      $table->enum('payment_type', ['top_up', 'withdraw', 'pay_freelancer']);
      $table->string('payment_method');
      $table->decimal('amount', 10, 2);
      $table->dateTime('date');
      $table->enum('status', [
        'authorize',
        'capture',
        'settlement',
        'deny',
        'pending',
        'cancel',
        'refund',
        'partial_refund',
        'chargeback',
        'partial_chargeback',
        'expire',
        'failure'
      ])->default('pending');
      $table->uuid('freelancer_id')->nullable();
      $table->uuid('client_id')->nullable();
      $table->timestamps();

      $table->foreign('client_id')
        ->references('id')->on('users')->onDelete('set null');
    });
    Schema::create('cards', function (Blueprint $table) {
      $table->uuid('id')->primary();
      $table->string('card_number');
      $table->string('card_holder_name');
      $table->uuid('user_id');
      $table->string('card_address');
      $table->timestamps();

      $table->foreign('user_id')
        ->references('id')
        ->on('users')
        ->onDelete('cascade');
    });

    Schema::create('provinces', function (Blueprint $t) {
      $t->string('id')->primary();
      $t->string('name');
    });
    Schema::create('regencies', function (Blueprint $t) {
      $t->string('id')->primary();
      $t->string('province_id');
      $t->string('name');
      $t->foreign('province_id')->references('id')->on('provinces')->onDelete('cascade');
    });
    Schema::create('districts', function (Blueprint $t) {
      $t->string('id')->primary();
      $t->string('regency_id');
      $t->string('name');
      $t->foreign('regency_id')->references('id')->on('regencies')->onDelete('cascade');
    });
    Schema::create('villages', function (Blueprint $t) {
      $t->string('id')->primary();
      $t->string('district_id');
      $t->string('name');
      $t->foreign('district_id')->references('id')->on('districts')->onDelete('cascade');
    });
  }
  public function down()
  {
    Schema::dropIfExists('villages');
    Schema::dropIfExists('districts');
    Schema::dropIfExists('regencies');
    Schema::dropIfExists('provinces');

    Schema::dropIfExists('order_complaints');
    Schema::dropIfExists('order_reviews');
    Schema::dropIfExists('order_negotiations');

    Schema::dropIfExists('payments');
    Schema::dropIfExists('cards');
    Schema::dropIfExists('wallets');

    Schema::dropIfExists('chat_room');

    Schema::dropIfExists('orders');
    Schema::dropIfExists('users');
    Schema::dropIfExists('sessions');
  }
};
