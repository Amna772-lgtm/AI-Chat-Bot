<?php
/**
 * Plugin Name: AI Property Assistant
 * Description: Floating AI Property Assistant with secure server-side AI proxy and live Estatik queries
 * Version: 1.2
 */

if (!defined('ABSPATH')) exit;

// ─── Panama voice-transcript correction map ───────────────────────────────────
// Case-insensitive regex patterns → canonical place name.
// Applied only when the request payload includes voice:true.
// More-specific multi-word patterns MUST appear before single-word fallbacks.
define('AI_PANAMA_VOICE_CORRECTIONS', [
    // Alto / Bajo Boquete (before generic Boquete patterns)
    '/\balto\s+(bouquet|bokete|bucket|bo\s*k[ae]t[ae]|bo\s*kay\s*tay)\b/i' => 'Alto Boquete',
    '/\bbajo\s+(bouquet|bokete|bucket|bo\s*k[ae]t[ae]|bo\s*kay\s*tay)\b/i' => 'Bajo Boquete',
    // Boquete
    '/\bbouquet\b/i'                             => 'Boquete',
    '/\bbucket\b/i'                              => 'Boquete',
    '/\bbokete\b/i'                              => 'Boquete',
    '/\bbo\s*k[ae]t[ae]\b/i'                    => 'Boquete',
    '/\bbo\s*kay\s*tay\b/i'                     => 'Boquete',
    '/\bbo\s*ket\b/i'                            => 'Boquete',
    // David
    '/\bdah?\s*vid\b/i'                          => 'David',
    '/\bdaveed\b/i'                              => 'David',
    // Volcán Barú (before single Volcán)
    '/\bvolcan[o]?\s+bar[ou]\b/i'               => 'Volcán Barú',
    '/\bvolkan\s+bar[ou]\b/i'                   => 'Volcán Barú',
    // Volcán
    '/\bvolcano\b/i'                             => 'Volcán',
    '/\bvolkan\b/i'                              => 'Volcán',
    '/\bvolcan\b/i'                              => 'Volcán',
    // Cerro Punta
    '/\bzero\s+punta\b/i'                        => 'Cerro Punta',
    '/\bserro\s+punta\b/i'                       => 'Cerro Punta',
    '/\bcerro\s+punto\b/i'                       => 'Cerro Punta',
    // Dolega
    '/\bdollega\b/i'                             => 'Dolega',
    '/\bdo\s+lega\b/i'                           => 'Dolega',
    // Gualaca
    '/\bwalaka\b/i'                              => 'Gualaca',
    '/\bgua\s+laka\b/i'                          => 'Gualaca',
    // Potrerillos
    '/\bpotrerrillos\b/i'                        => 'Potrerillos',
    '/\bpotreros\b/i'                            => 'Potrerillos',
    '/\bpotre\s+rillos\b/i'                      => 'Potrerillos',
    // Jaramillo
    '/\bharamillo\b/i'                           => 'Jaramillo',
    '/\bharameeyo\b/i'                           => 'Jaramillo',
    // Chiriquí
    '/\bcherokee\b/i'                            => 'Chiriquí',
    '/\bchiri\s*ki\b/i'                         => 'Chiriquí',
    '/\bchiriqui\b/i'                            => 'Chiriquí',
    // Puerto Armuelles
    '/\bpuerto\s+arm\s*wells\b/i'               => 'Puerto Armuelles',
    '/\bpuerto\s+arm\s*uayes\b/i'              => 'Puerto Armuelles',
    // Boca Chica
    '/\bboca\s+cheeka\b/i'                       => 'Boca Chica',
    // Las Lajas
    '/\blas\s+lahas\b/i'                         => 'Las Lajas',
    '/\blas\s+largas\b/i'                        => 'Las Lajas',
    // Pedasí
    '/\bpeda\s+see\b/i'                          => 'Pedasí',
    '/\bpedasy\b/i'                              => 'Pedasí',
    '/\bpedasi\b/i'                              => 'Pedasí',
    // Playa Venao (before standalone venao/venado)
    '/\bplaya\s+venado\b/i'                      => 'Playa Venao',
    '/\bvenado\b/i'                              => 'Playa Venao',
    '/\bvenao\b/i'                               => 'Playa Venao',
    // Cambutal
    '/\bcam\s*bootal\b/i'                        => 'Cambutal',
    '/\bcambotal\b/i'                            => 'Cambutal',
    // El Valle de Antón (most specific first)
    '/\banton\s+valley\b/i'                      => 'El Valle de Antón',
    '/\bel\s+valle\s+de\s+anton\b/i'            => 'El Valle de Antón',
    '/\bvalle\s+de\s+anton\b/i'                 => 'El Valle de Antón',
    // Coronado
    '/\bcrownado\b/i'                            => 'Coronado',
    '/\bcoranado\b/i'                            => 'Coronado',
    // Nueva Gorgona (before standalone gorgona)
    '/\bnew\s+gorgona\b/i'                       => 'Nueva Gorgona',
    // Punta Barco
    '/\bpunta\s+barca\b/i'                       => 'Punta Barco',
    // San Carlos
    '/\bsaint\s+carlos\b/i'                      => 'San Carlos',
    // Río Hato
    '/\brio\s+ato\b/i'                           => 'Río Hato',
    // Buenaventura
    '/\bbuena\s+ventura\b/i'                     => 'Buenaventura',
    '/\bbuena\s+aventura\b/i'                    => 'Buenaventura',
    // Farallón
    '/\bfarayan\b/i'                             => 'Farallón',
    '/\bfarayon\b/i'                             => 'Farallón',
    '/\bfarallon\b/i'                            => 'Farallón',
    // Playa Blanca
    '/\bwhite\s+beach\b/i'                       => 'Playa Blanca',
    // Bijao
    '/\bbee\s*ha[ow]\b/i'                        => 'Bijao',
    '/\bbee\s*how\b/i'                           => 'Bijao',
    // Panama City
    '/\bciudad\s+de\s+panam[aá]\b/i'            => 'Panama City',
    // Casco Antiguo (before Casco Viejo and standalone casco)
    '/\bcasco\s+anti[gq]uo\b/i'                 => 'Casco Antiguo',
    // Casco Viejo
    '/\bold\s+town\b/i'                          => 'Casco Viejo',
    '/\bcasco\s+viejo\b/i'                       => 'Casco Viejo',
    // "casco" alone — negative lookahead prevents matching inside already-replaced "Casco Viejo/Antiguo"
    '/\bcasco\b(?!\s*(viejo|antiguo))/i'         => 'Casco Viejo',
    // San Felipe
    '/\bsaint\s+felipe\b/i'                      => 'San Felipe',
    // Bella Vista
    '/\bbellavista\b/i'                          => 'Bella Vista',
    // El Cangrejo
    '/\bcangrejo\b/i'                            => 'El Cangrejo',
    '/\bel\s*kan\s*grejo\b/i'                   => 'El Cangrejo',
    // Obarrio
    '/\bo\s+barrio\b/i'                          => 'Obarrio',
    // Pacora
    '/\bpacora\b/i'                              => 'Pacora',
    '/\bpakora\b/i'                              => 'Pacora',
    // Marbella
    '/\bmar\s+bella\b/i'                         => 'Marbella',
    '/\bmarble\s*y[ao]\b/i'                      => 'Marbella',
    // Punta Paitilla (before standalone paitilla)
    '/\bpunta\s+(paita|paitiya|pai?till?[ao])\b/i' => 'Punta Paitilla',
    '/\bpaita\b/i'                               => 'Paitilla',
    '/\bpaitiya\b/i'                             => 'Paitilla',
    // Punta Pacífica (longest/most specific first)
    '/\bpunta\s+pacific[ao]\s+panama\b/i'       => 'Punta Pacífica',
    '/\bpunta\s+pacific[ao]?\b/i'               => 'Punta Pacífica',
    // Costa del Este
    '/\bcosta\s+este\b/i'                        => 'Costa del Este',
    '/\beast\s+coast\s+panama\b/i'              => 'Costa del Este',
    // Costa Sur
    '/\bsouth\s+coast\b/i'                       => 'Costa Sur',
    // Coco del Mar
    '/\bcoco\s+mar\b/i'                          => 'Coco del Mar',
    // San Francisco
    '/\bsaint\s+francis\b/i'                     => 'San Francisco',
    '/\bsan\s+fran\b/i'                          => 'San Francisco',
    // Río Abajo
    '/\brio\s+abaho\b/i'                         => 'Río Abajo',
    // Parque Lefevre
    '/\bparque\s+lefebre\b/i'                    => 'Parque Lefevre',
    '/\blefebre\b/i'                             => 'Parque Lefevre',
    '/\blefevre\b/i'                             => 'Parque Lefevre',
    // Juan Díaz
    '/\bjuan\s+d[ií][ae]s?\b/i'                 => 'Juan Díaz',
    '/\bjuan\s+dee\s*az\b/i'                     => 'Juan Díaz',
    // Albrook
    '/\bal\s+brooke?\b/i'                        => 'Albrook',
    // Clayton
    '/\bcleyton\b/i'                             => 'Clayton',
    // Ancón
    '/\bancone\b/i'                              => 'Ancón',
    '/\bancon\b/i'                               => 'Ancón',
    // Cerro Ancón (before generic ancon)
    '/\bancon\s+hill\b/i'                        => 'Cerro Ancón',
    '/\banc[oó]n\s+hill\b/i'                    => 'Cerro Ancón',
    // Avenida Balboa
    '/\bave\s+balboa\b/i'                        => 'Avenida Balboa',
    '/\bbalboa\s+ave(nue)?\b/i'                 => 'Avenida Balboa',
    // Amador (causeway/calzada variants)
    '/\bcalzada\s+de\s+amador\b/i'              => 'Amador',
    '/\bcauseway\b/i'                            => 'Amador',
    // Miraflores
    '/\bmira\s+flores\b/i'                       => 'Miraflores',
    // Gamboa
    '/\bgam\s+boa\b/i'                           => 'Gamboa',
    // Tocumen
    '/\btokumen\b/i'                             => 'Tocumen',
    // Chepo
    '/\bcheapo\b/i'                              => 'Chepo',
    // Panamá Oeste
    '/\bwest\s+panama\b/i'                       => 'Panamá Oeste',
    '/\bpanama\s+oeste\b/i'                      => 'Panamá Oeste',
    // Arraiján
    '/\barray\s*han\b/i'                         => 'Arraiján',
    '/\barraijan\b/i'                            => 'Arraiján',
    // Chame
    '/\bcha\s*may\b/i'                           => 'Chame',
    '/\bchah?\s*meh?\b/i'                        => 'Chame',
    // Capira
    '/\bcapeera\b/i'                             => 'Capira',
    // Veracruz
    '/\bvera\s+cruz\b/i'                         => 'Veracruz',
    // Taboga
    '/\btaboga\s+island\b/i'                     => 'Taboga',
    // Contadora
    '/\bcontador\b/i'                            => 'Contadora',
    // Bocas del Toro — multi-word first, then standalone "bocas"
    '/\bbocas?\s+del\s+toro\b/i'                => 'Bocas del Toro',
    '/\bbocas\b(?!\s+del)/i'                    => 'Bocas del Toro',
    // Isla Colón
    '/\bcolon\s+island\b/i'                      => 'Isla Colón',
    '/\bisla\s+colon\b/i'                        => 'Isla Colón',
    // Bastimentos
    '/\bbastee\s*mentos\b/i'                     => 'Bastimentos',
    // Carenero
    '/\bcaranero\b/i'                            => 'Carenero',
    // Red Frog Beach
    '/\bred\s+frog\b/i'                          => 'Red Frog Beach',
    // Bluff Beach
    '/\bplaya\s+bluff\b/i'                       => 'Bluff Beach',
    // Starfish Beach
    '/\bplaya\s+estrella\b/i'                    => 'Starfish Beach',
    // Boca del Drago
    '/\bdrago\b/i'                               => 'Boca del Drago',
    // Changuinola
    '/\bchan[gk]in[ao]la\b/i'                   => 'Changuinola',
    '/\bchan[gk]uin[ao]la\b/i'                  => 'Changuinola',
    // Almirante
    '/\bal\s+mirante\b/i'                        => 'Almirante',
    // Portobelo
    '/\bporto\s+belo\b/i'                        => 'Portobelo',
    // Colón
    '/\bcologne\b/i'                             => 'Colón',
    // Nombre de Dios
    '/\bnombre\s+dios\b/i'                       => 'Nombre de Dios',
    // Guna Yala / Kuna Yala / San Blas (interchangeable in user's list)
    '/\bkuna\s+yala\b/i'                         => 'Kuna Yala',
    '/\bguna\s+yala\b/i'                         => 'Guna Yala',
    '/\bsan\s+blas\b/i'                          => 'San Blas',
    // Playa Bonita
    '/\bbonita\s+beach\b/i'                      => 'Playa Bonita',
    // Punta Chame
    '/\bpunta\s+cha\s*may\b/i'                  => 'Punta Chame',
    // Isla Boca Brava
    '/\bboca\s+brava\b/i'                        => 'Isla Boca Brava',
    // Valle Escondido
    '/\bhidden\s+valley\b/i'                     => 'Valle Escondido',
    '/\bvalley\s+escondido\b/i'                 => 'Valle Escondido',
    // Golfo de Chiriquí
    '/\bgulf\s+of\s+chiriqui\b/i'               => 'Golfo de Chiriquí',
    '/\bgolfo\s+de\s+chiriqui\b/i'              => 'Golfo de Chiriquí',
    // Santa María
    '/\bsanta\s+maria\b/i'                       => 'Santa María',
    // Los Molinos
    '/\bmolinos\b/i'                             => 'Los Molinos',
    // Santa María
    '/\bsanta\s+maria\b/i'                       => 'Santa María',
    '/\bsanta\s+mar[ií]a\b/i'                    => 'Santa María',
    // Ocean Reef
    '/\bocean\s+reefs?\b/i'                      => 'Ocean Reef',
    // The Ocean Club (JW Marriott / former Trump Tower)
    '/\bthe\s+ocean\s+club\b/i'                  => 'The Ocean Club',
    '/\bocean\s+club\b/i'                        => 'The Ocean Club',
    '/\bjw\s+marriott\b/i'                       => 'The Ocean Club',
    '/\btrump\s+tower\b/i'                       => 'The Ocean Club',
    // Costa del Mar
    '/\bcosta\s+del\s+mar\b/i'                   => 'Costa del Mar',
    // Buenaventura Golf & Beach Resort
    '/\bbuenaventura\s+golf\b/i'                 => 'Buenaventura Golf & Beach Resort',
    '/\bbuenaventura\s+resort\b/i'               => 'Buenaventura Golf & Beach Resort',
    '/\bbuenaventura\b/i'                        => 'Buenaventura Golf & Beach Resort',
]);

