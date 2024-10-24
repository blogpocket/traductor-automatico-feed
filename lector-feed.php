<?php
/*
Plugin Name: Lector de Feed con Traducción y XML
Description: Lee el feed RSS del sitio, guarda título, enlace y descripción en feed.txt, traduce el título y la descripción usando la API de OpenAI y genera un archivo feed.xml. Además, redirige /en/feed a feed.xml.
Version: 3.8
Author: Tu Nombre
*/

// Evitar acceso directo al archivo
if (!defined('ABSPATH')) {
    exit; // Salir si se accede directamente
}

/**
 * 1. Añadir Menú en el Panel de Administración
 */
add_action('admin_menu', 'lfp_add_admin_menu');

function lfp_add_admin_menu() {
    // Página principal del plugin
    add_menu_page(
        'Lector de Feed',                  // Título de la página
        'Lector de Feed',                  // Título del menú
        'manage_options',                  // Capacidad requerida
        'lector-feed',                     // Slug del menú
        'lfp_admin_page',                  // Función que muestra la página
        'dashicons-rss',                   // Icono del menú
        6                                   // Posición en el menú
    );

    // Submenú para la configuración
    add_submenu_page(
        'lector-feed',                     // Slug del menú principal
        'Configuración de Lector de Feed', // Título de la página
        'Configuración',                    // Título del submenú
        'manage_options',                   // Capacidad requerida
        'lector-feed-configuracion',        // Slug del submenú
        'lfp_config_page'                   // Función que muestra la página de configuración
    );
}

/**
 * 2. Página Principal del Plugin
 */
function lfp_admin_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    // Procesar el formulario si se ha enviado
    if (isset($_POST['lfp_read_feed'])) {
        // Verificar el nonce para seguridad
        check_admin_referer('lfp_read_feed_action', 'lfp_read_feed_nonce');
        lfp_read_feed();
    }

    ?>
    <div class="wrap">
        <h1>Lector de Feed</h1>
        <form method="post">
            <?php
            // Campos de seguridad
            wp_nonce_field('lfp_read_feed_action', 'lfp_read_feed_nonce');
            ?>
            <p>
                <input type="submit" name="lfp_read_feed" class="button button-primary" value="Leer, Traducir y Generar XML">
            </p>
        </form>
    </div>
    <?php
}

/**
 * 3. Página de Configuración del Plugin
 */
