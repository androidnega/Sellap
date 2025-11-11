<?php

namespace App\Helpers;

class PaginationHelper {
    
    /**
     * Generate pagination data
     */
    public static function generate($currentPage, $totalItems, $itemsPerPage, $baseUrl) {
        $totalPages = ceil($totalItems / $itemsPerPage);
        $currentPage = max(1, min($currentPage, $totalPages));
        
        $pagination = [
            'current_page' => $currentPage,
            'total_pages' => $totalPages,
            'total_items' => $totalItems,
            'items_per_page' => $itemsPerPage,
            'has_previous' => $currentPage > 1,
            'has_next' => $currentPage < $totalPages,
            'previous_page' => $currentPage > 1 ? $currentPage - 1 : null,
            'next_page' => $currentPage < $totalPages ? $currentPage + 1 : null,
            'base_url' => $baseUrl
        ];
        
        // Generate page numbers to show
        $pagination['pages'] = self::generatePageNumbers($currentPage, $totalPages);
        
        return $pagination;
    }
    
    /**
     * Generate array of page numbers to display
     */
    private static function generatePageNumbers($currentPage, $totalPages, $maxPages = 5) {
        $pages = [];
        
        if ($totalPages <= $maxPages) {
            // Show all pages if total is small
            for ($i = 1; $i <= $totalPages; $i++) {
                $pages[] = $i;
            }
        } else {
            // Show pages around current page
            $start = max(1, $currentPage - 2);
            $end = min($totalPages, $currentPage + 2);
            
            // Adjust if we're near the beginning or end
            if ($end - $start < $maxPages - 1) {
                if ($start == 1) {
                    $end = min($totalPages, $start + $maxPages - 1);
                } else {
                    $start = max(1, $end - $maxPages + 1);
                }
            }
            
            for ($i = $start; $i <= $end; $i++) {
                $pages[] = $i;
            }
        }
        
        return $pages;
    }
    
    /**
     * Build URL with page parameter, replacing existing page param if present
     */
    private static function buildPageUrl($baseUrl, $pageNumber) {
        // Parse URL into components
        $urlParts = parse_url($baseUrl);
        $path = isset($urlParts['path']) ? $urlParts['path'] : '';
        $query = isset($urlParts['query']) ? $urlParts['query'] : '';
        
        // Parse existing query parameters
        parse_str($query, $params);
        
        // Set or replace page parameter
        $params['page'] = $pageNumber;
        
        // Rebuild query string
        $newQuery = http_build_query($params);
        
        // Rebuild full URL
        $url = $path;
        if (!empty($newQuery)) {
            $url .= '?' . $newQuery;
        }
        
        // Add fragment if it existed
        if (isset($urlParts['fragment'])) {
            $url .= '#' . $urlParts['fragment'];
        }
        
        return $url;
    }
    
    /**
     * Generate pagination HTML
     */
    public static function render($pagination) {
        if ($pagination['total_pages'] <= 1) {
            return '';
        }
        
        $html = '<div class="flex items-center justify-between mt-6">';
        
        // Info
        $startItem = (($pagination['current_page'] - 1) * $pagination['items_per_page']) + 1;
        $endItem = min($pagination['current_page'] * $pagination['items_per_page'], $pagination['total_items']);
        
        $html .= '<div class="text-sm text-gray-700">';
        $html .= 'Showing ' . $startItem . ' to ' . $endItem . ' of ' . $pagination['total_items'] . ' results';
        $html .= '</div>';
        
        // Pagination controls
        $html .= '<div class="flex items-center space-x-2">';
        
        // Previous button
        if ($pagination['has_previous']) {
            $prevUrl = self::buildPageUrl($pagination['base_url'], $pagination['previous_page']);
            $html .= '<a href="' . htmlspecialchars($prevUrl) . '" class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50">Previous</a>';
        } else {
            $html .= '<span class="px-3 py-2 text-sm font-medium text-gray-300 bg-gray-100 border border-gray-200 rounded-md cursor-not-allowed">Previous</span>';
        }
        
        // Page numbers
        foreach ($pagination['pages'] as $page) {
            if ($page == $pagination['current_page']) {
                $html .= '<span class="px-3 py-2 text-sm font-medium text-white bg-blue-600 border border-blue-600 rounded-md">' . $page . '</span>';
            } else {
                $pageUrl = self::buildPageUrl($pagination['base_url'], $page);
                $html .= '<a href="' . htmlspecialchars($pageUrl) . '" class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50">' . $page . '</a>';
            }
        }
        
        // Next button
        if ($pagination['has_next']) {
            $nextUrl = self::buildPageUrl($pagination['base_url'], $pagination['next_page']);
            $html .= '<a href="' . htmlspecialchars($nextUrl) . '" class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50">Next</a>';
        } else {
            $html .= '<span class="px-3 py-2 text-sm font-medium text-gray-300 bg-gray-100 border border-gray-200 rounded-md cursor-not-allowed">Next</span>';
        }
        
        $html .= '</div>';
        $html .= '</div>';
        
        return $html;
    }
}
