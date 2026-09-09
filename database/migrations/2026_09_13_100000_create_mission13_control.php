<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('display_name', 150)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('phone_normalized', 30)->nullable();
            $table->string('email', 160)->nullable();
            $table->boolean('is_company')->default(false);
            $table->string('legal_name', 200)->nullable();
            $table->string('tax_id', 40)->nullable();
            $table->string('fiscal_address', 500)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('province', 100)->nullable();
            $table->string('country', 100)->default('España');
            $table->string('notes', 1000)->nullable();
            $table->timestamps();
            $table->unique(['restaurant_id', 'phone_normalized']);
            $table->unique(['restaurant_id', 'tax_id']);
            $table->index(['restaurant_id', 'display_name']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->after('current_employee_id')->constrained()->nullOnDelete();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->boolean('track_stock')->default(false)->after('is_available');
            $table->integer('stock_quantity')->default(0)->after('track_stock');
            $table->integer('stock_minimum')->nullable()->after('stock_quantity');
        });

        Schema::table('order_lines', function (Blueprint $table) {
            $table->unsignedBigInteger('cost_minor')->nullable()->after('unit_total_minor');
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_line_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 30);
            $table->integer('quantity_delta');
            $table->integer('resulting_quantity');
            $table->string('reason', 500)->nullable();
            $table->timestamps();
            $table->index(['restaurant_id', 'order_line_id', 'type']);
            $table->index(['restaurant_id', 'product_id', 'created_at']);
        });

        Schema::create('sale_document_counters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20);
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();
            $table->unique(['restaurant_id', 'kind']);
        });

        Schema::create('sale_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('issued_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('issued_by_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('kind', 20);
            $table->unsignedBigInteger('number');
            $table->string('reference', 40);
            $table->char('currency', 3)->default('EUR');
            $table->unsignedBigInteger('subtotal_minor')->default(0);
            $table->unsignedBigInteger('discount_minor')->default(0);
            $table->unsignedBigInteger('charges_minor')->default(0);
            $table->unsignedBigInteger('total_minor')->default(0);
            $table->json('tax_breakdown')->nullable();
            $table->json('snapshot');
            $table->string('status', 20)->default('issued');
            $table->timestamp('issued_at')->nullable();
            $table->timestamps();
            $table->unique(['restaurant_id', 'kind', 'number']);
            $table->unique(['order_id', 'kind']);
            $table->index(['restaurant_id', 'kind', 'issued_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_documents');
        Schema::dropIfExists('sale_document_counters');
        Schema::dropIfExists('stock_movements');
        Schema::table('order_lines', fn (Blueprint $table) => $table->dropColumn('cost_minor'));
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn(['track_stock', 'stock_quantity', 'stock_minimum']));
        Schema::table('orders', fn (Blueprint $table) => $table->dropConstrainedForeignId('customer_id'));
        Schema::dropIfExists('customers');
    }
};