function lfp_config_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    // Procesar el formulario de configuración si se ha enviado
    if (isset($_POST['lfp_save_settings'])) {
        // Verificar el nonce para seguridad
        check_admin_referer('lfp_save_settings_action', 'lfp_save_settings_nonce');

        // Sanear y guardar la clave de API
        if (isset($_POST['lfp_api_key'])) {
            $api_key = sanitize_text_field($_POST['lfp_api_key']);
            update_option('lfp_openai_api_key', $api_key);
        }

        // Sanear y guardar el título del feed
        if (isset($_POST['lfp_feed_title'])) {
            $feed_title = sanitize_text_field($_POST['lfp_feed_title']);
            update_option('lfp_feed_title', $feed_title);
        }

        // Sanear y guardar la descripción del feed
        if (isset($_POST['lfp_feed_description'])) {
            $feed_description = sanitize_textarea_field($_POST['lfp_feed_description']);
            update_option('lfp_feed_description', $feed_description);
        }

        // Sanear y guardar el número de ítems
        if (isset($_POST['lfp_feed_items_count'])) {
            $feed_items_count = intval($_POST['lfp_feed_items_count']);
            update_option('lfp_feed_items_count', $feed_items_count);
        }

        // Mostrar mensaje de éxito
        echo '<div class="notice notice-success is-dismissible">';
        echo '<p>Configuración guardada exitosamente.</p>';
        echo '</div>';
    }

    // Obtener las opciones almacenadas
    $api_key = get_option('lfp_openai_api_key', '');
    $feed_title = get_option('lfp_feed_title', 'Translated Feed');
    $feed_description = get_option('lfp_feed_description', 'Description of the translated feed');
    $feed_items_count = get_option('lfp_feed_items_count', 10);

    ?>
    <div class="wrap">
        <h1>Configuración de Lector de Feed</h1>
        <form method="post">
            <?php
            // Campos de seguridad
            wp_nonce_field('lfp_save_settings_action', 'lfp_save_settings_nonce');
            ?>
            <table class="form-table">
                <!-- Clave de API de OpenAI -->
                <tr>
                    <th scope="row"><label for="lfp_api_key">Clave de API de OpenAI</label></th>
                    <td>
                        <input type="password" name="lfp_api_key" id="lfp_api_key" value="<?php echo esc_attr($api_key); ?>" class="regular-text" required>
                        <p class="description">Ingresa tu clave de API de OpenAI para traducir el contenido.</p>
                    </td>
                </tr>
                <!-- Título del Feed -->
                <tr>
                    <th scope="row"><label for="lfp_feed_title">Título del Feed</label></th>
                    <td>
                        <input type="text" name="lfp_feed_title" id="lfp_feed_title" value="<?php echo esc_attr($feed_title); ?>" class="regular-text" required>
                        <p class="description">El título que aparecerá en el feed RSS.</p>
                    </td>
                </tr>
                <!-- Descripción del Feed -->
                <tr>
                    <th scope="row"><label for="lfp_feed_description">Descripción del Feed</label></th>
                    <td>
                        <textarea name="lfp_feed_description" id="lfp_feed_description" rows="5" class="large-text" required><?php echo esc_textarea($feed_description); ?></textarea>
                        <p class="description">La descripción que aparecerá en el feed RSS.</p>
                    </td>
                </tr>
                <!-- Número de Ítems -->
                <tr>
                    <th scope="row"><label for="lfp_feed_items_count">Número de Ítems en el Feed</label></th>
                    <td>
                        <input type="number" name="lfp_feed_items_count" id="lfp_feed_items_count" value="<?php echo esc_attr($feed_items_count); ?>" class="small-text" min="1" required>
                        <p class="description">Cantidad de ítems que deseas incluir en el archivo feed.xml.</p>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit" name="lfp_save_settings" class="button button-primary" value="Guardar cambios">
            </p>
        </form>
    </div>
    <?php
}

/**
 * 4. Función para Leer, Traducir y Generar los Archivos
 */
