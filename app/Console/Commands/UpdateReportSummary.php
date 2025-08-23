<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ReportSummaryService;
use Illuminate\Console\Command;

class UpdateReportSummary extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'facebook:update-summary 
                            {--date= : Ngày cập nhật (YYYY-MM-DD), mặc định là hôm nay}
                            {--all : Cập nhật tất cả ngày trong tháng hiện tại}';

    /**
     * The console command description.
     */
    protected $description = 'Cập nhật bảng tổng hợp cho báo cáo Facebook';

    /**
     * Execute the console command.
     */
    public function handle(ReportSummaryService $summaryService): int
    {
        $this->info('Bắt đầu cập nhật bảng tổng hợp...');

        try {
            if ($this->option('all')) {
                $this->updateAllDates($summaryService);
            } else {
                $date = $this->option('date') ?? date('Y-m-d');
                $this->updateSingleDate($summaryService, $date);
            }

            $this->info('Cập nhật bảng tổng hợp thành công!');
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Lỗi cập nhật bảng tổng hợp: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Cập nhật một ngày cụ thể
     */
    private function updateSingleDate(ReportSummaryService $summaryService, string $date): void
    {
        $this->info("Cập nhật bảng tổng hợp cho ngày: {$date}");

        $results = $summaryService->updateAllSummaries($date);

        $this->table(
            ['Entity Type', 'Updated Count'],
            collect($results)->map(fn($count, $type) => [$type, $count])->toArray()
        );
    }

    /**
     * Cập nhật tất cả ngày trong tháng hiện tại
     */
    private function updateAllDates(ReportSummaryService $summaryService): void
    {
        $currentMonth = date('Y-m');
        $daysInMonth = date('t');
        $totalUpdated = 0;

        $this->info("Cập nhật bảng tổng hợp cho tháng: {$currentMonth}");

        $progressBar = $this->output->createProgressBar($daysInMonth);
        $progressBar->start();

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date = sprintf('%s-%02d', $currentMonth, $day);
            
            try {
                $results = $summaryService->updateAllSummaries($date);
                $totalUpdated += (int) array_sum($results);
            } catch (\Exception $e) {
                $this->warn("Lỗi cập nhật ngày {$date}: " . $e->getMessage());
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();
        $this->info("Hoàn thành! Tổng cộng cập nhật {$totalUpdated} records.");
    }
}
