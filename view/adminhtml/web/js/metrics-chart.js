define([
    'Sterk_GraphQlPerformance/js/chart/factory'
], function (chartFactory) {
    'use strict';

    return function (config) {
        return chartFactory.createLineChart('metricsChart', {
            labels: config.data.labels,
            responseTimes: config.data.responseTimes,
            cacheHitRates: config.data.cacheHitRates,
            labels: {
                responseTime: config.labels.responseTime,
                cacheHitRate: config.labels.cacheHitRate
            }
        });
    };
});
