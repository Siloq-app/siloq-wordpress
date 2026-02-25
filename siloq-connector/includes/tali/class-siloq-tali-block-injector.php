<?php
/**
 * Siloq TALI - Block Injector (v1.1 - Page Builder Aware)
 *
 * Injects authority content as native blocks, shortcodes, or clean HTML
 * depending on the active page builder. Handles claim anchors, access
 * states, and template mapping.
 *
 * Supported render modes:
 *   gutenberg — wp:group / wp:heading / wp:paragraph blocks (default)
 *   elementor — Elementor JSON widget data via elementor_data post meta
 *   divi      — Divi shortcode strings ([et_pb_section] etc.)
 *   classic   — Clean semantic HTML, no block comments, no visible data attrs
 *
 * @package Siloq
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Siloq_TALI_Block_Injector {

	/** @var Siloq_TALI_Component_Mapper */
	private $component_mapper;

	public function __construct() {
		$this->component_mapper = new Siloq_TALI_Component_Mapper();
	}

	// =========================================================================
	// PUBLIC: inject()
	// =========================================================================

	/**
	 * Inject authority content into a post.
	 *
	 * @param int    $post_id
	 * @param string $template       service_city | blog_post | project_post | generic
	 * @param array  $content_data
	 * @param array  $design_profile Includes design_profile['page_builder']
	 * @param array  $capability_map
	 * @param string $access_state   ENABLED | FROZEN
	 * @return array { success, blocks, confidence, warnings, block_count, render_mode }
	 */
	public function inject( $post_id, $template, $content_data, $design_profile, $capability_map, $access_state = 'ENABLED' ) {
		$result = array(
			'success'     => false,
			'blocks'      => array(),
			'confidence'  => 1.0,
			'warnings'    => array(),
			'block_count' => 0,
		);

		if ( empty( $post_id ) || empty( $template ) || empty( $content_data ) ) {
			$result['warnings'][] = 'Missing required parameters';
			return $result;
		}

		// ── Determine render mode ──────────────────────────────────────────────
		$render_mode = $this->resolve_render_mode( $design_profile );
		$theme_slug  = $design_profile['theme']['stylesheet'] ?? 'unknown';

		// ── Build content sections ─────────────────────────────────────────────
		$blocks      = array();
		$confidences = array();

		switch ( $template ) {
			case 'service_city':
				list( $blocks, $confidences ) = $this->build_service_city_blocks(
					$content_data, $design_profile, $capability_map, $access_state, $theme_slug, $render_mode
				);
				break;
			case 'blog_post':
				list( $blocks, $confidences ) = $this->build_blog_post_blocks(
					$content_data, $design_profile, $capability_map, $access_state, $theme_slug, $render_mode
				);
				break;
			case 'project_post':
				list( $blocks, $confidences ) = $this->build_project_post_blocks(
					$content_data, $design_profile, $capability_map, $access_state, $theme_slug, $render_mode
				);
				break;
			default:
				list( $blocks, $confidences ) = $this->build_generic_blocks(
					$content_data, $design_profile, $capability_map, $access_state, $theme_slug, $render_mode
				);
		}

		$overall_confidence = ! empty( $confidences ) ? array_sum( $confidences ) / count( $confidences ) : 0;

		// ── Write to WordPress ─────────────────────────────────────────────────
		$this->write_to_post( $post_id, $blocks, $render_mode );

		$result['success']     = true;
		$result['blocks']      = $blocks;
		$result['confidence']  = round( $overall_confidence, 2 );
		$result['block_count'] = count( $blocks );
		$result['render_mode'] = $render_mode;

		return $result;
	}

	// =========================================================================
	// RENDER MODE RESOLUTION
	// =========================================================================

	/**
	 * Resolve which render mode to use based on active page builder.
	 *
	 * @param array $design_profile
	 * @return string gutenberg|elementor|divi|beaver|classic
	 */
	private function resolve_render_mode( $design_profile ) {
		$detected = $design_profile['page_builder'] ?? 'classic';

		$mode_map = array(
			'elementor'      => 'elementor',
			'divi'           => 'divi',
			'beaver-builder' => 'beaver',
			'gutenberg'      => 'gutenberg',
			'classic'        => 'classic',
		);

		return $mode_map[ $detected ] ?? 'classic';
	}

	// =========================================================================
	// WRITE TO POST
	// =========================================================================

	/**
	 * Persist rendered content back to WordPress, handling each render mode
	 * correctly so the builder doesn't override or strip the content.
	 *
	 * @param int    $post_id
	 * @param array  $blocks     Array of rendered block strings
	 * @param string $render_mode
	 */
	private function write_to_post( $post_id, $blocks, $render_mode ) {
		switch ( $render_mode ) {
			case 'elementor':
				// Elementor stores its layout as JSON in post meta.
				// post_content holds a plain-text fallback (used by search engines).
				$elementor_data = $this->blocks_to_elementor_json( $blocks );
				update_post_meta( $post_id, '_elementor_data', wp_slash( wp_json_encode( $elementor_data ) ) );
				update_post_meta( $post_id, '_elementor_edit_mode', 'builder' );
				update_post_meta( $post_id, '_elementor_version', '3.0.0' );

				$plain = $this->blocks_to_plain_text( $blocks );
				wp_update_post( array( 'ID' => $post_id, 'post_content' => $plain ) );
				break;

			case 'divi':
				// Divi reads directly from post_content as shortcodes.
				$shortcode_content = implode( "\n\n", $blocks );
				wp_update_post( array( 'ID' => $post_id, 'post_content' => $shortcode_content ) );
				update_post_meta( $post_id, '_et_pb_use_builder', 'on' );
				update_post_meta( $post_id, '_et_pb_old_content', '' );
				break;

			case 'beaver':
				// Beaver Builder renders post_content as HTML.
				$html_content = implode( "\n\n", $blocks );
				wp_update_post( array( 'ID' => $post_id, 'post_content' => $html_content ) );
				break;

			case 'gutenberg':
			default:
				$block_content = implode( "\n\n", $blocks );
				wp_update_post( array( 'ID' => $post_id, 'post_content' => $block_content ) );
				break;
		}
	}

	// =========================================================================
	// TEMPLATE BUILDERS — service_city
	// =========================================================================

	private function build_service_city_blocks( $content_data, $design_profile, $capability_map, $access_state, $theme_slug, $render_mode ) {
		$blocks      = array();
		$confidences = array();

		$service_name  = $content_data['service_name'] ?? '';
		$city          = $content_data['city'] ?? '';
		$introduction  = $content_data['introduction'] ?? '';
		$services      = $content_data['services'] ?? array();
		$benefits      = $content_data['benefits'] ?? array();
		$process       = $content_data['process'] ?? array();
		$faq           = $content_data['faq'] ?? array();
		$cta           = $content_data['cta'] ?? array();
		$claim_prefix  = $content_data['claim_prefix'] ?? 'SRV';
		$claim_counter = 100;

		if ( ! empty( $introduction ) ) {
			$claim_id = "CLAIM:{$claim_prefix}-{$claim_counter}-A";
			$claim_counter++;
			$inner      = $this->h( $service_name . ' in ' . $city, 1, $render_mode )
				. $this->p( $introduction, $render_mode );
			$blocks[]      = $this->wrap( $inner, $claim_id, 'service_city', $theme_slug, $access_state, $render_mode );
			$confidences[] = 0.99;
		}

		if ( ! empty( $services ) ) {
			$claim_id = "CLAIM:{$claim_prefix}-{$claim_counter}-A";
			$claim_counter++;
			$inner      = $this->h( 'Our ' . $service_name . ' Services', 2, $render_mode )
				. $this->ul( $services, $render_mode );
			$blocks[]      = $this->wrap( $inner, $claim_id, 'service_city', $theme_slug, $access_state, $render_mode );
			$confidences[] = 0.99;
		}

		if ( ! empty( $benefits ) ) {
			$claim_id = "CLAIM:{$claim_prefix}-{$claim_counter}-A";
			$claim_counter++;
			$inner      = $this->h( 'Why Choose Us', 2, $render_mode )
				. $this->ul( $benefits, $render_mode );
			$blocks[]      = $this->wrap( $inner, $claim_id, 'service_city', $theme_slug, $access_state, $render_mode );
			$confidences[] = 0.99;
		}

		if ( ! empty( $process ) ) {
			$claim_id = "CLAIM:{$claim_prefix}-{$claim_counter}-A";
			$claim_counter++;
			$inner      = $this->h( 'Our Process', 2, $render_mode )
				. $this->ol( $process, $render_mode );
			$blocks[]      = $this->wrap( $inner, $claim_id, 'service_city', $theme_slug, $access_state, $render_mode );
			$confidences[] = 0.95;
		}

		if ( ! empty( $faq ) ) {
			$claim_id  = "CLAIM:FAQ-{$claim_counter}-A";
			$claim_counter++;
			$faq_rec   = $this->component_mapper->get_recommended_block( 'faq', $capability_map );
			$faq_inner = $this->h( 'Frequently Asked Questions', 2, $render_mode );
			foreach ( $faq as $item ) {
				$faq_inner .= $this->faq_item( $item['question'] ?? '', $item['answer'] ?? '', $render_mode );
			}
			$blocks[]      = $this->wrap( $faq_inner, $claim_id, 'service_city', $theme_slug, $access_state, $render_mode );
			$confidences[] = $faq_rec['confidence'];
		}

		if ( ! empty( $cta ) ) {
			$claim_id  = "CLAIM:CTA-{$claim_counter}-A";
			$cta_inner = $this->h( $cta['heading'] ?? 'Get Started Today', 2, $render_mode );
			if ( ! empty( $cta['text'] ) ) {
				$cta_inner .= $this->p( $cta['text'], $render_mode );
			}
			$cta_inner .= $this->btn( $cta['button_text'] ?? 'Contact Us', $cta['button_url'] ?? '#contact', $render_mode, $design_profile );
			$blocks[]      = $this->wrap( $cta_inner, $claim_id, 'service_city', $theme_slug, $access_state, $render_mode );
			$confidences[] = 0.95;
		}

		return array( $blocks, $confidences );
	}

	// =========================================================================
	// TEMPLATE BUILDERS — blog_post
	// =========================================================================

	private function build_blog_post_blocks( $content_data, $design_profile, $capability_map, $access_state, $theme_slug, $render_mode ) {
		$blocks        = array();
		$confidences   = array();
		$sections      = $content_data['sections'] ?? array();
		$claim_prefix  = $content_data['claim_prefix'] ?? 'BLOG';
		$claim_counter = 100;

		foreach ( $sections as $section ) {
			$heading  = $section['heading'] ?? '';
			$content  = $section['content'] ?? '';
			$items    = $section['items'] ?? array();
			$claim_id = "CLAIM:{$claim_prefix}-{$claim_counter}-A";
			$claim_counter++;

			$inner = '';
			if ( ! empty( $heading ) ) {
				$inner .= $this->h( $heading, 2, $render_mode );
			}
			if ( ! empty( $content ) ) {
				foreach ( array_filter( array_map( 'trim', explode( "\n\n", $content ) ) ) as $para ) {
					$inner .= $this->p( $para, $render_mode );
				}
			}
			if ( ! empty( $items ) ) {
				$inner .= $this->ul( $items, $render_mode );
			}
			if ( ! empty( $inner ) ) {
				$blocks[]      = $this->wrap( $inner, $claim_id, 'blog_post', $theme_slug, $access_state, $render_mode );
				$confidences[] = 0.99;
			}
		}

		return array( $blocks, $confidences );
	}

	// =========================================================================
	// TEMPLATE BUILDERS — project_post
	// =========================================================================

	private function build_project_post_blocks( $content_data, $design_profile, $capability_map, $access_state, $theme_slug, $render_mode ) {
		$blocks        = array();
		$confidences   = array();
		$title         = $content_data['title'] ?? '';
		$description   = $content_data['description'] ?? '';
		$images        = $content_data['images'] ?? array();
		$details       = $content_data['details'] ?? array();
		$claim_prefix  = $content_data['claim_prefix'] ?? 'JOB';
		$claim_counter = 200;

		if ( ! empty( $description ) ) {
			$claim_id = "CLAIM:{$claim_prefix}-{$claim_counter}-A";
			$claim_counter++;
			$inner      = $this->h( $title, 1, $render_mode ) . $this->p( $description, $render_mode );
			$blocks[]      = $this->wrap( $inner, $claim_id, 'project_post', $theme_slug, $access_state, $render_mode );
			$confidences[] = 0.99;
		}

		if ( ! empty( $details ) ) {
			$claim_id = "CLAIM:{$claim_prefix}-{$claim_counter}-A";
			$claim_counter++;
			$inner      = $this->h( 'Project Details', 2, $render_mode ) . $this->ul( $details, $render_mode );
			$blocks[]      = $this->wrap( $inner, $claim_id, 'project_post', $theme_slug, $access_state, $render_mode );
			$confidences[] = 0.95;
		}

		if ( ! empty( $images ) ) {
			$claim_id = "CLAIM:{$claim_prefix}-{$claim_counter}-A";
			$claim_counter++;
			$gallery    = $this->gallery( $images, $render_mode );
			$blocks[]      = $this->wrap( $gallery, $claim_id, 'project_post', $theme_slug, $access_state, $render_mode );
			$confidences[] = 0.90;
		}

		return array( $blocks, $confidences );
	}

	// =========================================================================
	// TEMPLATE BUILDERS — generic
	// =========================================================================

	private function build_generic_blocks( $content_data, $design_profile, $capability_map, $access_state, $theme_slug, $render_mode ) {
		$blocks        = array();
		$confidences   = array();
		$sections      = $content_data['sections'] ?? array();
		$claim_prefix  = $content_data['claim_prefix'] ?? 'GEN';
		$claim_counter = 100;

		foreach ( $sections as $section ) {
			$inner    = '';
			$claim_id = "CLAIM:{$claim_prefix}-{$claim_counter}-A";
			$claim_counter++;

			if ( ! empty( $section['heading'] ) ) {
				$inner .= $this->h( $section['heading'], $section['heading_level'] ?? 2, $render_mode );
			}
			if ( ! empty( $section['content'] ) ) {
				$inner .= $this->p( $section['content'], $render_mode );
			}
			if ( ! empty( $section['items'] ) ) {
				$inner .= $this->ul( $section['items'], $render_mode );
			}
			if ( ! empty( $inner ) ) {
				$blocks[]      = $this->wrap( $inner, $claim_id, 'generic', $theme_slug, $access_state, $render_mode );
				$confidences[] = 0.90;
			}
		}

		return array( $blocks, $confidences );
	}

	// =========================================================================
	// AUTHORITY CONTAINER WRAPPER
	// =========================================================================

	/**
	 * Wrap inner content in an authority container appropriate to the render mode.
	 * FROZEN state: emits only an invisible claim receipt, no visible content.
	 *
	 * @param string $inner
	 * @param string $claim_id
	 * @param string $template
	 * @param string $theme_slug
	 * @param string $access_state ENABLED | FROZEN
	 * @param string $render_mode
	 * @return string
	 */
	private function wrap( $inner, $claim_id, $template, $theme_slug, $access_state, $render_mode ) {
		if ( $access_state === 'FROZEN' ) {
			return $this->frozen_receipt( $claim_id, $render_mode );
		}

		$attrs = array(
			'data-siloq-claim-id'   => esc_attr( $claim_id ),
			'data-siloq-governance' => 'V1',
			'data-siloq-template'   => esc_attr( $template ),
			'data-siloq-theme'      => esc_attr( $theme_slug ),
		);

		switch ( $render_mode ) {
			case 'gutenberg':
				$attr_str = '';
				foreach ( $attrs as $k => $v ) {
					$attr_str .= " {$k}=\"{$v}\"";
				}
				return "<!-- wp:group {\"className\":\"siloq-authority-container\"} -->\n"
					. "<div class=\"wp-block-group siloq-authority-container\"{$attr_str}>\n"
					. "<!-- SiloqAuthorityReceipt: {$claim_id} -->\n"
					. $inner
					. "\n</div>\n"
					. '<!-- /wp:group -->';

			case 'elementor':
				$attr_str = '';
				foreach ( $attrs as $k => $v ) {
					$attr_str .= " {$k}=\"{$v}\"";
				}
				return "<div class=\"siloq-authority-container\"{$attr_str}>\n{$inner}\n</div>";

			case 'divi':
				$receipt = "/* SiloqAuthorityReceipt: {$claim_id} */";
				return "[et_pb_section custom_css_main_element=\"{$receipt}\" "
					. "siloq_claim_id=\"{$claim_id}\" siloq_template=\"{$template}\"]\n"
					. "[et_pb_row]\n[et_pb_column type=\"4_4\"]\n"
					. $inner
					. "\n[/et_pb_column]\n[/et_pb_row]\n[/et_pb_section]";

			case 'beaver':
				$attr_str = '';
				foreach ( $attrs as $k => $v ) {
					$attr_str .= " {$k}=\"{$v}\"";
				}
				return "<div class=\"siloq-authority-container fl-content\"{$attr_str}>\n{$inner}\n</div>";

			case 'classic':
			default:
				// Hidden receipt span — governance metadata visible only to Siloq scanner.
				$meta_span = '<span class="siloq-receipt" style="display:none;" aria-hidden="true"'
					. ' data-siloq-claim-id="' . esc_attr( $claim_id ) . '"'
					. ' data-siloq-governance="V1"'
					. ' data-siloq-template="' . esc_attr( $template ) . '"'
					. ' data-siloq-theme="' . esc_attr( $theme_slug ) . '">'
					. '</span>';
				return "<section class=\"siloq-authority-section\">\n{$meta_span}\n{$inner}\n</section>";
		}
	}

	/**
	 * Frozen receipt — completely invisible, zero visible output.
	 *
	 * @param string $claim_id
	 * @param string $render_mode
	 * @return string
	 */
	private function frozen_receipt( $claim_id, $render_mode ) {
		switch ( $render_mode ) {
			case 'gutenberg':
				return "<!-- SiloqFrozen: {$claim_id} -->";
			case 'divi':
				return "[et_pb_section siloq_claim_id=\"{$claim_id}\" siloq_state=\"FROZEN\"][/et_pb_section]";
			case 'elementor':
			case 'beaver':
			case 'classic':
			default:
				return '<span class="siloq-frozen" style="display:none;" aria-hidden="true"'
					. ' data-siloq-claim-id="' . esc_attr( $claim_id ) . '"'
					. ' data-siloq-state="FROZEN"></span>';
		}
	}

	// =========================================================================
	// PRIMITIVE RENDERERS
	// =========================================================================

	private function h( $text, $level, $render_mode ) {
		$text  = wp_kses_post( $text );
		$level = max( 1, min( 6, (int) $level ) );
		switch ( $render_mode ) {
			case 'gutenberg':
				return "<!-- wp:heading {\"level\":{$level}} -->\n<h{$level} class=\"wp-block-heading\">{$text}</h{$level}>\n<!-- /wp:heading -->\n";
			case 'divi':
				return "[et_pb_text]\n<h{$level}>{$text}</h{$level}>\n[/et_pb_text]\n";
			default:
				return "<h{$level}>{$text}</h{$level}>\n";
		}
	}

	private function p( $text, $render_mode ) {
		$text = wp_kses_post( $text );
		switch ( $render_mode ) {
			case 'gutenberg':
				return "<!-- wp:paragraph -->\n<p>{$text}</p>\n<!-- /wp:paragraph -->\n";
			case 'divi':
				return "[et_pb_text]\n<p>{$text}</p>\n[/et_pb_text]\n";
			default:
				return "<p>{$text}</p>\n";
		}
	}

	private function ul( $items, $render_mode ) {
		if ( empty( $items ) ) {
			return '';
		}
		$li_html = '';
		foreach ( $items as $item ) {
			$li_html .= '<li>' . wp_kses_post( is_array( $item ) ? ( $item['text'] ?? '' ) : $item ) . '</li>';
		}
		switch ( $render_mode ) {
			case 'gutenberg':
				return "<!-- wp:list -->\n<ul class=\"wp-block-list\">{$li_html}</ul>\n<!-- /wp:list -->\n";
			case 'divi':
				return "[et_pb_text]\n<ul>{$li_html}</ul>\n[/et_pb_text]\n";
			default:
				return "<ul>{$li_html}</ul>\n";
		}
	}

	private function ol( $items, $render_mode ) {
		if ( empty( $items ) ) {
			return '';
		}
		$li_html = '';
		foreach ( $items as $item ) {
			$li_html .= '<li>' . wp_kses_post( is_array( $item ) ? ( $item['text'] ?? '' ) : $item ) . '</li>';
		}
		switch ( $render_mode ) {
			case 'gutenberg':
				return "<!-- wp:list {\"ordered\":true} -->\n<ol class=\"wp-block-list\">{$li_html}</ol>\n<!-- /wp:list -->\n";
			case 'divi':
				return "[et_pb_text]\n<ol>{$li_html}</ol>\n[/et_pb_text]\n";
			default:
				return "<ol>{$li_html}</ol>\n";
		}
	}

	private function btn( $text, $url, $render_mode, $design_profile = array() ) {
		$text = esc_html( $text );
		$url  = esc_url( $url );
		switch ( $render_mode ) {
			case 'gutenberg':
				return "<!-- wp:buttons -->\n<div class=\"wp-block-buttons\">"
					. "<!-- wp:button -->"
					. "<div class=\"wp-block-button\"><a class=\"wp-block-button__link wp-element-button\" href=\"{$url}\">{$text}</a></div>"
					. "<!-- /wp:button -->"
					. "</div>\n<!-- /wp:buttons -->\n";
			case 'divi':
				return "[et_pb_button button_url=\"{$url}\" button_text=\"{$text}\" /]\n";
			default:
				return "<p><a class=\"siloq-cta-button\" href=\"{$url}\">{$text}</a></p>\n";
		}
	}

	private function faq_item( $question, $answer, $render_mode ) {
		$question = wp_kses_post( $question );
		$answer   = wp_kses_post( $answer );
		switch ( $render_mode ) {
			case 'gutenberg':
				return "<!-- wp:html -->\n"
					. "<details class=\"siloq-faq-item\">\n"
					. "<summary class=\"siloq-faq-question\">{$question}</summary>\n"
					. "<div class=\"siloq-faq-answer\"><p>{$answer}</p></div>\n"
					. "</details>\n"
					. "<!-- /wp:html -->\n";
			case 'divi':
				return "[et_pb_toggle title=\"{$question}\"]\n{$answer}\n[/et_pb_toggle]\n";
			default:
				return "<details class=\"siloq-faq-item\">\n"
					. "<summary class=\"siloq-faq-question\">{$question}</summary>\n"
					. "<div class=\"siloq-faq-answer\"><p>{$answer}</p></div>\n"
					. "</details>\n";
		}
	}

	private function gallery( $images, $render_mode ) {
		if ( empty( $images ) ) {
			return '';
		}
		$ids  = array();
		$html = '';
		foreach ( $images as $img ) {
			$id  = is_array( $img ) ? ( $img['id'] ?? 0 ) : 0;
			$url = is_array( $img ) ? ( $img['url'] ?? $img ) : $img;
			$alt = is_array( $img ) ? ( $img['alt'] ?? '' ) : '';
			if ( $id ) {
				$ids[] = $id;
			}
			$html .= '<figure><img src="' . esc_url( $url ) . '" alt="' . esc_attr( $alt ) . '"></figure>';
		}
		$ids_str = implode( ',', $ids );
		switch ( $render_mode ) {
			case 'gutenberg':
				$attr = $ids_str ? " {\"ids\":[{$ids_str}]}" : '';
				return "<!-- wp:gallery{$attr} -->\n<figure class=\"wp-block-gallery\">{$html}</figure>\n<!-- /wp:gallery -->\n";
			case 'divi':
				return "[et_pb_gallery gallery_ids=\"{$ids_str}\" show_title_and_caption=\"on\" /]\n";
			default:
				return "<div class=\"siloq-gallery\">{$html}</div>\n";
		}
	}

	// =========================================================================
	// ELEMENTOR JSON BUILDER
	// =========================================================================

	/**
	 * Convert blocks array into Elementor section/column/widget JSON.
	 * Each block becomes one Section → Column → HTML Widget.
	 *
	 * @param array $blocks
	 * @return array
	 */
	private function blocks_to_elementor_json( $blocks ) {
		$sections = array();
		foreach ( $blocks as $index => $html ) {
			$sections[] = array(
				'id'       => 'siloq-sec-' . ( $index + 1 ),
				'elType'   => 'section',
				'settings' => array( 'css_classes' => 'siloq-authority-section' ),
				'elements' => array(
					array(
						'id'       => 'siloq-col-' . ( $index + 1 ),
						'elType'   => 'column',
						'settings' => array( '_column_size' => 100 ),
						'elements' => array(
							array(
								'id'         => 'siloq-wgt-' . ( $index + 1 ),
								'elType'     => 'widget',
								'widgetType' => 'html',
								'settings'   => array( 'html' => $html ),
							),
						),
					),
				),
			);
		}
		return $sections;
	}

	/**
	 * Strip HTML to produce a plain-text fallback for post_content (Elementor).
	 *
	 * @param array $blocks
	 * @return string
	 */
	private function blocks_to_plain_text( $blocks ) {
		return wp_strip_all_tags( implode( "\n\n", $blocks ) );
	}
}