function lfp_read_feed() {
    // Obtener la URL del feed del sitio
    $feed_url = get_feed_link();

    // Obtener el contenido del feed RSS
    $response = wp_remote_get($feed_url);

    if (is_wp_error($response)) {
        echo '<div class="notice notice-error is-dismissible">';
        echo '<p>Error al obtener el feed: ' . esc_html($response->get_error_message()) . '</p>';
        echo '</div>';
        return;
    }

    $body = wp_remote_retrieve_body($response);

    // Cargar el XML con manejo de espacios de nombres
    $rss = simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NOCDATA);

    if ($rss === false) {
        echo '<div class="notice notice-error is-dismissible">';
        echo '<p>Error al procesar el feed.</p>';
        echo '</div>';
        return;
    }

    $output_feed = '<items>' . "\n";         // Inicio de feed.txt
    $output_traducido = '<items>' . "\n";    // Inicio de feed-traducido.txt

    foreach ($rss->channel->item as $item) {
        $title = (string)$item->title;
        $link = (string)$item->link;

        // Obtener la descripción directamente de la etiqueta <description>
        $description = (string)$item->description;

        // Procesar la descripción eliminando todas las etiquetas HTML
        libxml_use_internal_errors(true); // Evitar errores de parsing
        $dom = new DOMDocument();
        // Convertir caracteres especiales a entidades para evitar problemas
        $dom->loadHTML('<?xml encoding="UTF-8">' . $description);
        $xpath = new DOMXPath($dom);
        $nodes = $xpath->query('//body/node()');

        $clean_description = '';
        foreach ($nodes as $node) {
            $clean_description .= $dom->saveHTML($node);
        }

        // Limpiar la descripción eliminando todas las etiquetas HTML
        $clean_description = strip_tags($clean_description); // Eliminar todas las etiquetas HTML
        $clean_description = html_entity_decode($clean_description, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $clean_description = preg_replace('/[\x00-\x09\x0B\x0C\x0E-\x1F\x7F]/u', '', $clean_description); // Eliminar caracteres de control excepto saltos de línea
        $clean_description = trim($clean_description);
        // Ya no limitamos los caracteres para tener el contenido completo

        // Asegurarse de que no haya saltos de línea extraños
        $title = trim($title);
        $link = trim($link);

        // Escribir en feed.txt con etiquetas en español
        $output_feed .= "<item>\n";
        $output_feed .= "    <titulo>" . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . "</titulo>\n";
        $output_feed .= "    <link>" . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . "</link>\n";
        $output_feed .= "    <descripcion>" . htmlspecialchars($clean_description, ENT_QUOTES, 'UTF-8') . "</descripcion>\n";
        $output_feed .= "</item>\n\n";

        // Escribir en feed-traducido.txt con etiquetas en inglés (links sin modificar)
        $output_traducido .= "<item>\n";
        $output_traducido .= "    <title>" . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . "</title>\n";
        $output_traducido .= "    <link>" . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . "</link>\n"; // No modificar el link
        $output_traducido .= "    <description>" . htmlspecialchars($clean_description, ENT_QUOTES, 'UTF-8') . "</description>\n";
        $output_traducido .= "</item>\n\n";
    }

    $output_feed .= '</items>';
    $output_traducido .= '</items>';

    $plugin_dir = plugin_dir_path(__FILE__);
    $file_path_feed = $plugin_dir . 'feed.txt';
    $file_path_traducido = $plugin_dir . 'feed-traducido.txt';

    // Guardar feed.txt
    if (file_put_contents($file_path_feed, $output_feed) === false) {
        echo '<div class="notice notice-error is-dismissible">';
        echo '<p>Error al escribir en feed.txt.</p>';
        echo '</div>';
        return;
    }

    // Traducir el contenido de feed-traducido.txt
    $translated_content = lfp_translate_xml_content($output_traducido);

    if ($translated_content !== false) {
        // Guardar feed-traducido.txt con el contenido traducido
        if (file_put_contents($file_path_traducido, $translated_content) === false) {
            echo '<div class="notice notice-error is-dismissible">';
            echo '<p>Error al escribir en feed-traducido.txt.</p>';
            echo '</div>';
            return;
        }

        // Generar feed.xml
        lfp_generate_xml_feed();
    } else {
        echo '<div class="notice notice-error is-dismissible">';
        echo '<p>Error al traducir el contenido.</p>';
        echo '</div>';
    }
}

/**
 * 5. Función para Traducir el Contenido XML Usando la API de OpenAI
 */