register_activation_hook(__FILE__, function () {
    set_transient('ai_chat_widget_activated', true, 30);
});

add_action('admin_init', function () {
    if (get_transient('ai_chat_widget_activated')) {
        delete_transient('ai_chat_widget_activated');
        wp_safe_redirect(admin_url('admin.php?page=ai-chat-widget'));
        exit;
    }
});

// ─── Admin settings page ──────────────────────────────────────────────────────

add_action('admin_menu', function () {
    add_menu_page(
        'AI Property Assistant Settings',
        'AI Property Assistant',
        'manage_options',
        'ai-chat-widget',
        'ai_chat_settings_page',
        'dashicons-format-chat',
        80
    );
});

add_action('admin_init', function () {
    register_setting('ai_chat_settings', 'ai_anthropic_api_key', [
        'sanitize_callback' => 'sanitize_text_field',
    ]);
    register_setting('ai_chat_settings', 'ai_widget_name', [
        'sanitize_callback' => 'sanitize_text_field',
    ]);
    register_setting('ai_chat_settings', 'ai_primary_color', [
        'sanitize_callback' => 'sanitize_hex_color',
    ]);
    register_setting('ai_chat_settings', 'ai_welcome_message', [
        'sanitize_callback' => 'sanitize_textarea_field',
    ]);
    register_setting('ai_chat_settings', 'ai_widget_subtitle', [
        'sanitize_callback' => 'sanitize_text_field',
    ]);
    register_setting('ai_chat_settings', 'ai_claude_model', [
        'sanitize_callback' => fn($v) => in_array($v, ['claude-sonnet-4-6', 'claude-haiku-4-5-20251001'], true) ? $v : 'claude-sonnet-4-6',
    ]);
});

