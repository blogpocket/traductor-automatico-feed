<?php
/*
Plugin Name: Lector de Feed con Traducción y XML Optimizado
Description: Lee el feed RSS del sitio, guarda las últimas publicaciones en feed.txt, traduce los títulos y descripciones usando la API de OpenAI y guarda en feed-traducido.txt. Genera un archivo feed.xml con el número configurado de ítems en orden cronológico inverso. Además, redirige /en/feed a feed.xml mediante .htaccess.
Version: 4.4
Author: Tu nombre
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
        'Configuración',                   // Título del submenú
        'manage_options',                  // Capacidad requerida
        'lector-feed-configuracion',       // Slug del submenú
        'lfp_config_page'                  // Función que muestra la página de configuración
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

        // Sanear y guardar el número de ítems a procesar (N)
        if (isset($_POST['lfp_feed_items_process'])) {
            $feed_items_process = intval($_POST['lfp_feed_items_process']);
            update_option('lfp_feed_items_process', $feed_items_process);
        }

        // Sanear y guardar el número total de ítems en feed.xml (X)
        if (isset($_POST['lfp_feed_items_total'])) {
            $feed_items_total = intval($_POST['lfp_feed_items_total']);
            update_option('lfp_feed_items_total', $feed_items_total);
        }

        // Obtener las opciones actualizadas para validación
        $feed_items_process = get_option('lfp_feed_items_process', 1);
        $feed_items_total = get_option('lfp_feed_items_total', 10);

        // Validar que X >= N
        if ($feed_items_total < $feed_items_process) {
            echo '<div class="notice notice-error is-dismissible">';
            echo '<p>El número total de ítems en feed.xml (X) debe ser mayor o igual que el número de ítems a procesar (N).</p>';
            echo '</div>';
        } else {
            // Mostrar mensaje de éxito
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p>Configuración guardada exitosamente.</p>';
            echo '</div>';
        }
    }

    // Obtener las opciones almacenadas
    $api_key = get_option('lfp_openai_api_key', '');
    $feed_title = get_option('lfp_feed_title', 'Translated Feed');
    $feed_description = get_option('lfp_feed_description', 'Description of the translated feed');
    $feed_items_process = get_option('lfp_feed_items_process', 1); // Default a 1 ítem
    $feed_items_total = get_option('lfp_feed_items_total', 10);   // Default a 10 ítems

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
                <!-- Número de Ítems a Procesar (N) -->
                <tr>
                    <th scope="row"><label for="lfp_feed_items_process">Número de Ítems a Procesar (N)</label></th>
                    <td>
                        <input type="number" name="lfp_feed_items_process" id="lfp_feed_items_process" value="<?php echo esc_attr($feed_items_process); ?>" class="small-text" min="1" required>
                        <p class="description">Cantidad de ítems nuevos que deseas procesar y añadir a feed.xml en cada ejecución.</p>
                    </td>
                </tr>
                <!-- Número Total de Ítems en feed.xml (X) -->
                <tr>
                    <th scope="row"><label for="lfp_feed_items_total">Número Total de Ítems en feed.xml (X)</label></th>
                    <td>
                        <input type="number" name="lfp_feed_items_total" id="lfp_feed_items_total" value="<?php echo esc_attr($feed_items_total); ?>" class="small-text" min="<?php echo esc_attr($feed_items_process); ?>" required>
                        <p class="description">Cantidad total de ítems que debe contener feed.xml. Debe ser mayor o igual que N.</p>
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

    // Cargar el XML
    $rss = simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NOCDATA);

    if ($rss === false) {
        echo '<div class="notice notice-error is-dismissible">';
        echo '<p>Error al procesar el feed.</p>';
        echo '</div>';
        return;
    }

    // Obtener la cantidad de ítems a procesar (N) y el total de ítems en feed.xml (X)
    $feed_items_process = get_option('lfp_feed_items_process', 1);
    $feed_items_total = get_option('lfp_feed_items_total', 10);

    // Validar que X >= N
    if ($feed_items_total < $feed_items_process) {
        echo '<div class="notice notice-error is-dismissible">';
        echo '<p>El número total de ítems en feed.xml (X) debe ser mayor o igual que el número de ítems a procesar (N).</p>';
        echo '</div>';
        return;
    }

    // Obtener los primeros N ítems (últimos N posts)
    $items = [];
    foreach ($rss->channel->item as $item) {
        if (count($items) >= $feed_items_process) break;
        $items[] = $item;
    }

    if (empty($items)) {
        echo '<div class="notice notice-error is-dismissible">';
        echo '<p>No se encontraron ítems en el feed.</p>';
        echo '</div>';
        return;
    }

    $plugin_dir = plugin_dir_path(__FILE__);
    $file_path_feed = $plugin_dir . 'feed.txt';
    $file_path_traducido = $plugin_dir . 'feed-traducido.txt';

    $output_feed = "<items>\n";         // Inicio de feed.txt
    $output_traducido = "<items>\n";    // Inicio de feed-traducido.txt

    foreach ($items as $item) {
        $title = (string)$item->title;
        $link = (string)$item->link;
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

        // Asegurarse de que no haya saltos de línea extraños
        $title = trim($title);
        $link = trim($link);

        // Escribir en feed.txt con etiquetas en español
        $output_feed .= "<item>\n";
        $output_feed .= "    <titulo>" . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . "</titulo>\n";
        $output_feed .= "    <link>" . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . "</link>\n";
        $output_feed .= "    <descripcion>" . htmlspecialchars($clean_description, ENT_QUOTES, 'UTF-8') . "</descripcion>\n";
        $output_feed .= "</item>\n\n";

        // Preparar XML para traducción
        $item_xml = "<item>\n";
        $item_xml .= "    <title>" . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . "</title>\n";
        $item_xml .= "    <link>" . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . "</link>\n";
        $item_xml .= "    <description>" . htmlspecialchars($clean_description, ENT_QUOTES, 'UTF-8') . "</description>\n";
        $item_xml .= "</item>\n";

        $output_traducido .= $item_xml;
    }

    $output_feed .= '</items>';
    $output_traducido .= '</items>';

    // Guardar feed.txt con las últimas N publicaciones
    if (file_put_contents($file_path_feed, $output_feed) === false) {
        echo '<div class="notice notice-error is-dismissible">';
        echo '<p>Error al escribir en feed.txt.</p>';
        echo '</div>';
        return;
    }

    // Traducir el contenido de feed-traducido.txt (últimos N ítems)
    $translated_content = lfp_translate_xml_content($output_traducido);

    if ($translated_content !== false) {
        // Guardar feed-traducido.txt con el contenido traducido
        if (file_put_contents($file_path_traducido, $translated_content) === false) {
            echo '<div class="notice notice-error is-dismissible">';
            echo '<p>Error al escribir en feed-traducido.txt.</p>';
            echo '</div>';
            return;
        }

        // Parsear el contenido traducido para extraer los ítems
        $translated_xml = simplexml_load_string($translated_content);

        if ($translated_xml === false) {
            echo '<div class="notice notice-error is-dismissible">';
            echo '<p>Error al procesar feed-traducido.txt como XML.</p>';
            echo '</div>';
            return;
        }

        // Construir un arreglo de ítems traducidos
        $translated_items = [];
        foreach ($translated_xml->item as $translated_item) {
            $translated_items[] = [
                'title' => (string)$translated_item->title,
                'link' => (string)$translated_item->link,
                'description' => (string)$translated_item->description,
            ];
        }

        // Actualizar feed.xml con las nuevas traducciones y mantener X ítems
        lfp_update_xml_feed($translated_items, $feed_items_process, $feed_items_total);
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
 * 6. Función para Actualizar el Archivo feed.xml
 * 
 * @param array $new_translations Arreglo de ítems traducidos.
 * @param int $n Número de ítems añadidos.
 * @param int $x Número total de ítems que debe tener feed.xml.
 */
