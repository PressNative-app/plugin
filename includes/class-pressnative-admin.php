<?php
/**
 * PressNative WP Admin: Registry URL and settings.
 *
 * @package PressNative
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PressNative_Admin
 */
class PressNative_Admin {

	const OPTION_REGISTRY_URL    = 'pressnative_registry_url';
	const DEFAULT_REGISTRY_URL   = 'http://localhost:3000';
	const OPTION_SCHEMA_VERIFIED = 'pressnative_schema_verified';

	/**
	 * Hooks into admin.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_app_settings_assets' ) );
	}

	/**
	 * Adds PressNative top-level menu and settings pages.
	 *
	 * @return void
	 */
	public static function add_menu() {
		add_menu_page(
			__( 'PressNative', 'pressnative' ),
			__( 'PressNative', 'pressnative' ),
			'manage_options',
			'pressnative',
			array( __CLASS__, 'render_settings_page' ),
			'dashicons-smartphone',
			30
		);
		add_submenu_page(
			'pressnative',
			__( 'App Settings', 'pressnative' ),
			__( 'App Settings', 'pressnative' ),
			'manage_options',
			'pressnative-app-settings',
			array( __CLASS__, 'render_app_settings_page' )
		);
		add_submenu_page(
			'pressnative',
			__( 'Layout Settings', 'pressnative' ),
			__( 'Layout Settings', 'pressnative' ),
			'manage_options',
			'pressnative-layout-settings',
			array( __CLASS__, 'render_layout_settings_page' )
		);
	}

	/**
	 * Registers Registry URL setting.
	 *
	 * @return void
	 */
	public static function register_settings() {
		register_setting(
			'pressnative_settings',
			self::OPTION_REGISTRY_URL,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_registry_url' ),
				'default'           => self::DEFAULT_REGISTRY_URL,
			)
		);

