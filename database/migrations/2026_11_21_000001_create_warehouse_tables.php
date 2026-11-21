<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('contact_person')->nullable();
            $table->string('phone')->nullable();
            $table->string('region')->nullable();
            $table->decimal('rating', 2, 1)->default(4.0);
            $table->timestamps();
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type')->default('retailer'); // supermarket | retailer | wholesaler | hotel | market
            $table->string('phone')->nullable();
            $table->string('location')->nullable();
            $table->timestamps();
        });

        Schema::create('storage_locations', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('zone'); // receiving | pre_ripening | ripening | sales | dispatch
            $table->unsignedInteger('capacity_kg');
            $table->string('status')->default('active'); // active | gassing | venting | idle | maintenance
            $table->decimal('target_temp', 4, 1)->nullable();
            $table->decimal('target_humidity', 4, 1)->nullable();
            $table->unsignedSmallInteger('target_ethylene')->nullable(); // ppm
            $table->timestamps();
        });

        Schema::create('room_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('storage_location_id')->constrained()->cascadeOnDelete();
            $table->decimal('temperature', 4, 1);
            $table->decimal('humidity', 4, 1);
            $table->unsignedSmallInteger('ethylene')->default(0);
            $table->dateTime('recorded_at')->index();
        });

        Schema::create('batches', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->foreignId('supplier_id')->constrained();
            $table->foreignId('storage_location_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('variety')->default('Cavendish');
            $table->unsignedInteger('initial_kg');
            $table->unsignedInteger('remaining_kg');
            $table->unsignedInteger('cartons')->default(0);
            $table->string('stage')->default('green'); // green | turning | ripe | overripe
            $table->string('status')->default('receiving'); // receiving | pre_ripening | ripening | sales | depleted | spoiled
            $table->char('grade', 1)->default('A');
            $table->decimal('cost_per_kg', 8, 2)->default(0);
            $table->dateTime('arrived_at')->index();
            $table->timestamp('ripening_started_at')->nullable();
            $table->timestamp('expected_ripe_at')->nullable();
            $table->timestamp('ripe_at')->nullable();
            $table->timestamp('depleted_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['status', 'stage']);
        });

        Schema::create('inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type')->default('arrival'); // arrival | ripening | pre_sale
            $table->string('stage');
            $table->char('grade', 1);
            $table->decimal('damage_pct', 4, 1)->default(0);
            $table->decimal('pulp_temp', 4, 1)->nullable();
            $table->json('checks')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('batch_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_location_id')->nullable()->constrained('storage_locations')->nullOnDelete();
            $table->foreignId('to_location_id')->nullable()->constrained('storage_locations')->nullOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->unsignedInteger('quantity_kg');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamps();
        });

        Schema::create('losses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('quantity_kg');
            $table->string('reason'); // spoiled | damaged | overripe | handling | other
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('price_rules', function (Blueprint $table) {
            $table->id();
            $table->string('stage'); // ripe | overripe
            $table->char('grade', 1);
            $table->decimal('price_per_kg', 8, 2);
            $table->decimal('discount_pct', 4, 1)->default(0);
            $table->timestamps();
            $table->unique(['stage', 'grade']);
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('customer_id')->constrained();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('allocated'); // allocated | packed | dispatched | delivered | cancelled
            $table->unsignedInteger('quantity_kg')->default(0);
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->dateTime('ordered_at')->index();
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('batch_id')->constrained();
            $table->string('stage');
            $table->unsignedInteger('quantity_kg');
            $table->decimal('unit_price', 8, 2);
            $table->decimal('discount_pct', 4, 1)->default(0);
            $table->decimal('line_total', 12, 2);
            $table->timestamps();
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action'); // e.g. batch.received
            $table->nullableMorphs('subject');
            $table->string('description');
            $table->json('meta')->nullable();
            $table->dateTime('created_at')->index();
        });
    }

    public function down(): void
    {
        foreach (['activity_logs', 'order_items', 'orders', 'price_rules', 'losses', 'batch_movements', 'inspections', 'batches', 'room_readings', 'storage_locations', 'customers', 'suppliers'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