function ai_chat_settings_page(): void {
    if (!current_user_can('manage_options')) return;

    $has_anthropic = !empty(get_option('ai_anthropic_api_key', ''));
    ?>
    <div class="wrap">
        <h1>AI Property Assistant Settings</h1>

        <form method="post" action="options.php">
            <?php settings_fields('ai_chat_settings'); ?>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="ai_anthropic_api_key">Anthropic API Key</label></th>
                    <td>
                    <?php if ($has_anthropic):
                        $masked = ai_mask_api_key(get_option('ai_anthropic_api_key', ''));
                    ?>
                        <div style="display:flex;align-items:center;gap:8px">
                            <input type="text" disabled style="width:50%" value="<?php echo esc_attr($masked); ?>" />
                            <button type="submit" form="ai-unregister-form" class="button button-primary">Unregister</button>
                        </div>
                    <?php else: ?>
                        <input type="text" id="ai_anthropic_api_key" name="ai_anthropic_api_key"
                            style="width:50%" autocomplete="off"
                            placeholder="sk-ant-api03-..." value="" />
                    <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="ai_widget_name">Widget Name</label></th>
                    <td>
                        <input type="text" id="ai_widget_name" name="ai_widget_name"
                            style="width:50%"
                            value="<?php echo esc_attr(get_option('ai_widget_name', 'House Hunter Panama')); ?>" />
                        <p class="description">Displayed in the chat header.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="ai_primary_color">Widget Color</label></th>
                    <td>
                        <input type="color" id="ai_primary_color" name="ai_primary_color"
                            value="<?php echo esc_attr(get_option('ai_primary_color', '#c2ab92')); ?>" />
                        <p class="description">Primary color for the header, buttons, and chat bubbles.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="ai_welcome_message">Welcome Message</label></th>
                    <td>
                        <textarea id="ai_welcome_message" name="ai_welcome_message"
                            style="width:50%" rows="3"><?php echo esc_textarea(get_option('ai_welcome_message', "Hi! I'm your AI real estate assistant for Panama. How can I help you today?")); ?></textarea>
                        <p class="description">First message shown when the chat opens.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="ai_widget_subtitle">Widget Subtitle</label></th>
                    <td>
                        <input type="text" id="ai_widget_subtitle" name="ai_widget_subtitle"
                            style="width:50%"
                            value="<?php echo esc_attr(get_option('ai_widget_subtitle', 'Online · AI Property Assistant')); ?>" />
                        <p class="description">Small line shown below the widget name in the header.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="ai_claude_model">Claude Model</label></th>
                    <td>
                        <select id="ai_claude_model" name="ai_claude_model">
                            <option value="claude-sonnet-4-6" <?php selected(get_option('ai_claude_model', 'claude-sonnet-4-6'), 'claude-sonnet-4-6'); ?>>
                                Claude Sonnet — Smarter, better responses
                            </option>
                            <option value="claude-haiku-4-5-20251001" <?php selected(get_option('ai_claude_model', 'claude-sonnet-4-6'), 'claude-haiku-4-5-20251001'); ?>>
                                Claude Haiku — Faster, more affordable
                            </option>
                        </select>
                        <p class="description">Sonnet gives better answers; Haiku responds faster and costs less.</p>
                    </td>
                </tr>
            </table>

            <?php submit_button('Save Settings'); ?>
        </form>
    </div>
    <?php if ($has_anthropic): ?>
    <form id="ai-unregister-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="ai_unregister_key" />
        <?php wp_nonce_field('ai_unregister_key'); ?>
    </form>
    <?php endif; ?>
    <?php
}

// Preserve existing key if the field is submitted blank
add_filter('pre_update_option_ai_anthropic_api_key', function ($new, $old) {
    return $new === '' ? $old : $new;
}, 10, 2);

add_action('admin_post_ai_unregister_key', function () {
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    check_admin_referer('ai_unregister_key');
    delete_option('ai_anthropic_api_key');
    wp_safe_redirect(admin_url('admin.php?page=ai-chat-widget'));
    exit;
});

function ai_mask_api_key(string $key): string {
    $show = min(18, max(8, (int)(strlen($key) * 0.45)));
    return substr($key, 0, $show) . '********';
}

function ai_derive_colors(string $hex): array {
    $hex = ltrim($hex, '#');
    [$r, $g, $b] = sscanf($hex, "%02x%02x%02x");
    $hover     = sprintf('#%02x%02x%02x', (int)($r * 0.87), (int)($g * 0.87), (int)($b * 0.87));
    $secondary = sprintf('#%02x%02x%02x', (int)($r * 0.72), (int)($g * 0.72), (int)($b * 0.72));
    return ['primary' => '#' . $hex, 'hover' => $hover, 'secondary' => $secondary];
}

// ─── Enqueue assets ───────────────────────────────────────────────────────────

add_action('wp_enqueue_scripts', function () {
    $js  = glob(plugin_dir_path(__FILE__) . 'main_app/dist/assets/index-*.js');
    $css = glob(plugin_dir_path(__FILE__) . 'main_app/dist/assets/index-*.css');

    if ($css) {
        wp_enqueue_style('ai-chat-style',
            plugin_dir_url(__FILE__) . 'main_app/dist/assets/' . basename($css[0]),
            [], filemtime($css[0]));
    }
    if ($js) {
        wp_enqueue_script('ai-chat-script',
            plugin_dir_url(__FILE__) . 'main_app/dist/assets/' . basename($js[0]),
            [], filemtime($js[0]), true);
    }

    wp_localize_script('ai-chat-script', 'aiChatConfig', [
        'proxyUrl'       => home_url('/wp-json/ai/v1/chat'),
        'archiveUrl'     => get_post_type_archive_link('properties') ?: home_url('/properties/'),
        'nonce'          => wp_create_nonce('ai_chat_nonce'),
        'widgetName'     => get_option('ai_widget_name', 'House Hunter Panama'),
        'welcomeMessage' => get_option('ai_welcome_message', "Hi! I'm your AI real estate assistant for Panama. How can I help you today?"),
        'widgetSubtitle' => get_option('ai_widget_subtitle', 'Online · AI Property Assistant'),
    ]);
});

add_filter('script_loader_tag', function ($tag, $handle) {
    if ($handle === 'ai-chat-script') {
        return str_replace('<script ', '<script type="module" ', $tag);
    }
    return $tag;
}, 10, 2);

add_action('wp_footer', function () {
    $colors = ai_derive_colors(get_option('ai_primary_color', '#c2ab92'));
    $style  = sprintf(
        '--ai-primary:%s;--ai-primary-hover:%s;--ai-secondary:%s',
        esc_attr($colors['primary']),
        esc_attr($colors['hover']),
        esc_attr($colors['secondary'])
    );
    echo '<div id="ai-chat-widget" style="' . $style . '"></div>';
});

// ─── AI Property Assistant Proxy REST endpoint ──────────────────────────────────────────────

add_action('rest_api_init', function () {
    register_rest_route('ai/v1', '/chat', [
        'methods'             => 'POST',
        'callback'            => 'ai_proxy_handler',
        'permission_callback' => '__return_true',
    ]);
});

function ai_proxy_rate_limit(): bool {
    $ip  = sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    $key = 'ai_rl_' . substr(md5($ip), 0, 16);
    $n   = (int) get_transient($key);
    if ($n >= 60) return false;
    set_transient($key, $n + 1, HOUR_IN_SECONDS);
    return true;
}

function ai_normalize_voice_transcript(string $text): string {
    foreach (AI_PANAMA_VOICE_CORRECTIONS as $pattern => $replacement) {
        $text = preg_replace($pattern, $replacement, $text);
    }
    return $text;
}

