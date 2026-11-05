<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var array<string, list<string>>
     */
    private array $columns = [
        'items' => ['gross_weight', 'stone_weight', 'net_weight'],
        'sale_items' => ['weight'],
        'sale_exchanges' => ['weight'],
        'pawn_items' => ['gross_weight', 'stone_weight', 'net_weight'],
        'purchase_items' => ['gross_weight', 'stone_weight', 'net_weight'],
        'stock_movements' => ['weight'],
    ];

    public function up(): void
    {
        foreach ($this->columns as $tableName => $columns) {
            Schema::table($tableName, function (Blueprint $table) use ($columns): void {
                foreach ($columns as $column) {
                    $table->unsignedBigInteger($column.'_points')->nullable()->after($column);
                }
            });

            foreach ($columns as $column) {
                DB::table($tableName)->update([
                    $column.'_points' => DB::raw('CAST(ROUND('.$column.' / 0.01215) AS UNSIGNED)'),
                ]);
            }

            Schema::table($tableName, function (Blueprint $table) use ($columns): void {
                foreach ($columns as $column) {
                    $table->dropColumn($column);
                }
            });

            Schema::table($tableName, function (Blueprint $table) use ($columns): void {
                foreach ($columns as $column) {
                    $table->unsignedBigInteger($column.'_points')->nullable(false)->change();
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->columns as $tableName => $columns) {
            Schema::table($tableName, function (Blueprint $table) use ($columns): void {
                foreach ($columns as $column) {
                    $table->decimal($column, 14, 3)->nullable()->after($column.'_points');
                }
            });

            foreach ($columns as $column) {
                DB::table($tableName)->update([
                    $column => DB::raw($column.'_points * 0.01215'),
                ]);
            }

            Schema::table($tableName, function (Blueprint $table) use ($columns): void {
                foreach ($columns as $column) {
                    $table->dropColumn($column.'_points');
                }
            });
        }
    }
};
