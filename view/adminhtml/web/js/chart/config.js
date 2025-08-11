define(
    [], function () {
        'use strict';

        return {
            defaultColors: {
                responseTime: 'rgb(75, 192, 192)',
                cacheHitRate: 'rgb(54, 162, 235)',
                errorRate: 'rgb(255, 99, 132)',
                memoryUsage: 'rgb(153, 102, 255)'
            },

            defaultOptions: {
                responsive: true,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            drawBorder: false
                        }
                    },
                    x: {
                        grid: {
                            drawBorder: false
                        }
                    }
                },
                plugins: {
                    legend: {
                        position: 'top',
                        align: 'start'
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false
                    }
                }
            },

            lineChartDefaults: {
                tension: 0.1,
                fill: false,
                pointRadius: 2,
                pointHoverRadius: 4
            }
        };
    }
);
