define([
    'jquery',
    'chartjs',
    'Sterk_GraphQlPerformance/js/chart/config'
], function ($, Chart, chartConfig) {
    'use strict';

    return {
        /**
         * Create line chart
         *
         * @param {string} elementId
         * @param {Object} data
         * @param {Object} options
         * @returns {Chart}
         */
        createLineChart: function (elementId, data, options) {
            var ctx = document.getElementById(elementId).getContext('2d');
            var defaultOptions = $.extend(true, {}, chartConfig.defaultOptions, options);

            return new Chart(ctx, {
                type: 'line',
                data: this.prepareLineChartData(data),
                options: defaultOptions
            });
        },

        /**
         * Prepare line chart data
         *
         * @param {Object} data
         * @returns {Object}
         */
        prepareLineChartData: function (data) {
            var datasets = [];

            if (data.responseTimes) {
                datasets.push(this.createDataset(
                    data.labels.responseTime,
                    data.responseTimes,
                    chartConfig.defaultColors.responseTime
                ));
            }

            if (data.cacheHitRates) {
                datasets.push(this.createDataset(
                    data.labels.cacheHitRate,
                    data.cacheHitRates,
                    chartConfig.defaultColors.cacheHitRate
                ));
            }

            return {
                labels: data.labels,
                datasets: datasets
            };
        },

        /**
         * Create dataset
         *
         * @param {string} label
         * @param {Array} data
         * @param {string} color
         * @returns {Object}
         */
        createDataset: function (label, data, color) {
            return $.extend({}, chartConfig.lineChartDefaults, {
                label: label,
                data: data,
                borderColor: color,
                backgroundColor: color
            });
        }
    };
});
