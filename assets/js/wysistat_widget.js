/*!!
 * Wysistat - Widget 
 *
 * JavaScript ChartJs
 *
 * @link https://wysistat.net
 */

function wysistat_afficherGraphique(graph, label, color) {

    // Réinitialiser les classes de tous les onglets
    document.getElementById('wysistat_onglet_visitors').classList.remove('wysistat_onglet-actif');
    document.getElementById('wysistat_onglet_visits').classList.remove('wysistat_onglet-actif');
    document.getElementById('wysistat_onglet_views').classList.remove('wysistat_onglet-actif');

    // Ajouter la classe à l'onglet actif
    document.getElementById('wysistat_onglet_' + graph).classList.add('wysistat_onglet-actif');

    var ctx = document.getElementById('graph_wysistat').getContext('2d');
    var dates = [];
    var donnees = [];
   
    var existingChart = Chart.getChart(ctx);

    if (existingChart) {
        existingChart.destroy();
    }

    if (wysistatDataWidget && wysistatDataWidget.apiWidgetJson){

        var apiWidgetJson = wysistatDataWidget.apiWidgetJson;

        if (apiWidgetJson){
            apiWidgetJson.forEach(function (entry) {
                var formattedDate = entry.date.replace(/(\d{4})(\d{2})(\d{2})/, '$1-$2-$3');
                var formattedDateString = new Date(formattedDate).toLocaleDateString('fr-FR');

                dates.push(formattedDateString);
                donnees.push(entry[graph]);
            });

            // Créer le graphique
            var myChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: dates,
                    datasets: [{
                        label: label,
                        data: donnees,
                        borderColor: color,
                        borderWidth: 1,
                        fill: false
                    }]
                },
                options: {
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    },
                    plugins: {
                        legend: {
                            display: false 
                        }
                    }
                }
            });
        }
    }
}
wysistat_afficherGraphique('visitors', 'Visiteurs', '#ef4444');