function lfp_translate_xml_content($xml_content) {
    $api_key = get_option('lfp_openai_api_key', '');

    if (empty($api_key)) {
        echo '<div class="notice notice-error is-dismissible">';
        echo '<p>La clave de API de OpenAI no está configurada. Por favor, ingrésala en la página de configuración del plugin.</p>';
        echo '</div>';
        return false;
    }

    $url = 'https://api.openai.com/v1/chat/completions';

    // Instrucciones claras para mantener la estructura XML y solo traducir título y descripción
    $prompt = "Translate the following XML content to English while maintaining the XML structure. Only translate the text inside the <title> and <description> tags, do not modify the tags themselves or the content of the <link> tags.\n\n" . $xml_content;

    $data = [
        'model' => 'gpt-3.5-turbo',
        'messages' => [
            [
                'role' => 'user',
                'content' => $prompt,
            ],
        ],
        'temperature' => 0.3,
    ];

    $args = [
        'headers' => [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $api_key,
        ],
        'body' => json_encode($data),
        'timeout' => 300, // Aumentar el timeout si es necesario
    ];

    $response = wp_remote_post($url, $args);

    if (is_wp_error($response)) {
        error_log("Error en la API de OpenAI: " . $response->get_error_message());
        return false;
    }

    $body = wp_remote_retrieve_body($response);
    $response_data = json_decode($body, true);

    if (isset($response_data['error'])) {
        error_log("Error en la API de OpenAI: " . $response_data['error']['message']);
        return false;
    }

    if (!isset($response_data['choices'][0]['message']['content'])) {
        error_log("Respuesta inesperada de la API de OpenAI.");
        return false;
    }

    $translation = $response_data['choices'][0]['message']['content'];

    // Verificar si la traducción es un XML válido
    libxml_use_internal_errors(true);
    $translated_xml = simplexml_load_string($translation);
    if ($translated_xml === false) {
        // Log de errores para depuración
        $errors = libxml_get_errors();
        libxml_clear_errors();
        error_log("Error al parsear la traducción XML: " . print_r($errors, true));
        return false;
    }

    return $translation;
}

/**
 * 6. Función para Generar el Archivo feed.xml
 */
function lfp_generate_xml_feed() {
    $plugin_dir = plugin_dir_path(__FILE__);
    $translated_file_path = $plugin_dir . 'feed-traducido.txt';

    if (!file_exists($translated_file_path)) {
        // Archivo traducido no existe
        echo '<div class="notice notice-error is-dismissible">';
        echo '<p>El archivo feed-traducido.txt no existe. Por favor, ejecuta el proceso de lectura y traducción primero.</p>';
        echo '</div>';
        return;
    }

    // Obtener opciones
    $feed_title = get_option('lfp_feed_title', 'Translated Feed');
    $feed_description = get_option('lfp_feed_description', 'Description of the translated feed');
    $feed_items_count = get_option('lfp_feed_items_count', 10);

    // Leer el contenido del archivo traducido
    $content = file_get_contents($translated_file_path);

    // Cargar el contenido como XML
    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($content);
    if ($xml === false) {
        // Mostrar errores de XML
        $errors = libxml_get_errors();
        libxml_clear_errors();
        echo '<div class="notice notice-error is-dismissible">';
        echo '<p>Error al procesar el archivo feed-traducido.txt como XML.</p>';
        foreach ($errors as $error) {
            echo '<p>' . htmlspecialchars($error->message, ENT_QUOTES, 'UTF-8') . '</p>';
        }
        echo '</div>';
        return;
    }

    // Limitar el número de ítems
    $items = [];
    $count = 0;
    foreach ($xml->item as $item_data) {
        if ($count >= $feed_items_count) break;
        $items[] = $item_data;
        $count++;
    }

    // Crear el XML del feed
    $rss = new DOMDocument('1.0', 'UTF-8');
    $rss->formatOutput = true;

    // Crear el elemento raíz <rss>
    $rss_element = $rss->createElement('rss');
    $rss_element->setAttribute('version', '2.0');
    $rss->appendChild($rss_element);

    // Crear el elemento <channel>
    $channel = $rss->createElement('channel');
    $rss_element->appendChild($channel);

    // Añadir elementos al canal
    $channel_title = $rss->createElement('title', htmlspecialchars($feed_title, ENT_QUOTES, 'UTF-8'));
    $channel->appendChild($channel_title);

    $channel_link = $rss->createElement('link', home_url('/en/'));
    $channel->appendChild($channel_link);

    $channel_description = $rss->createElement('description', htmlspecialchars($feed_description, ENT_QUOTES, 'UTF-8'));
    $channel->appendChild($channel_description);

    $channel_language = $rss->createElement('language', 'en-us');
    $channel->appendChild($channel_language);

    // Añadir los ítems
    foreach ($items as $item_data) {
        $item = $rss->createElement('item');

        // Título
        $title_text = (string)$item_data->title;
        $item_title = $rss->createElement('title', htmlspecialchars($title_text, ENT_QUOTES, 'UTF-8'));
        $item->appendChild($item_title);

        // Link
        $original_link = (string)$item_data->link;
        $modified_link = lfp_insert_en_in_url($original_link);
        $item_link = $rss->createElement('link', htmlspecialchars($modified_link, ENT_QUOTES, 'UTF-8'));
        $item->appendChild($item_link);

        // Descripción
        $description_text = (string)$item_data->description;
        // Reemplazar saltos de línea con entidades XML para preservarlos
        $description_text = htmlspecialchars($description_text, ENT_QUOTES, 'UTF-8');
        $description_text = str_replace("\n", "&#10;", $description_text);
        $item_description = $rss->createElement('description', $description_text);
        $item->appendChild($item_description);

        $channel->appendChild($item);
    }

    // Guardar el XML en feed.xml
    if ($rss->save($feed_xml_path = $plugin_dir . 'feed.xml') === false) {
        echo '<div class="notice notice-error is-dismissible">';
        echo '<p>Error al guardar feed.xml.</p>';
        echo '</div>';
        return;
    }

    echo '<div class="notice notice-success is-dismissible">';
    echo '<p>El archivo feed.xml ha sido generado exitosamente.</p>';
    echo '</div>';
}

