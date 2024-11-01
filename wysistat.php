<?php
/*
Plugin Name: Wysistat - Mesure d'audience
Description: Solution de mesure d’audience pour site internet. La solution de WebAnalytics est exemptée de consentement par la CNIL et certifiée par l’ACPM pour la publication de votre trafic.
Author: Wysistat
Author URI: https://wysistat.net
Version: 1.1
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Text Domain: wysistat
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/********************************************************************
 * MENU extensions
 */

function wysistat_plugin_settings_link($links) {
    $settings_link = '<a href="options-general.php?page=wysistat">Réglages</a>';
    array_unshift($links, $settings_link);
    return $links;
}

$plugin = plugin_basename(__FILE__);
add_filter("plugin_action_links_$plugin", 'wysistat_plugin_settings_link');


/********************************************************************
 * MENU Réglages
 */

// Créez le menu "Wysistat" dans "réglages"
function wysistat_settings_menu() {
    add_submenu_page(
        'options-general.php', // Parent menu slug (Réglages)
        'Wysistat', // Page title
        'Wysistat', // Menu title
        'manage_options', // Capability required to access the page
        'wysistat', // Menu slug (unique identifier)
        'wysistat_callback_function' // Callback function to display the page content
    );
}

add_action('admin_menu', 'wysistat_settings_menu');


/********************************************************************
 * MODULE WYSISTAT
 */