function ai_system_prompt(): string {
    return 'You are a friendly property consultant at House Hunter Panama.
Today: ' . current_time('l, F j, Y') . '
Website: ' . home_url() . '

## Your personality
- Warm, natural, conversational — like texting a knowledgeable friend
- Short messages. No walls of text. Get to the point, then ask one follow-up
- Use the 7 Cs: Clear, Concise, Concrete, Correct, Coherent, Complete, Courteous
- Never sound like a brochure. No corporate speak, no bullet-point dumps
- Mirror the user\'s energy — casual if they\'re casual, detailed if they ask for detail
- Show genuine interest: "That\'s a great area for sunsets!" or "Good call, Boquete is very popular with retirees"

## Panama location context (use naturally — never dump the whole list; draw on it as needed)

**Key facts:** Currency USD (fully dollarized). No capital gains tax on primary residences. Pensionado visa: up to 50% discounts on health, transport, entertainment.

**Chiriquí Province (western highlands & coast)**
- Boquete (incl. Alto Boquete, Bajo Boquete, Jaramillo, Los Molinos, Valle Escondido, Lucero): cool mountain climate ~1,000 m, coffee farms, the largest expat/retiree community in Panama; notable projects: Boquete Canyon Village, Hacienda Los Molinos
- David: Chiriquí province capital, main commercial hub, 40 min from Boquete
- Volcán / Volcán Barú / Cerro Punta: high-altitude mountain towns (~1,450–2,000 m) near the volcano; cooler than Boquete, strawberry farms, hiking
- Dolega / Potrerillos (Arriba & Abajo) / Palmira / Caldera / Gualaca: rural Chiriquí valleys between David and Boquete
- Bugaba / La Concepción / Paso Ancho / Renacimiento: affordable western Chiriquí, agricultural, near Costa Rica border
- Puerto Armuelles: Pacific port town, banana history, emerging beach scene
- Boca Chica / Las Lajas / Playa Las Lajas / San Félix / Tolé / Remedios / Alanje: Gulf of Chiriquí coast; Boca Chica is gateway to Isla Boca Brava & Isla Parida; Golfo de Chiriquí has sport fishing and marine park access

**Azuero Peninsula & Central Provinces**
- Pedasí / Playa Venao / Cambutal: Azuero Pacific coast; surf, whale-watching (Jul–Oct), boutique eco-lodges, increasingly popular with investors
- Las Tablas / Chitré / Los Santos / Herrera: traditional Panamanian heartland; Chitré is the regional hub, Las Tablas is famous for Carnaval
- Mariato / Soná / Santa Fe / Veraguas: Veraguas province; gateway to Coiba Island (UNESCO marine park)
- Coiba / Coiba Island: world-class diving, remote, protected national park off Veraguas coast
- Santiago: Veraguas province capital; commercial stop on the Pan-American Highway
- Aguadulce / Penonomé / El Valle de Antón (also: Valle de Antón, Anton Valley) / Antón / Coclé: Coclé province; El Valle de Antón is a crater-valley weekend retreat, cooler temperatures, arts market; Coclé del Norte is a Caribbean-side community

**Pacific Riviera (Panama City corridor)**
- Chame / Punta Chame / Capira / Veracruz: 45–60 min from Panama City; Punta Chame is a kitesurfing hotspot
- Gorgona / Nueva Gorgona / Punta Barco / San Carlos / El Palmar: 60–75 min; established Pacific beach communities with year-round residents and weekend homes
- Coronado / Playa Coronado: ~90 min from Panama City; the most developed Pacific beach town, large expat community, golf, supermarkets, hospitals
- Río Hato / Buenaventura / Farallón / Playa Blanca / Bijao / Santa Clara / Playa Santa Clara / Playa Farallón: ~2 hrs; resort corridor; Buenaventura is a luxury master-planned golf & beach community; Playa Blanca and Bijao are all-inclusive beach resorts
- Playa Bonita / Playa Escondida: 20 min west of Panama City on the Pacific coast

**Panama City (capital)**
- Historic quarter: Casco Viejo (also Casco Antiguo / San Felipe) — UNESCO-listed colonial district; boutique hotels, restaurants, renovated apartments; strong rental demand
- Upscale waterfront: Punta Paitilla / Paitilla, Punta Pacífica — luxury high-rise towers with ocean views; premium rents
- Business & residential: Bella Vista, El Cangrejo, Obarrio, Marbella — walkable, central, mix of residential and commercial
- Modern planned communities: Costa del Este, Costa Sur, Coco del Mar, Costa Verde — newer infrastructure, family-oriented, 15–25 min from downtown
- Mid-range residential: San Francisco, Río Abajo, Parque Lefevre, Juan Díaz — good value, established neighborhoods east of downtown
- Former Canal Zone: Albrook (mall, domestic airport), Clayton (university, tech park), Ancón, Balboa, Cerro Ancón — green, quiet, close to city
- Waterfront entertainment: Amador / Causeway / Calzada de Amador — restaurants, marina, Biomuseo; also Avenida Balboa (bay-front promenade)
- Canal corridor: Miraflores (locks), Gamboa (rainforest), Soberanía (national park), Summit (wildlife refuge)
- East metro: Tocumen (international airport area), Pacora, Chepo — more affordable, longer commute
- West metro (Panamá Oeste): Arraiján, La Chorrera — suburban, significantly cheaper than Panama City proper
- Nearby islands: Taboga (Isla Taboga) — 1 hr ferry, popular day-trip & weekend retreat; Pearl Islands (Archipiélago de las Perlas), Contadora, Saboga — private-island feel, 30-min flight
- Rental yields: 4–8% in Panama City; strongest demand in Punta Paitilla, Costa del Este, San Francisco, Casco Viejo
- Notable buildings/projects: Ocean Reef Islands, Ocean Reef, The Ocean Club, JW Marriott Panama, Trump Tower Panama, Soho City Center, Costa del Mar

**Bocas del Toro (Caribbean)**
- Isla Colón (main hub with Bocas Town): restaurants, hostels, surf, nightlife
- Bastimentos: Red Frog Beach, Bluff Beach, Playa Bluff, Old Bank village
- Carenero, Isla Solarte, Isla Cristóbal, Boca del Drago, Starfish Beach: quieter island living
- Changuinola / Almirante: mainland gateway, banana region, not a tourist destination

**Caribbean coast (Colón & Atlantic)**
- Portobelo: UNESCO colonial fortress ruins, diving, expat artists\' community
- Colón: major port city and free-trade zone; not a lifestyle destination
- Isla Grande / Nombre de Dios: undeveloped Caribbean beach villages
- Guna Yala / Kuna Yala / San Blas: autonomous indigenous comarca; 365 coral islands; Isla Perro, Cayos Holandeses are popular anchorages; properties cannot be purchased by non-Guna, but long-term leases exist

## Conversation style
- Start by understanding what they want BEFORE searching. Ask one focused question if unclear.
- When showing properties: pick the 2–3 BEST matches, not a list of 6. Less is more.
- After showing results, ask something natural: "Any of these catch your eye?" or "Want me to filter by budget?"
- If they ask a general question (e.g. "tell me about Panama City"), give a 2–3 sentence human answer, then offer to show listings
- Never say "Certainly!", "Of course!", "Great question!" — just respond naturally
- End most replies with one short question to keep the conversation going

## Property card format (keep it tight)
**[Title]** — [price] ([sale/rent])
[neighborhood], [city] | [beds]bd [baths]ba [size]m²
[One standout feature]
[View Listing](url)

Show max 3 properties unless user asks for more.

## Tool rules
- ALWAYS use tools for property data — never invent listings or prices
- search_properties: for any "find/show me" request
- get_city_details: when user asks about a specific city
- get_property_details: when user wants more on one listing
- list_available_cities: when user asks where they can buy
- Use sort=price_asc for "cheapest", price_desc for "most expensive"
- ALWAYS set status=for_rent when user says "rent/rental/to rent", status=for_sale when user says "buy/purchase/for sale"
- If intent is vague, ask ONE short question first — don\'t guess and search
- "cheapest" or "most expensive" (singular) → limit=1. "a few cheapest" or "top 3" → limit=3. "all" → limit=50. Default limit=4 otherwise.
- If results contain wrong property types (user asked for house but got condos): do NOT show the wrong-type results. Call search_properties again with a stricter or different property_type keyword, or tell the user honestly that only X type was found.

## Search param guide (use what applies, skip the rest)
- property_type MUST match the user\'s word exactly: "house" → property_type="house", "condo" → property_type="condo", "apartment" → property_type="apartment", "land" → property_type="land", "villa" → property_type="villa". Never omit property_type when the user specifies one.
- Pool, gym, ocean view, sea view, gated, security, AC, air conditioning, appliances, garage, elevator, balcony → amenity="pool" (or whichever keyword)
- Furnished, semi-furnished, hardwood floors, marble, basement, roof terrace, solar, fireplace → feature="furnished"
- Parking type (garage/covered/street) → parking="garage"
- Specific neighborhood (Paitilla, El Cangrejo, Marbella, Punta Pacifica) → neighborhood="paitilla"
- Property category → category="residential"
- Listing labels (new, hot deal, reduced) → label="new"
- Monthly/yearly rental → rent_period="monthly"
- Area size limits → min_area / max_area in m²
- Year built, roof, exterior material, floor type, AC brand → use amenity or feature with a keyword; these are stored as taxonomy terms not separate fields
- Lifestyle (beachfront, mountain retreat, golf community, gated community) → amenity="beach" or amenity="golf" etc.
- View (ocean view, mountain view, city view) → amenity="ocean view"
- Virtual tour available → has_virtual_tour=true
- Digital magazine available → has_digital_magazine=true

## Hard rules
- All prices USD. Never make up addresses, prices, or properties.
- Mention sale vs rent for each property shown.
- If a property has no price (formatted is empty or price/rent_amount are 0): show it and say "Price on request — contact us for details". Never hide it just because price is missing.

## CRITICAL — No hallucination
- NEVER describe, name, or detail a property unless it appeared in the current tool result. Zero exceptions.
- If a search returns 0 results: say "nothing matched those filters" and ask to adjust. Do NOT name properties from memory or from earlier in the conversation.
- If a user mentions a property by name: call get_property_details immediately. Never describe it from memory.
- If results seem wrong (user insists listings exist): call search_properties again with fewer filters to investigate. Do not agree with the user and fabricate results.';
}