/**
 * 7. Función para Modificar la URL Insertando '/en' Entre el Dominio y el Resto
 */
function lfp_insert_en_in_url($url) {
    $parsed_url = parse_url($url);

    if (!$parsed_url) {
        // URL no válida
        return $url;
    }

    $scheme = isset($parsed_url['scheme']) ? $parsed_url['scheme'] . '://' : '';
    $host = isset($parsed_url['host']) ? $parsed_url['host'] : '';
    $port = isset($parsed_url['port']) ? ':' . $parsed_url['port'] : '';
    $path = isset($parsed_url['path']) ? $parsed_url['path'] : '';
    $query = isset($parsed_url['query']) ? '?' . $parsed_url['query'] : '';
    $fragment = isset($parsed_url['fragment']) ? '#' . $parsed_url['fragment'] : '';

    // Insertar '/en' al inicio del path si no está ya presente
    if (strpos($path, '/en/') !== 0 && $path !== '/en') {
        // Asegurarse de que el path comience con '/'
        if (substr($path, 0, 1) !== '/') {
            $path = '/' . $path;
        }
        $new_path = '/en' . $path;
    } else {
        $new_path = $path;
    }

    $new_url = $scheme . $host . $port . $new_path . $query . $fragment;

    return $new_url;
}


/**
 * 10. Manejar la Solicitud y Servir feed.xml
 */
add_action('template_redirect', 'lfp_handle_custom_feed');

function lfp_handle_custom_feed() {
    $custom_feed = get_query_var('custom_feed');

    if ($custom_feed == 1) {
        $plugin_dir = plugin_dir_path(__FILE__);
        $feed_xml = $plugin_dir . 'feed.xml';

        if (file_exists($feed_xml)) {
            // Establecer el tipo de contenido adecuado para RSS
            header('Content-Type: application/rss+xml; charset=UTF-8');

            // Leer y enviar el contenido del archivo feed.xml
            readfile($feed_xml);
            exit; // Terminar la ejecución para evitar cargar más contenido
        } else {
            // Manejar el caso en que feed.xml no existe
            status_header(404);
            echo 'Feed not found.';
            exit;
        }
    }
}


/**
 * 12. Seguridad y Buenas Prácticas
 * - Asegúrate de mantener tu clave de API segura.
 * - Evita exponer rutas internas del servidor.
 * - Sanitiza y valida todas las entradas y salidas.
 */

?>
