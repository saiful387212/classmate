/**
 * ClassMate - Admin Dashboard JavaScript
 */

document.addEventListener('DOMContentLoaded', function() {
    'use strict';
    
    // ===== Check if Chart.js is loaded =====
    if (typeof Chart === 'undefined') {
        console.warn('Chart.js not loaded. Charts will not render.');
        return;
    }
    
    // ===== Get chart data =====
    const data = window.chartData || {};
    console.log('Chart Data:', data);
    
    // ===== Helper: Check if data has any values =====
    function hasData(dataObj) {
        if (!dataObj) return false;
        const values = Object.values(dataObj);
        return values.some(v => v > 0);
    }
    
    // ===== 1. Class Status Pie Chart =====
    const classCtx = document.getElementById('classStatusChart');
    if (classCtx && data.classStatus && hasData(data.classStatus)) {
        const total = Object.values(data.classStatus).reduce((a, b) => a + b, 0);
        
        new Chart(classCtx, {
            type: 'doughnut',
            data: {
                labels: ['Scheduled', 'Completed', 'Cancelled', 'Retaked'],
                datasets: [{
                    data: [
                        data.classStatus.scheduled || 0,
                        data.classStatus.completed || 0,
                        data.classStatus.cancelled || 0,
                        data.classStatus.retaked || 0
                    ],
                    backgroundColor: [
                        '#4F46E5', // Primary - Scheduled
                        '#10B981', // Success - Completed
                        '#EF4444', // Danger - Cancelled
                        '#F59E0B'  // Warning - Retaked
                    ],
                    borderWidth: 2,
                    borderColor: '#FFFFFF'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed || 0;
                                const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                return label + ': ' + value + ' (' + percentage + '%)';
                            }
                        }
                    }
                },
                cutout: '70%'
            }
        });
    } else if (classCtx) {
        // Show empty state message
        classCtx.parentElement.innerHTML = `
            <div class="empty-state small">
                <i class="fas fa-chart-pie"></i>
                <p>No class data available</p>
            </div>
        `;
    }
    
    // ===== 2. Student Status Pie Chart =====
    const studentCtx = document.getElementById('studentStatusChart');
    if (studentCtx && data.studentStatus && hasData(data.studentStatus)) {
        const total = Object.values(data.studentStatus).reduce((a, b) => a + b, 0);
        
        new Chart(studentCtx, {
            type: 'doughnut',
            data: {
                labels: ['Active', 'Pending', 'Suspended', 'Inactive'],
                datasets: [{
                    data: [
                        data.studentStatus.active || 0,
                        data.studentStatus.pending || 0,
                        data.studentStatus.suspended || 0,
                        data.studentStatus.inactive || 0
                    ],
                    backgroundColor: [
                        '#10B981', // Success - Active
                        '#F59E0B', // Warning - Pending
                        '#EF4444', // Danger - Suspended
                        '#6B7280'  // Gray - Inactive
                    ],
                    borderWidth: 2,
                    borderColor: '#FFFFFF'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed || 0;
                                const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                return label + ': ' + value + ' (' + percentage + '%)';
                            }
                        }
                    }
                },
                cutout: '70%'
            }
        });
    } else if (studentCtx) {
        studentCtx.parentElement.innerHTML = `
            <div class="empty-state small">
                <i class="fas fa-users"></i>
                <p>No student data available</p>
            </div>
        `;
    }
    
    // ===== 3. Assignment Status Pie Chart =====
    const assignmentCtx = document.getElementById('assignmentStatusChart');
    if (assignmentCtx && data.assignmentStatus && hasData(data.assignmentStatus)) {
        const total = Object.values(data.assignmentStatus).reduce((a, b) => a + b, 0);
        
        new Chart(assignmentCtx, {
            type: 'doughnut',
            data: {
                labels: ['Ongoing', 'Due Soon', 'Overdue', 'Completed'],
                datasets: [{
                    data: [
                        data.assignmentStatus.ongoing || 0,
                        data.assignmentStatus.due_soon || 0,
                        data.assignmentStatus.overdue || 0,
                        data.assignmentStatus.completed || 0
                    ],
                    backgroundColor: [
                        '#3B82F6', // Info - Ongoing
                        '#F59E0B', // Warning - Due Soon
                        '#EF4444', // Danger - Overdue
                        '#10B981'  // Success - Completed
                    ],
                    borderWidth: 2,
                    borderColor: '#FFFFFF'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed || 0;
                                const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                return label + ': ' + value + ' (' + percentage + '%)';
                            }
                        }
                    }
                },
                cutout: '70%'
            }
        });
    } else if (assignmentCtx) {
        assignmentCtx.parentElement.innerHTML = `
            <div class="empty-state small">
                <i class="fas fa-tasks"></i>
                <p>No assignment data available</p>
            </div>
        `;
    }
    
    // ===== 4. Monthly Fund Bar Chart =====
    const fundCtx = document.getElementById('monthlyFundChart');
    if (fundCtx && data.monthlyFund && data.monthlyFund.length > 0) {
        const hasData = data.monthlyFund.some(item => item.income > 0 || item.expenditure > 0 || item.refunds > 0);
        
        if (hasData) {
            const months = data.monthlyFund.map(item => {
                const [year, month] = item.month.split('-');
                const date = new Date(year, month - 1, 1);
                return date.toLocaleString('default', { month: 'short', year: 'numeric' });
            });
            
            new Chart(fundCtx, {
                type: 'bar',
                data: {
                    labels: months,
                    datasets: [
                        {
                            label: 'Income',
                            data: data.monthlyFund.map(item => item.income || 0),
                            backgroundColor: 'rgba(16, 185, 129, 0.7)',
                            borderColor: '#10B981',
                            borderWidth: 2,
                            borderRadius: 4
                        },
                        {
                            label: 'Expenditure',
                            data: data.monthlyFund.map(item => item.expenditure || 0),
                            backgroundColor: 'rgba(239, 68, 68, 0.7)',
                            borderColor: '#EF4444',
                            borderWidth: 2,
                            borderRadius: 4
                        },
                        {
                            label: 'Refunds',
                            data: data.monthlyFund.map(item => item.refunds || 0),
                            backgroundColor: 'rgba(245, 158, 11, 0.7)',
                            borderColor: '#F59E0B',
                            borderWidth: 2,
                            borderRadius: 4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                usePointStyle: true,
                                padding: 20
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': BDT ' + context.parsed.y.toLocaleString('en-US', {
                                        minimumFractionDigits: 2,
                                        maximumFractionDigits: 2
                                    });
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return 'BDT ' + value.toLocaleString();
                                }
                            }
                        }
                    }
                }
            });
        } else {
            fundCtx.parentElement.innerHTML = `
                <div class="empty-state small">
                    <i class="fas fa-chart-bar"></i>
                    <p>No fund data available</p>
                </div>
            `;
        }
    } else if (fundCtx) {
        fundCtx.parentElement.innerHTML = `
            <div class="empty-state small">
                <i class="fas fa-chart-bar"></i>
                <p>No fund data available</p>
            </div>
        `;
    }
});