		// App Settings (branding)
		register_setting(
			'pressnative_app_settings',
			PressNative_Themes::OPTION_THEME_ID,
			array(
				'type'              => 'string',
				'sanitize_callback' => function ( $v ) {
					$themes = PressNative_Themes::get_themes();
					return isset( $themes[ $v ] ) ? $v : PressNative_Themes::THEME_CUSTOM;
				},
				'default'           => 'editorial',
			)
		);
		register_setting(
			'pressnative_app_settings',
			PressNative_Options::OPTION_APP_NAME,
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => PressNative_Options::DEFAULT_APP_NAME,
			)
		);
		register_setting(
			'pressnative_app_settings',
			PressNative_Options::OPTION_PRIMARY_COLOR,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( 'PressNative_Options', 'sanitize_hex' ),
				'default'           => PressNative_Options::DEFAULT_PRIMARY_COLOR,
			)
		);
		register_setting(
			'pressnative_app_settings',
			PressNative_Options::OPTION_ACCENT_COLOR,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( 'PressNative_Options', 'sanitize_hex' ),
				'default'           => PressNative_Options::DEFAULT_ACCENT_COLOR,
			)
		);
		register_setting(
			'pressnative_app_settings',
			PressNative_Options::OPTION_LOGO_ATTACHMENT,
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 0,
			)
		);
		register_setting(
			'pressnative_app_settings',
			PressNative_Options::OPTION_BACKGROUND_COLOR,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( 'PressNative_Options', 'sanitize_hex' ),
				'default'           => PressNative_Options::DEFAULT_BACKGROUND_COLOR,
			)
		);
		register_setting(
			'pressnative_app_settings',
			PressNative_Options::OPTION_TEXT_COLOR,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( 'PressNative_Options', 'sanitize_hex' ),
				'default'           => PressNative_Options::DEFAULT_TEXT_COLOR,
			)
		);
		register_setting(
			'pressnative_app_settings',
			PressNative_Options::OPTION_FONT_FAMILY,
			array(
				'type'              => 'string',
				'sanitize_callback' => function ( $v ) {
					$allowed = array( 'sans-serif', 'serif', 'monospace' );
					return in_array( $v, $allowed, true ) ? $v : PressNative_Options::DEFAULT_FONT_FAMILY;
				},
				'default'           => PressNative_Options::DEFAULT_FONT_FAMILY,
			)
		);
		register_setting(
			'pressnative_app_settings',
			PressNative_Options::OPTION_BASE_FONT_SIZE,
			array(
				'type'              => 'integer',
				'sanitize_callback' => function ( $v ) {
					$v = absint( $v );
					return max( 12, min( 24, $v ) );
				},
				'default'           => PressNative_Options::DEFAULT_BASE_FONT_SIZE,
			)
		);

		// Layout Settings
		register_setting(
			'pressnative_layout_settings',
			PressNative_Layout_Options::OPTION_HERO_CATEGORY_SLUG,
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => PressNative_Layout_Options::DEFAULT_HERO_CATEGORY_SLUG,
			)
		);
		register_setting(
			'pressnative_layout_settings',
			PressNative_Layout_Options::OPTION_HERO_MAX_ITEMS,
			array(
				'type'              => 'integer',
				'sanitize_callback' => function ( $v ) {
					$v = absint( $v );
					return max( 1, min( 10, $v ) );
				},
				'default'           => PressNative_Layout_Options::DEFAULT_HERO_MAX_ITEMS,
			)
		);
		register_setting(
			'pressnative_layout_settings',
			PressNative_Layout_Options::OPTION_POST_GRID_COLUMNS,
			array(
				'type'              => 'integer',
				'sanitize_callback' => function ( $v ) {
					$v = absint( $v );
					return max( 1, min( 4, $v ) );
				},
				'default'           => PressNative_Layout_Options::DEFAULT_POST_GRID_COLUMNS,
			)
		);
		register_setting(
			'pressnative_layout_settings',
			PressNative_Layout_Options::OPTION_POST_GRID_PER_PAGE,
			array(
				'type'              => 'integer',
				'sanitize_callback' => function ( $v ) {
					$v = absint( $v );
					return max( 1, min( 50, $v ) );
				},
				'default'           => PressNative_Layout_Options::DEFAULT_POST_GRID_PER_PAGE,
			)
		);
		register_setting(
			'pressnative_layout_settings',
			PressNative_Layout_Options::OPTION_ENABLED_CATEGORIES,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_category_ids' ),
				'default'           => array(),
			)
		);
		register_setting(
			'pressnative_layout_settings',
			PressNative_Layout_Options::OPTION_ENABLED_COMPONENTS,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_component_ids' ),
				'default'           => PressNative_Layout_Options::COMPONENT_IDS,
			)
		);
	}

	/**
	 * Sanitizes category IDs array.
	 *
	 * @param mixed $value Raw value.
	 * @return int[]
	 */
	public static function sanitize_category_ids( $value ) {
		if ( empty( $value ) || ! is_array( $value ) ) {
			return array();
		}
		$ids = array_map( 'absint', $value );
		$ids = array_filter( $ids );
		return array_values( array_unique( $ids ) );
	}

	/**
	 * Sanitizes component IDs array.
	 *
	 * @param mixed $value Raw value.
	 * @return string[]
	 */
	public static function sanitize_component_ids( $value ) {
		if ( empty( $value ) || ! is_array( $value ) ) {
			return PressNative_Layout_Options::COMPONENT_IDS;
		}
		$valid = array_intersect( array_map( 'sanitize_text_field', $value ), PressNative_Layout_Options::COMPONENT_IDS );
		return empty( $valid ) ? PressNative_Layout_Options::COMPONENT_IDS : array_values( $valid );
	}

	/**
	 * Sanitizes Registry URL (ensure no trailing slash, valid URL).
	 *
	 * @param string $value Raw input.
	 * @return string
	 */
	public static function sanitize_registry_url( $value ) {
		$value = trim( $value );
		if ( '' === $value ) {
			return self::DEFAULT_REGISTRY_URL;
		}
		$value = rtrim( $value, '/' );
		if ( ! preg_match( '#^https?://#', $value ) ) {
			return 'http://' . $value;
		}
		return $value;
	}

	/**
	 * Renders the PressNative settings page.
	 *
	 * @return void
	 */
	public static function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$registry_url = get_option( self::OPTION_REGISTRY_URL, self::DEFAULT_REGISTRY_URL );
		$verified     = get_option( self::OPTION_SCHEMA_VERIFIED, '' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'PressNative', 'pressnative' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'pressnative_settings' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="pressnative_registry_url"><?php esc_html_e( 'Registry URL', 'pressnative' ); ?></label>
						</th>
						<td>
							<input type="url"
								   id="pressnative_registry_url"
								   name="<?php echo esc_attr( self::OPTION_REGISTRY_URL ); ?>"
								   value="<?php echo esc_attr( $registry_url ); ?>"
								   class="regular-text"
								   placeholder="<?php echo esc_attr( self::DEFAULT_REGISTRY_URL ); ?>"/>
							<p class="description">
								<?php esc_html_e( 'Base URL of the PressNative Registry (Core) service. Used to verify schema on activation.', 'pressnative' ); ?>
								<?php if ( $verified ) : ?>
									<br><strong><?php esc_html_e( 'Last verified:', 'pressnative' ); ?></strong> <?php echo esc_html( $verified ); ?>
								<?php endif; ?>
							</p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Enqueues scripts and styles for App Settings page (color picker, media library).
	 *
	 * @param string $hook_suffix Current admin page hook.
	 * @return void
	 */
	public static function enqueue_app_settings_assets( $hook_suffix ) {
		$app_settings = ( $hook_suffix === 'pressnative_page_pressnative-app-settings' );
		$layout_settings = ( $hook_suffix === 'pressnative_page_pressnative-layout-settings' );
		if ( ! $app_settings && ! $layout_settings ) {
			return;
		}
		if ( $app_settings ) {
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );
		wp_enqueue_media();
		wp_add_inline_script(
			'wp-color-picker',
			'jQuery(function($){$(".pressnative-color-picker").wpColorPicker();});'
		);
		wp_add_inline_script(
			'wp-color-picker',
			self::get_logo_upload_script() . "\n" . self::get_theme_card_script(),
			'after'
		);
		}
	}

	/**
	 * Inline script for logo upload (WP Media Library).
	 *
	 * @return string
	 */
	private static function get_logo_upload_script() {
		return "
		jQuery(function($){
			var frame;
			$('#pressnative_logo_select').on('click', function(e){
				e.preventDefault();
				if (frame) { frame.open(); return; }
				frame = wp.media({
					title: 'Select or upload logo',
					library: { type: 'image' },
					button: { text: 'Use this image' },
					multiple: false
				});
				frame.on('select', function(){
					var att = frame.state().get('selection').first().toJSON();
					$('#pressnative_logo_attachment_id').val(att.id);
					$('#pressnative_logo_preview').html('<img src=\"'+att.url+'\" style=\"max-height:60px;\" />');
				});
				frame.open();
			});
			$('#pressnative_logo_remove').on('click', function(e){
				e.preventDefault();
				$('#pressnative_logo_attachment_id').val('');
				$('#pressnative_logo_preview').html('');
			});
		});
		";
	}

	/**
	 * Inline script for theme card clicks (ensures radios work when label/input association fails).
	 *
	 * @return string
	 */
	private static function get_theme_card_script() {
		return "
		jQuery(function($){
			$('.pressnative-theme-card').on('click', function(e){
				var radio = $(this).find('input[type=radio]');
				if (radio.length) {
					radio.prop('checked', true);
					$('.pressnative-theme-card').css('border', '2px solid #ddd');
					$(this).css('border', '2px solid #2271b1');
				}
			});
		});
		";
	}

	/**
	 * Renders the App Settings page (branding: app name, colors, logo).
	 *
	 * @return void
	 */
	public static function render_app_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$app_name        = get_option( PressNative_Options::OPTION_APP_NAME, PressNative_Options::DEFAULT_APP_NAME );
		$primary_color   = get_option( PressNative_Options::OPTION_PRIMARY_COLOR, PressNative_Options::DEFAULT_PRIMARY_COLOR );
		$accent_color    = get_option( PressNative_Options::OPTION_ACCENT_COLOR, PressNative_Options::DEFAULT_ACCENT_COLOR );
		$logo_attachment = (int) get_option( PressNative_Options::OPTION_LOGO_ATTACHMENT, 0 );
		$logo_preview    = '';
		if ( $logo_attachment > 0 ) {
			$url = wp_get_attachment_image_url( $logo_attachment, 'thumbnail' );
			if ( $url ) {
				$logo_preview = '<img src="' . esc_url( $url ) . '" style="max-height:60px;" />';
			}
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'App Settings', 'pressnative' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Branding shown in the PressNative mobile app (toolbar title, logo, theme colors).', 'pressnative' ); ?></p>
			<form method="post" action="options.php">
				<?php settings_fields( 'pressnative_app_settings' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="pressnative_theme_id"><?php esc_html_e( 'Theme', 'pressnative' ); ?></label>
						</th>
						<td>
							<?php
							$current_theme = PressNative_Themes::get_selected_theme_id();
							$themes       = PressNative_Themes::get_themes();
							?>
							<div class="pressnative-theme-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:10px;margin-bottom:12px;">
								<?php foreach ( $themes as $id => $t ) :
									$active = $current_theme === $id ? '2px solid #2271b1' : '2px solid #ddd';
									if ( $id === PressNative_Themes::THEME_CUSTOM ) {
										$bg = '#f6f7f7';
										$accent = '#999';
									} else {
										$bg = isset( $t['theme']['background_color'] ) ? esc_attr( $t['theme']['background_color'] ) : '#fff';
										$accent = isset( $t['theme']['accent_color'] ) ? esc_attr( $t['theme']['accent_color'] ) : '#34c759';
									}
									?>
									<label class="pressnative-theme-card" style="cursor:pointer;border:<?php echo $active; ?>;border-radius:8px;overflow:hidden;transition:border-color .15s;position:relative;display:block;">
										<input type="radio" name="<?php echo esc_attr( PressNative_Themes::OPTION_THEME_ID ); ?>" value="<?php echo esc_attr( $id ); ?>" <?php checked( $current_theme, $id ); ?> style="position:absolute;top:0;left:0;right:0;bottom:0;width:100%;height:100%;margin:0;opacity:0;cursor:pointer;">
										<div style="background:<?php echo $bg; ?>;padding:10px;min-height:70px;pointer-events:none;">
											<div style="width:20px;height:20px;border-radius:4px;background:<?php echo $accent; ?>;margin-bottom:6px;"></div>
											<span style="font-size:12px;font-weight:600;color:inherit;"><?php echo esc_html( $t['name'] ); ?></span>
										</div>
									</label>
								<?php endforeach; ?>
							</div>
							<p class="description">
								<?php
								$desc = isset( $themes[ $current_theme ]['description'] ) ? $themes[ $current_theme ]['description'] : '';
								echo $desc ? esc_html( $desc ) : esc_html__( 'Choose a preset theme or Custom to use your own colors below.', 'pressnative' );
								?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="pressnative_app_name"><?php esc_html_e( 'App Name', 'pressnative' ); ?></label>
						</th>
						<td>
							<input type="text"
								   id="pressnative_app_name"
								   name="<?php echo esc_attr( PressNative_Options::OPTION_APP_NAME ); ?>"
								   value="<?php echo esc_attr( $app_name ); ?>"
								   class="regular-text"
								   placeholder="<?php echo esc_attr( PressNative_Options::DEFAULT_APP_NAME ); ?>"/>
							<p class="description"><?php esc_html_e( 'Title shown in the app toolbar.', 'pressnative' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="pressnative_primary_color"><?php esc_html_e( 'Primary Color', 'pressnative' ); ?></label>
						</th>
						<td>
							<input type="text"
								   id="pressnative_primary_color"
								   name="<?php echo esc_attr( PressNative_Options::OPTION_PRIMARY_COLOR ); ?>"
								   value="<?php echo esc_attr( $primary_color ); ?>"
								   class="pressnative-color-picker"/>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="pressnative_accent_color"><?php esc_html_e( 'Accent Color', 'pressnative' ); ?></label>
						</th>
						<td>
							<input type="text"
								   id="pressnative_accent_color"
								   name="<?php echo esc_attr( PressNative_Options::OPTION_ACCENT_COLOR ); ?>"
								   value="<?php echo esc_attr( $accent_color ); ?>"
								   class="pressnative-color-picker"/>
							<p class="description"><?php esc_html_e( 'Buttons, links, highlights.', 'pressnative' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="pressnative_background_color"><?php esc_html_e( 'Background Color', 'pressnative' ); ?></label>
						</th>
						<td>
							<?php
							$bg_color = get_option( PressNative_Options::OPTION_BACKGROUND_COLOR, PressNative_Options::DEFAULT_BACKGROUND_COLOR );
							?>
							<input type="text"
								   id="pressnative_background_color"
								   name="<?php echo esc_attr( PressNative_Options::OPTION_BACKGROUND_COLOR ); ?>"
								   value="<?php echo esc_attr( $bg_color ); ?>"
								   class="pressnative-color-picker"/>
							<p class="description"><?php esc_html_e( 'Main app background.', 'pressnative' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="pressnative_text_color"><?php esc_html_e( 'Text Color', 'pressnative' ); ?></label>
						</th>
						<td>
							<?php
							$text_color = get_option( PressNative_Options::OPTION_TEXT_COLOR, PressNative_Options::DEFAULT_TEXT_COLOR );
							?>
							<input type="text"
								   id="pressnative_text_color"
								   name="<?php echo esc_attr( PressNative_Options::OPTION_TEXT_COLOR ); ?>"
								   value="<?php echo esc_attr( $text_color ); ?>"
								   class="pressnative-color-picker"/>
							<p class="description"><?php esc_html_e( 'Primary text color.', 'pressnative' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="pressnative_font_family"><?php esc_html_e( 'Font Family', 'pressnative' ); ?></label>
						</th>
						<td>
							<?php
							$font_family = get_option( PressNative_Options::OPTION_FONT_FAMILY, PressNative_Options::DEFAULT_FONT_FAMILY );
							?>
							<select id="pressnative_font_family"
									name="<?php echo esc_attr( PressNative_Options::OPTION_FONT_FAMILY ); ?>">
								<option value="sans-serif" <?php selected( $font_family, 'sans-serif' ); ?>><?php esc_html_e( 'Sans-serif (default)', 'pressnative' ); ?></option>
								<option value="serif" <?php selected( $font_family, 'serif' ); ?>><?php esc_html_e( 'Serif', 'pressnative' ); ?></option>
								<option value="monospace" <?php selected( $font_family, 'monospace' ); ?>><?php esc_html_e( 'Monospace', 'pressnative' ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'Base font for app content.', 'pressnative' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="pressnative_base_font_size"><?php esc_html_e( 'Base Font Size', 'pressnative' ); ?></label>
						</th>
						<td>
							<?php
							$base_font_size = (int) get_option( PressNative_Options::OPTION_BASE_FONT_SIZE, PressNative_Options::DEFAULT_BASE_FONT_SIZE );
							?>
							<input type="number"
								   id="pressnative_base_font_size"
								   name="<?php echo esc_attr( PressNative_Options::OPTION_BASE_FONT_SIZE ); ?>"
								   value="<?php echo esc_attr( $base_font_size ); ?>"
								   min="12" max="24" step="1" class="small-text"/>
							<span><?php esc_html_e( 'px (12–24)', 'pressnative' ); ?></span>
							<p class="description"><?php esc_html_e( 'Base font size for app typography.', 'pressnative' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label><?php esc_html_e( 'Logo', 'pressnative' ); ?></label>
						</th>
						<td>
							<input type="hidden" id="pressnative_logo_attachment_id" name="<?php echo esc_attr( PressNative_Options::OPTION_LOGO_ATTACHMENT ); ?>" value="<?php echo esc_attr( $logo_attachment ); ?>"/>
							<div id="pressnative_logo_preview"><?php echo wp_kses_post( $logo_preview ); ?></div>
							<button type="button" class="button" id="pressnative_logo_select"><?php esc_html_e( 'Select or upload logo', 'pressnative' ); ?></button>
							<button type="button" class="button" id="pressnative_logo_remove"><?php esc_html_e( 'Remove', 'pressnative' ); ?></button>
							<p class="description"><?php esc_html_e( 'Header logo for the app. Used when provided in the API.', 'pressnative' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Renders the Layout Settings page (hero, post grid, categories, components).
	 *
	 * @return void
	 */
	public static function render_layout_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$hero_slug    = get_option( PressNative_Layout_Options::OPTION_HERO_CATEGORY_SLUG, PressNative_Layout_Options::DEFAULT_HERO_CATEGORY_SLUG );
		$hero_max     = (int) get_option( PressNative_Layout_Options::OPTION_HERO_MAX_ITEMS, PressNative_Layout_Options::DEFAULT_HERO_MAX_ITEMS );
		$grid_cols    = (int) get_option( PressNative_Layout_Options::OPTION_POST_GRID_COLUMNS, PressNative_Layout_Options::DEFAULT_POST_GRID_COLUMNS );
		$grid_per     = (int) get_option( PressNative_Layout_Options::OPTION_POST_GRID_PER_PAGE, PressNative_Layout_Options::DEFAULT_POST_GRID_PER_PAGE );
		$enabled_cats = PressNative_Layout_Options::get_enabled_category_ids();
		$enabled_comp = get_option( PressNative_Layout_Options::OPTION_ENABLED_COMPONENTS, PressNative_Layout_Options::COMPONENT_IDS );
		if ( ! is_array( $enabled_comp ) ) {
			$enabled_comp = PressNative_Layout_Options::COMPONENT_IDS;
		}

		$categories = get_categories( array( 'hide_empty' => false, 'parent' => 0 ) );
		$component_labels = array(
			'hero-carousel'  => __( 'Hero Carousel', 'pressnative' ),
			'post-grid'      => __( 'Post Grid', 'pressnative' ),
			'category-list'  => __( 'Category List', 'pressnative' ),
			'ad-slot-1'      => __( 'Ad Placement', 'pressnative' ),
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Layout Settings', 'pressnative' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Configure home screen components and content. Changes appear in the mobile app.', 'pressnative' ); ?></p>
			<form method="post" action="options.php">
				<?php settings_fields( 'pressnative_layout_settings' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="pressnative_hero_category_slug"><?php esc_html_e( 'Hero Carousel: Featured Category', 'pressnative' ); ?></label>
						</th>
						<td>
							<input type="text"
								   id="pressnative_hero_category_slug"
								   name="<?php echo esc_attr( PressNative_Layout_Options::OPTION_HERO_CATEGORY_SLUG ); ?>"
								   value="<?php echo esc_attr( $hero_slug ); ?>"
								   class="regular-text"
								   placeholder="featured"/>
							<p class="description"><?php esc_html_e( 'Category slug for hero items. Create a category with this slug (e.g. "featured") and assign posts to it.', 'pressnative' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="pressnative_hero_max_items"><?php esc_html_e( 'Hero Carousel: Max Items', 'pressnative' ); ?></label>
						</th>
						<td>
							<input type="number"
								   id="pressnative_hero_max_items"
								   name="<?php echo esc_attr( PressNative_Layout_Options::OPTION_HERO_MAX_ITEMS ); ?>"
								   value="<?php echo esc_attr( $hero_max ); ?>"
								   min="1" max="10" step="1" class="small-text"/>
							<p class="description"><?php esc_html_e( 'Maximum slides in the hero carousel (1–10).', 'pressnative' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="pressnative_post_grid_columns"><?php esc_html_e( 'Post Grid: Columns', 'pressnative' ); ?></label>
						</th>
						<td>
							<select id="pressnative_post_grid_columns"
									name="<?php echo esc_attr( PressNative_Layout_Options::OPTION_POST_GRID_COLUMNS ); ?>">
								<?php for ( $i = 1; $i <= 4; $i++ ) : ?>
									<option value="<?php echo esc_attr( $i ); ?>" <?php selected( $grid_cols, $i ); ?>><?php echo esc_html( $i ); ?></option>
								<?php endfor; ?>
							</select>
							<p class="description"><?php esc_html_e( 'Number of columns in the post grid.', 'pressnative' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="pressnative_post_grid_per_page"><?php esc_html_e( 'Post Grid: Posts Per Page', 'pressnative' ); ?></label>
						</th>
						<td>
							<input type="number"
								   id="pressnative_post_grid_per_page"
								   name="<?php echo esc_attr( PressNative_Layout_Options::OPTION_POST_GRID_PER_PAGE ); ?>"
								   value="<?php echo esc_attr( $grid_per ); ?>"
								   min="1" max="50" step="1" class="small-text"/>
							<p class="description"><?php esc_html_e( 'Number of posts shown in the grid (1–50).', 'pressnative' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label><?php esc_html_e( 'Category List: Visible Categories', 'pressnative' ); ?></label>
						</th>
						<td>
							<input type="hidden" name="<?php echo esc_attr( PressNative_Layout_Options::OPTION_ENABLED_CATEGORIES ); ?>[]" value="0" />
							<?php if ( ! empty( $categories ) ) : ?>
								<fieldset>
									<?php foreach ( $categories as $cat ) : ?>
										<label style="display:block;margin-bottom:4px;">
											<input type="checkbox"
												   name="<?php echo esc_attr( PressNative_Layout_Options::OPTION_ENABLED_CATEGORIES ); ?>[]"
												   value="<?php echo esc_attr( $cat->term_id ); ?>"
												   <?php checked( in_array( (int) $cat->term_id, $enabled_cats, true ) ); ?>/>
											<?php echo esc_html( $cat->name ); ?>
										</label>
									<?php endforeach; ?>
								</fieldset>
								<p class="description"><?php esc_html_e( 'Select categories to show in the app. Leave all unchecked to show all top-level categories.', 'pressnative' ); ?></p>
							<?php else : ?>
								<p><?php esc_html_e( 'No categories found. Create categories in Posts → Categories.', 'pressnative' ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label><?php esc_html_e( 'Component Order', 'pressnative' ); ?></label>
						</th>
						<td>
							<p class="description"><?php esc_html_e( 'Check components to include. Order reflects display order.', 'pressnative' ); ?></p>
							<fieldset>
								<?php foreach ( PressNative_Layout_Options::COMPONENT_IDS as $cid ) : ?>
									<label style="display:block;margin-bottom:4px;">
										<input type="checkbox"
											   name="<?php echo esc_attr( PressNative_Layout_Options::OPTION_ENABLED_COMPONENTS ); ?>[]"
											   value="<?php echo esc_attr( $cid ); ?>"
											   <?php checked( in_array( $cid, $enabled_comp, true ) ); ?>/>
										<?php echo esc_html( $component_labels[ $cid ] ?? $cid ); ?>
									</label>
								<?php endforeach; ?>
							</fieldset>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Returns the configured Registry URL.
	 *
	 * @return string
	 */
	public static function get_registry_url() {
		return get_option( self::OPTION_REGISTRY_URL, self::DEFAULT_REGISTRY_URL );
	}

	/**
	 * Pings the Registry to verify schema (GET /api/v1/schema). Saves verification timestamp on success.
	 *
	 * @return bool True if schema was verified, false otherwise.
	 */
	public static function verify_registry_schema() {
		$base = self::get_registry_url();
		$url  = rtrim( $base, '/' ) . '/api/v1/schema';

		$response = wp_remote_get(
			$url,
			array(
				'timeout'    => 10,
				'sslverify'  => true,
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code !== 200 ) {
			return false;
		}

		$body = wp_remote_retrieve_body( $response );
		$json = json_decode( $body, true );
		if ( ! is_array( $json ) || ! isset( $json['branding'] ) || ! isset( $json['screen'] ) || ! isset( $json['components'] ) ) {
			return false;
		}

		update_option( self::OPTION_SCHEMA_VERIFIED, current_time( 'mysql' ) );
		return true;
	}
}
