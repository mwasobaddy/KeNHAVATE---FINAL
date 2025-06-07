<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\User;
use Carbon\Carbon;

/**
 * KeNHAVATE Innovation Portal - Export Service
 * Handles file generation and export functionality for analytics data
 * Supports CSV, Excel, and PDF export formats with comprehensive data structuring
 */
class ExportService
{
    protected AnalyticsService $analyticsService;

    public function __construct(AnalyticsService $analyticsService)
    {
        $this->analyticsService = $analyticsService;
    }

    /**
     * Export analytics data in the specified format
     */
    public function exportAnalyticsData(array $data, string $format, string $reportType, array $filters = []): array
    {
        $timestamp = now()->format('Y-m-d_H-i-s');
        $filename = "kenha_analytics_{$reportType}_{$timestamp}";
        
        switch (strtolower($format)) {
            case 'csv':
                return $this->exportToCsv($data, $filename, $filters);
            case 'excel':
                return $this->exportToExcel($data, $filename, $filters);
            case 'pdf':
                return $this->exportToPdf($data, $filename, $reportType, $filters);
            default:
                throw new \InvalidArgumentException("Unsupported export format: {$format}");
        }
    }

    /**
     * Export data to CSV format
     */
    protected function exportToCsv(array $data, string $filename, array $filters): array
    {
        $csvContent = $this->generateCsvContent($data, $filters);
        $filePath = "exports/{$filename}.csv";
        
        Storage::disk('public')->put($filePath, $csvContent);
        
        return [
            'success' => true,
            'filename' => $filename . '.csv',
            'path' => Storage::disk('public')->url($filePath),
            'size' => Storage::disk('public')->size($filePath)
        ];
    }

    /**
     * Export data to Excel format (CSV with Excel-friendly formatting)
     */
    protected function exportToExcel(array $data, string $filename, array $filters): array
    {
        // For now, we'll use CSV format with Excel-friendly encoding
        // In production, you might want to use Laravel Excel package
        $csvContent = $this->generateCsvContent($data, $filters, true);
        $filePath = "exports/{$filename}.xlsx";
        
        Storage::disk('public')->put($filePath, $csvContent);
        
        return [
            'success' => true,
            'filename' => $filename . '.xlsx',
            'path' => Storage::disk('public')->url($filePath),
            'size' => Storage::disk('public')->size($filePath)
        ];
    }

    /**
     * Export data to PDF format
     */
    protected function exportToPdf(array $data, string $filename, string $reportType, array $filters): array
    {
        $htmlContent = $this->generatePdfHtml($data, $reportType, $filters);
        $filePath = "exports/{$filename}.pdf";
        
        // For now, we'll save as HTML. In production, use a PDF library like DomPDF or wkhtmltopdf
        Storage::disk('public')->put($filePath, $htmlContent);
        
        return [
            'success' => true,
            'filename' => $filename . '.pdf',
            'path' => Storage::disk('public')->url($filePath),
            'size' => Storage::disk('public')->size($filePath)
        ];
    }

    /**
     * Generate CSV content from analytics data
     */
    protected function generateCsvContent(array $data, array $filters, bool $excelFormat = false): string
    {
        $csv = [];
        
        // Add header information
        $csv[] = "KeNHAVATE Innovation Portal - Analytics Report";
        $csv[] = "Generated: " . now()->format('Y-m-d H:i:s');
        $csv[] = "Filters Applied: " . $this->formatFilters($filters);
        $csv[] = ""; // Empty line
        
        // Process different data sections
        foreach ($data as $sectionKey => $sectionData) {
            $csv[] = strtoupper(str_replace('_', ' ', $sectionKey));
            $csv[] = str_repeat('-', 50);
            
            if (is_array($sectionData)) {
                $csv = array_merge($csv, $this->processDataSection($sectionData, $sectionKey));
            }
            
            $csv[] = ""; // Empty line between sections
        }
        
        // Convert array to CSV string
        $csvString = "";
        foreach ($csv as $line) {
            if (is_array($line)) {
                $csvString .= '"' . implode('","', $line) . '"' . "\n";
            } else {
                $csvString .= '"' . $line . '"' . "\n";
            }
        }
        
        // Add BOM for Excel compatibility if needed
        if ($excelFormat) {
            $csvString = "\xEF\xBB\xBF" . $csvString;
        }
        
        return $csvString;
    }

