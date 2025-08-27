<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckDatabaseSchema extends Command
{
    protected $signature = 'db:check-schema {table : Tên bảng cần kiểm tra}';
    protected $description = 'Kiểm tra schema của bảng trong database';

    public function handle(): int
    {
        $tableName = $this->argument('table');
        
        try {
            $columns = DB::select("DESCRIBE {$tableName}");
            
            $this->info("📊 Schema của bảng: {$tableName}");
            $this->table(
                ['Field', 'Type', 'Null', 'Key', 'Default', 'Extra'],
                array_map(function($column) {
                    return [
                        $column->Field,
                        $column->Type,
                        $column->Null,
                        $column->Key,
                        $column->Default ?? 'NULL',
                        $column->Extra
                    ];
                }, $columns)
            );
            
            // Đếm số lượng video fields
            $videoFields = array_filter($columns, function($column) {
                return strpos($column->Field, 'video_') !== false;
            });
            
            $this->info("🎥 Tổng số video fields: " . count($videoFields));
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error("❌ Lỗi: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}