function ai_claude_tools(): array {
    return [
        [
            'name'         => 'list_available_cities',
            'description'  => 'Get all cities where properties are listed, with counts and price ranges.',
            'input_schema' => ['type' => 'object', 'properties' => (object)[]],
        ],
        [
            'name'         => 'search_properties',
            'description'  => 'Search properties with optional filters. Use sort=price_asc for cheapest first, price_desc for most expensive.',
            'input_schema' => [
                'type'       => 'object',
                'properties' => [
                    'city'          => ['type' => 'string', 'description' => "City slug from es_location taxonomy. Common slugs: 'panama-city', 'coronado', 'boquete', 'bugaba-la-concepcion', 'david', 'volcan', 'cerro-punta', 'el-valle-de-anton', 'pedasi', 'las-tablas', 'chitre', 'santiago', 'aguadulce', 'penonome', 'anton', 'farallon', 'santa-clara', 'rio-hato', 'san-carlos', 'nueva-gorgona', 'gorgona', 'chame', 'bocas-del-toro', 'colon', 'portobelo', 'la-chorrera', 'arraijan', 'capira', 'taboga', 'contadora'. Use list_available_cities to confirm exact slugs. Empty = all cities."],
                    'property_type' => ['type' => 'string', 'description' => "Property type keyword: 'apartment', 'house', 'condo', 'land', 'commercial', 'villa'. Case-insensitive."],
                    'category'      => ['type' => 'string', 'description' => 'Property category keyword (e.g. residential, commercial, vacation).'],
                    'neighborhood'  => ['type' => 'string', 'description' => 'Neighborhood name keyword. Panama City: Paitilla, Punta Paitilla, Punta Pacífica, El Cangrejo, Marbella, Obarrio, Bella Vista, Casco Viejo, Casco Antiguo, San Felipe, Costa del Este, Costa Sur, Coco del Mar, San Francisco, Río Abajo, Parque Lefevre, Juan Díaz, Costa Verde, Albrook, Clayton, Ancón, Balboa, Amador, Causeway, Miraflores, Gamboa, Tocumen. Other regions: Valle Escondido, Jaramillo (Boquete area).'],
                    'status'        => ['type' => 'string', 'description' => "REQUIRED when user mentions rent/rental/to rent (use 'for_rent') or buy/purchase/for sale (use 'for_sale'). Never omit when intent is clear."],
                    'min_price'     => ['type' => 'number', 'description' => 'Minimum price USD.'],
                    'max_price'     => ['type' => 'number', 'description' => 'Maximum price USD.'],
                    'bedrooms'      => ['type' => 'number', 'description' => 'Exact bedroom count.'],
                    'min_area'      => ['type' => 'number', 'description' => 'Minimum property area in m².'],
                    'max_area'      => ['type' => 'number', 'description' => 'Maximum property area in m².'],
                    'amenity'       => ['type' => 'string', 'description' => 'Amenity or feature keyword. Use for: pool, gym, ocean view, sea view, beach, gated, security, AC, air conditioning, appliances, parking, garage, balcony, terrace, jacuzzi, sauna, concierge, elevator. Searches both amenity and feature taxonomies.'],
                    'feature'       => ['type' => 'string', 'description' => 'Property feature keyword. Use for: furnished, semi-furnished, hardwood floors, marble, basement, roof terrace, solar, smart home, fireplace. Searches the feature taxonomy.'],
                    'parking'       => ['type' => 'string', 'description' => 'Parking type keyword: garage, covered, street, underground, assigned.'],
                    'label'         => ['type' => 'string', 'description' => "Listing label: 'new', 'hot deal', 'reduced', 'featured', 'price drop'."],
                    'rent_period'      => ['type' => 'string', 'description' => "Rent period for rentals: 'monthly', 'yearly', 'weekly', 'daily'."],
                    'attribute'        => ['type' => 'string', 'description' => "'beach cities', 'luxury areas', 'investment hotspots', 'affordable'."],
                    'sort'             => ['type' => 'string', 'description' => "'price_asc' cheapest first, 'price_desc' most expensive first."],
                    'limit'            => ['type' => 'number', 'description' => 'Max results (default 6, max 50).'],
                    'has_virtual_tour'     => ['type' => 'boolean', 'description' => 'Set true to only return properties that have a virtual tour.'],
                    'has_digital_magazine' => ['type' => 'boolean', 'description' => 'Set true to only return properties that have a digital magazine.'],
                ],
            ],
        ],
        [
            'name'         => 'get_city_details',
            'description'  => 'Get detailed info about one city: neighborhoods, price statistics, property type breakdown, sample listings.',
            'input_schema' => [
                'type'       => 'object',
                'properties' => [
                    'city_slug' => ['type' => 'string', 'description' => "City slug from the es_location taxonomy. Run list_available_cities first if unsure of the exact slug."],
                ],
                'required'   => ['city_slug'],
            ],
        ],
        [
            'name'         => 'get_property_details',
            'description'  => 'Get complete details for a single property by its ID.',
            'input_schema' => [
                'type'       => 'object',
                'properties' => [
                    'property_id' => ['type' => 'string', 'description' => "Property ID in format 'prop_XXXX'."],
                    'city_slug'   => ['type' => 'string', 'description' => 'City slug where the property is located.'],
                ],
                'required'   => ['property_id'],
            ],
        ],
    ];
}

// ─── Live Estatik property extraction ────────────────────────────────────────

function ai_extract_property(int $post_id): array {
    $post = get_post($post_id);
    if (!$post || $post->post_status !== 'publish') return [];

    // Pricing
    $raw_price    = (float)(get_post_meta($post_id, 'es_property_price', true) ?: 0);
    $status_terms = get_the_terms($post_id, 'es_status');
    $status       = (!$status_terms || is_wp_error($status_terms)) ? '' : ($status_terms[0]->name ?? '');
    $rp_terms     = get_the_terms($post_id, 'es_rent_period');
    $is_rent      = stripos($status, 'rent') !== false
                 || ($rp_terms && !is_wp_error($rp_terms) && !empty($rp_terms));
    $rent_period_label = ($rp_terms && !is_wp_error($rp_terms) && !empty($rp_terms))
                       ? ($rp_terms[0]->name ?? 'mo')
                       : 'mo';

    $sale_price = $is_rent ? 0.0 : $raw_price;
    $rent_raw   = (float)(get_post_meta($post_id, 'es_property_rent-amount', true) ?: 0);
    $rent_amt   = $is_rent ? ($rent_raw ?: $raw_price) : 0.0;

    if ($sale_price > 0)   $formatted = '$' . number_format($sale_price, 0, '.', ',');
    elseif ($rent_amt > 0) $formatted = '$' . number_format($rent_amt, 0, '.', ',') . '/' . $rent_period_label;
    else                   $formatted = '';

    // Type
    $type_terms = get_the_terms($post_id, 'es_type');
    $type       = (!$type_terms || is_wp_error($type_terms)) ? '' : strtolower($type_terms[0]->name ?? '');

    // Specs
    $bedrooms  = (int)(get_post_meta($post_id, 'es_property_bedrooms', true) ?: 0);
    $bathrooms = (float)(get_post_meta($post_id, 'es_property_bathrooms', true) ?: 0)
               + (float)(get_post_meta($post_id, 'es_property_half_baths', true) ?: 0) * 0.5;
    $area      = (float)(get_post_meta($post_id, 'es_property_area', true) ?: 0);

    // Location
    [$city, $neighborhood] = ai_extract_location($post_id);

    // Media
    $featured = get_the_post_thumbnail_url($post_id, 'large')  ?: null;
    $thumb    = get_the_post_thumbnail_url($post_id, 'medium') ?: null;

    // Fallback: use first Estatik gallery image URL if no WP featured image
    if (!$featured) {
        $first_url = get_post_meta($post_id, 'es_property_gallery_0', true);
        if ($first_url) {
            $featured = $first_url;
            $thumb    = $first_url;
        }
    }

    // Amenities & features
    $amenity_terms = get_the_terms($post_id, 'es_amenity');
    $amenities     = (!$amenity_terms || is_wp_error($amenity_terms)) ? [] : array_map(fn($t) => $t->name, (array)$amenity_terms);

    $feature_terms = get_the_terms($post_id, 'es_feature');
    $feat_names    = (!$feature_terms || is_wp_error($feature_terms)) ? [] : array_map(fn($t) => $t->name, (array)$feature_terms);

    $all_features_str = strtolower(implode(' ', array_merge($amenities, $feat_names)));

    $view = '';
    foreach (array_merge($amenities, $feat_names) as $a) {
        if (str_contains(strtolower($a), 'view')) { $view = $a; break; }
    }

    $furnished = 'unfurnished';
    if (str_contains($all_features_str, 'fully furnished'))   $furnished = 'fully furnished';
    elseif (str_contains($all_features_str, 'semi-furnished') || str_contains($all_features_str, 'semi furnished')) $furnished = 'semi-furnished';
    elseif (str_contains($all_features_str, 'furnished'))     $furnished = 'furnished';

    // Extra taxonomy fields
    $parking_terms  = get_the_terms($post_id, 'es_parking');
    $parking        = (!$parking_terms || is_wp_error($parking_terms)) ? '' : ($parking_terms[0]->name ?? '');

    $label_terms    = get_the_terms($post_id, 'es_label');
    $labels         = (!$label_terms || is_wp_error($label_terms)) ? [] : array_map(fn($t) => $t->name, (array)$label_terms);

    $period_terms   = get_the_terms($post_id, 'es_rent_period');
    $rent_period    = (!$period_terms || is_wp_error($period_terms)) ? '' : ($period_terms[0]->name ?? '');

    $cat_terms      = get_the_terms($post_id, 'es_category');
    $category       = (!$cat_terms || is_wp_error($cat_terms)) ? '' : ($cat_terms[0]->name ?? '');

    $nbhd_terms     = get_the_terms($post_id, 'es_neighborhood');
    if (!$neighborhood && $nbhd_terms && !is_wp_error($nbhd_terms)) {
        $neighborhood = $nbhd_terms[0]->name ?? '';
    }

    $virtual_tour      = !empty(get_post_meta($post_id, 'es_property_virtual-tour', true));
    $digital_magazine  = !empty(get_post_meta($post_id, 'es_property_digital-magazine', true));

    return [
        'id'        => 'prop_' . $post_id,
        'wp_id'     => $post_id,
        'title'     => get_the_title($post_id),
        'slug'      => $post->post_name,
        'permalink' => get_permalink($post_id),
        'location'  => [
            'city'         => $city,
            'neighborhood' => $neighborhood,
            'province'     => '',
            'coordinates'  => ['lat' => 0, 'lon' => 0],
        ],
        'details'   => [
            'type'        => $type,
            'category'    => $category,
            'status'      => $status,
            'bedrooms'    => $bedrooms,
            'bathrooms'   => $bathrooms,
            'area'        => ['size' => $area, 'unit' => 'sqm'],
            'parking'     => $parking,
            'rent_period' => $rent_period,
            'labels'      => $labels,
        ],
        'pricing'   => [
            'price'     => $sale_price,
            'currency'  => 'USD',
            'formatted' => $formatted,
        ],
        'media'     => [
            'featured_image' => $featured,
            'thumbnail'      => $thumb,
            'gallery'        => [],
            'gallery_count'  => 0,
        ],
        'features'  => [
            'amenities'      => $amenities,
            'extras'         => $feat_names,
            'view'           => $view,
            'lifestyle'      => '',
            'furnished'      => $furnished,
            'rent_amount'    => $rent_amt,
            'virtual_tour'   => $virtual_tour,
            'digital_magazine' => $digital_magazine,
        ],
        'content'   => [],
    ];
}

