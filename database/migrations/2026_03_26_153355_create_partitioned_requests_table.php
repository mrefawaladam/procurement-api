<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Example of year-based partitioning for 5M+ records
        DB::statement('CREATE TABLE requests_partitioned (
            id BIGSERIAL,
            user_id BIGINT NOT NULL,
            department_id BIGINT NOT NULL,
            status VARCHAR(50) NOT NULL,
            total_amount DECIMAL(15, 2),
            notes TEXT,
            created_at TIMESTAMP NOT NULL,
            updated_at TIMESTAMP,
            deleted_at TIMESTAMP,
            PRIMARY KEY (id, created_at)
        ) PARTITION BY RANGE (created_at)');

        // Create partitions for 2024, 2025, 2026
        DB::statement("CREATE TABLE requests_y2024 PARTITION OF requests_partitioned FOR VALUES FROM ('2024-01-01') TO ('2025-01-01')");
        DB::statement("CREATE TABLE requests_y2025 PARTITION OF requests_partitioned FOR VALUES FROM ('2025-01-01') TO ('2026-01-01')");
        DB::statement("CREATE TABLE requests_y2026 PARTITION OF requests_partitioned FOR VALUES FROM ('2026-01-01') TO ('2027-01-01')");
    }

    public function down(): void
    {
        Schema::dropIfExists('requests_partitioned');
    }
};