function wysistat_callback_function() {

    wp_enqueue_style('wysistat-style', plugin_dir_url(__FILE__) . 'assets/css/admin-style.css');

    // Vérifiez si le formulaire a été soumis
    if (isset($_POST['wysistat_submit'])) {

        // Vérification du nonce
        if (!isset($_POST['wysistat_nonce_field']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['wysistat_nonce_field'])), 'wysistat_action')) {
            die('Sécurité de la vérification échouée');
        }

        // Validez et traitez les données ici
        $compte = isset($_POST['wysistat_compte']) ? sanitize_text_field(wp_unslash($_POST['wysistat_compte'])) : '';
        $token = isset($_POST['wysistat_token']) ? sanitize_text_field(wp_unslash($_POST['wysistat_token'])) : '';
        $date_range = isset($_POST['wysistat_periode']) ? sanitize_text_field(wp_unslash($_POST['wysistat_periode'])) : '';

        // Enregistrez les données dans les options
        update_option('wysistat_compte', $compte);
        update_option('wysistat_token', $token);
        update_option('wysistat_periode', $date_range);

        wysistat_callApi();
        wysistat_callApiWidget();
        wysistat_resetDateApi();
        wysistat_check_traker();

        // Ajoutez une notification de réussite
        add_settings_error('wysistat-notices', 'wysistat-success', 'Paramètres enregistrés avec succès.', 'updated');
    }

    //appel à l'api : rechargement
    if (isset($_POST['wysistat_reload'])) {

        // Vérification du nonce
        if (!isset($_POST['wysistat_nonce_field']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['wysistat_nonce_field'])), 'wysistat_action')) {
            die('Sécurité de la vérification échouée');
        }

        wysistat_callApi();
        wysistat_callApiWidget();
        wysistat_resetDateApi();
        wysistat_check_traker();
        add_settings_error('wysistat-notices', 'wysistat-success', 'Données mises à jour avec succès.', 'updated');
    }

    // Récupérez les valeurs stockées en base de données (si elles existent)
    $compte = get_option('wysistat_compte');
    $token = get_option('wysistat_token');
    $date_range = get_option('wysistat_periode');
    

    // Affichez le formulaire HTML
    ?>
    <div class="wrap">
        <header>
            <div class="wysistat-title-container">
                <img src="<?php echo esc_url(plugin_dir_url(__FILE__)); ?>assets/img/logo-wysistat.webp" alt="Logo Wysistat">
                <div class="wysistat-title">Configuration Wysistat</div>
            </div>
            <!-- Ajoutez les liens en bas du formulaire -->
            <div class="wysistat-link-container">
                <a href="https://wiki.wysistat.com/fr/plan-marquage/plugin-Wordpress" class="wysistat-link">Documentation sur la configuration</a>
            </div>
        </header>
    </div>

    <?php
    // Vérifiez si le script Wysistat est présent et affichez la notification
    $script_present = get_option('wysistat_script_present');
    if ($script_present) {
        ?>
        <div class="notice notice-error is-dismissible">
            <p>
                Le script Wysistat est déjà ajouté manuellement sur certaines pages ! (test effectué sur la page d'accueil)
                <br /> 
                Veuillez <strong>supprimer</strong> le marquage manuel et supprimer le cache de votre site pour qu'il soit ajouté automatiquement par le plugin.
                <br />
                <i>Note : Commenter le marqueur ajouté manuellement ne suffit pas, la suppression de celui-ci est obligatoire.</i>
                <br /><br />
                <strong>Tant que ce message est affiché, votre site n'est pas marqué par le plugin.</strong>
                
            </p>
        </div>
        <?php
    }

    // Ajoutez cette ligne pour afficher les notifications
    settings_errors('wysistat-notices');

    ?>
    <form method="post" action="options-general.php?page=wysistat" class="wysistat-form">
        <?php wp_nonce_field('wysistat_action', 'wysistat_nonce_field'); ?>
        <label for="wysistat_compte"  class="wysistat-label">Nom du compte:</label>
        <input type="text" id="wysistat_compte" name="wysistat_compte" value="<?php echo esc_html(esc_attr($compte)); ?>" />
        <br />
        
        <label for="wysistat_token"  class="wysistat-label">Token:</label>
        <input type="text" id="wysistat_token" name="wysistat_token" value="<?php echo esc_html(esc_attr($token)); ?>" />
        <br />
        
        <label for="wysistat_periode"  class="wysistat-label">Période:</label>
        <select id="wysistat_periode" name="wysistat_periode" class="wysistat-select">
            <option value="hier" <?php echo selected($date_range, 'hier', false); ?>>Hier</option>
            <option value="semaine" <?php echo selected($date_range, 'semaine', false); ?>>Semaine</option>
            <option value="mois" <?php echo selected($date_range, 'mois', false); ?>>Mois</option>
        </select>
        <br />
    
        <input type="submit"  class="wysistat-submit" name="wysistat_submit" value="Enregistrer">
        
        <?php
        $wysistat_api_last_update = get_option('wysistat_api_last_update');
        $formatted_date = gmdate('d/m/Y H:i:s', strtotime($wysistat_api_last_update));
        ?>

        <p>Dernière mise à jour des données : <?php echo esc_html($formatted_date); ?>
        &nbsp;<input type="submit" class="wysistat-submit" name="wysistat_reload" value="Recharger" /></p>
    </form>
    
    <div class="wysistat-dashboard">
        <a target="_blank" href="https://dashboard.wysistat.com/">Accéder à votre Dashboard</a>
        &nbsp;
        <a target="_blank" href="https://www.wysistat.net/tarifs?wysi_source=module_wordpress">Créer votre compte</a>
    </div>   
    <?php      
}

/********************************************************************
 * MARQUAGE DU SITE
 */

// Vérifiez si le script Wysistat est déjà présent dans la page
function wysistat_check_traker() {
    update_option('wysistat_script_present', true);

    
    // Récupérer le contenu HTML de la page d'accueil
    $response = wp_remote_get(home_url());

    if (is_wp_error($response)) {
        return;
    }
    else{
        $html = wp_remote_retrieve_body($response);
        
        // Rechercher la présence du script ws.jsa
        $script_present = strpos($html, 'ws.jsa') !== false;
        update_option('wysistat_script_present', $script_present);
    }
}

