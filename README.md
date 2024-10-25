# Lector de Feed con Traducción y XML optimizado
Lee el feed RSS del sitio, guarda las últimas publicaciones en feed.txt, traduce los títulos y descripciones usando la API de OpenAI y guarda en feed-traducido.txt. Genera un archivo feed.xml con el número configurado de ítems en orden cronológico inverso. También se configura el número de items a procesar (las últimas publicaciones). Además, redirige /en/feed a feed.xml mediante .htaccess. Requiere API de OpenAI (consulta documentación de OpenAI para obtener dicha clave). Requiere modificación del archivo .htaccess para incluir reglas de reescritura.
## Activar el Plugin
- Ve al panel de administración de WordPress.
- Navega a "Plugins" > "Plugins instalados".
- Busca "Lector de Feed con Traducción y XML" y haz clic en "Activar".
## Configurar el Plugin
- Una vez activado, verás un nuevo menú llamado "Lector de Feed" en el panel de administración.
- Haz clic en "Lector de Feed" y luego en "Configuración".
- Ingresa tu clave de API de OpenAI.
- Configura el título del feed y su descripción.
- Configura N = el Número de Ítems a Procesar (Cantidad de ítems nuevos que deseas procesar y añadir a feed.xml en cada ejecución).
- Configura X = el Número Total de Ítems en feed.xml (Cantidad total de ítems que debe contener feed.xml. Debe ser mayor o igual que N).
- Guarda los cambios.
## Ejecutar el Proceso de Lectura, Traducción y Generación del XML
- Ve a "Lector de Feed" en el panel de administración.
- Haz clic en el botón "Leer, Traducir y Generar XML".
- El plugin procesará el feed RSS, traducirá el contenido y generará los archivos feed.txt, feed-traducido.txt y feed.xml en la carpeta del plugin.
## Verificar la Redirección
- Accede a https://misitio.com/en/feed en tu navegador.
- Deberías ver el contenido de feed.xml generado por el plugin.
## Validar el Feed XML
- Utiliza herramientas como W3C Feed Validation Service (https://validator.w3.org/feed/) para validar que feed.xml es un feed RSS válido.
## Seguridad
- Mantén tu clave de API de OpenAI segura y evita exponerla públicamente.
- Asegúrate de que el servidor esté correctamente configurado para evitar accesos no autorizados a los archivos sensibles.
- Uso de Nonces: Para mejorar la seguridad y evitar ataques CSRF, se utilizan wp_nonce_field y check_admin_referer en los formularios.
- Sanitización y Escapado: Todas las entradas de usuarios y salidas al navegador se sanitizan y escapan correctamente para prevenir vulnerabilidades.
## Actualización de Reglas de Reescritura
- Si realizas cambios manuales en las reglas de reescritura o en la estructura de los enlaces, recuerda ir a "Ajustes" > "Enlaces permanentes" en el panel de administración de WordPress y hacer clic en "Guardar cambios" para actualizar las reglas.
### Añadir las Instrucciones de Redirección en .htaccess
Ahora, procederemos a configurar la redirección directamente en el archivo .htaccess. Esto permitirá que las solicitudes a misitio.com/en/feed se redirijan al archivo feed.xml de tu plugin sin pasar por WordPress.
Pasos a Seguir:
- Accede al Archivo .htaccess:
- Utiliza un cliente FTP, el administrador de archivos de tu hosting, o cualquier método que prefieras para acceder a los archivos de tu sitio.
- Navega a la raíz de tu instalación de WordPress, donde se encuentra el archivo .htaccess.
- Editar el Archivo .htaccess:
- Abre el archivo .htaccess para editarlo.
- Antes del bloque de reglas de WordPress, que generalmente está delimitado por # BEGIN WordPress y # END WordPress, añade las siguientes líneas. Si las reglas del plugin GTranslate (u otro) están situadas al principio, la regla de redirección personalizada debe ir antes.
- Copiar código: 
#### Redirección personalizada para /en/feed
RewriteRule ^en/feed/?$ /wp-content/plugins/lector-feed/feed.xml [L]
### Verificar la Redirección
- Es crucial asegurarse de que la redirección funcione correctamente después de realizar estos cambios.
Pasos para Probar:
- Limpiar Caché (si aplica).
- Si utilizas plugins de caché o un CDN, limpia la caché para asegurarte de que los cambios se reflejen inmediatamente.