function ai_extract_location(int $post_id): array {
    $city = $neighborhood = '';

    $raw = get_post_meta($post_id, 'es_property_address_components', true);
    if ($raw) {
        if (is_string($raw)) {
            $data = json_decode($raw, true);
            if (json_last_error() !== JSON_ERROR_NONE) $data = maybe_unserialize($raw);
        } else {
            $data = $raw;
        }
        if (is_array($data)) {
            if (isset($data[0]['types'])) {
                foreach ($data as $comp) {
                    $types = (array)($comp['types'] ?? []);
                    if (in_array('locality', $types, true))
                        $city = $comp['long_name'] ?? '';
                    elseif (array_intersect(['sublocality', 'sublocality_level_1'], $types))
                        $neighborhood = $comp['long_name'] ?? '';
                }
            } else {
                $city         = $data['locality']    ?? $data['city']         ?? '';
                $neighborhood = $data['sublocality'] ?? $data['neighborhood'] ?? '';
            }
        }
    }

    if (!$city) {
        $loc_terms = get_the_terms($post_id, 'es_location');
        if ($loc_terms && !is_wp_error($loc_terms)) {
            foreach ($loc_terms as $term) {
                if ($term->parent !== 0) { $city = $term->name; break; }
            }
            if (!$city && isset($loc_terms[0])) $city = $loc_terms[0]->name;
        }
    }

    return [$city ?: 'Unknown', $neighborhood];
}

// Slim version for AI token efficiency — strips media, keeps searchable fields
function ai_slim_for_ai(array $p): array {
    return [
        'id'        => $p['id'],
        'title'     => $p['title'],
        'permalink' => $p['permalink'],
        'location'  => [
            'city'         => $p['location']['city'],
            'neighborhood' => $p['location']['neighborhood'],
        ],
        'details'   => $p['details'],
        'pricing'   => $p['pricing'],
        'features'  => [
            'amenities'        => array_slice($p['features']['amenities'] ?? [], 0, 5),
            'extras'           => array_slice($p['features']['extras'] ?? [], 0, 5),
            'view'             => $p['features']['view'],
            'furnished'        => $p['features']['furnished'],
            'rent_amount'      => $p['features']['rent_amount'],
            'virtual_tour'     => $p['features']['virtual_tour'],
            'digital_magazine' => $p['features']['digital_magazine'],
        ],
    ];
}

// ─── City term resolution ──────────────────────────────────────────────────────

function ai_resolve_city_term_ids(string $city_slug): array {
    // 1. Direct slug match
    $term = get_term_by('slug', $city_slug, 'es_location');
    if ($term && !is_wp_error($term)) return [$term->term_id];

    // 2. Title-case name: panama-city → Panama City
    $city_name = ucwords(str_replace('-', ' ', $city_slug));
    $term = get_term_by('name', $city_name, 'es_location');
    if ($term && !is_wp_error($term)) return [$term->term_id];

    // 3. Full-phrase LIKE search (finds "Panama City" but not "Panama (City)")
    $phrase = str_replace('-', ' ', $city_slug);
    $found  = get_terms(['taxonomy' => 'es_location', 'search' => $phrase, 'fields' => 'ids', 'hide_empty' => false, 'number' => 10]);
    if (is_array($found) && !is_wp_error($found) && !empty($found)) return array_values((array)$found);

    // 4. Word-by-word union — handles "Panama (City)", "Bugaba La Concepción", etc.
    //    Skip short filler words (la, de, el) but keep words like "city", "boca".
    $stop = ['la', 'de', 'el', 'los', 'las', 'del'];
    $ids  = [];
    foreach (explode('-', $city_slug) as $word) {
        if (strlen($word) < 3 || in_array($word, $stop, true)) continue;
        $wfound = get_terms(['taxonomy' => 'es_location', 'search' => $word, 'fields' => 'ids', 'hide_empty' => false, 'number' => 20]);
        if (is_array($wfound) && !is_wp_error($wfound)) $ids = array_merge($ids, (array)$wfound);
    }
    return array_values(array_unique($ids));
}

function ai_attribute_to_cities(string $attr): array {
    $attr = strtolower($attr);
    if (str_contains($attr, 'beach'))      return ['coronado'];
    if (str_contains($attr, 'luxury'))     return ['panama-city'];
    if (str_contains($attr, 'investment')) return ['panama-city'];
    if (str_contains($attr, 'affordable')) return ['boquete', 'bugaba-la-concepcion'];
    return [];
}

// ─── Tool implementations (live WP_Query) ────────────────────────────────────

function ai_tool_list_cities(): array {
    $query = new WP_Query([
        'post_type'              => 'properties',
        'post_status'            => 'publish',
        'posts_per_page'         => 500,
        'no_found_rows'          => true,
        'update_post_meta_cache' => true,
        'update_post_term_cache' => true,
    ]);

    $city_stats = [];
    foreach ($query->posts as $post) {
        // Prefer actual es_location term slug so the AI gets slugs that resolve exactly
        $loc_terms  = get_the_terms($post->ID, 'es_location');
        $city_name  = '';
        $city_slug  = '';
        if ($loc_terms && !is_wp_error($loc_terms)) {
            // Prefer top-level term (city, not neighborhood)
            foreach ($loc_terms as $lt) {
                if ($lt->parent === 0) { $city_name = $lt->name; $city_slug = $lt->slug; break; }
            }
            if (!$city_slug && isset($loc_terms[0])) {
                $city_name = $loc_terms[0]->name;
                $city_slug = $loc_terms[0]->slug;
            }
        }
        if (!$city_slug) {
            [$city_name] = ai_extract_location($post->ID);
            $city_slug   = sanitize_title($city_name);
        }

        $price = (float)(get_post_meta($post->ID, 'es_property_price', true) ?: 0);

        if (!isset($city_stats[$city_slug])) {
            $city_stats[$city_slug] = ['name' => $city_name, 'slug' => $city_slug, 'count' => 0, 'prices' => []];
        }
        $city_stats[$city_slug]['count']++;
        if ($price > 0) $city_stats[$city_slug]['prices'][] = $price;
    }

    $result = [];
    foreach ($city_stats as $slug => $s) {
        $p            = $s['prices'];
        $result[$slug] = [
            'name'           => $s['name'],
            'slug'           => $slug,
            'property_count' => $s['count'],
            'price_range'    => $p ? ['min' => (int)min($p), 'max' => (int)max($p)] : null,
        ];
    }

    return ['result' => ['cities' => $result], 'properties' => []];
}

// Helper: search a single taxonomy by keyword, returns tax_query clause or empty array
function ai_tax_clause(string $taxonomy, string $keyword): array {
    if (!$keyword) return [];
    $ids = get_terms(['taxonomy' => $taxonomy, 'search' => $keyword, 'fields' => 'ids', 'hide_empty' => false, 'number' => 20]);
    if (empty($ids) || is_wp_error($ids)) return [];
    return [['taxonomy' => $taxonomy, 'field' => 'term_id', 'terms' => (array)$ids]];
}