function wysistat_check_and_add_tracker() {
    $script_present = get_option('wysistat_script_present', true);
    $wysistat_compte = get_option('wysistat_compte');
    
    // Vérifiez si wysistat_compte existe et n'est pas vide
    if ($wysistat_compte && !$script_present) {

        wp_register_script('wysistat-tracker', '');
        wp_enqueue_script('wysistat-tracker');

        $inline_js = sprintf("
            var _wsq = _wsq || [];
            _wsq.push(['_setNom', '" . esc_js($wysistat_compte) . "']);
            _wsq.push(['_wysistat']);
    
            (function(){
                var ws = document.createElement('script');
                ws.type = 'text/javascript';
                ws.async = true;
                ws.src = (document.location.protocol === 'https:' ? 'https://www' : 'http://www') + '.wysistat.com/ws.jsa';
                var s = document.getElementsByTagName('script')[0] || document.getElementsByTagName('body')[0];
                s.parentNode.insertBefore(ws, s);
            })();
        ");
        
        wp_add_inline_script('wysistat-tracker', $inline_js); 
    }
}

add_action('wp_head', 'wysistat_check_and_add_tracker');


/********************************************************************
 * PAGES
 */

$custom_column_name = 'wysistat_column';

// Définissez le titre de la colonne personnalisée
function wysistat_add_custom_column($columns) {
    global $custom_column_name;

    $periode = get_option('wysistat_periode');
    $wysistat_api_last_update = get_option('wysistat_api_last_update');
    
    $columns[$custom_column_name] = 'Nb pages vues';

    if ($periode) {
        if (empty($wysistat_api_last_update) || strtotime('-1 day') > strtotime($wysistat_api_last_update)) {
            wysistat_callApi();
            wysistat_callApiWidget();
            wysistat_resetDateApi();
        }

        $wysistat_api_json = get_option('wysistat_api_json');
        if ($wysistat_api_json && $wysistat_api_json['total']) {
            $nbViews = $wysistat_api_json['total'];
        }
        else{
            $nbViews = 'NC';
        }
        $columns[$custom_column_name] .= " : " . esc_html($nbViews) . "<br />(Période : " . esc_html($periode) . ")";
    }
    else{
        update_option('wysistat_api_json', '');
        $columns[$custom_column_name] .= " (pas de période définie)";
    }
    return $columns;
}
add_filter('manage_pages_columns', 'wysistat_add_custom_column');
add_filter('manage_posts_columns', 'wysistat_add_custom_column');


// Affichez le contenu de la colonne personnalisée
function wysistat_render_custom_column($column_name, $post_id) {
    global $custom_column_name ;

    if ($column_name === $custom_column_name ) {
        // Vérifiez si le post est en mode brouillon
        $post_status = get_post_status($post_id);

        // Si le post n'est pas en mode brouillon, affichez la colonne personnalisée
        if ($post_status === 'draft') {
            echo 'Brouillon';
        }
        else{
            $wysistat_api_json = get_option('wysistat_api_json');
            $post_url = get_permalink($post_id);
            $url = parse_url($post_url);
            $path = $url['path'];
            
            if (isset($wysistat_api_json[$path])){
                echo esc_html(intval($wysistat_api_json[$path]));
            }
            else{
                echo 'NC';
            }
        }
    }
}
add_action('manage_page_posts_custom_column', 'wysistat_render_custom_column', 10, 2);
add_action('manage_posts_custom_column', 'wysistat_render_custom_column', 10, 2);


/********************************************************************
 * API
 */

function wysistat_resetDateApi(){
    update_option('wysistat_api_last_update', current_time('mysql'));
}

function wysistat_callApi(){
    $periode = get_option('wysistat_periode');

    switch($periode){
        case 'hier' : 
            $start = new DateTime('-1 day');
        break;
        case 'semaine' : 
            $start = new DateTime('-7 day');
        break;
        case 'mois' : 
            $start = new DateTime('-30 day');
        break;
    }
    $startDate = $start->format('Ymd');
    $endDate = gmdate('Ymd');

    $compte = get_option('wysistat_compte');
    $token = get_option('wysistat_token');

    update_option('wysistat_api_json', '');

    if ($token && $compte){
        //appel à l'api 
        $args = array(
            'headers' => array(
                'Authorization' => $token,
            ),
        );
        $api_url = "https://api.wysistat.com/topten/".$compte."?startDate=".$startDate."&endDate=".$endDate."&frequency=jour&type=url&from=0&size=1000&order_by=views&order=desc&graph=false";
        $response = wp_remote_get($api_url, $args);
          
        if (is_wp_error($response)) {
            // Gérer les erreurs
            echo 'Erreur lors de la requête API: ' . esc_html($response->get_error_message());
        } 
        else {
            $body = wp_remote_retrieve_body($response);
            $result = wysistat_parseJson($body);
            update_option('wysistat_api_json', $result);
        }
    }
}

function wysistat_parseJson($json){
    $arrReturn =  array();
    $arr = json_decode($json, true);

    foreach ($arr['datasets'] as $data){
        $url = parse_url('http://' . $data['url']);
        $path = $url['path'].'/';
        $arrReturn[$path] = $data['views'];
    }
    $arrReturn['total'] = esc_html($arr['totals']['views']);
    
    return $arrReturn;
}


/* WIDGET */
function wysistat_mon_widget() {
    $start = new DateTime('-30 day');
    $endDate = gmdate('Ymd');
    $wysistat_api_last_update = get_option('wysistat_api_last_update');

    if (empty($wysistat_api_last_update) || strtotime('-1 day') > strtotime($wysistat_api_last_update)) {
        wysistat_callApi();
        wysistat_callApiWidget(); 
        wysistat_resetDateApi();       
    }

    // Affichage des onglets et graphiques
    wysistat_onglets_et_graphiques(); 
}

function wysistat_callApiWidget(){
    $start = new DateTime('-30 day');
    $startDate = $start->format('Ymd');
    $endDate = gmdate('Ymd');

    $compte = get_option('wysistat_compte');
    $token = get_option('wysistat_token');

    update_option('wysistat_api_widget_json', '');

    if ($token && $compte){
        //appel à l'api 
        $args = array(
            'headers' => array(
                'Authorization' => $token,
            ),
        );
        $api_url = "https://api.wysistat.com/clients/".$compte."?startDate=".$startDate."&endDate=".$endDate;
        $response = wp_remote_get($api_url, $args);

        if (is_wp_error($response)) {
            // Gérer les erreurs
            echo 'Erreur lors de la requête API: ' . esc_html($response->get_error_message());
        } else {
            $body = wp_remote_retrieve_body($response);
            $result = wysistat_parseJsonWidget($body);
            update_option('wysistat_api_widget_json', $result);
        }
    }
}

function wysistat_parseJsonWidget($json){
    $arrReturn =  array();
    $arr = json_decode($json, true);

    if (is_array($arr)) {
        foreach ($arr as $entry) {
            if (isset($entry['general'])) {
                $arrReturn[] = $entry['general'];
            }
        }
    } else {
         echo esc_html('Le JSON retourné n\'est pas valide.');
    }

    return $arrReturn;
}

function wysistat_onglets_et_graphiques() {
    $wysistat_api_widget_json = get_option('wysistat_api_widget_json');

    wp_enqueue_script('wysistat-chartJS', plugin_dir_url(__FILE__) . 'assets/js/chart.js', array(), '1.0', true);
    wp_enqueue_script('wysistat-scriptWidget', plugin_dir_url(__FILE__) . 'assets/js/wysistat_widget.js', array(), '1.0', true);
    wp_localize_script('wysistat-scriptWidget', 'wysistatDataWidget', array('apiWidgetJson' => $wysistat_api_widget_json));

    wp_enqueue_style('wysistat-styleWidget', plugin_dir_url(__FILE__) . 'assets/css/widget-style.css');

    ?>
    <div>
        <button id="wysistat_onglet_visitors" onclick="wysistat_afficherGraphique('visitors', 'Visiteurs', '#ef4444')">Visiteurs</button>
        <button id="wysistat_onglet_visits" onclick="wysistat_afficherGraphique('visits', 'Visites', '#3b82f6')">Visites</button>
        <button id="wysistat_onglet_views" onclick="wysistat_afficherGraphique('views', 'Pages vues', '#eab308')">Pages vues</button>
    </div>

    <div>
        <?php
        if (!$wysistat_api_widget_json || empty($wysistat_api_widget_json)) {
            echo '<p>Aucune donnée disponible.</p>';
            return;
        }
        ?>
        <canvas id="graph_wysistat"></canvas>
    </div>
    <?php
}

function wysistat_mon_enregistrement_widget() {
    wp_add_dashboard_widget(
        'widget_wysistat',
        'Wysistat',
        'wysistat_mon_widget'
    );
}

// Action pour lier la fonction d'enregistrement au hook 'wp_dashboard_setup'
add_action('wp_dashboard_setup', 'wysistat_mon_enregistrement_widget');