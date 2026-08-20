/**
 * Graphiques Chart.js — tableau de bord admin
 * Données injectées via #dashChartsData (JSON)
 */
(function () {
    'use strict';

    var dataEl = document.getElementById('dashChartsData');
    if (!dataEl || typeof Chart === 'undefined') {
        return;
    }

    var payload;
    try {
        payload = JSON.parse(dataEl.textContent || '{}');
    } catch (e) {
        return;
    }

    var navy = '#10316F';
    var blue = '#2957AE';
    var orange = '#FF6B35';
    var green = '#12694A';
    var slate = '#5C6A85';
    var grid = 'rgba(223, 228, 236, 0.9)';

    Chart.defaults.font.family = '"Inter", "Segoe UI", system-ui, sans-serif';
    Chart.defaults.color = slate;
    Chart.defaults.plugins.legend.labels.boxWidth = 12;
    Chart.defaults.plugins.legend.labels.padding = 14;

    function fmtMontant(v) {
        try {
            return new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(v) + ' FCFA';
        } catch (e) {
            return v + ' FCFA';
        }
    }

    var mensuelEl = document.getElementById('dashChartMensuel');
    if (mensuelEl && payload.mensuel) {
        new Chart(mensuelEl, {
            type: 'bar',
            data: {
                labels: payload.mensuel.labels || [],
                datasets: [
                    {
                        label: 'Quantité vendue',
                        data: payload.mensuel.qte || [],
                        backgroundColor: 'rgba(41, 87, 174, 0.75)',
                        borderColor: blue,
                        borderWidth: 1,
                        borderRadius: 4,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Montant (FCFA)',
                        data: payload.mensuel.montant || [],
                        type: 'line',
                        borderColor: orange,
                        backgroundColor: 'rgba(255, 107, 53, 0.12)',
                        tension: 0.35,
                        fill: true,
                        pointRadius: 3,
                        pointBackgroundColor: orange,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                scales: {
                    x: { grid: { display: false } },
                    y: {
                        position: 'left',
                        grid: { color: grid },
                        title: { display: true, text: 'Quantité' }
                    },
                    y1: {
                        position: 'right',
                        grid: { drawOnChartArea: false },
                        title: { display: true, text: 'Montant' },
                        ticks: {
                            callback: function (v) {
                                return fmtMontant(v);
                            }
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                var label = ctx.dataset.label || '';
                                if (ctx.dataset.yAxisID === 'y1') {
                                    return label + ' : ' + fmtMontant(ctx.parsed.y);
                                }
                                return label + ' : ' + ctx.parsed.y;
                            }
                        }
                    }
                }
            }
        });
    }

    var topEl = document.getElementById('dashChartTopProduits');
    if (topEl && payload.top_produits) {
        new Chart(topEl, {
            type: 'bar',
            data: {
                labels: payload.top_produits.labels || [],
                datasets: [{
                    label: 'Unités vendues',
                    data: payload.top_produits.qte || [],
                    backgroundColor: 'rgba(16, 49, 111, 0.82)',
                    borderColor: navy,
                    borderWidth: 1,
                    borderRadius: 4
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: { grid: { color: grid }, beginAtZero: true },
                    y: { grid: { display: false } }
                },
                plugins: { legend: { display: false } }
            }
        });
    }

    var blEl = document.getElementById('dashChartBlRecents');
    if (blEl && payload.bl_recents) {
        new Chart(blEl, {
            type: 'bar',
            data: {
                labels: payload.bl_recents.labels || [],
                datasets: [
                    {
                        label: 'Pièces',
                        data: payload.bl_recents.pieces || [],
                        backgroundColor: 'rgba(41, 87, 174, 0.7)',
                        borderColor: blue,
                        borderWidth: 1,
                        borderRadius: 4,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Montant HT',
                        data: payload.bl_recents.montant || [],
                        backgroundColor: 'rgba(255, 107, 53, 0.75)',
                        borderColor: orange,
                        borderWidth: 1,
                        borderRadius: 4,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: { grid: { display: false } },
                    y: {
                        position: 'left',
                        grid: { color: grid },
                        title: { display: true, text: 'Pièces' },
                        beginAtZero: true
                    },
                    y1: {
                        position: 'right',
                        grid: { drawOnChartArea: false },
                        title: { display: true, text: 'Montant HT' },
                        ticks: {
                            callback: function (v) {
                                return fmtMontant(v);
                            }
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            afterTitle: function (items) {
                                if (!items.length || !payload.bl_recents.clients) {
                                    return '';
                                }
                                var idx = items[0].dataIndex;
                                return payload.bl_recents.clients[idx] || '';
                            },
                            label: function (ctx) {
                                if (ctx.dataset.yAxisID === 'y1') {
                                    return ctx.dataset.label + ' : ' + fmtMontant(ctx.parsed.y);
                                }
                                return ctx.dataset.label + ' : ' + ctx.parsed.y;
                            }
                        }
                    }
                }
            }
        });
    }
})();