function ai_tool_search(array $args): array {
    $city         = sanitize_text_field($args['city'] ?? '');
    $ptype        = sanitize_text_field($args['property_type'] ?? '');
    $category     = sanitize_text_field($args['category'] ?? '');
    $min_price    = (float)($args['min_price'] ?? 0);
    $max_price    = (float)($args['max_price'] ?? 0);
    $min_area     = (float)($args['min_area'] ?? 0);
    $max_area     = (float)($args['max_area'] ?? 0);
    $bedrooms     = isset($args['bedrooms']) ? (int)$args['bedrooms'] : null;
    $neighborhood = sanitize_text_field($args['neighborhood'] ?? '');
    $amenity      = sanitize_text_field($args['amenity'] ?? '');
    $feature      = sanitize_text_field($args['feature'] ?? '');
    $parking      = sanitize_text_field($args['parking'] ?? '');
    $label        = sanitize_text_field($args['label'] ?? '');
    $rent_period  = sanitize_text_field($args['rent_period'] ?? '');
    $attribute    = sanitize_text_field($args['attribute'] ?? '');
    $status       = sanitize_text_field($args['status'] ?? '');
    $sort                 = sanitize_text_field($args['sort'] ?? '');
    $limit                = min((int)($args['limit'] ?? 6), 50);
    $has_virtual_tour     = !empty($args['has_virtual_tour']);
    $has_digital_magazine = !empty($args['has_digital_magazine']);

    $meta_query = ['relation' => 'AND'];
    $tax_query  = ['relation' => 'AND'];

    // Price filter — check both sale price and rent-amount fields so rental filters work
    $is_rent_search = $status && str_contains(strtolower($status), 'rent');
    if ($min_price > 0 || $max_price > 0) {
        $price_key = $is_rent_search ? 'es_property_rent-amount' : 'es_property_price';
        if ($min_price > 0 && $max_price > 0) {
            $meta_query[] = ['key' => $price_key, 'value' => [$min_price, $max_price], 'type' => 'NUMERIC', 'compare' => 'BETWEEN'];
        } elseif ($min_price > 0) {
            $meta_query[] = ['key' => $price_key, 'value' => $min_price, 'type' => 'NUMERIC', 'compare' => '>='];
        } elseif ($max_price > 0) {
            $meta_query[] = ['key' => $price_key, 'value' => $max_price, 'type' => 'NUMERIC', 'compare' => '<='];
        }
    }

    // Area filter
    if ($min_area > 0 && $max_area > 0) {
        $meta_query[] = ['key' => 'es_property_area', 'value' => [$min_area, $max_area], 'type' => 'NUMERIC', 'compare' => 'BETWEEN'];
    } elseif ($min_area > 0) {
        $meta_query[] = ['key' => 'es_property_area', 'value' => $min_area, 'type' => 'NUMERIC', 'compare' => '>='];
    } elseif ($max_area > 0) {
        $meta_query[] = ['key' => 'es_property_area', 'value' => $max_area, 'type' => 'NUMERIC', 'compare' => '<='];
    }

    // Bedrooms filter
    if ($bedrooms !== null) {
        $meta_query[] = ['key' => 'es_property_bedrooms', 'value' => $bedrooms, 'type' => 'NUMERIC', 'compare' => '='];
    }

    // Status filter (for_rent / for_sale)
    // Try multiple keywords to catch "For Rent", "Rental", "For Sale", "Sale" etc.
    if ($status) {
        $is_rent   = str_contains(strtolower($status), 'rent');
        $keywords  = $is_rent ? ['rent', 'rental', 'lease'] : ['sale', 'purchase', 'sold'];
        $status_ids = [];
        foreach ($keywords as $kw) {
            $ids = get_terms(['taxonomy' => 'es_status', 'search' => $kw, 'fields' => 'ids', 'hide_empty' => false, 'number' => 20]);
            if (is_array($ids) && !is_wp_error($ids)) $status_ids = array_merge($status_ids, $ids);
        }
        $status_ids = array_unique($status_ids);
        if (!empty($status_ids)) {
            $tax_query[] = ['taxonomy' => 'es_status', 'field' => 'term_id', 'terms' => $status_ids, 'operator' => 'IN'];
        }
    }

    // Single-taxonomy filters
    foreach ([
        'es_type'         => $ptype,
        'es_category'     => $category,
        'es_neighborhood' => $neighborhood,
        'es_parking'      => $parking,
        'es_label'        => $label,
        'es_rent_period'  => $rent_period,
        'es_feature'      => $feature,
    ] as $tax => $kw) {
        $tax_query = array_merge($tax_query, ai_tax_clause($tax, $kw));
    }

    // Amenity: search both es_amenity AND es_feature (user may not know which)
    if ($amenity) {
        $a_ids = get_terms(['taxonomy' => 'es_amenity', 'search' => $amenity, 'fields' => 'ids', 'hide_empty' => false, 'number' => 20]);
        $f_ids = get_terms(['taxonomy' => 'es_feature',  'search' => $amenity, 'fields' => 'ids', 'hide_empty' => false, 'number' => 20]);
        $a_ids = (is_array($a_ids) && !is_wp_error($a_ids)) ? (array)$a_ids : [];
        $f_ids = (is_array($f_ids) && !is_wp_error($f_ids)) ? (array)$f_ids : [];
        if (!empty($a_ids) && !empty($f_ids)) {
            $tax_query[] = ['relation' => 'OR',
                ['taxonomy' => 'es_amenity', 'field' => 'term_id', 'terms' => $a_ids],
                ['taxonomy' => 'es_feature',  'field' => 'term_id', 'terms' => $f_ids],
            ];
        } elseif (!empty($a_ids)) {
            $tax_query[] = ['taxonomy' => 'es_amenity', 'field' => 'term_id', 'terms' => $a_ids];
        } elseif (!empty($f_ids)) {
            $tax_query[] = ['taxonomy' => 'es_feature', 'field' => 'term_id', 'terms' => $f_ids];
        }
    }

    // Virtual tour / digital magazine filters
    if ($has_virtual_tour) {
        $meta_query[] = ['key' => 'es_property_virtual-tour', 'value' => '', 'compare' => '!='];
    }
    if ($has_digital_magazine) {
        $meta_query[] = ['key' => 'es_property_digital-magazine', 'value' => '', 'compare' => '!='];
    }

    // City / attribute filter
    $city_term_ids = [];
    if ($city) {
        $city_term_ids = ai_resolve_city_term_ids($city);
    } elseif ($attribute) {
        foreach (ai_attribute_to_cities($attribute) as $slug) {
            $city_term_ids = array_merge($city_term_ids, ai_resolve_city_term_ids($slug));
        }
    }
    if (!empty($city_term_ids)) {
        $tax_query[] = ['taxonomy' => 'es_location', 'field' => 'term_id', 'terms' => $city_term_ids];
    }

    // When sorting by price, fetch a larger pool so PHP sort finds the true cheapest/most expensive
    $fetch_limit = ($sort === 'price_asc' || $sort === 'price_desc') ? min($limit * 8, 200) : $limit;

    $query_args = [
        'post_type'              => 'properties',
        'post_status'            => 'publish',
        'posts_per_page'         => $fetch_limit,
        'no_found_rows'          => true,
        'update_post_meta_cache' => true,
        'update_post_term_cache' => true,
    ];

    if (count($meta_query) > 1) $query_args['meta_query'] = $meta_query;
    if (count($tax_query)  > 1) $query_args['tax_query']  = $tax_query;

    $query      = new WP_Query($query_args);
    $properties = array_values(array_filter(array_map(fn($p) => ai_extract_property($p->ID), $query->posts)));

    // PHP-level price sort — includes zero-price listings (put last so priced ones appear first)
    if ($sort === 'price_asc' || $sort === 'price_desc') {
        usort($properties, function ($a, $b) use ($sort) {
            $pa = $a['pricing']['price'] ?: $a['features']['rent_amount'];
            $pb = $b['pricing']['price'] ?: $b['features']['rent_amount'];
            if (!$pa && !$pb) return 0;
            if (!$pa) return 1;   // no price → goes after priced ones
            if (!$pb) return -1;
            return $sort === 'price_asc' ? ($pa <=> $pb) : ($pb <=> $pa);
        });
        $properties = array_slice($properties, 0, $limit);
    }

    return [
        'result'     => ['total' => count($properties), 'properties' => array_map('ai_slim_for_ai', $properties)],
        'properties' => $properties,
    ];
}