function lfp_update_xml_feed($new_translations, $n, $x) {
    $plugin_dir = plugin_dir_path(__FILE__);
    $feed_xml_path = $plugin_dir . 'feed.xml';
    $feed_title = get_option('lfp_feed_title', 'Translated Feed');
    $feed_description = get_option('lfp_feed_description', 'Description of the translated feed');

    // Inicializar un arreglo para almacenar los ítems traducidos
    $translated_items = [];

    // Si feed.xml existe, cargar los ítems existentes
    if (file_exists($feed_xml_path)) {
        $existing_xml = simplexml_load_file($feed_xml_path);
        if ($existing_xml !== false) {
            foreach ($existing_xml->channel->item as $existing_item) {
                $translated_items[] = [
                    'title' => (string)$existing_item->title,
                    'link' => (string)$existing_item->link,
                    'description' => (string)$existing_item->description,
                ];
            }
        }
    }

    // Obtener una lista de enlaces originales de los ítems existentes en feed.xml
    $existing_original_links = [];
    foreach ($translated_items as $existing_item) {
        // Extraer el enlace original sin '/en/'
        $original_link = lfp_get_original_link($existing_item['link']);
        if ($original_link) {
            $existing_original_links[] = $original_link;
        }
    }

    // Añadir las nuevas traducciones al inicio
    foreach ($new_translations as $translated_item) {
        // Verificar si el ítem ya existe en feed.xml para evitar duplicados
        if (!in_array($translated_item['link'], $existing_original_links)) {
            // Prepend the new item to translated_items
            array_unshift($translated_items, [
                'title' => $translated_item['title'],
                'link' => $translated_item['link'],
                'description' => $translated_item['description'],
            ]);
            // Añadir el enlace original a la lista para futuras verificaciones
            $existing_original_links[] = $translated_item['link'];
        }
    }

    // Limitar el número de ítems según el número total X
    if (count($translated_items) > $x) {
        // Calcular cuántos ítems exceden el límite
        $excess = count($translated_items) - $x;
        // Eliminar los ítems más antiguos (los últimos en el arreglo)
        for ($i = 0; $i < $excess; $i++) {
            array_pop($translated_items);
        }
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
    foreach ($translated_items as $item_data) {
        $item = $rss->createElement('item');

        // Título
        $title_text = $item_data['title'];
        $item_title = $rss->createElement('title', htmlspecialchars($title_text, ENT_QUOTES, 'UTF-8'));
        $item->appendChild($item_title);

        // Link
        $original_link = $item_data['link'];
        $modified_link = lfp_insert_en_in_url($original_link);
        $item_link = $rss->createElement('link', htmlspecialchars($modified_link, ENT_QUOTES, 'UTF-8'));
        $item->appendChild($item_link);

        // Descripción
        $description_text = $item_data['description'];
        // Reemplazar saltos de línea con entidades XML para preservarlos
        $description_text = htmlspecialchars($description_text, ENT_QUOTES, 'UTF-8');
        $description_text = str_replace("\n", "&#10;", $description_text);
        $item_description = $rss->createElement('description', $description_text);
        $item->appendChild($item_description);

        $channel->appendChild($item);
    }

    // Guardar el XML en feed.xml
    if ($rss->save($feed_xml_path) === false) {
        echo '<div class="notice notice-error is-dismissible">';
        echo '<p>Error al guardar feed.xml.</p>';
        echo '</div>';
        return;
    }

    echo '<div class="notice notice-success is-dismissible">';
    echo '<p>El archivo feed.xml ha sido actualizado exitosamente.</p>';
    echo '</div>';
}

/**
 * Función auxiliar para obtener el enlace original sin '/en/'
 */
function lfp_get_original_link($modified_link) {
    $parsed_url = parse_url($modified_link);

    if (!$parsed_url) {
        // URL no válida
        return false;
    }

    $scheme = isset($parsed_url['scheme']) ? $parsed_url['scheme'] . '://' : '';
    $host = isset($parsed_url['host']) ? $parsed_url['host'] : '';
    $port = isset($parsed_url['port']) ? ':' . $parsed_url['port'] : '';
    $path = isset($parsed_url['path']) ? $parsed_url['path'] : '';
    $query = isset($parsed_url['query']) ? '?' . $parsed_url['query'] : '';
    $fragment = isset($parsed_url['fragment']) ? '#' . $parsed_url['fragment'] : '';

    // Eliminar '/en' del path si está presente
    $path = preg_replace('#^/en/#', '/', $path);
    $path = preg_replace('#^/en$#', '/', $path);

    $original_link = $scheme . $host . $port . $path . $query . $fragment;

    return $original_link;
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
 * 8. Seguridad y Buenas Prácticas
 * - Asegúrate de mantener tu clave de API segura.
 * - Evita exponer rutas internas del servidor.
 * - Sanitiza y valida todas las entradas y salidas.
 */
?>
