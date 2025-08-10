var config = {
    map: {
        '*': {
            chartFactory: 'Sterk_GraphQlPerformance/js/chart/factory',
            chartConfig: 'Sterk_GraphQlPerformance/js/chart/config'
        }
    },
    paths: {
        chartjs: 'Sterk_GraphQlPerformance/js/chart.min'
    },
    shim: {
        chartjs: {
            exports: 'Chart'
        }
    }
};