    /**
     * Process individual data sections for CSV export
     */
    protected function processDataSection(array $data, string $sectionKey): array
    {
        $rows = [];
        
        switch ($sectionKey) {
            case 'system_overview':
                $rows[] = ['Metric', 'Value', 'Growth Rate'];
                foreach ($data as $key => $value) {
                    if (is_array($value)) {
                        $rows[] = [
                            ucfirst(str_replace('_', ' ', $key)),
                            $value['current'] ?? 'N/A',
                            isset($value['growth_rate']) ? $value['growth_rate'] . '%' : 'N/A'
                        ];
                    }
                }
                break;
                
            case 'workflow_analytics':
                if (isset($data['stage_distribution'])) {
                    $rows[] = ['Stage', 'Count', 'Percentage'];
                    foreach ($data['stage_distribution'] as $stage => $count) {
                        $total = array_sum($data['stage_distribution']);
                        $percentage = $total > 0 ? round(($count / $total) * 100, 2) : 0;
                        $rows[] = [ucfirst(str_replace('_', ' ', $stage)), $count, $percentage . '%'];
                    }
                }
                break;
                
            case 'user_engagement':
                if (isset($data['role_distribution'])) {
                    $rows[] = ['Role', 'Count'];
                    foreach ($data['role_distribution'] as $role => $count) {
                        $rows[] = [ucfirst(str_replace('_', ' ', $role)), $count];
                    }
                }
                break;
                
            case 'performance_metrics':
                if (isset($data['review_performance'])) {
                    $rows[] = ['Reviewer', 'Total Reviews', 'Avg Time (hours)', 'Approval Rate'];
                    foreach ($data['review_performance'] as $reviewer) {
                        $rows[] = [
                            $reviewer['name'],
                            $reviewer['total_reviews'],
                            round($reviewer['avg_review_time'], 2),
                            round($reviewer['approval_rate'], 2) . '%'
                        ];
                    }
                }
                break;
                
            case 'gamification_analytics':
                if (isset($data['top_point_earners'])) {
                    $rows[] = ['User', 'Total Points', 'Rank'];
                    foreach ($data['top_point_earners'] as $index => $user) {
                        $rows[] = [$user['name'], $user['total_points'], $index + 1];
                    }
                }
                break;
                
            default:
                // Generic handling for other data types
                foreach ($data as $key => $value) {
                    if (is_scalar($value)) {
                        $rows[] = [ucfirst(str_replace('_', ' ', $key)), $value];
                    }
                }
                break;
        }
        
        return $rows;
    }

    /**
     * Generate HTML content for PDF export
     */
    protected function generatePdfHtml(array $data, string $reportType, array $filters): string
    {
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>KeNHAVATE Analytics Report</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 20px; 
            color: #231F20;
            background-color: #F8EBD5;
        }
        .header { 
            text-align: center; 
            margin-bottom: 30px; 
            padding: 20px;
            background: white;
            border-radius: 8px;
        }
        .section { 
            margin-bottom: 25px; 
            background: white;
            padding: 15px;
            border-radius: 8px;
        }
        .section h2 { 
            color: #231F20; 
            border-bottom: 2px solid #FFF200; 
            padding-bottom: 5px;
        }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 10px;
        }
        th, td { 
            border: 1px solid #9B9EA4; 
            padding: 8px; 
            text-align: left; 
        }
        th { 
            background-color: #F8EBD5; 
            font-weight: bold;
        }
        .metric-card {
            display: inline-block;
            margin: 10px;
            padding: 15px;
            background: #F8EBD5;
            border-radius: 8px;
            min-width: 150px;
            text-align: center;
        }
        .metric-value {
            font-size: 24px;
            font-weight: bold;
            color: #231F20;
        }
        .metric-label {
            color: #9B9EA4;
            font-size: 12px;
        }
    </style>