function ai_tool_city_details(string $city_slug): array {
    if (!$city_slug) {
        return ['result' => ['error' => 'city_slug required'], 'properties' => []];
    }

    $term_ids   = ai_resolve_city_term_ids($city_slug);
    $query_args = [
        'post_type'              => 'properties',
        'post_status'            => 'publish',
        'posts_per_page'         => 100,
        'no_found_rows'          => true,
        'update_post_meta_cache' => true,
        'update_post_term_cache' => true,
    ];
    if (!empty($term_ids)) {
        $query_args['tax_query'] = [['taxonomy' => 'es_location', 'field' => 'term_id', 'terms' => $term_ids]];
    }

    $query     = new WP_Query($query_args);
    $all_props = array_values(array_filter(array_map(fn($p) => ai_extract_property($p->ID), $query->posts)));

    if (empty($all_props)) {
        return ['result' => ['error' => "No properties found for '$city_slug'"], 'properties' => []];
    }

    $prices        = array_filter(array_map(fn($p) => $p['pricing']['price'], $all_props));
    $type_count    = [];
    $neighborhoods = [];
    foreach ($all_props as $p) {
        $t             = $p['details']['type'] ?: 'unknown';
        $type_count[$t] = ($type_count[$t] ?? 0) + 1;
        $n             = $p['location']['neighborhood'];
        if ($n && !in_array($n, $neighborhoods, true)) $neighborhoods[] = $n;
    }

    $samples = array_slice($all_props, 0, 5);

    return [
        'result' => [
            'city_info'         => [
                'name'          => ucwords(str_replace('-', ' ', $city_slug)),
                'neighborhoods' => array_slice($neighborhoods, 0, 10),
            ],
            'statistics'        => [
                'total'       => count($all_props),
                'price_range' => $prices ? [
                    'min' => (int)min($prices),
                    'max' => (int)max($prices),
                    'avg' => (int)(array_sum($prices) / count($prices)),
                ] : null,
                'by_type' => $type_count,
            ],
            'sample_properties' => array_map('ai_slim_for_ai', $samples),
        ],
        'properties' => $samples,
    ];
}

function ai_tool_property_details(string $property_id): array {
    $post_id = (int)preg_replace('/\D/', '', $property_id);
    if (!$post_id) {
        return ['result' => ['error' => "Invalid property ID: $property_id"], 'properties' => []];
    }

    $prop = ai_extract_property($post_id);
    if (empty($prop)) {
        return ['result' => ['error' => "Property $property_id not found"], 'properties' => []];
    }

    return ['result' => $prop, 'properties' => [$prop]];
}

function ai_execute_tool(string $name, array $args): array {
    return match ($name) {
        'list_available_cities' => ai_tool_list_cities(),
        'search_properties'     => ai_tool_search($args),
        'get_city_details'      => ai_tool_city_details($args['city_slug'] ?? ''),
        'get_property_details'  => ai_tool_property_details($args['property_id'] ?? ''),
        default                 => ['result' => ['error' => "Unknown tool: $name"], 'properties' => []],
    };
}

// ─── Proxy handlers ──────────────────────────────────────────────────────────

function ai_proxy_handler(WP_REST_Request $request): WP_REST_Response {
    set_time_limit(300);

    if (!ai_proxy_rate_limit()) {
        return new WP_REST_Response(['type' => 'error', 'message' => 'Too many requests. Please wait a minute and try again.'], 429);
    }

    $api_key = get_option('ai_anthropic_api_key', '');

    if (empty($api_key)) {
        return new WP_REST_Response(['type' => 'error', 'message' => 'AI not configured. Go to AI Property Assistant in the WordPress admin sidebar to enter your API key.'], 503);
    }

    $body    = $request->get_json_params() ?? [];
    $text    = sanitize_textarea_field($body['text'] ?? '');
    $history = is_array($body['history'] ?? null) ? $body['history'] : [];
    $text = ai_normalize_voice_transcript($text);

    return ai_proxy_claude($api_key, $text, $history);
}

function ai_merge_search_args(array $calls): ?array {
    if (empty($calls)) return null;
    $args = end($calls); // base on last call
    if (count($calls) > 1) {
        // If searches used different statuses (or some omitted it), don't filter by status in the URL
        $statuses = array_unique(array_map(fn($a) => strtolower($a['status'] ?? ''), $calls));
        if (count($statuses) > 1) unset($args['status']);
    }
    return $args;
}

function ai_proxy_claude(string $api_key, string $text, array $history): WP_REST_Response {
    $messages      = $history;
    $messages[]    = ['role' => 'user', 'content' => $text];
    $all_properties = [];
    $search_calls  = [];

    for ($i = 0; $i < 5; $i++) {
        $http = wp_remote_post('https://api.anthropic.com/v1/messages', [
            'headers' => [
                'Content-Type'      => 'application/json',
                'x-api-key'         => $api_key,
                'anthropic-version' => '2023-06-01',
            ],
            'body'    => wp_json_encode([
                'model'      => get_option('ai_claude_model', 'claude-sonnet-4-6'),
                'max_tokens' => 4096,
                'system'     => [['type' => 'text', 'text' => ai_system_prompt()]],
                'tools'      => ai_claude_tools(),
                'messages'   => $messages,
            ]),
            'timeout' => 90,
        ]);

        if (is_wp_error($http)) {
            return new WP_REST_Response(['type' => 'error', 'message' => $http->get_error_message()], 502);
        }

        $status = (int)wp_remote_retrieve_response_code($http);
        $data   = json_decode(wp_remote_retrieve_body($http), true);

        if ($status !== 200) {
            $msg = $data['error']['message'] ?? "Claude API error (HTTP $status)";
            return new WP_REST_Response(['type' => 'error', 'message' => $msg], $status === 429 ? 429 : 502);
        }

        $messages[] = ['role' => 'assistant', 'content' => $data['content']];

        if (($data['stop_reason'] ?? '') !== 'tool_use') {
            $text_block = current(array_filter($data['content'], fn($b) => $b['type'] === 'text'));
            $merged     = ai_merge_search_args($search_calls);
            return new WP_REST_Response([
                'type'       => 'text',
                'text'       => $text_block['text'] ?? '',
                'history'    => $messages,
                'properties' => ai_dedupe_properties($all_properties),
                'search_url' => $merged ? ai_build_search_url($merged) : null,
            ]);
        }

        // Execute tool calls server-side
        $tool_results    = [];
        $batch_properties = [];
        foreach ($data['content'] as $block) {
            if ($block['type'] !== 'tool_use') continue;
            $block_args = (array)($block['input'] ?? []);
            if ($block['name'] === 'search_properties') $search_calls[] = $block_args;
            $tool_output       = ai_execute_tool($block['name'], $block_args);
            $batch_properties  = array_merge($batch_properties, $tool_output['properties']);
            $tool_results[]    = [
                'type'        => 'tool_result',
                'tool_use_id' => $block['id'],
                'content'     => wp_json_encode($tool_output['result']),
            ];
        }
        // Overwrite (not accumulate) so cards always reflect the latest tool call batch
        $all_properties = $batch_properties;
        $messages[] = ['role' => 'user', 'content' => $tool_results];
    }

    return new WP_REST_Response(['type' => 'error', 'message' => 'Max tool iterations reached.'], 500);
}

function ai_resolve_term_name(string $key, string $taxonomy): string {
    $ids = ai_resolve_city_term_ids($key);
    if (!empty($ids)) {
        $term = get_term($ids[0], $taxonomy);
        if ($term && !is_wp_error($term)) return $term->name;
    }
    return $key;
}

function ai_build_search_url(array $args): string {
    $params = ['es' => '1'];

    $city_key = $args['city'] ?? ($args['neighborhood'] ?? '');
    if ($city_key) {
        // Use the real stored term name so Estatick's address filter matches exactly
        $params['address'] = ai_resolve_term_name($city_key, 'es_location');
    }

    if (!empty($args['min_price']))     $params['min_price']     = (string)(int)$args['min_price'];
    if (!empty($args['max_price']))     $params['max_price']     = (string)(int)$args['max_price'];
    if (!empty($args['min_area']))      $params['min_area']      = (string)(int)$args['min_area'];
    if (!empty($args['max_area']))      $params['max_area']      = (string)(int)$args['max_area'];
    if (!empty($args['property_type'])) {
        $type_ids = get_terms(['taxonomy' => 'es_type', 'search' => $args['property_type'], 'fields' => 'ids', 'hide_empty' => false, 'number' => 1]);
        if (!empty($type_ids) && !is_wp_error($type_ids)) {
            $params['es_type'] = (int)$type_ids[0];
        }
    }
    if (!empty($args['bedrooms']))      $params['from_bedrooms'] = (string)(int)$args['bedrooms'];
    if (!empty($args['status'])) {
        $is_rent  = str_contains(strtolower($args['status']), 'rent');
        $keywords = $is_rent ? ['rent', 'rental', 'lease'] : ['sale', 'purchase', 'sold'];
        foreach ($keywords as $kw) {
            $found = get_terms(['taxonomy' => 'es_status', 'search' => $kw, 'fields' => 'ids', 'hide_empty' => false, 'number' => 1]);
            if (!empty($found) && !is_wp_error($found)) {
                $params['es_status'] = (int) $found[0];
                break;
            }
        }
    }

    return home_url('/search-results-2/') . '?' . http_build_query($params);
}

function ai_dedupe_properties(array $props): array {
    $seen   = [];
    $unique = [];
    foreach ($props as $p) {
        $id = $p['id'] ?? '';
        if ($id && !isset($seen[$id])) {
            $seen[$id] = true;
            $unique[]  = $p;
        }
    }
    return $unique;
}
