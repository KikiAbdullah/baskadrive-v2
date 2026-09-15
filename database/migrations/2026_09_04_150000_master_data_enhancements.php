<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 2.2.1 VehicleModel foto
        Schema::table('m_vehicle_model', function (Blueprint $table) {
            $table->string('photo')->nullable()->after('deposit_amount');
        });

        // 2.2.3 Customer blacklist + NIK validation ready (columns)
        Schema::table('m_customer', function (Blueprint $table) {
            $table->boolean('is_blacklisted')->default(false)->after('is_verified');
            $table->text('blacklist_reason')->nullable()->after('is_blacklisted');
        });

        // 2.2.6 Location GPS + jam operasional
        Schema::table('m_location', function (Blueprint $table) {
            $table->string('opening_hours')->nullable()->after('contact_phone');
            $table->decimal('latitude', 10, 7)->nullable()->after('opening_hours');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
        });

        // 2.2.7 Workshop rating + specialization
        Schema::table('m_workshop', function (Blueprint $table) {
            $table->decimal('rating', 2, 1)->nullable()->after('contact_person');
            $table->string('specialization')->nullable()->after('rating');
        });

        // 2.2.9 Promo applicable categories
        Schema::table('m_promo', function (Blueprint $table) {
            $table->json('applicable_categories')->nullable()->after('max_usage');
        });

        // 2.2.2 Vehicle mutasi riwayat
        Schema::create('vehicle_location_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vehicle_id');
            $table->unsignedBigInteger('from_location_id')->nullable();
            $table->unsignedBigInteger('to_location_id');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('vehicle_id')->references('vehicle_id')->on('m_vehicle')->onDelete('cascade');
            $table->foreign('from_location_id')->references('location_id')->on('m_location')->onDelete('set null');
            $table->foreign('to_location_id')->references('location_id')->on('m_location')->onDelete('cascade');
        });

        // Add current location to vehicle for quick lookup (optional)
        Schema::table('m_vehicle', function (Blueprint $table) {
            $table->unsignedBigInteger('location_id')->nullable()->after('model_id');
            $table->foreign('location_id')->references('location_id')->on('m_location')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('m_vehicle', function (Blueprint $table) {
            $table->dropForeign(['location_id']);
            $table->dropColumn('location_id');
        });
        Schema::dropIfExists('vehicle_location_histories');
        Schema::table('m_promo', function (Blueprint $table) {
            $table->dropColumn('applicable_categories');
        });
        Schema::table('m_workshop', function (Blueprint $table) {
            $table->dropColumn(['rating', 'specialization']);
        });
        Schema::table('m_location', function (Blueprint $table) {
            $table->dropColumn(['opening_hours', 'latitude', 'longitude']);
        });
        Schema::table('m_customer', function (Blueprint $table) {
            $table->dropColumn(['is_blacklisted', 'blacklist_reason']);
        });
        Schema::table('m_vehicle_model', function (Blueprint $table) {
            $table->dropColumn('photo');
        });
    }
};