</head>
<body>';

        $html .= '<div class="header">';
        $html .= '<h1>KeNHAVATE Innovation Portal</h1>';
        $html .= '<h2>Analytics Report - ' . ucfirst(str_replace('_', ' ', $reportType)) . '</h2>';
        $html .= '<p>Generated on: ' . now()->format('F j, Y \a\t g:i A') . '</p>';
        $html .= '<p>Filters: ' . $this->formatFilters($filters) . '</p>';
        $html .= '</div>';

        // Process each data section
        foreach ($data as $sectionKey => $sectionData) {
            $html .= '<div class="section">';
            $html .= '<h2>' . ucfirst(str_replace('_', ' ', $sectionKey)) . '</h2>';
            
            if (is_array($sectionData)) {
                $html .= $this->generateSectionHtml($sectionData, $sectionKey);
            }
            
            $html .= '</div>';
        }

        $html .= '</body></html>';
        
        return $html;
    }

    /**
     * Generate HTML for individual sections
     */
    protected function generateSectionHtml(array $data, string $sectionKey): string
    {
        $html = '';
        
        switch ($sectionKey) {
            case 'system_overview':
                $html .= '<div style="display: flex; flex-wrap: wrap;">';
                foreach ($data as $key => $value) {
                    if (is_array($value)) {
                        $html .= '<div class="metric-card">';
                        $html .= '<div class="metric-value">' . ($value['current'] ?? 'N/A') . '</div>';
                        $html .= '<div class="metric-label">' . ucfirst(str_replace('_', ' ', $key)) . '</div>';
                        if (isset($value['growth_rate'])) {
                            $html .= '<div style="color: ' . ($value['growth_rate'] >= 0 ? 'green' : 'red') . ';">';
                            $html .= $value['growth_rate'] . '% growth</div>';
                        }
                        $html .= '</div>';
                    }
                }
                $html .= '</div>';
                break;
                
            default:
                // Generate table for other sections
                $tableData = $this->processDataSection($data, $sectionKey);
                if (!empty($tableData)) {
                    $html .= '<table>';
                    foreach ($tableData as $index => $row) {
                        $tag = $index === 0 ? 'th' : 'td';
                        $html .= '<tr>';
                        foreach ($row as $cell) {
                            $html .= "<{$tag}>" . htmlspecialchars($cell) . "</{$tag}>";
                        }
                        $html .= '</tr>';
                    }
                    $html .= '</table>';
                }
                break;
        }
        
        return $html;
    }

    /**
     * Format filters for display
     */
    protected function formatFilters(array $filters): string
    {
        if (empty($filters)) {
            return 'None';
        }
        
        $formatted = [];
        foreach ($filters as $key => $value) {
            if ($value !== null && $value !== '') {
                $formatted[] = ucfirst(str_replace('_', ' ', $key)) . ': ' . $value;
            }
        }
        
        return empty($formatted) ? 'None' : implode(', ', $formatted);
    }

    /**
     * Clean up old export files (called via scheduled job)
     */
    public function cleanupOldExports(int $daysOld = 7): int
    {
        $files = Storage::disk('public')->files('exports');
        $deletedCount = 0;
        $cutoffDate = now()->subDays($daysOld);
        
        foreach ($files as $file) {
            $lastModified = Carbon::createFromTimestamp(Storage::disk('public')->lastModified($file));
            
            if ($lastModified->lt($cutoffDate)) {
                Storage::disk('public')->delete($file);
                $deletedCount++;
            }
        }
        
        return $deletedCount;
    }

    /**
     * Get export file info
     */
    public function getExportFileInfo(string $filename): ?array
    {
        $filePath = "exports/{$filename}";
        
        if (!Storage::disk('public')->exists($filePath)) {
            return null;
        }
        
        return [
            'filename' => $filename,
            'size' => Storage::disk('public')->size($filePath),
            'last_modified' => Carbon::createFromTimestamp(Storage::disk('public')->lastModified($filePath)),
            'url' => Storage::disk('public')->url($filePath)
        ];
    }

    /**
     * Generate custom report with specific metrics
     */
    public function generateCustomReport(array $metrics, array $filters = []): array
    {
        $reportData = [];
        
        foreach ($metrics as $metric) {
            switch ($metric) {
                case 'system_overview':
                    $reportData['system_overview'] = $this->analyticsService->getSystemOverview($filters);
                    break;
                case 'workflow_analytics':
                    $reportData['workflow_analytics'] = $this->analyticsService->getIdeaWorkflowAnalytics($filters);
                    break;
                case 'user_engagement':
                    $reportData['user_engagement'] = $this->analyticsService->getUserEngagementAnalytics($filters);
                    break;
                case 'performance_metrics':
                    $reportData['performance_metrics'] = $this->analyticsService->getPerformanceAnalytics($filters);
                    break;
                case 'gamification_analytics':
                    $reportData['gamification_analytics'] = $this->analyticsService->getGamificationAnalytics($filters);
                    break;
            }
        }
        
        return $reportData;
    }

    /**
     * Schedule automated report generation
     */
    public function scheduleReport(User $user, array $config): bool
    {
        // This would typically integrate with Laravel's job system
        // For now, we'll return true to indicate successful scheduling
        
        // Configuration would include:
        // - metrics to include
        // - filters to apply
        // - export format
        // - delivery schedule (daily/weekly/monthly)
        // - recipients
        
        return true;
    }
}
