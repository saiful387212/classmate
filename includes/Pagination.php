<?php
/**
 * Pagination Class - Handles pagination logic
 */

class Pagination {
    private $totalItems;
    private $itemsPerPage;
    private $currentPage;
    private $totalPages;
    private $url;
    private $params;
    
    public function __construct($totalItems, $itemsPerPage = 20, $currentPage = 1, $url = null) {
        $this->totalItems = (int)$totalItems;
        $this->itemsPerPage = (int)$itemsPerPage;
        $this->currentPage = max(1, (int)$currentPage);
        $this->totalPages = ceil($this->totalItems / $this->itemsPerPage);
        $this->currentPage = min($this->currentPage, $this->totalPages);
        
        if ($this->currentPage < 1) {
            $this->currentPage = 1;
        }
        
        $this->url = $url ?: $_SERVER['REQUEST_URI'];
        $this->params = $_GET;
        unset($this->params['page']);
    }
    
    public function getOffset() {
        return ($this->currentPage - 1) * $this->itemsPerPage;
    }
    
    public function getLimit() {
        return $this->itemsPerPage;
    }
    
    public function getCurrentPage() {
        return $this->currentPage;
    }
    
    public function getTotalPages() {
        return $this->totalPages;
    }
    
    public function getTotalItems() {
        return $this->totalItems;
    }
    
    public function getPageUrl($page) {
        $params = $this->params;
        $params['page'] = $page;
        $query = http_build_query($params);
        return strtok($this->url, '?') . '?' . $query;
    }
    
    public function render($style = 'bootstrap') {
        if ($this->totalPages <= 1) {
            return '';
        }
        
        $html = '<nav class="pagination-container" aria-label="Pagination">';
        $html .= '<ul class="pagination">';
        
        // Previous button
        if ($this->currentPage > 1) {
            $html .= '<li class="page-item">';
            $html .= '<a href="' . $this->getPageUrl($this->currentPage - 1) . '" class="page-link" aria-label="Previous">';
            $html .= '<i class="fas fa-chevron-left"></i>';
            $html .= '</a>';
            $html .= '</li>';
        }
        
        // Page numbers
        $start = max(1, $this->currentPage - 2);
        $end = min($this->totalPages, $this->currentPage + 2);
        
        if ($start > 1) {
            $html .= '<li class="page-item">';
            $html .= '<a href="' . $this->getPageUrl(1) . '" class="page-link">1</a>';
            $html .= '</li>';
            
            if ($start > 2) {
                $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
        }
        
        for ($i = $start; $i <= $end; $i++) {
            $active = ($i == $this->currentPage) ? ' active' : '';
            $html .= '<li class="page-item' . $active . '">';
            if ($i == $this->currentPage) {
                $html .= '<span class="page-link">' . $i . '</span>';
            } else {
                $html .= '<a href="' . $this->getPageUrl($i) . '" class="page-link">' . $i . '</a>';
            }
            $html .= '</li>';
        }
        
        if ($end < $this->totalPages) {
            if ($end < $this->totalPages - 1) {
                $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
            $html .= '<li class="page-item">';
            $html .= '<a href="' . $this->getPageUrl($this->totalPages) . '" class="page-link">' . $this->totalPages . '</a>';
            $html .= '</li>';
        }
        
        // Next button
        if ($this->currentPage < $this->totalPages) {
            $html .= '<li class="page-item">';
            $html .= '<a href="' . $this->getPageUrl($this->currentPage + 1) . '" class="page-link" aria-label="Next">';
            $html .= '<i class="fas fa-chevron-right"></i>';
            $html .= '</a>';
            $html .= '</li>';
        }
        
        $html .= '</ul>';
        
        // Info text
        $startItem = $this->getOffset() + 1;
        $endItem = min($this->getOffset() + $this->itemsPerPage, $this->totalItems);
        $html .= '<div class="pagination-info">';
        $html .= 'Showing ' . $startItem . ' to ' . $endItem . ' of ' . $this->totalItems . ' items';
        $html .= '</div>';
        
        $html .= '</nav>';
        
        return $html;
    }
}